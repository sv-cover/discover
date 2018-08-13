<?php
require_once 'include/utils.php';
require_once 'include/TemplateView.class.php';

/**
 * FormView: An abstract class to manage a view that renders and processes a form
 */
abstract class FormView extends TemplateView
{
    // The form to manage
    protected $form;

    // The url to redirect to after (successful) form processing
    protected $success_url;

    /** Run the page logic and render its content */
    protected function run_page() {
        $form = $this->get_form();
        echo $this->run_form($form);
    }

    /** Runs the form logic: validates the form and responds accordingly */
    protected function run_form($form) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $form->validate())
            return $this->form_valid($form);
        return $this->form_invalid($form);
    }

    /** Processes a valid form and redirects to the success url */
    protected function form_valid($form) {
        $this->process_form_data($form->get_values());
        $this->redirect($this->get_success_url());
    }

    /** Renders an invalid form */
    protected function form_invalid($form) {
        // the get_template call is a bit verbose to make subclassing this easier.
        return $this->render_template($this->get_template('form'), ['form' => $form]);
    }

    /** Processes the data of a valid form */
    abstract protected function process_form_data($data);

    /** Returns this Form object to use */
    protected function get_form() {
        if (!isset($this->form))
            throw new RuntimeException('Please define the form property or override the get_form method!');
        return $this->form;
    }

    /** Returns the url to redirect to after (successful) processing */
    protected function get_success_url() {
        if (!isset($this->success_url))
            throw new RuntimeException('Please define the success_url property or override the get_success_url method!');
        return $this->success_url;
    }
}
