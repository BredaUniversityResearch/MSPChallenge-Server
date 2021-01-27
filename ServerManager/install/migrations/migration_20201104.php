<?php 
// make sure this file only performs SQL statements on the database
// use $this to work with the database, e.g. $this->query($sql);
// this file will be run once after a successful login


// but don't run when called directly
defined('APP_RAN') or die();

// dbase upgrade 04 Nov 2020 >> mysql_structure.php & install.php already updated accordingly so first-time installers won't need this

$sql = "ALTER TABLE `game_saves`
			ADD `name` varchar(128) NOT NULL AFTER `id`,
			ADD `game_config_version_id` int(11) NOT NULL AFTER `id`,
			ADD `game_server_id` int(11) NOT NULL AFTER `id`,
			ADD `watchdog_server_id` int(11) NOT NULL AFTER `id`,
			ADD `game_creation_time` bigint(20) NOT NULL COMMENT 'Unix timestamp' AFTER `id`,
			ADD `game_start_year` int(11) NOT NULL AFTER `id`,
			ADD `game_end_month` int(11) NOT NULL AFTER `id`,
			ADD `game_running_til_time` bigint(20) NOT NULL COMMENT 'Unix timestamp' AFTER `id`,
			ADD `password_admin` varchar(45) NOT NULL AFTER `id`,
			ADD `password_player` varchar(45) NOT NULL AFTER `id`,
			ADD `session_state` enum('request','initializing','healthy','failed','archived') NOT NULL AFTER `id`,
			ADD `game_state` enum('setup','simulation','play','pause','end') NOT NULL AFTER `id`,
			ADD `game_visibility` enum('public','private') NOT NULL AFTER `id`,
			ADD `players_active` int(10) UNSIGNED DEFAULT NULL AFTER `id`,
			ADD `players_past_hour` int(10) UNSIGNED DEFAULT NULL AFTER `id`,
			ADD `demo_session` tinyint(1) NOT NULL DEFAULT 0 AFTER `id`,
			ADD `api_access_token` varchar(32) NOT NULL DEFAULT '' AFTER `id`,
			ADD `game_config_filename` VARCHAR(45) NOT NULL  AFTER `game_config_version_id`,
			DROP `game_id`;
		ALTER TABLE `game_list` ADD `save_id` INT(11) NOT NULL DEFAULT '0' AFTER `api_access_token`;";

?>
