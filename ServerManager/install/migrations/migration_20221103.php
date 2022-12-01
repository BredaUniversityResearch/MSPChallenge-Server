<?php
// make sure this file only performs SQL statements on the database
// use just use the $sql var

// but don't run when called directly
defined('APP_RAN') or die();

// dbase upgrade 3 Nov 2022 >> mysql_structure.php already updated accordingly so first-time installers won't need this

$sql = "ALTER TABLE `users`
CHANGE `id` `id` int(11) NOT NULL AUTO_INCREMENT,
DROP `email_new`,
ADD `token` text COLLATE 'utf8_general_ci' NOT NULL AFTER `username`,
ADD `refresh_token` text COLLATE 'utf8_general_ci' NOT NULL AFTER `token`,
ADD `refresh_token_expiration` datetime NOT NULL AFTER `refresh_token`,
DROP `password`,
DROP `fname`,
DROP `lname`,
DROP `permissions`,
DROP `logins`,
DROP `company`,
DROP `join_date`,
DROP `last_login`,
DROP `email_verified`,
DROP `vericode`,
DROP `active`,
DROP `oauth_provider`,
DROP `oauth_uid`,
DROP `gender`,
DROP `locale`,
DROP `gpluslink`,
DROP `picture`,
DROP `created`,
DROP `modified`,
DROP `fb_uid`,
DROP `un_changed`,
DROP `msg_exempt`,
DROP `last_confirm`,
DROP `protected`,
DROP `dev_user`,
DROP `msg_notification`,
DROP `force_pr`,
DROP `twoKey`,
DROP `twoEnabled`,
DROP `twoDate`,
DROP `cloak_allowed`,
DROP `org`,
DROP `account_mgr`,
DROP `oauth_tos_accepted`,
DROP `vericode_expiry`,
DROP `language`,
DROP `apibld_key`,
DROP `apibld_ip`,
DROP `apibld_blocked`,
DROP `plg_sl_opt_out`,
DROP `ldap`;
ALTER TABLE `users`
ADD UNIQUE `username` (`username`);";
