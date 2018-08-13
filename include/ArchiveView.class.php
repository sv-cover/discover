<?php
require_once 'include/CachedImage.class.php';

if (!defined('ALLOWED_EXTENSIONS'))
    define('ALLOWED_EXTENSIONS', []);

/**
 * ArchiveView: A class to render the magazine archive and serve archived magazines
 * Folders or files starting with an '_' are considered private and won't be shown.
 */
class ArchiveView extends TemplateView
{
    public function __construct($root, $page_id, $title='') {
        $this->root = $root;
        parent::__construct($page_id, $title);
    }

    protected function run_page() {
        if (!empty($_GET['magazine'])) {
            $magazine = new CachedImage(urldecode($_GET['magazine']), $this->root);
            if (!empty($_GET['view']) && $_GET['view'] === 'thumbnail') 
                $magazine->get_thumbnail();
            else
                $magazine->get_raw();
            die();
        } else {
            return parent::run_page();
        }
    }

    /** Returns the default context */
    protected function get_default_context() {
        if (!empty($_GET['path']) && $_GET['path'] !== '/')
            $path = fsencode_path($_GET['path'], $this->root);
        else
            $path = $this->root;

        return [
            'title' => $this->title,
            'page_id' => $this->page_id,
            'index' => $this->get_index(),
            'magazines' => $this->list_folder($path),
            'path' => urlencode_path($path, $this->root),
        ];
    }

    /** Returns array with all visible files and folders within a folder */
    protected function list_folder($dir) {
        $dir .= DIRECTORY_SEPARATOR;
        $output = [];
        foreach (glob($dir.'[!_]*') as $item){
            $info = pathinfo($item);
            if (empty(ALLOWED_EXTENSIONS) || !array_key_exists('extension', $info) 
                || in_array(strtolower($info['extension']), ALLOWED_EXTENSIONS)) {
                $output[] = array_merge(
                    [
                        'type' => is_dir($item) ? 'dir' : 'file',
                        'path' => urlencode_path($item, $this->root),
                    ],
                    $info
                );
            }
        }
        return $output;
    }

    /** Returns array with all visible folders in the archive root */
    protected function get_index() {
        $dirs = glob($this->root .  DIRECTORY_SEPARATOR . '[!_]*', GLOB_ONLYDIR);
        $output = [];
        foreach (array_reverse($dirs) as $item){
            $output[] = array_merge(
                [
                    'path' => urlencode_path($item, $this->root)
                ],
                pathinfo($item)
            );
        }
        return $output;
    }
}