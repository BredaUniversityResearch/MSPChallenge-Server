<?php
// phpcs:disable PSR1.Files.SideEffects
if (1 === $return = require_once('bootstrap.php')) {
    return;
}
defined('APP_RAN') or define('APP_RAN', true);

// all the configurable variables
require_once 'config.php';

// all our own classes
require_once 'classes/class.autoloader.php';

// all the helper functions
require_once 'helpers.php';

// let's get going!
session_start();

// language setting
checklanguage();
require ServerManager::getInstance()->GetServerManagerRoot().'lang/'.$_SESSION['us_lang'].".php";
