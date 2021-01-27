<?php 
// make sure this file only performs SQL statements on the database
// use $this to work with the database, e.g. $this->query($sql);
// this file will be run once after a successful login


// but don't run when called directly
defined('APP_RAN') or die();

// dbase upgrade 13 July 2020 >> mysql_structure.php already updated accordingly so first-time installers won't need this

   $sql = 
   "CREATE TABLE `users_online` (
     `id` int(10) NOT NULL,
     `ip` varchar(15) NOT NULL,
     `timestamp` varchar(15) NOT NULL,
     `user_id` int(10) NOT NULL,
     `session` varchar(255) NOT NULL
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
   ALTER TABLE `users_online`
     ADD PRIMARY KEY (`id`);
   ALTER TABLE `users_online`
     MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;";

?>
