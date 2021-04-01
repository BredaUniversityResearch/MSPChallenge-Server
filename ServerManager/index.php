<?php
require_once 'init.php';
$servermanager = ServerManager::getInstance();

// run any outstanding database migrations
$db = DB::getInstance();
$db->dbase_migrate();

if(isset($user) && $user->isLoggedIn()){
  if ($servermanager->freshinstall()) {
    Redirect::to($servermanager->GetServerManagerFolder().'install/install.php');
  }
  else {
    Redirect::to($servermanager->GetServerManagerFolder().'manager.php');
  }
}
else{
  Redirect::to($servermanager->GetServerManagerFolder().'login.php');
}
die();
?>
