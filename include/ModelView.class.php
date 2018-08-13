<?php
require_once 'include/utils.php';
require_once 'include/FormView.class.php';


/**
 * ModelView: A class to manage CRUD(L) actions for a Model object.
 * Separates reading a single object and listing multiple objects for clarity.
 */
class ModelView extends FormView
{
    // The names of the available views (override to limit actions)
    protected $views = ['create', 'read', 'update', 'delete', 'list'];

    // The default view to run if $_GET['view'] is not provided
    protected $default_view = 'list';

    // The Model object of this view.
    protected $model;

    protected $_view;


    public function __construct($page_id, $title='', $model=null, $form=null) {
        $this->model = $model;
        $this->form = $form;
        parent::__construct($page_id, $title);

        if (!isset($_GET['view']))
            $this->_view = $this->default_view;
        else
            $this->_view = $_GET['view'];
    }

    /** Runs the correct function based on the $_GET['view'] parameter */
    protected function run_page() {
        if (!in_array($this->_view, $this->views))
            throw new HttpException(404, 'View not found!');
            
        if ($this->_view === 'create')
            return $this->run_create();
        elseif ($this->_view === 'read')
            return $this->run_read();
        elseif ($this->_view === 'update')
            return $this->run_update();
        elseif ($this->_view === 'delete')
            return $this->run_delete();
        elseif ($this->_view === 'list')
            return $this->run_list();
        else
            throw new HttpException(404, 'View not found!');
    }

    /** Runs the create view */
    protected function run_create() {
        $form = $this->get_form();
        return $this->run_form($form);
    }

    /** Runs the read view */
    protected function run_read() {
        $object = $this->get_object();
        return $this->render_template($this->get_template(), ['object' => $object]);
    }

    /** Runs the update view */
    protected function run_update() {
        $form = $this->get_form();
        if ($_SERVER['REQUEST_METHOD'] === 'GET')
            $form->populate_fields($this->get_object());
        return $this->run_form($form);
    }

    /** Runs the delete view */
    protected function run_delete() {
        $object = $this->get_object();

        if ($_SERVER['REQUEST_METHOD'] === 'POST'){
            $this->get_model()->delete_by_id($object['id']);
            $this->redirect($this->get_success_url());
        }

        return $this->render_template($this->get_template(), ['object' => $object]);
    }

    /** Runs the list view */
    protected function run_list() {
        return $this->render_template($this->get_template(), ['objects' => $this->get_model()->get()]);
    }

    /** Processes the form data for create and update */
    protected function process_form_data($data) {
        if ($this->_view === 'create')
            $this->get_model()->create($data);
        elseif ($this->_view === 'update')
            $this->get_model()->update_by_id($this->get_object()['id'], $data);
        else
            throw new RuntimeException('Incompatible view while processing form!');
    }

    /** Returns the Model object to use for the view */
    protected function get_model() {
        if (!isset($this->model))
            throw new RuntimeException('Please define the model property or override the get_model method!');
        return $this->model;
    }

    /** Returns the object referenced to by the $_GET['id'] parameter */
    protected function get_object() {
        static $object = null;

        if (!isset($_GET['id']))
            throw new HttpException(400, 'Please provide an ID!');

        if ($object !== null)
            return $object;

        $object = $this->get_model()->get_by_id($_GET['id']);

        if (empty($object))
            throw new HttpException(404, 'No object found for id');

        return $object;
    }

    /** Returns the url to redirect to after (successful) create, update or delete */
    protected function get_success_url() {
        $parts = explode('?', $_SERVER['REQUEST_URI'], 2);
        return $parts[0];
    }

    /** Returns the name of the template to use to render the current view */
    protected function get_template($view_name='') {
        if (empty($view_name))
            $view_name = $this->_view;

        return parent::get_template($view_name);
    }
}
