<?php
// phpcs:ignoreFile Generic.Files.LineLength.TooLong
// make sure this file only performs SQL statements on the database
// use just use the $sql var


// but don't run when called directly
defined('APP_RAN') or die();

// dbase upgrade 11 feb 2021 >> mysql_structure.php already updated accordingly so first-time installers won't need this

$sql = "ALTER TABLE `game_list` CHANGE `game_state` `game_state` ENUM('setup','simulation','play','pause','end','fastforward') CHARACTER SET utf8mb4 NOT NULL;";
