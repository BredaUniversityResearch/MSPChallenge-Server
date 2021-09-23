<?php 
// make sure this file only performs SQL statements on the database
// use just use the $sql var


// but don't run when called directly
defined('APP_RAN') or die();

// dbase upgrade 13 apr 2021 >> mysql_structure.php already updated accordingly so first-time installers won't need this

$sql = "DROP TABLE IF EXISTS `users_online`; 
ALTER TABLE `game_watchdog_servers` ADD `available` TINYINT(1) NOT NULL DEFAULT '1'; 
ALTER TABLE `game_geoservers` ADD `available` TINYINT(1) NOT NULL DEFAULT '1'; 
ALTER TABLE `game_list` 
    CHANGE `password_admin` `password_admin` TEXT NOT NULL, 
    CHANGE `password_player` `password_player` TEXT NOT NULL,
    ADD `server_version` VARCHAR(45) NOT NULL DEFAULT '4.0-beta7';
ALTER TABLE `game_saves` 
    CHANGE `password_admin` `password_admin` TEXT NOT NULL, 
    CHANGE `password_player` `password_player` TEXT NOT NULL,
    ADD `server_version` VARCHAR(45) NOT NULL DEFAULT '4.0-beta7';";

?>
