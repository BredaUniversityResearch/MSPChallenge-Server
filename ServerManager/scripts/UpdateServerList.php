<?php
$thispath = dirname(__FILE__);
chdir($thispath);

// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';

$updater = new ServerListUpdater();
$updater->UpdateList(true);


?>
