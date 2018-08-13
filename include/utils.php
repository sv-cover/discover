<?php

/**
 * HttpException: implements exceptions with HTTP status code and optional HTML message
 */
class HttpException extends Exception {
    protected $html_message;
    protected $status;

    public function __construct($status=500, $message=null, $html_message=null, $code=0) {
        $this->status = $status;
        $this->html_message = $html_message;

        if (empty($message))
            $message = $html_message;

        parent::__construct($message, $code);
    }

    public function getHtmlMessage () {
        return $this->html_message;
    }

    public function getStatus () {
        return $this->status;
    }
}


/** Converts committee login into emailaddress */
function get_committee_email($login){
    return filter_var(sprintf('%s@svcover.nl', $login), FILTER_SANITIZE_EMAIL);
}


/** Wrapper function for PHP's built in mail. */
function send_mail($from, $to, $body, $subject=null, array $headers=[]) {
    // Create email headers
    $headers = array_merge([
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        sprintf('From: %s', $from)
    ], $headers);

    if (empty($subject)) {
        // Fetch subject from rendered email body
        preg_match('{<title>(.+?)</title>}', $body, $subject);
        $subject = $subject[1];
    }
    
    // Send email
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

/**
 * Template: Implements a simple template engine that allows template inheritance
 */
class Template
{
    private $__TEMPLATE__;
    private $__DATA__;
    private $__PARENT__;
    private $__BLOCK__;

    public function __construct($file, array $data = []) {
        $this->__TEMPLATE__ = $file;
        $this->__DATA__ = $data;
    }

    public function __set($key, $value) {
        $this->__DATA__[$key] = $value;
    }

    /** Renders the template */
    public function render() {
        ob_start();
        extract($this->__DATA__);
        include $this->__TEMPLATE__;
        if ($this->__PARENT__) {
            ob_end_clean();
            return $this->__PARENT__->render();
        } else {
            return ob_get_clean();
        }
    }

    /** Extends the template from another template */
    protected function extends($template) {
        if ($this->__PARENT__)
            throw new LogicException('Cannot call Template::extend twice from the same template');
        $this->__PARENT__ = new Template(dirname($this->__TEMPLATE__) . '/' . $template, $this->__DATA__);
    }

    /** Creates a template block */
    protected function begin($block_name) {
        if (!$this->__PARENT__)
            throw new LogicException('You cannot begin a block while not extending a parent template');

        if ($this->__BLOCK__)
            throw new LogicException('You cannot have a block inside a block in templates');

        $this->__BLOCK__ = $block_name;
        ob_start();
    }

    /** Ends a template block */
    protected function end() {
        if (!$this->__BLOCK__)
            throw new LogicException('Calling Template::end while not in a block. Template::begin missing?');

        $this->__PARENT__->__set($this->__BLOCK__, ob_get_clean());
        $this->__BLOCK__ = null;
    }
    
    /** Escape text for generic use in html */
    static public function html($data) {
        return htmlspecialchars($data, ENT_COMPAT, 'utf-8');
    }

    /** Escape value for attributes */
    static public function attr($data) {
        return htmlspecialchars($data, ENT_QUOTES, 'utf-8');
    }

    /** Escape and format plain text */
    static public function format_plain_text($text) {
        $plain_paragraphs = preg_split("/\r?\n\r?\n/", $text);

        $formatted_paragraphs = array_map(
            function($plain_paragraph) {
                return sprintf('<p>%s</p>', nl2br(self::html(trim($plain_paragraph))));
            }, 
            $plain_paragraphs
        );

        return implode("\n", $formatted_paragraphs);
    }
}
