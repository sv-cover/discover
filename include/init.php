<?php
require_once 'include/config.php';
require_once 'include/utils.php';
require_once 'include/sessions.php';
require_once 'include/form.php';
require_once 'include/TemplateView.class.php';
require_once 'include/FormView.class.php';
require_once 'include/ModelView.class.php';


if (!defined('ADMIN_COMMITTEE'))
    define('ADMIN_COMMITTEE', 'webcie');

/** Creates and caches PDO object for the database */
function get_db() {
    static $db;

    if (!$db){
        $db = new PDO(DB_DSN, DB_USER, DB_PASSWORD);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    return $db;
}

/**
 * Loads and creates class from model name.
 *
 * (Borrowed from the Cover website)
 */
function create_model($name) {
    require_once 'include/models/' . $name . '.class.php';

    if (!class_exists($name))
        throw new InvalidArgumentException(sprintf('Can not find the model %s', $name));

    $refl = new ReflectionClass($name);
    return $refl->newInstance(get_db());
}

/**
 * Get a model. This function will create data models for you if 
 * necessary. Mind that this function will only create one instance
 * of a model and return that every time, unless specified otherwise.
 * @param $name the name of the model
 *
 * @result a #Model object (either created or the one that was 
 * created before), or false if the model could not be created
 *
 * (Borrowed from the Cover website)
 */
function get_model($name) {
    static $models = [];

    return isset($models[$name])
        ? $models[$name]
        : $models[$name] = create_model($name);
}

