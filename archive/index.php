<?php
set_include_path ( dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' );
require_once 'include/init.php';
require_once 'include/ArchiveView.class.php';

class DisCoverArchiveView extends ArchiveView
{
    public function __construct() {
        parent::__construct(DISCOVER_ARCHIVE, 'discover_archive', 'DisCover Archive');
    }

    /** Returns the default context */
    protected function get_default_context() {
        return array_merge(
            parent::get_default_context(),
            [
                'brainstorms' => array_reverse($this->list_folder(BRAINSTORM_ARCHIVE)),
            ]
        );
    }
}

// Create and run archive view
$view = new DisCoverArchiveView();
$view->run();
