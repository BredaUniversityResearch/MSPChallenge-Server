<?php 
// make sure this file only performs SQL statements on the database
// use just use the $sql var


// but don't run when called directly
defined('APP_RAN') or die();

// dbase upgrade 31 may 2021 >> mysql_structure.php already updated accordingly so first-time installers won't need this

$sql = "UPDATE game_geoservers SET address = 'https://geo.mspchallenge.info/geoserver/', username = 'YXV0b21hdGljYWxseW9idGFpbmVk', password = 'YXV0b21hdGljYWxseW9idGFpbmVk' WHERE id = 1;
        ALTER TABLE `game_saves` DROP `save_path`;
        ALTER TABLE `game_watchdog_servers` ADD UNIQUE(`address`); 
        ALTER TABLE `game_watchdog_servers` ADD UNIQUE(`name`); 
        ALTER TABLE `game_geoservers` ADD UNIQUE(`name`); 
        ALTER TABLE `game_geoservers` ADD UNIQUE(`address`); 
        ALTER TABLE `settings` CHANGE `value` `value` LONGTEXT NOT NULL; ";

?>
