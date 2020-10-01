--
-- Table structure for table `phyxo_caddie`
--

DROP TABLE IF EXISTS `phyxo_caddie`;
CREATE TABLE `phyxo_caddie` (
  `user_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `element_id` MEDIUMINT(8) NOT NULL DEFAULT '0',
  PRIMARY KEY  (`user_id`,`element_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_categories`
--

DROP TABLE IF EXISTS `phyxo_categories`;
CREATE TABLE `phyxo_categories` (
  `id` SMALLINT(5) UNSIGNED NOT NULL auto_increment,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `id_uppercat` SMALLINT(5) UNSIGNED DEFAULT NULL,
  `comment` text,
  `dir` VARCHAR(255) DEFAULT NULL,
  `rank` SMALLINT(5) UNSIGNED DEFAULT NULL,
  `status` enum('public','private') NOT NULL DEFAULT 'public',
  `site_id` TINYINT(4) UNSIGNED DEFAULT NULL,
  `visible` enum('true','false') NOT NULL DEFAULT 'true',
  `representative_picture_id` MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  `uppercats` VARCHAR(255) NOT NULL DEFAULT '',
  `commentable` enum('true','false') NOT NULL DEFAULT 'true',
  `global_rank` VARCHAR(255) DEFAULT NULL,
  `image_order` VARCHAR(128) DEFAULT NULL,
  `permalink` VARCHAR(64) BINARY DEFAULT NULL,
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `categories_i3` (`permalink`),
  KEY `categories_i2` (`id_uppercat`),
  KEY `lastmodified` (`lastmodified`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_comments`
--

DROP TABLE IF EXISTS `phyxo_comments`;
CREATE TABLE `phyxo_comments` (
  `id` int(11) UNSIGNED NOT NULL auto_increment,
  `image_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `date` datetime DEFAULT NULL,
  `author` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `author_id` MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  `anonymous_id` VARCHAR(45) NOT NULL,
  `website_url` VARCHAR(255) DEFAULT NULL,
  `content` longtext,
  `validated` enum('true','false') NOT NULL DEFAULT 'false',
  `validation_date` datetime DEFAULT NULL,
  PRIMARY KEY  (`id`),
  KEY `comments_i2` (`validation_date`),
  KEY `comments_i1` (`image_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_config`
--

DROP TABLE IF EXISTS `phyxo_config`;
CREATE TABLE `phyxo_config` (
  `param` VARCHAR(40) NOT NULL DEFAULT '',
  `type` VARCHAR(15),
  `value` text,
  `comment` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY  (`param`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_favorites`
--

DROP TABLE IF EXISTS `phyxo_favorites`;
CREATE TABLE `phyxo_favorites` (
  `user_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `image_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY  (`user_id`,`image_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_group_access`
--

DROP TABLE IF EXISTS `phyxo_group_access`;
CREATE TABLE `phyxo_group_access` (
  `group_id` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
  `cat_id` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY  (`group_id`,`cat_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_groups`
--

DROP TABLE IF EXISTS `phyxo_groups`;
CREATE TABLE `phyxo_groups` (
  `id` SMALLINT(5) UNSIGNED NOT NULL auto_increment,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `is_default` TINYINT(1) NOT NULL,
  `lastmodified` DATETIME DEFAULT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `groups_ui1` (`name`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_history`
--

DROP TABLE IF EXISTS `phyxo_history`;
CREATE TABLE `phyxo_history` (
  `id` int(10) UNSIGNED NOT NULL auto_increment,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `user_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `ip` VARCHAR(15) NOT NULL DEFAULT '',
  `section` enum('categories','tags','search','list','favorites','most_visited','best_rated','recent_pics','recent_cats') DEFAULT NULL,
  `category_id` SMALLINT(5) DEFAULT NULL,
  `tag_ids` VARCHAR(50) DEFAULT NULL,
  `image_id` MEDIUMINT(8) DEFAULT NULL,
  `summarized` enum('true','false') DEFAULT 'false',
  `image_type` enum('picture','high','other') DEFAULT NULL,
  PRIMARY KEY  (`id`),
  KEY `history_i1` (`summarized`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_history_summary`
--

DROP TABLE IF EXISTS `phyxo_history_summary`;
CREATE TABLE `phyxo_history_summary` (
  `year` SMALLINT(4) NOT NULL DEFAULT '0',
  `month` TINYINT(2) DEFAULT NULL,
  `day` TINYINT(2) DEFAULT NULL,
  `hour` TINYINT(2) DEFAULT NULL,
  `nb_pages` int(11) DEFAULT NULL,
  UNIQUE KEY history_summary_ymdh (`year`,`month`,`day`,`hour`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_image_category`
--

DROP TABLE IF EXISTS `phyxo_image_category`;
CREATE TABLE `phyxo_image_category` (
  `image_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `category_id` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
  `rank` MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  PRIMARY KEY  (`image_id`,`category_id`),
  KEY `image_category_i1` (`category_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_image_tag`
--

DROP TABLE IF EXISTS `phyxo_image_tag`;
CREATE TABLE `phyxo_image_tag` (
  `image_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `tag_id` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
  `validated` enum('true','false') NOT NULL DEFAULT 'true',
  `created_by` MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  `status` SMALLINT(3) DEFAULT 1,
  PRIMARY KEY  (`image_id`,`tag_id`),
  KEY `image_tag_i1` (`tag_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_images`
--

DROP TABLE IF EXISTS `phyxo_images`;
CREATE TABLE `phyxo_images` (
  `id` MEDIUMINT(8) UNSIGNED NOT NULL auto_increment,
  `file` VARCHAR(255) BINARY NOT NULL DEFAULT '',
  `date_available` datetime DEFAULT NULL,
  `date_creation` datetime DEFAULT NULL,
  `name` VARCHAR(255) DEFAULT NULL,
  `comment` text,
  `author` VARCHAR(255) DEFAULT NULL,
  `hit` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `filesize` MEDIUMINT(9) UNSIGNED DEFAULT NULL,
  `width` SMALLINT(9) UNSIGNED DEFAULT NULL,
  `height` SMALLINT(9) UNSIGNED DEFAULT NULL,
  `coi` char(4) DEFAULT NULL COMMENT 'center of interest',
  `representative_ext` VARCHAR(4) DEFAULT NULL,
  `date_metadata_update` date DEFAULT NULL,
  `rating_score` float(5,2) UNSIGNED DEFAULT NULL,
  `path` VARCHAR(255) NOT NULL DEFAULT '',
  `storage_category_id` SMALLINT(5) UNSIGNED DEFAULT NULL,
  `level` TINYINT UNSIGNED NOT NULL DEFAULT '0',
  `md5sum` char(32) DEFAULT NULL,
  `added_by` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `rotation` TINYINT UNSIGNED DEFAULT NULL,
  `latitude` double(8, 6) DEFAULT NULL,
  `longitude` double(9, 6) DEFAULT NULL,
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `images_i2` (`date_available`),
  KEY `images_i3` (`rating_score`),
  KEY `images_i4` (`hit`),
  KEY `images_i5` (`date_creation`),
  KEY `images_i1` (`storage_category_id`),
  KEY `images_i6` (`latitude`),
  KEY `lastmodified` (`lastmodified`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_languages`
--

DROP TABLE IF EXISTS `phyxo_languages`;
CREATE TABLE `phyxo_languages` (
  `id` VARCHAR(64) NOT NULL DEFAULT '',
  `version` VARCHAR(64) NOT NULL DEFAULT '0',
  `name` VARCHAR(64) DEFAULT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_old_permalinks`
--

DROP TABLE IF EXISTS `phyxo_old_permalinks`;
CREATE TABLE `phyxo_old_permalinks` (
  `cat_id` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
  `permalink` VARCHAR(64) BINARY NOT NULL DEFAULT '',
  `date_deleted` datetime DEFAULT NULL,
  `last_hit` datetime DEFAULT NULL,
  `hit` int(10) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY  (`permalink`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_plugins`
--

DROP TABLE IF EXISTS `phyxo_plugins`;
CREATE TABLE `phyxo_plugins` (
  `id` VARCHAR(64) BINARY NOT NULL DEFAULT '',
  `state` VARCHAR(25) NOT NULL DEFAULT 'inactive',
  `version` VARCHAR(64) NOT NULL DEFAULT '0',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_rate`
--

DROP TABLE IF EXISTS `phyxo_rate`;
CREATE TABLE `phyxo_rate` (
  `user_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `element_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `anonymous_id` VARCHAR(45) NOT NULL DEFAULT '',
  `rate` TINYINT(2) UNSIGNED NOT NULL DEFAULT '0',
  `date` date DEFAULT NULL,
  PRIMARY KEY  (`element_id`,`user_id`,`anonymous_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_search`
--

DROP TABLE IF EXISTS `phyxo_search`;
CREATE TABLE `phyxo_search` (
  `id` int(10) UNSIGNED NOT NULL auto_increment,
  `last_seen` date DEFAULT NULL,
  `rules` text,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_sessions`
--

DROP TABLE IF EXISTS `phyxo_sessions`;
CREATE TABLE `phyxo_sessions` (
  `sess_id` VARCHAR(128) NOT NULL PRIMARY KEY,
  `sess_data` BLOB NOT NULL,
  `sess_time` INTEGER UNSIGNED NOT NULL,
  `sess_lifetime` INTEGER UNSIGNED NOT NULL
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_sites`
--

DROP TABLE IF EXISTS `phyxo_sites`;
CREATE TABLE `phyxo_sites` (
  `id` TINYINT(4) NOT NULL auto_increment,
  `galleries_url` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_tags`
--

DROP TABLE IF EXISTS `phyxo_tags`;
CREATE TABLE `phyxo_tags` (
  `id` SMALLINT(5) UNSIGNED NOT NULL auto_increment,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `url_name` VARCHAR(255) BINARY NOT NULL DEFAULT '',
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY  (`id`),
  KEY `tags_i1` (`url_name`),
  KEY `lastmodified` (`lastmodified`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_themes`
--

DROP TABLE IF EXISTS `phyxo_themes`;
CREATE TABLE `phyxo_themes` (
  `id` VARCHAR(64) NOT NULL DEFAULT '',
  `version` VARCHAR(64) NOT NULL DEFAULT '0',
  `name` VARCHAR(64) DEFAULT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_upgrade`
--

DROP TABLE IF EXISTS `phyxo_upgrade`;
CREATE TABLE `phyxo_upgrade` (
  `id` VARCHAR(20) NOT NULL DEFAULT '',
  `applied` datetime DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_user_access`
--

DROP TABLE IF EXISTS `phyxo_user_access`;
CREATE TABLE `phyxo_user_access` (
  `user_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `cat_id` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY  (`user_id`,`cat_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_user_cache`
--

DROP TABLE IF EXISTS `phyxo_user_cache`;
CREATE TABLE `phyxo_user_cache` (
  `user_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `need_update` enum('true','false') NOT NULL DEFAULT 'true',
  `cache_update_time` integer UNSIGNED NOT NULL DEFAULT 0,
  `forbidden_categories` mediumtext,
  `nb_total_images` MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  `last_photo_date` datetime DEFAULT NULL,
  `nb_available_tags` INT(5) DEFAULT NULL,
  `nb_available_comments` INT(5) DEFAULT NULL,
  `image_access_type` enum('NOT IN','IN') NOT NULL DEFAULT 'NOT IN',
  `image_access_list` mediumtext DEFAULT NULL,
  PRIMARY KEY  (`user_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_user_cache_categories`
--

DROP TABLE IF EXISTS `phyxo_user_cache_categories`;
CREATE TABLE `phyxo_user_cache_categories` (
  `user_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `cat_id` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
  `date_last` datetime DEFAULT NULL,
  `max_date_last` datetime DEFAULT NULL,
  `nb_images` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `count_images` MEDIUMINT(8) UNSIGNED DEFAULT '0',
  `nb_categories` MEDIUMINT(8) UNSIGNED DEFAULT '0',
  `count_categories` MEDIUMINT(8) UNSIGNED DEFAULT '0',
  `user_representative_picture_id` MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  PRIMARY KEY  (`user_id`,`cat_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_user_feed`
--

DROP TABLE IF EXISTS `phyxo_user_feed`;
CREATE TABLE `phyxo_user_feed` (
  `id` VARCHAR(50) BINARY NOT NULL DEFAULT '',
  `user_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `last_check` datetime DEFAULT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_user_group`
--

DROP TABLE IF EXISTS `phyxo_user_group`;
CREATE TABLE `phyxo_user_group` (
  `user_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `group_id` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY  (`group_id`,`user_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_user_infos`
--

DROP TABLE IF EXISTS `phyxo_user_infos`;
CREATE TABLE `phyxo_user_infos` (
  `user_id` INTEGER NOT NULL,
  `nb_image_page` SMALLINT(3) UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(50) NOT NULL,
  `language` VARCHAR(50) NOT NULL,
  `expand` TINYINT(1) NOT NULL DEFAULT 0,
  `show_nb_comments` TINYINT(1) NOT NULL DEFAULT 0,
  `show_nb_hits` TINYINT(1) NOT NULL DEFAULT 0,
  `recent_period` TINYINT(3) UNSIGNED NOT NULL,
  `theme` VARCHAR(255) NOT NULL,
  `registration_date` DATETIME DEFAULT NULL,
  `enabled_high` TINYINT(1) NOT NULL,
  `level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `activation_key` VARCHAR(255) DEFAULT NULL,
  `activation_key_expire` DATETIME DEFAULT NULL,
  `lastmodified` DATETIME DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_user_mail_notification`
--

DROP TABLE IF EXISTS `phyxo_user_mail_notification`;
CREATE TABLE `phyxo_user_mail_notification` (
  `user_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `check_key` VARCHAR(16) BINARY NOT NULL DEFAULT '',
  `enabled` TINYINT(1) NOT NULL,
  `last_send` datetime DEFAULT NULL,
  PRIMARY KEY  (`user_id`),
  UNIQUE KEY `user_mail_notification_ui1` (`check_key`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_users`
--

DROP TABLE IF EXISTS `phyxo_users`;
CREATE TABLE `phyxo_users` (
  `id` MEDIUMINT(8) UNSIGNED NOT NULL auto_increment,
  `username` VARCHAR(100) BINARY NOT NULL DEFAULT '',
  `password` VARCHAR(255) DEFAULT NULL,
  `mail_address` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `users_ui1` (`username`)
) ENGINE=InnoDB;
