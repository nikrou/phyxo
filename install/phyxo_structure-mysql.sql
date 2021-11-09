--
-- Table structure for table `phyxo_caddie`
--

DROP TABLE IF EXISTS `phyxo_caddie`;
CREATE TABLE `phyxo_caddie` (
  `user_id` INT NOT NULL,
  `element_id` INT NOT NULL,
  PRIMARY KEY  (`user_id`,`element_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_categories`
--

DROP TABLE IF EXISTS `phyxo_categories`;
CREATE TABLE `phyxo_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `id_uppercat` INT DEFAULT NULL,
  `comment` TEXT,
  `dir` VARCHAR(255) DEFAULT NULL,
  `rank` INT DEFAULT NULL,
  `status` VARCHAR(25) NOT NULL DEFAULT 'public',
  `visible` TINYINT(1) NOT NULL DEFAULT 1,
  `representative_picture_id` INT DEFAULT NULL,
  `uppercats` VARCHAR(255) NOT NULL DEFAULT '',
  `commentable` TINYINT(1) NOT NULL DEFAULT 1,
  `global_rank` VARCHAR(255) DEFAULT NULL,
  `image_order` VARCHAR(255) DEFAULT NULL,
  `permalink` VARCHAR(255) DEFAULT NULL,
  `lastmodified` DATETIME DEFAULT NULL,
  INDEX `IDX_725D6641C7F87B72` (`id_uppercat`),
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_comments`
--

DROP TABLE IF EXISTS `phyxo_comments`;
CREATE TABLE `phyxo_comments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `image_id` INT NOT NULL,
  `date` datetime DEFAULT NULL,
  `author` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `author_id` INT DEFAULT NULL,
  `anonymous_id` VARCHAR(45) NOT NULL,
  `website_url` VARCHAR(255) DEFAULT NULL,
  `content` LONGTEXT,
  `validated` TINYINT(1) NOT NULL DEFAULT 0,
  `validation_date` DATETIME DEFAULT NULL,
  INDEX `IDX_259D537BF675F31B` (`author_id`),
  INDEX `IDX_259D537B3DA5256D` (`image_id`),
  PRIMARY KEY  (`id`)
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
  `user_id` INT NOT NULL,
  `image_id` INT NOT NULL,
  INDEX `IDX_F87F0252A76ED395` (`user_id`),
  INDEX `IDX_F87F02523DA5256D` (`image_id`),
  PRIMARY KEY  (`user_id`,`image_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_group_access`
--

DROP TABLE IF EXISTS `phyxo_group_access`;
CREATE TABLE `phyxo_group_access` (
  `group_id` INT NOT NULL,
  `cat_id` INT NOT NULL,
  INDEX `IDX_AAC70409FE54D947` (`group_id`),
  INDEX `IDX_AAC70409E6ADA943` (`cat_id`),
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
  UNIQUE INDEX `UNIQ_69564CE35E237E06` (`name`),
  PRIMARY KEY(`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_history`
--

DROP TABLE IF EXISTS `phyxo_history`;
CREATE TABLE `phyxo_history` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `user_id` INT NOT NULL,
  `ip` VARCHAR(255) NOT NULL,
  `section` VARCHAR(255) NOT NULL,
  `category_id` INT DEFAULT NULL,
  `tag_ids` VARCHAR(50) DEFAULT NULL,
  `image_id` INT DEFAULT NULL,
  `summarized` TINYINT(1) DEFAULT 0,
  `image_type` VARCHAR(255) DEFAULT NULL,
  INDEX `IDX_4E2589C0A76ED395` (`user_id`),
  INDEX `IDX_4E2589C012469DE2` (`category_id`),
  INDEX `IDX_4E2589C03DA5256D` (`image_id`),
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_history_summary`
--

DROP TABLE IF EXISTS `phyxo_history_summary`;
CREATE TABLE `phyxo_history_summary` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `year` SMALLINT(4) NOT NULL,
  `month` TINYINT(2) DEFAULT NULL,
  `day` TINYINT(2) DEFAULT NULL,
  `hour` TINYINT(2) DEFAULT NULL,
  `nb_pages` int(11) DEFAULT NULL,
  UNIQUE KEY history_summary_ymdh (`year`,`month`,`day`,`hour`),
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_image_category`
--

DROP TABLE IF EXISTS `phyxo_image_category`;
CREATE TABLE `phyxo_image_category` (
  `image_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `rank` INT DEFAULT NULL,
  INDEX `IDX_244869F83DA5256D` (`image_id`),
  INDEX `IDX_244869F812469DE2` (`category_id`),
  PRIMARY KEY  (`image_id`,`category_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_image_tag`
--

DROP TABLE IF EXISTS `phyxo_image_tag`;
CREATE TABLE `phyxo_image_tag` (
  `image_id` INT NOT NULL,
  `tag_id` INT NOT NULL,
  `validated` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT DEFAULT NULL,
  `status` SMALLINT(3) DEFAULT 1,
  INDEX `IDX_477505773DA5256D` (`image_id`),
  INDEX `IDX_47750577BAD26311` (`tag_id`),
  INDEX `IDX_47750577DE12AB56` (`created_by`),
  PRIMARY KEY  (`image_id`,`tag_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_images`
--

DROP TABLE IF EXISTS `phyxo_images`;
CREATE TABLE `phyxo_images` (
  `id` INT NOT NULL AUTO_INCREMENT,
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
  `storage_category_id` INT DEFAULT NULL,
  `level` TINYINT UNSIGNED NOT NULL DEFAULT '0',
  `md5sum` VARCHAR(32) DEFAULT NULL,
  `added_by` INT NOT NULL,
  `rotation` TINYINT UNSIGNED DEFAULT NULL,
  `latitude` DOUBLE(8, 6) DEFAULT NULL,
  `longitude` DOUBLE(9, 6) DEFAULT NULL,
  `lastmodified` DATETIME DEFAULT NULL,
  PRIMARY KEY  (`id`)
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
  `state` VARCHAR(25) NOT NULL DEFAULT 'inactive',
  `version` VARCHAR(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_rate`
--

DROP TABLE IF EXISTS `phyxo_rate`;
CREATE TABLE `phyxo_rate` (
  `user_id` INT NOT NULL,
  `element_id` INT NOT NULL,
  `anonymous_id` VARCHAR(45) NOT NULL,
  `rate` TINYINT(2) UNSIGNED NOT NULL,
  `date` DATETIME DEFAULT NULL,
  INDEX `IDX_23A9DF15A76ED395` (`user_id`),
  INDEX `IDX_23A9DF151F1F2A24` (`element_id`),
  PRIMARY KEY  (`element_id`,`user_id`,`anonymous_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_search`
--

DROP TABLE IF EXISTS `phyxo_search`;
CREATE TABLE `phyxo_search` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `last_seen` DATETIME DEFAULT NULL,
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
-- Table structure for table `phyxo_tags`
--

DROP TABLE IF EXISTS `phyxo_tags`;
CREATE TABLE `phyxo_tags` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `url_name` VARCHAR(255) NOT NULL,
  `lastmodified` DATETIME DEFAULT NULL,
  PRIMARY KEY  (`id`)
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
  `user_id` INT NOT NULL,
  `cat_id` INT NOT NULL,
  INDEX `IDX_21C10625A76ED395` (`user_id`),
  INDEX `IDX_21C10625E6ADA943` (`cat_id`),
  PRIMARY KEY  (`user_id`,`cat_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_user_cache`
--

DROP TABLE IF EXISTS `phyxo_user_cache`;
CREATE TABLE `phyxo_user_cache` (
  `user_id` INT NOT NULL,
  `need_update` TINYINT(1) NOT NULL DEFAULT 1,
  `cache_update_time` INT NOT NULL,
  `forbidden_categories` MEDIUMTEXT,
  `nb_total_images` INT DEFAULT NULL,
  `last_photo_date` DATETIME DEFAULT NULL,
  `nb_available_tags` INT DEFAULT NULL,
  `nb_available_comments` INT DEFAULT NULL,
  `image_access_type` VARCHAR(255) NOT NULL DEFAULT 'NOT IN',
  `image_access_list` LONGTEXT DEFAULT NULL,
  PRIMARY KEY  (`user_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_user_cache_categories`
--

DROP TABLE IF EXISTS `phyxo_user_cache_categories`;
CREATE TABLE `phyxo_user_cache_categories` (
  `user_id` INT NOT NULL,
  `cat_id` INT NOT NULL,
  `date_last` DATETIME DEFAULT NULL,
  `max_date_last` DATETIME DEFAULT NULL,
  `nb_images` INT NOT NULL,
  `count_images` INT,
  `nb_categories` INT,
  `count_categories` INT,
  `user_representative_picture_id` INT DEFAULT NULL,
  INDEX `IDX_38F22377A76ED395` (`user_id`),
  INDEX `IDX_38F22377E6ADA943` (`cat_id`),
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
  UNIQUE INDEX `UNIQ_45D76AC5D17F50A6` (`uuid`),
  UNIQUE INDEX `UNIQ_45D76AC5A76ED395` (`user_id`),
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_user_group`
--

DROP TABLE IF EXISTS `phyxo_user_group`;
CREATE TABLE `phyxo_user_group` (
  `user_id` INT NOT NULL,
  `group_id` INT NOT NULL,
  INDEX `IDX_C7AC9FB4FE54D947` (`group_id`),
  INDEX `IDX_C7AC9FB4A76ED395` (`user_id`),
  PRIMARY KEY (`group_id`, `user_id`)
) ENGINE=InnoDB;

--
-- Table structure for table `phyxo_user_infos`
--

DROP TABLE IF EXISTS `phyxo_user_infos`;
CREATE TABLE `phyxo_user_infos` (
  `user_id` INT NOT NULL,
  `nb_image_page` INT NOT NULL DEFAULT 15,
  `status` VARCHAR(50) NOT NULL DEFAULT 'guest',
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
  UNIQUE INDEX `UNIQ_6E424936DF5A2764` (`check_key`),
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

ALTER TABLE `phyxo_comments` ADD CONSTRAINT `FK_259D537BF675F31B` FOREIGN KEY (`author_id`) REFERENCES `phyxo_users` (`id`);
ALTER TABLE `phyxo_comments` ADD CONSTRAINT `FK_259D537B3DA5256D` FOREIGN KEY (`image_id`) REFERENCES `phyxo_images` (`id`);
ALTER TABLE `phyxo_user_mail_notification` ADD CONSTRAINT `FK_6E424936A76ED395` FOREIGN KEY (`user_id`) REFERENCES `phyxo_users` (`id`);
ALTER TABLE `phyxo_user_infos` ADD CONSTRAINT `FK_44A6591CA76ED395` FOREIGN KEY (`user_id`) REFERENCES `phyxo_users` (`id`);
ALTER TABLE `phyxo_favorites` ADD CONSTRAINT `FK_F87F0252A76ED395` FOREIGN KEY (`user_id`) REFERENCES `phyxo_users` (`id`);
ALTER TABLE `phyxo_favorites` ADD CONSTRAINT `FK_F87F02523DA5256D` FOREIGN KEY (`image_id`) REFERENCES `phyxo_images` (`id`);
ALTER TABLE `phyxo_user_cache` ADD CONSTRAINT `FK_EB2BB096A76ED395` FOREIGN KEY (`user_id`) REFERENCES `phyxo_users` (`id`);
ALTER TABLE `phyxo_caddie` ADD CONSTRAINT `FK_70B6B1A8A76ED395` FOREIGN KEY (`user_id`) REFERENCES `phyxo_users` (`id`);
ALTER TABLE `phyxo_caddie` ADD CONSTRAINT `FK_70B6B1A81F1F2A24` FOREIGN KEY (`element_id`) REFERENCES `phyxo_images` (`id`);
ALTER TABLE `phyxo_user_group` ADD CONSTRAINT `FK_C7AC9FB4FE54D947` FOREIGN KEY (`group_id`) REFERENCES `phyxo_groups` (`id`) ON DELETE CASCADE;
ALTER TABLE `phyxo_user_group` ADD CONSTRAINT `FK_C7AC9FB4A76ED395` FOREIGN KEY (`user_id`) REFERENCES `phyxo_users` (`id`) ON DELETE CASCADE;
ALTER TABLE `phyxo_group_access` ADD CONSTRAINT `FK_AAC70409FE54D947` FOREIGN KEY (`group_id`) REFERENCES `phyxo_groups` (`id`);
ALTER TABLE `phyxo_group_access` ADD CONSTRAINT `FK_AAC70409E6ADA943` FOREIGN KEY (`cat_id`) REFERENCES `phyxo_categories` (`id`);
ALTER TABLE `phyxo_image_tag` ADD CONSTRAINT `FK_477505773DA5256D` FOREIGN KEY (`image_id`) REFERENCES `phyxo_images` (`id`);
ALTER TABLE `phyxo_image_tag` ADD CONSTRAINT `FK_47750577BAD26311` FOREIGN KEY (`tag_id`) REFERENCES `phyxo_tags` (`id`);
ALTER TABLE `phyxo_image_tag` ADD CONSTRAINT `FK_47750577DE12AB56` FOREIGN KEY (`created_by`) REFERENCES `phyxo_users` (`id`);
ALTER TABLE `phyxo_image_category` ADD CONSTRAINT `FK_244869F83DA5256D` FOREIGN KEY (`image_id`) REFERENCES `phyxo_images` (`id`);
ALTER TABLE `phyxo_image_category` ADD CONSTRAINT `FK_244869F812469DE2` FOREIGN KEY (`category_id`) REFERENCES `phyxo_categories` (`id`);
ALTER TABLE `phyxo_history` ADD CONSTRAINT `FK_4E2589C0A76ED395` FOREIGN KEY (`user_id`) REFERENCES `phyxo_users` (`id`);
ALTER TABLE `phyxo_history` ADD CONSTRAINT `FK_4E2589C012469DE2` FOREIGN KEY (`category_id`) REFERENCES `phyxo_categories` (`id`);
ALTER TABLE `phyxo_history` ADD CONSTRAINT `FK_4E2589C03DA5256D` FOREIGN KEY (`image_id`) REFERENCES `phyxo_images` (`id`);
ALTER TABLE `phyxo_user_access` ADD CONSTRAINT `FK_21C10625A76ED395` FOREIGN KEY (`user_id`) REFERENCES `phyxo_users` (`id`);
ALTER TABLE `phyxo_user_access` ADD CONSTRAINT `FK_21C10625E6ADA943` FOREIGN KEY (`cat_id`) REFERENCES `phyxo_categories` (`id`);
ALTER TABLE `phyxo_categories` ADD CONSTRAINT `FK_725D6641C7F87B72` FOREIGN KEY (`id_uppercat`) REFERENCES `phyxo_categories` (`id`);
ALTER TABLE `phyxo_categories` ADD CONSTRAINT `FK_725D6641F6BD1646` FOREIGN KEY (`site_id`) REFERENCES `phyxo_sites` (`id`);
ALTER TABLE `phyxo_rate` ADD CONSTRAINT `FK_23A9DF15A76ED395` FOREIGN KEY (`user_id`) REFERENCES `phyxo_users` (`id`);
ALTER TABLE `phyxo_rate` ADD CONSTRAINT `FK_23A9DF151F1F2A24` FOREIGN KEY (`element_id`) REFERENCES `phyxo_images` (`id`);
ALTER TABLE `phyxo_user_feed` ADD CONSTRAINT `FK_45D76AC5A76ED395` FOREIGN KEY (`user_id`) REFERENCES `phyxo_users` (`id`);
ALTER TABLE `phyxo_user_cache_categories` ADD CONSTRAINT `FK_38F22377A76ED395` FOREIGN KEY (`user_id`) REFERENCES `phyxo_users` (`id`);
ALTER TABLE `phyxo_user_cache_categories` ADD CONSTRAINT `FK_38F22377E6ADA943` FOREIGN KEY (`cat_id`) REFERENCES `phyxo_categories` (`id`);
