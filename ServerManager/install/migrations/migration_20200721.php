<?php 
// make sure this file only performs SQL statements on the database
// use $this to work with the database, e.g. $this->query($sql);
// this file will be run once after a successful login


// but don't run when called directly
defined('APP_RAN') or die();
$servermanager = ServerManager::getInstance();

// dbase upgrade 21 July 2020 >> mysql_structure.php & install.php already updated accordingly so first-time installers won't need this
$auth_getservername = $servermanager->GetMSPAuthAPI().'getservername.php';
$rawresponse = CallAPI("POST", $auth_getservername, array("server_id" => $servermanager->GetServerID()));
$response = json_decode($rawresponse);
$sql = 
   "INSERT INTO settings (name, value) VALUES ('server_name', '".$response->server_name."');";

?>
