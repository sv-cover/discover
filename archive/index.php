<?php
set_include_path ( dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' );
require_once 'include/init.php';
require_once 'include/ArchiveView.class.php';

// Create and run archive view
$view = new ArchiveView(DISCOVER_ARCHIVE, 'discover_archive', 'DisCover Archive');
$view->run();
