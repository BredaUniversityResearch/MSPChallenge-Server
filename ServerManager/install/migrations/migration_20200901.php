<?php 
// make sure this file only performs SQL statements on the database
// use just use the $sql var


// but don't run when called directly
defined('APP_RAN') or die();

// dbase upgrade 1 Sep 2020 >> mysql_structure.php already updated accordingly so first-time installers won't need this

$sql = 
   "UPDATE game_config_version SET region = 'northsea' WHERE region = 'North Sea';
   UPDATE game_config_version SET region = 'northsea' WHERE region = 'North Sea Digitwin';
   UPDATE game_config_version SET region = 'balticline' WHERE region = 'Baltic Sea';
   UPDATE game_config_version SET region = 'balticline' WHERE region = 'Baltic';
   UPDATE game_config_version SET region = 'simcelt' WHERE region = 'Clyde Region';
   UPDATE game_config_version SET region = 'adriatic' WHERE region = 'Unknown';";

?>
