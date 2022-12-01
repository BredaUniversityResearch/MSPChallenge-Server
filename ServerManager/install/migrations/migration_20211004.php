<?php
// phpcs:ignoreFile Generic.Files.LineLength.TooLong
// make sure this file only performs SQL statements on the database
// use just use the $sql var


// but don't run when called directly
defined('APP_RAN') or die();

// dbase upgrade 4 oct 2021 >> mysql_structure.php already updated accordingly so first-time installers won't need this

$sql = "INSERT INTO `game_config_files` (`filename`, `description`) VALUES ('Adriatic_Sea_basic', 'Adriatic Sea basic configuration file supplied by BUas');
        INSERT INTO `game_config_version` (`game_config_files_id`, `version`, `version_message`, `visibility`, `upload_time`, `upload_user`, `last_played_time`, `file_path`, `region`, `client_versions`) VALUES
        (LAST_INSERT_ID(), 1, 'See www.mspchallenge.info', 'active', 1585692000, 1, 0, 'Adriatic_Sea_basic/Adriatic_Sea_basic_1.json', 'adriatic', 'Any');";
