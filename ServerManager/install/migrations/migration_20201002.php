<?php 
// make sure this file only performs SQL statements on the database
// use just use the $sql var


// but don't run when called directly
defined('APP_RAN') or die();

// dbase upgrade 02 Oct 2020 >> mysql_structure.php already updated accordingly so first-time installers won't need this

$sql = "ALTER TABLE `game_list` 
		ADD `api_access_token` varchar(32) NOT NULL DEFAULT '';";

?>
