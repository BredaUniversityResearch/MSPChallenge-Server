<?php 
// make sure this file only performs SQL statements on the database
// use $this to work with the database, e.g. $this->query($sql);
// this file will be run once after a successful login


// but don't run when called directly
defined('APP_RAN') or die();

// dbase upgrade 30 Nov 2020 >> mysql_structure.php & install.php already updated accordingly so first-time installers won't need this

$sql = "ALTER TABLE `game_saves` CHANGE `game_config_filename` `game_config_files_filename` VARCHAR(45) NOT NULL, 
                                 ADD `game_config_versions_region` VARCHAR(45) NOT NULL;";

?>
