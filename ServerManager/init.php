<?php
// phpcs:disable PSR1.Files.SideEffects
use ServerManager\ServerManager;
use function ServerManager\checklanguage;

if (1 === $return = require_once('bootstrap.php')) {
    return;
}
defined('APP_RAN') or define('APP_RAN', true);

// all the configurable variables
require_once 'config.php';

// all the helper functions
require_once 'ServerManager/helpers.php';

// let's get going!
session_start();

// language setting
checklanguage();

