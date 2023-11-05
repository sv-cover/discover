<?php 

if (!defined('DEFAULT_ROOT'))
    define('DEFAULT_ROOT', '');

/** 
 * Encodes relative path to absolute location on disk 
 * (borrowed from Documents & Templates) 
 */
function fsencode_path($path, $root=DEFAULT_ROOT){
    $parts = array_filter(explode('/', $path));

    array_unshift($parts, $root);

    return implode(DIRECTORY_SEPARATOR, $parts);
}


/** 
 * Convert absolute path to urlencoded relative path string
 * (borrowed from Documents & Templates) 
 */
function urlencode_path($path, $root=DEFAULT_ROOT) {
    $path = preg_replace('{^' . preg_quote( $root ) . '}', '', $path);

    $parts = explode(DIRECTORY_SEPARATOR, $path);

    $parts = array_map('rawurlencode', $parts);

    return implode('/', $parts);
}


/** 
 * Returns mimietype of file
 * (borrowed from Documents & Templates) 
 */
function get_mime_type($file) {
    if (function_exists("finfo_file")) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
        $mime = finfo_file($finfo, $file);
        finfo_close($finfo);
        return $mime;
    }
    
    else if (function_exists("mime_content_type"))
        return mime_content_type($file);
    
    else if (!stristr(ini_get("disable_functions"), "shell_exec")) {
        // http://stackoverflow.com/a/134930/1593459
        $file = escapeshellarg($file);
        $mime = shell_exec('file -bi ' . escapeshellarg($file));
        return $mime;
    }
    
    else
        return null;
}


/**
 * CachedImage: A class to manage a file and its thumbnail cache
 */
class CachedImage
{
    private $IMAGE_TYPES = array('bmp', 'eps', 'gif', 'jpg', 'jpeg', 'png', 'tif');
    private $DOCUMENT_TYPES = array('pdf', 'psd');

    public function __construct($path, $root=DEFAULT_ROOT){
        $this->path = $path;
        $this->type = pathinfo($path)['extension'];
        $this->root = $root;
    }

    /** Returns path of the source file on disk */
    protected function get_file_path(){
        return fsencode_path($this->path, $this->root);
    }

    /** Returns path of the cached thumbnail on disk */
    protected function get_cached_path($width, $height){
        $path = pathinfo(fsencode_path($this->path, CACHE_ROOT));
        $cached_name = sprintf('%s_%s_%s.jpg', $path['filename'], $width, $height);
        return $path['dirname'] . DIRECTORY_SEPARATOR . $path['filename'] . DIRECTORY_SEPARATOR . $cached_name ;
    }
    
    /**  Serves source file to client */
    public function get_raw() {
        $this->serve_file($this->get_file_path());
    }

    /**  Generates cached thumbnail and serves to client */
    public function get_thumbnail(){
        $width = defined('THUMBNAIL_WIDTH') ? THUMBNAIL_WIDTH : 0;
        $height = defined('THUMBNAIL_HEIGHT') ? THUMBNAIL_HEIGHT : 0;
        $this->view_cached($width, $height) or $this->generate_thumbnail($width, $height);
    }
 
    /** View a chached thumbnail (partially borrowed from cover website) */
    protected function view_cached($width, $height){
        $cached_file_path = $this->get_cached_path($width, $height);

        if (!file_exists($cached_file_path))
            return false;

        // Send an extra header with the mtime to make debugging the cache easier
        header('X-Cache: ' . date('r', filemtime($cached_file_path)));

        $this->serve_file($cached_file_path);

        // Let them know we succeeded, no need to generate a new image.
        return true;
    }

    /** Generates a new thumbnail (and serves it to the client) */
    protected function generate_thumbnail($width, $height){
        // Open poster, get first page of complex poster
        if (in_array(strtolower($this->type), $this->DOCUMENT_TYPES))
            $imagick = new imagick($this->get_file_path().'[0]');
        else if (in_array(strtolower($this->type), $this->IMAGE_TYPES))
            $imagick = new imagick($this->get_file_path());
        else
            return false;

        $imagick->setBackgroundColor('#ffffff');
        $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

        $cur_height = $imagick->getImageHeight();
        $cur_width = $imagick->getImageWidth();

        // Crop image
        if ( $cur_height > $cur_width && $cur_height / $cur_width > 1.4143) {
            // Image is higher then portrait A4-paper ( 1:sqrt(2) )
            $new_height = $cur_width * sqrt(2);
            $y = (int)($cur_height/2) - (int)($new_height/2);
            $imagick->cropImage($cur_width, $new_height, 0, $y);
        } else if ($cur_height < $cur_width && $cur_width / $cur_height > 1.78) {
            // Image is wider then 16:9
            $new_width = $cur_height * (16/9);
            $x = (int)($cur_width/2) - (int)($new_width/2);
            var_dump($x);
            $imagick->cropImage($new_width, $cur_height, $x, 0);
        }

        // Resize image, bestfit only works if both dimensions are given
        $bestfit = $width != 0 && $height != 0;
        $imagick->scaleImage($width, $height, $bestfit);

        $filename = $this->get_cached_path($width, $height);

        // Make sure output directory exists
        if (!file_exists(dirname($filename))){
            mkdir(dirname($filename), 0777, true);
            chgrp(dirname($filename), LINUX_GROUP_NAME);
        }

        // Convert colour profile if needed
        if ($imagick->getImageColorspace() == Imagick::COLORSPACE_CMYK) {
            $profiles = $imagick->getImageProfiles('*', false);

            // we're only interested if ICC profile(s) exist 
            $has_icc_profile = (array_search('icc', $profiles) !== false);

            // if it doesnt have a CMYK ICC profile, we add one 
            if ($has_icc_profile === false) { 
                $icc_cmyk = file_get_contents(dirname(__FILE__).'/profiles/USWebUncoated.icc');
                $imagick->profileImage('icc', $icc_cmyk); 
                unset($icc_cmyk); 
            } 

            // then we add an RGB profile 
            $icc_rgb = file_get_contents(dirname(__FILE__).'/profiles/sRGB_v4_ICC_preference.icc');
            $imagick->profileImage('icc', $icc_rgb);
            unset($icc_rgb);
        }
        $imagick->stripImage(); // this will drop down the size of the image dramatically (removes all profiles)

        // Write image to cache
        $imagick->setImageFormat('jpeg');
        $imagick->writeImage( $filename );
        $imagick->clear();
        
        chgrp($filename, LINUX_GROUP_NAME);

        // Send cached image to client
        return $this->view_cached($width, $height);
    }
    
    /** Serves a file from disk to client */
    protected function serve_file($file){
        if ($content_type = get_mime_type($file)){
            header('Content-Type: ' . $content_type);
            $name = pathinfo($file, PATHINFO_BASENAME);
            header('Content-Disposition: inline; filename="' . $name . '"');
        } else {
            // If mime type not found, offer file for download
            $name = pathinfo($file, PATHINFO_BASENAME);
            header('Content-Disposition: attachment; filename="' . $name . '"');
        }

        header(sprintf('Content-Length: %d', filesize($file)));

        $fout = fopen($file, 'rb');
        fpassthru($fout);
        fclose($fout);
    }
}
