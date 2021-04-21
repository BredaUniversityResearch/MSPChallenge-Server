<?php
require_once 'init.php';
$servermanager = ServerManager::getInstance();

if(isset($user) && $user->isLoggedIn()){
  if ($servermanager->freshinstall()) {
    // new installation, redirect to set up ServerManager database
    Redirect::to($servermanager->GetServerManagerFolder().'install/install.php');
  }
  else {
    // run any outstanding database migrations
    $db = DB::getInstance()->dbase_migrate();
    Redirect::to($servermanager->GetServerManagerFolder().'manager.php');
  }
}
else{
  Redirect::to($servermanager->GetServerManagerFolder().'login.php');
}
die();
?>
