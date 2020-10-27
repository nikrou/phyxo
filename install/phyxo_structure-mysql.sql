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
  `id` SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `id_uppercat` SMALLINT(5) UNSIGNED DEFAULT NULL,
  `comment` TEXT,
  `dir` VARCHAR(255) DEFAULT NULL,
  `rank` SMALLINT(5) UNSIGNED DEFAULT NULL,
  `status` VARCHAR(25) NOT NULL DEFAULT 'public',
  `site_id` TINYINT(4) UNSIGNED DEFAULT NULL,
  `visible` TINYINT(1) NOT NULL DEFAULT 1,
  `representative_picture_id` MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  `uppercats` VARCHAR(255) NOT NULL DEFAULT '',
  `commentable` TINYINT(1) NOT NULL DEFAULT 1,
  `global_rank` VARCHAR(255) DEFAULT NULL,
  `image_order` VARCHAR(128) DEFAULT NULL,
  `permalink` VARCHAR(64) BINARY DEFAULT NULL,
  `lastmodified` DATETIME DEFAULT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `categories_i3` (`permalink`),
  KEY `categories_i2` (`id_uppercat`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_comments`
--

DROP TABLE IF EXISTS `phyxo_comments`;
CREATE TABLE `phyxo_comments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `image_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `date` datetime DEFAULT NULL,
  `author` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `author_id` MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  `anonymous_id` VARCHAR(45) NOT NULL,
  `website_url` VARCHAR(255) DEFAULT NULL,
  `content` LONGTEXT,
  `validated` ENUM('true','false') NOT NULL DEFAULT 'false',
  `validation_date` DATETIME DEFAULT NULL,
  PRIMARY KEY  (`id`),
  KEY `comments_i2` (`validation_date`),
  KEY `comments_i1` (`image_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_config`
--

DROP TABLE IF EXISTS `phyxo_config`;
CREATE TABLE `phyxo_config` (
  `param` VARCHAR(40) NOT NULL,
  `value` LONGTEXT DEFAULT NULL,
  `type` VARCHAR(15) DEFAULT 'string',
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
  `id` INT AUTO_INCREMENT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `is_default` TINYINT(1) NOT NULL,
  `lastmodified` DATETIME DEFAULT NULL,
  UNIQUE KEY `groups_ui1` (`name`),
  PRIMARY KEY(`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_history`
--

DROP TABLE IF EXISTS `phyxo_history`;
CREATE TABLE `phyxo_history` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `user_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `ip` VARCHAR(15) NOT NULL DEFAULT '',
  `section` enum('categories','tags','search','list','favorites','most_visited','best_rated','recent_pics','recent_cats') DEFAULT NULL,
  `category_id` SMALLINT(5) DEFAULT NULL,
  `tag_ids` VARCHAR(50) DEFAULT NULL,
  `image_id` MEDIUMINT(8) DEFAULT NULL,
  `summarized` ENUM('true','false') DEFAULT 'false',
  `image_type` ENUM('picture','high','other') DEFAULT NULL,
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
  `id` MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,
  `file` VARCHAR(255) BINARY NOT NULL DEFAULT '',
  `date_available` DATETIME DEFAULT NULL,
  `date_creation` DATETIME DEFAULT NULL,
  `name` VARCHAR(255) DEFAULT NULL,
  `comment` text,
  `author` VARCHAR(255) DEFAULT NULL,
  `hit` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `filesize` MEDIUMINT(9) UNSIGNED DEFAULT NULL,
  `width` SMALLINT(9) UNSIGNED DEFAULT NULL,
  `height` SMALLINT(9) UNSIGNED DEFAULT NULL,
  `coi` VARCHAR(4) DEFAULT NULL COMMENT 'center of interest',
  `representative_ext` VARCHAR(4) DEFAULT NULL,
  `date_metadata_update` DATE DEFAULT NULL,
  `rating_score` FLOAT(5,2) UNSIGNED DEFAULT NULL,
  `path` VARCHAR(255) NOT NULL DEFAULT '',
  `storage_category_id` SMALLINT(5) UNSIGNED DEFAULT NULL,
  `level` TINYINT UNSIGNED NOT NULL DEFAULT '0',
  `md5sum` VARCHAR(32) DEFAULT NULL,
  `added_by` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `rotation` TINYINT UNSIGNED DEFAULT NULL,
  `latitude` DOUBLE(8, 6) DEFAULT NULL,
  `longitude` DOUBLE(9, 6) DEFAULT NULL,
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
  `id` VARCHAR(40) NOT NULL,
  `version` VARCHAR(64) DEFAULT NULL,
  `name` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;


--
-- Table structure for table `phyxo_plugins`
--

DROP TABLE IF EXISTS `phyxo_plugins`;
CREATE TABLE `phyxo_plugins` (
  `id` VARCHAR(40) NOT NULL,
  `state` VARCHAR(25) NOT NULL,
  `version` VARCHAR(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
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
  `id` INT AUTO_INCREMENT NOT NULL,
  `last_seen` DATE DEFAULT NULL,
  `rules` LONGTEXT DEFAULT NULL,
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
  `id` INT AUTO_INCREMENT NOT NULL,
  `galleries_url` VARCHAR(255) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_tags`
--

DROP TABLE IF EXISTS `phyxo_tags`;
CREATE TABLE `phyxo_tags` (
  `id` SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
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
  `id` VARCHAR(40) NOT NULL,
  `version` VARCHAR(64) DEFAULT NULL,
  `name` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_upgrade`
--

DROP TABLE IF EXISTS `phyxo_upgrade`;
CREATE TABLE `phyxo_upgrade` (
  `id` VARCHAR(40) NOT NULL,
  `applied` DATETIME NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
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
  `need_update` TINYINT(1) NOT NULL DEFAULT 1,
  `cache_update_time` INTEGER UNSIGNED NOT NULL DEFAULT 0,
  `forbidden_categories` MEDIUMTEXT,
  `nb_total_images` MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  `last_photo_date` DATETIME DEFAULT NULL,
  `nb_available_tags` INT(5) DEFAULT NULL,
  `nb_available_comments` INT(5) DEFAULT NULL,
  `image_access_type` VARCHAR(255) NOT NULL DEFAULT 'NOT IN',
  `image_access_list` MEDIUMTEXT DEFAULT NULL,
  PRIMARY KEY  (`user_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_user_cache_categories`
--

DROP TABLE IF EXISTS `phyxo_user_cache_categories`;
CREATE TABLE `phyxo_user_cache_categories` (
  `user_id` INT NOT NULL,
  `cat_id` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
  `date_last` DATETIME DEFAULT NULL,
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
  `id` INT AUTO_INCREMENT NOT NULL,
  `user_id` INT NOT NULL,
  `last_check` DATETIME DEFAULT NULL,
  `uuid` CHAR(36) NOT NULL,
  UNIQUE KEY `feed_uuid` (`uuid`),
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_user_group`
--

DROP TABLE IF EXISTS `phyxo_user_group`;
CREATE TABLE `phyxo_user_group` (
  `user_id` INT NOT NULL,
  `group_id` INT NOT NULL,
  PRIMARY KEY (`group_id`, `user_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_user_infos`
--

DROP TABLE IF EXISTS `phyxo_user_infos`;
CREATE TABLE `phyxo_user_infos` (
  `user_id` INT NOT NULL,
  `nb_image_page` INT NOT NULL DEFAULT 15,
  `status` VARCHAR(50) NOT NULL,
  `language` VARCHAR(50) NOT NULL DEFAULT 'en_GB',
  `expand` TINYINT(1) NOT NULL DEFAULT 0,
  `show_nb_comments` TINYINT(1) NOT NULL DEFAULT 0,
  `show_nb_hits` TINYINT(1) NOT NULL DEFAULT 0,
  `recent_period` INT NOT NULL DEFAULT 7,
  `theme` VARCHAR(255) NOT NULL DEFAULT 'treflez',
  `registration_date` DATETIME DEFAULT NULL,
  `enabled_high` TINYINT(1) NOT NULL,
  `level` INT DEFAULT 0,
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
  `user_id` INT NOT NULL,
  `check_key` VARCHAR(16) DEFAULT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  last_send DATETIME DEFAULT NULL,
  UNIQUE KEY `user_mail_notification_ui1` (`check_key`),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_users`
--

DROP TABLE IF EXISTS `phyxo_users`;
CREATE TABLE `phyxo_users` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `username` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) DEFAULT NULL,
  `mail_address` VARCHAR(255) DEFAULT NULL,
  UNIQUE KEY `users_ui1` (`username`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
