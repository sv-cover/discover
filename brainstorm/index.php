<?php
set_include_path ( dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' );
require_once 'include/init.php';
require_once 'include/ArchiveView.class.php';

// Create and run archive view
$view = new ArchiveView(BRAINSTORM_ARCHIVE, 'brainstorm_archive', 'Brainstorm Archive');
$view->run();
