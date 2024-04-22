<?php

use ServerManager\Redirect;
use ServerManager\ServerManager;
use ServerManager\User;

require 'init.php';
$user = new User();
$serverManager = ServerManager::getInstance();

if ($user->isLoggedIn()) {
    if ($serverManager->freshInstall()) {
      // new installation, redirect to set up ServerManager database
        Redirect::to($serverManager->getAbsolutePathBase().'install/install_php');
    }
    Redirect::to($serverManager->getAbsolutePathBase().'manager_php');
} else {
    Redirect::to($serverManager->getAbsolutePathBase().'login_php');
}
die();
