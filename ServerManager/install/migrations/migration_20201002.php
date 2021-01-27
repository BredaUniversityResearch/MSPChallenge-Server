<?php 
// make sure this file only performs SQL statements on the database
// use $this to work with the database, e.g. $this->query($sql);
// this file will be run once after a successful login


// but don't run when called directly
defined('APP_RAN') or die();

// dbase upgrade 02 Oct 2020 >> mysql_structure.php & install.php already updated accordingly so first-time installers won't need this

$sql = "ALTER TABLE `game_list` 
		ADD `api_access_token` varchar(32) NOT NULL DEFAULT '';";

?>
