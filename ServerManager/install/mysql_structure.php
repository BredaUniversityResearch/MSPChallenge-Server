<?php

$sqls = "
--
-- Table structure for table `game_config_files`
--

CREATE TABLE `game_config_files` (
  `id` int(11) NOT NULL,
  `filename` varchar(45) NOT NULL COMMENT 'no whitespaces and other strage characters please and without file extension (.json)',
  `description` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `game_config_version`
--

CREATE TABLE `game_config_version` (
  `id` int(11) NOT NULL,
  `game_config_files_id` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `version_message` mediumtext DEFAULT NULL,
  `visibility` enum('active','archived') NOT NULL,
  `upload_time` bigint(20) NOT NULL,
  `upload_user` int(11) NOT NULL COMMENT 'User ID from UserSpice.',
  `last_played_time` bigint(20) UNSIGNED NOT NULL COMMENT 'Unix timestamp',
  `file_path` varchar(255) NOT NULL COMMENT 'File path relative to the root config directory',
  `region` varchar(45) NOT NULL COMMENT 'Region defined in the config file',
  `client_versions` varchar(45) NOT NULL COMMENT 'Compatible client versions. Formatted as \"min-max\"'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `game_list`
--

CREATE TABLE `game_list` (
`id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `game_config_version_id` int(11) NOT NULL,
  `game_server_id` int(11) NOT NULL,
  `watchdog_server_id` int(11) NOT NULL,
  `game_creation_time` bigint(20) NOT NULL COMMENT 'Unix timestamp',
  `game_start_year` int(11) NOT NULL,
  `game_end_month` int(11) NOT NULL,
  `game_current_month` int(11) NOT NULL,
  `game_running_til_time` bigint(20) NOT NULL COMMENT 'Unix timestamp',
  `password_admin` varchar(45) NOT NULL,
  `password_player` varchar(45) NOT NULL,
  `session_state` enum('request','initializing','healthy','failed','archived') NOT NULL,
  `game_state` enum('setup','simulation','play','pause','end', 'fastforward') NOT NULL,
  `game_visibility` enum('public','private') NOT NULL,
  `players_active` int(10) unsigned DEFAULT NULL,
  `players_past_hour` int(10) unsigned DEFAULT NULL,
  `demo_session` tinyint(1) NOT NULL DEFAULT '0',
  `api_access_token` varchar(32) NOT NULL DEFAULT '',
  `save_id` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `game_saves`
--

CREATE TABLE `game_saves` (
  `id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `game_config_version_id` int(11) NOT NULL,
  `game_config_files_filename` VARCHAR(45) NOT NULL,
  `game_config_versions_region` VARCHAR(45) NOT NULL,
  `game_server_id` int(11) NOT NULL,
  `watchdog_server_id` int(11) NOT NULL,
  `game_creation_time` bigint(20) NOT NULL COMMENT 'Unix timestamp',
  `game_start_year` int(11) NOT NULL,
  `game_end_month` int(11) NOT NULL,
  `game_current_month` int(11) NOT NULL,
  `game_running_til_time` bigint(20) NOT NULL COMMENT 'Unix timestamp',
  `password_admin` varchar(45) NOT NULL,
  `password_player` varchar(45) NOT NULL,
  `session_state` enum('request','initializing','healthy','failed','archived') NOT NULL,
  `game_state` enum('setup','simulation','play','pause','end') NOT NULL,
  `game_visibility` enum('public','private') NOT NULL,
  `players_active` int(10) UNSIGNED DEFAULT NULL,
  `players_past_hour` int(10) UNSIGNED DEFAULT NULL,
  `demo_session` tinyint(1) NOT NULL DEFAULT 0,
  `api_access_token` varchar(32) NOT NULL DEFAULT '',
  `save_type` enum('full','layers') NOT NULL DEFAULT 'full',
  `save_path` varchar(255) NOT NULL,
  `save_notes` longtext NOT NULL DEFAULT '',
  `save_visibility` enum('active','archived') NOT NULL DEFAULT 'active',
  `save_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `game_servers`
--

CREATE TABLE `game_servers` (
  `id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `address` varchar(255) NOT NULL COMMENT 'with trailing slash'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `game_watchdog_servers`
--

CREATE TABLE `game_watchdog_servers` (
  `id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `address` varchar(255) NOT NULL COMMENT 'with trailing slash'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `name` varchar(50) NOT NULL,
  `value` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `users` (
`id` int(11) NOT NULL,
  `email` varchar(155) NOT NULL,
  `email_new` varchar(155) DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `pin` varchar(255) DEFAULT NULL,
  `fname` varchar(255) NOT NULL,
  `lname` varchar(255) NOT NULL,
  `permissions` int(11) NOT NULL,
  `logins` int(11) unsigned NOT NULL,
  `account_owner` tinyint(4) NOT NULL DEFAULT '0',
  `account_id` int(11) NOT NULL DEFAULT '0',
  `company` varchar(255) NOT NULL,
  `join_date` datetime NOT NULL,
  `last_login` datetime NOT NULL,
  `email_verified` tinyint(4) NOT NULL DEFAULT '0',
  `vericode` varchar(15) NOT NULL,
  `active` int(1) NOT NULL,
  `oauth_provider` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `oauth_uid` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `gender` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `locale` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `gpluslink` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `picture` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  `fb_uid` varchar(255) NOT NULL,
  `un_changed` int(1) NOT NULL,
  `msg_exempt` int(1) NOT NULL DEFAULT '0',
  `last_confirm` datetime DEFAULT NULL,
  `protected` int(1) NOT NULL DEFAULT '0',
  `dev_user` int(1) NOT NULL DEFAULT '0',
  `msg_notification` int(1) NOT NULL DEFAULT '1',
  `force_pr` int(1) NOT NULL DEFAULT '0',
  `twoKey` varchar(16) DEFAULT NULL,
  `twoEnabled` int(1) DEFAULT '0',
  `twoDate` datetime DEFAULT NULL,
  `cloak_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `org` int(11) DEFAULT NULL,
  `account_mgr` int(11) DEFAULT '0',
  `oauth_tos_accepted` tinyint(1) DEFAULT NULL,
  `vericode_expiry` datetime DEFAULT NULL,
  `language` varchar(255) DEFAULT 'en-US',
  `apibld_key` varchar(255) DEFAULT NULL,
  `apibld_ip` varchar(255) DEFAULT NULL,
  `apibld_blocked` tinyint(1) DEFAULT '0',
  `plg_sl_opt_out` tinyint(1) DEFAULT '0',
  `ldap` varchar(255) DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `users_online`
--

CREATE TABLE `users_online` (
  `id` int(10) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `timestamp` varchar(15) NOT NULL,
  `user_id` int(10) NOT NULL,
  `session` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
 ADD PRIMARY KEY (`id`), ADD KEY `EMAIL` (`email`) USING BTREE;
 
 --
 -- Indexes for table `users_online`
 --
 ALTER TABLE `users_online`
   ADD PRIMARY KEY (`id`);

--
-- Indexes for table `game_config_files`
--
ALTER TABLE `game_config_files`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `game_config_version`
--
ALTER TABLE `game_config_version`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_game_config_version` (`game_config_files_id`,`version`),
  ADD KEY `fk_game_config_version_game_config_files1_idx` (`game_config_files_id`);

--
-- Indexes for table `game_list`
--
ALTER TABLE `game_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_game_list_available_game_servers_idx` (`game_server_id`),
  ADD KEY `fk_game_list_available_watchdog_servers1_idx` (`watchdog_server_id`),
  ADD KEY `fk_game_list_game_config_version1_idx` (`game_config_version_id`);

--
-- Indexen voor tabel `game_saves`
--
ALTER TABLE `game_saves`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE (`save_path`);
     
--
-- Indexes for table `game_servers`
--
ALTER TABLE `game_servers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `game_watchdog_servers`
--
ALTER TABLE `game_watchdog_servers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users_online`
--
ALTER TABLE `users_online`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;
  
--
-- AUTO_INCREMENT for table `game_config_files`
--
ALTER TABLE `game_config_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_config_version`
--
ALTER TABLE `game_config_version`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_list`
--
ALTER TABLE `game_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `game_saves`
--
ALTER TABLE `game_saves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_servers`
--
ALTER TABLE `game_servers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_watchdog_servers`
--
ALTER TABLE `game_watchdog_servers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

  --
  -- Dumping data for table `game_servers`
  --

  INSERT INTO `game_servers` (`id`, `name`, `address`) VALUES
  (1, 'Default: the server machine', 'localhost');

  INSERT INTO `game_watchdog_servers` (`id`, `name`, `address`) VALUES
  (1, 'Default: the same server machine', 'localhost');

  INSERT INTO `game_config_files` (`id`, `filename`, `description`) VALUES
  (1, 'North_Sea_basic', 'North Sea basic configuration file supplied by BUas'),
  (2, 'Baltic_Sea_basic', 'Baltic Sea basic configuration file supplied by BUas'),
  (3, 'Clyde_marine_region_basic', 'Clyde marine region basic configuration file supplied by BUas'),
  (4, 'North_Sea_Digitwin_basic', 'North Sea Digitwin basic configuration file supplied by BUas');

  INSERT INTO `game_config_version` (`id`, `game_config_files_id`, `version`, `version_message`, `visibility`, `upload_time`, `upload_user`, `last_played_time`, `file_path`, `region`, `client_versions`) VALUES
  (1, 1, 1, 'See www.mspchallenge.info', 'active', 1585692000, 1, 0, 'North_Sea_basic/North_Sea_basic_1.json', 'northsee', 'Any'),
  (2, 2, 1, 'See www.mspchallenge.info', 'active', 1585692000, 1, 0, 'Baltic_Sea_basic/Baltic_Sea_basic_1.json', 'balticline', 'Any'),
  (3, 3, 1, 'See www.mspchallenge.info', 'active', 1585692000, 1, 0, 'Clyde_marine_region_basic/Clyde_marine_region_basic_1.json', 'simcelt', 'Any'),
  (4, 4, 1, 'See www.mspchallenge.info', 'active', 1585692000, 1, 0, 'North_Sea_Digitwin_basic/North_Sea_Digitwin_basic_1.json', 'northsee', 'Any');

  INSERT INTO `settings` (`name`, `value`) VALUES 
  ('migration_20200618.php', 'Never'),
  ('migration_20200713.php', 'Never'),
  ('migration_20200721.php', 'Never'),
  ('migration_20200901.php', 'Never'),
  ('migration_20200917.php', 'Never'),
  ('migration_20200930.php', 'Never'),
  ('migration_20201002.php', 'Never'),
  ('migration_20201104.php', 'Never');

";
?>
