<?php

/**
 * View: An abstract class to provide a basic view interface
 */
abstract class View
{
    /** Run the view */
    abstract public function run();

    /** Function to redirect the browser to a different location */
    protected function redirect($url, $status_code=302) {
       header('Location: ' . $url, true, $status_code);
       die();
    }
}
