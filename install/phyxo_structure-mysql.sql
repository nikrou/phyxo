--
-- Table structure for table `phyxo_caddie`
--

DROP TABLE IF EXISTS `phyxo_caddie`;
CREATE TABLE `phyxo_caddie` (
  `user_id` mediumint(8) unsigned NOT NULL default '0',
  `element_id` mediumint(8) NOT NULL default '0',
  PRIMARY KEY  (`user_id`,`element_id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_categories`
--

DROP TABLE IF EXISTS `phyxo_categories`;
CREATE TABLE `phyxo_categories` (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `id_uppercat` smallint(5) unsigned default NULL,
  `comment` text,
  `dir` varchar(255) default NULL,
  `rank` smallint(5) unsigned default NULL,
  `status` enum('public','private') NOT NULL default 'public',
  `site_id` tinyint(4) unsigned default NULL,
  `visible` enum('true','false') NOT NULL default 'true',
  `representative_picture_id` mediumint(8) unsigned default NULL,
  `uppercats` varchar(255) NOT NULL default '',
  `commentable` enum('true','false') NOT NULL default 'true',
  `global_rank` varchar(255) default NULL,
  `image_order` varchar(128) default NULL,
  `permalink` varchar(64) binary default NULL,
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `categories_i3` (`permalink`),
  KEY `categories_i2` (`id_uppercat`),
  KEY `lastmodified` (`lastmodified`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_comments`
--

DROP TABLE IF EXISTS `phyxo_comments`;
CREATE TABLE `phyxo_comments` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `image_id` mediumint(8) unsigned NOT NULL default '0',
  `date` datetime NOT NULL default '0000-00-00 00:00:00',
  `author` varchar(255) default NULL,
  `email` varchar(255) default NULL,
  `author_id` mediumint(8) unsigned DEFAULT NULL,
  `anonymous_id` varchar(45) NOT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `content` longtext,
  `validated` enum('true','false') NOT NULL default 'false',
  `validation_date` datetime default NULL,
  PRIMARY KEY  (`id`),
  KEY `comments_i2` (`validation_date`),
  KEY `comments_i1` (`image_id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_config`
--

DROP TABLE IF EXISTS `phyxo_config`;
CREATE TABLE `phyxo_config` (
  `param` varchar(40) NOT NULL default '',
  `value` text,
  `comment` varchar(255) default NULL,
  PRIMARY KEY  (`param`)
) ENGINE=MyISAM COMMENT='configuration table';

--
-- Table structure for table `phyxo_favorites`
--

DROP TABLE IF EXISTS `phyxo_favorites`;
CREATE TABLE `phyxo_favorites` (
  `user_id` mediumint(8) unsigned NOT NULL default '0',
  `image_id` mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (`user_id`,`image_id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_group_access`
--

DROP TABLE IF EXISTS `phyxo_group_access`;
CREATE TABLE `phyxo_group_access` (
  `group_id` smallint(5) unsigned NOT NULL default '0',
  `cat_id` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`group_id`,`cat_id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_groups`
--

DROP TABLE IF EXISTS `phyxo_groups`;
CREATE TABLE `phyxo_groups` (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `is_default` enum('true','false') NOT NULL default 'false',
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `groups_ui1` (`name`),
  KEY `lastmodified` (`lastmodified`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_history`
--

DROP TABLE IF EXISTS `phyxo_history`;
CREATE TABLE `phyxo_history` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `date` date NOT NULL default '0000-00-00',
  `time` time NOT NULL default '00:00:00',
  `user_id` mediumint(8) unsigned NOT NULL default '0',
  `ip` varchar(15) NOT NULL default '',
  `section` enum('categories','tags','search','list','favorites','most_visited','best_rated','recent_pics','recent_cats') default NULL,
  `category_id` smallint(5) default NULL,
  `tag_ids` varchar(50) default NULL,
  `image_id` mediumint(8) default NULL,
  `summarized` enum('true','false') default 'false',
  `image_type` enum('picture','high','other') default NULL,
  PRIMARY KEY  (`id`),
  KEY `history_i1` (`summarized`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_history_summary`
--

DROP TABLE IF EXISTS `phyxo_history_summary`;
CREATE TABLE `phyxo_history_summary` (
  `year` smallint(4) NOT NULL default '0',
  `month` tinyint(2) default NULL,
  `day` tinyint(2) default NULL,
  `hour` tinyint(2) default NULL,
  `nb_pages` int(11) default NULL,
  UNIQUE KEY history_summary_ymdh (`year`,`month`,`day`,`hour`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_image_category`
--

DROP TABLE IF EXISTS `phyxo_image_category`;
CREATE TABLE `phyxo_image_category` (
  `image_id` mediumint(8) unsigned NOT NULL default '0',
  `category_id` smallint(5) unsigned NOT NULL default '0',
  `rank` mediumint(8) unsigned default NULL,
  PRIMARY KEY  (`image_id`,`category_id`),
  KEY `image_category_i1` (`category_id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_image_tag`
--

DROP TABLE IF EXISTS `phyxo_image_tag`;
CREATE TABLE `phyxo_image_tag` (
  `image_id` mediumint(8) unsigned NOT NULL default '0',
  `tag_id` smallint(5) unsigned NOT NULL default '0',
  `validated` enum('true','false') NOT NULL default 'true',
  `created_by` mediumint(8) unsigned DEFAULT NULL,
  `status` smallint(3) DEFAULT 1,
  PRIMARY KEY  (`image_id`,`tag_id`),
  KEY `image_tag_i1` (`tag_id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_images`
--

DROP TABLE IF EXISTS `phyxo_images`;
CREATE TABLE `phyxo_images` (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `file` varchar(255) binary NOT NULL default '',
  `date_available` datetime NOT NULL default '0000-00-00 00:00:00',
  `date_creation` datetime default NULL,
  `name` varchar(255) default NULL,
  `comment` text,
  `author` varchar(255) default NULL,
  `hit` int(10) unsigned NOT NULL default '0',
  `filesize` mediumint(9) unsigned default NULL,
  `width` smallint(9) unsigned default NULL,
  `height` smallint(9) unsigned default NULL,
  `coi` char(4) default NULL COMMENT 'center of interest',
  `representative_ext` varchar(4) default NULL,
  `date_metadata_update` date default NULL,
  `rating_score` float(5,2) unsigned default NULL,
  `path` varchar(255) NOT NULL default '',
  `storage_category_id` smallint(5) unsigned default NULL,
  `level` tinyint unsigned NOT NULL default '0',
  `md5sum` char(32) default NULL,
  `added_by` mediumint(8) unsigned NOT NULL default '0',
  `rotation` tinyint unsigned default NULL,
  `latitude` double(8, 6) default NULL,
  `longitude` double(9, 6) default NULL,
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `images_i2` (`date_available`),
  KEY `images_i3` (`rating_score`),
  KEY `images_i4` (`hit`),
  KEY `images_i5` (`date_creation`),
  KEY `images_i1` (`storage_category_id`),
  KEY `images_i6` (`latitude`),
  KEY `lastmodified` (`lastmodified`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_languages`
--

DROP TABLE IF EXISTS `phyxo_languages`;
CREATE TABLE `phyxo_languages` (
  `id` varchar(64) NOT NULL default '',
  `version` varchar(64) NOT NULL default '0',
  `name` varchar(64) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_old_permalinks`
--

DROP TABLE IF EXISTS `phyxo_old_permalinks`;
CREATE TABLE `phyxo_old_permalinks` (
  `cat_id` smallint(5) unsigned NOT NULL default '0',
  `permalink` varchar(64) binary NOT NULL default '',
  `date_deleted` datetime NOT NULL default '0000-00-00 00:00:00',
  `last_hit` datetime default NULL,
  `hit` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`permalink`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_plugins`
--

DROP TABLE IF EXISTS `phyxo_plugins`;
CREATE TABLE `phyxo_plugins` (
  `id` varchar(64) binary NOT NULL default '',
  `state` enum('inactive','active') NOT NULL default 'inactive',
  `version` varchar(64) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_rate`
--

DROP TABLE IF EXISTS `phyxo_rate`;
CREATE TABLE `phyxo_rate` (
  `user_id` mediumint(8) unsigned NOT NULL default '0',
  `element_id` mediumint(8) unsigned NOT NULL default '0',
  `anonymous_id` varchar(45) NOT NULL default '',
  `rate` tinyint(2) unsigned NOT NULL default '0',
  `date` date NOT NULL default '0000-00-00',
  PRIMARY KEY  (`element_id`,`user_id`,`anonymous_id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_search`
--

DROP TABLE IF EXISTS `phyxo_search`;
CREATE TABLE `phyxo_search` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `last_seen` date default NULL,
  `rules` text,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_sessions`
--

DROP TABLE IF EXISTS `phyxo_sessions`;
CREATE TABLE `phyxo_sessions` (
  `sess_id` VARCHAR(128) NOT NULL PRIMARY KEY,
  `sess_data` BLOB NOT NULL,
  `sess_time` INTEGER UNSIGNED NOT NULL,
  `sess_lifetime` INTEGER NOT NULL
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_sites`
--

DROP TABLE IF EXISTS `phyxo_sites`;
CREATE TABLE `phyxo_sites` (
  `id` tinyint(4) NOT NULL auto_increment,
  `galleries_url` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `sites_ui1` (`galleries_url`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_tags`
--

DROP TABLE IF EXISTS `phyxo_tags`;
CREATE TABLE `phyxo_tags` (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `url_name` varchar(255) binary NOT NULL default '',
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY  (`id`),
  KEY `tags_i1` (`url_name`),
  KEY `lastmodified` (`lastmodified`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_themes`
--

DROP TABLE IF EXISTS `phyxo_themes`;
CREATE TABLE `phyxo_themes` (
  `id` varchar(64) NOT NULL default '',
  `version` varchar(64) NOT NULL default '0',
  `name` varchar(64) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_upgrade`
--

DROP TABLE IF EXISTS `phyxo_upgrade`;
CREATE TABLE `phyxo_upgrade` (
  `id` varchar(20) NOT NULL default '',
  `applied` datetime NOT NULL default '0000-00-00 00:00:00',
  `description` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_user_access`
--

DROP TABLE IF EXISTS `phyxo_user_access`;
CREATE TABLE `phyxo_user_access` (
  `user_id` mediumint(8) unsigned NOT NULL default '0',
  `cat_id` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`user_id`,`cat_id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_user_cache`
--

DROP TABLE IF EXISTS `phyxo_user_cache`;
CREATE TABLE `phyxo_user_cache` (
  `user_id` mediumint(8) unsigned NOT NULL default '0',
  `need_update` enum('true','false') NOT NULL default 'true',
  `cache_update_time` integer unsigned NOT NULL default 0,
  `forbidden_categories` mediumtext,
  `nb_total_images` mediumint(8) unsigned default NULL,
  `last_photo_date` datetime DEFAULT NULL,
  `nb_available_tags` INT(5) DEFAULT NULL,
  `nb_available_comments` INT(5) DEFAULT NULL,
  `image_access_type` enum('NOT IN','IN') NOT NULL default 'NOT IN',
  `image_access_list` mediumtext default NULL,
  PRIMARY KEY  (`user_id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_user_cache_categories`
--

DROP TABLE IF EXISTS `phyxo_user_cache_categories`;
CREATE TABLE `phyxo_user_cache_categories` (
  `user_id` mediumint(8) unsigned NOT NULL default '0',
  `cat_id` smallint(5) unsigned NOT NULL default '0',
  `date_last` datetime default NULL,
  `max_date_last` datetime default NULL,
  `nb_images` mediumint(8) unsigned NOT NULL default '0',
  `count_images` mediumint(8) unsigned default '0',
  `nb_categories` mediumint(8) unsigned default '0',
  `count_categories` mediumint(8) unsigned default '0',
  `user_representative_picture_id` mediumint(8) unsigned default NULL,
  PRIMARY KEY  (`user_id`,`cat_id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_user_feed`
--

DROP TABLE IF EXISTS `phyxo_user_feed`;
CREATE TABLE `phyxo_user_feed` (
  `id` varchar(50) binary NOT NULL default '',
  `user_id` mediumint(8) unsigned NOT NULL default '0',
  `last_check` datetime default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_user_group`
--

DROP TABLE IF EXISTS `phyxo_user_group`;
CREATE TABLE `phyxo_user_group` (
  `user_id` mediumint(8) unsigned NOT NULL default '0',
  `group_id` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`group_id`,`user_id`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_user_infos`
--

DROP TABLE IF EXISTS `phyxo_user_infos`;
CREATE TABLE `phyxo_user_infos` (
  `user_id` mediumint(8) unsigned NOT NULL default '0',
  `nb_image_page` smallint(3) unsigned NOT NULL default '15',
  `status` enum('webmaster','admin','normal','guest') NOT NULL default 'guest',
  `language` varchar(50) NOT NULL default 'en_GB',
  `expand` enum('true','false') NOT NULL default 'false',
  `show_nb_comments` enum('true','false') NOT NULL default 'false',
  `show_nb_hits` enum('true','false') NOT NULL default 'false',
  `recent_period` tinyint(3) unsigned NOT NULL default '7',
  `theme` varchar(255) NOT NULL default 'treflez',
  `registration_date` datetime NOT NULL default '0000-00-00 00:00:00',
  `enabled_high` enum('true','false') NOT NULL default 'true',
  `level` tinyint unsigned NOT NULL default '0',
  `activation_key` varchar(255) default NULL,
  `activation_key_expire` datetime default NULL,
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `lastmodified` (`lastmodified`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_user_mail_notification`
--

DROP TABLE IF EXISTS `phyxo_user_mail_notification`;
CREATE TABLE `phyxo_user_mail_notification` (
  `user_id` mediumint(8) unsigned NOT NULL default '0',
  `check_key` varchar(16) binary NOT NULL default '',
  `enabled` enum('true','false') NOT NULL default 'false',
  `last_send` datetime default NULL,
  PRIMARY KEY  (`user_id`),
  UNIQUE KEY `user_mail_notification_ui1` (`check_key`)
) ENGINE=MyISAM;

--
-- Table structure for table `phyxo_users`
--

DROP TABLE IF EXISTS `phyxo_users`;
CREATE TABLE `phyxo_users` (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `username` varchar(100) binary NOT NULL default '',
  `password` varchar(255) default NULL,
  `mail_address` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `users_ui1` (`username`)
) ENGINE=MyISAM;
