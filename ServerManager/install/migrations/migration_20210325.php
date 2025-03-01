<?php
// phpcs:ignoreFile Generic.Files.LineLength.TooLong
// make sure this file only performs SQL statements on the database
// use just use the $sql var


// but don't run when called directly
defined('APP_RAN') or die();

// dbase upgrade 25 mar 2021 >> mysql_structure.php already updated accordingly so first-time installers won't need this

$sql = "CREATE TABLE `game_geoservers` (
    `id` int(11) NOT NULL,
    `name` varchar(128) NOT NULL,
    `address` varchar(255) NOT NULL,
    `username` varchar(255) NOT NULL,
    `password` varchar(255) NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ALTER TABLE `game_geoservers`
    ADD PRIMARY KEY (`id`);
  ALTER TABLE `game_geoservers`
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
  INSERT INTO `game_geoservers` (`id`, `name`, `address`, `username`, `password`) VALUES ('1', 'Default: the public MSP Challenge GeoServer', 'automatically obtained', 'automatically obtained', 'automatically obtained'); 
  ALTER TABLE `game_list`
    ADD `game_geoserver_id` INT(11) NOT NULL DEFAULT 1 AFTER `game_server_id`;
  ALTER TABLE `game_list` 
    CHANGE `password_admin` `password_admin` TEXT NOT NULL, 
    CHANGE `password_player` `password_player` TEXT NOT NULL; ";
