<?php

use ServerManager\DB;
use ServerManager\Redirect;
use ServerManager\ServerManager;
use ServerManager\User;

require 'init.php';
$user = new User();
$serverManager = ServerManager::getInstance();

if ($user->isLoggedIn()) {
    if ($serverManager->freshInstall()) {
      // new installation, redirect to set up ServerManager database
        Redirect::to($serverManager->GetServerManagerFolder().'install/install.php');
    } else {
      // run any outstanding database migrations
        DB::getInstance()->dbase_migrate();
        Redirect::to($serverManager->GetServerManagerFolder().'manager.php');
    }
} else {
    Redirect::to($serverManager->GetServerManagerFolder().'login.php');
}
die();
