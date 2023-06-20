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
        Redirect::to($serverManager->getAbsolutePathBase().'install/install.php');
    }
  // deprecated migrations...
//    else {
//      // run any outstanding database migrations
//        DB::getInstance()->dbase_migrate();
//        Redirect::to($serverManager->GetServerManagerFolder().'manager.php');
//    }

    Redirect::to($serverManager->getAbsolutePathBase().'manager.php');
} else {
    Redirect::to($serverManager->getAbsolutePathBase().'login.php');
}
die();
