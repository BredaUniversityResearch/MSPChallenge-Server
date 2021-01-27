<?php
require_once 'init.php';
$servermanager = ServerManager::getInstance();

// run any outstanding database migrations
$db = DB::getInstance();
$db->dbase_migrate();

if(isset($user) && $user->isLoggedIn()){
  if ($servermanager->freshinstall()) {
    Redirect::to($url_app_root.'install/install.php');
  }
  else {
    Redirect::to($url_app_root.'manager.php');
  }
}
else{
  Redirect::to($url_app_root.'login.php');
}
die();
?>
