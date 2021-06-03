<?php
define('APP_RAN', true);

// all the configurable variables
require_once 'config.php';

// all our own classes
require_once 'classes/class.autoloader.php';

// all others' classes
require_once 'vendor/autoload.php';

// all the helper functions
require_once 'helpers.php';

// let's get going!
session_start();

// language setting
checklanguage();
require ServerManager::getInstance()->GetServerManagerRoot().'lang/'.$_SESSION['us_lang'].".php";

?>
