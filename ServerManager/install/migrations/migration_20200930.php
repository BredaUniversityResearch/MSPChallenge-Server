<?php 
// make sure this file only performs SQL statements on the database
// use just use the $sql var


// but don't run when called directly
defined('APP_RAN') or die();

// dbase upgrade 30 Sep 2020 >> mysql_structure.php already updated accordingly so first-time installers won't need this

$sql = "CREATE TABLE `game_saves` (
         `id` int(11) NOT NULL,
         `game_id` int(11) NOT NULL,
         `game_current_month` int(11) NOT NULL,
         `save_type` enum('full','layers') NOT NULL DEFAULT 'full',
         `save_path` varchar(255) NOT NULL,
         `save_notes` longtext NOT NULL DEFAULT '',
         `save_visibility` enum('active','archived') NOT NULL DEFAULT 'active',
         `save_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;--
         ALTER TABLE `game_saves`
         ADD PRIMARY KEY (`id`),
         ADD UNIQUE (`save_path`);
         ALTER TABLE `game_saves`
         MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;";

?>
