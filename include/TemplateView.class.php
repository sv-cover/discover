<?php
require_once 'include/utils.php';
require_once 'include/View.class.php';

if (!defined('ERROR_TEMPLATE'))
    define('ERROR_TEMPLATE', 'templates/error.phtml');

/**
 * TemplateView: A class to manage a view based on a template
 */
class TemplateView extends View
{
    // The template to use for the content of this page (defaults to /templates/<page_id>.phtml)
    protected $template;

    // The base name of the template to use (defaults to 'templates/<page_id>')
    protected $template_base_name;
    
    // The title of the page
    protected $title;

    // The ID of the page
    protected $page_id;

    public function __construct($page_id, $title='') {
        $this->page_id = $page_id;
        $this->title = $title;
    }

    /** Run the view */
    public function run() {
        try {
            echo $this->run_page();
        } catch (Exception $e) {
            echo $this->run_exception($e);
        } catch (TypeError $e) {
            echo $this->run_exception($e);
        }
    }

    /** Run the view */
    protected function run_page() {
        return $this->render_template($this->get_template());
    }

    /** Handle exceptions encountered during running */
    protected function run_exception($e){
        if ($e instanceof HttpException){
            $html_message = $e->getHtmlMessage();
            $status = $e->getStatus();
        } else {
            $html_message = null;
            $status = 500;
        }
        
        http_response_code($status);

        return $this->render_template(ERROR_TEMPLATE, [
            'title' => 'Error',
            'exception' => $e,
            'status' => $status,
            'message' => $e->getMessage(),
            'html_message' => $html_message,
            'exception' => $e,
        ]);
    }

    /** Render a template */
    protected function render_template($template, array $context=[]) {
        $templ = new Template($template, array_merge($this->get_default_context(), $context));
        return $templ->render();
    }

    /** Returns the default context */
    protected function get_default_context() {        
        return [
            'title' => $this->title,
            'page_id' => $this->page_id,
        ];
    }

    /** Returns the name of the template to use */
    protected function get_template($view_name='') {
        if (isset($this->template))
            return $this->template;

        if (isset($this->template_base_name))
            $base_name = $this->template_base_name;
        else
            $base_name =  sprintf('templates/%s', $this->page_id);

        if (empty($view_name))
            return sprintf('%s.phtml', $base_name);

        return sprintf('%s_%s.phtml', $base_name, $view_name);
    }
}
