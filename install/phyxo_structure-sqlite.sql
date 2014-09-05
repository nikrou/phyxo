-----------------------------------------------------------------------------
-- phyxo_caddie
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_caddie";
CREATE TABLE "phyxo_caddie"
(
  "user_id" INTEGER default 0 NOT NULL,
  "element_id" INTEGER default 0 NOT NULL,
  PRIMARY KEY ("user_id","element_id")
);


-----------------------------------------------------------------------------
-- phyxo_categories
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_categories";
CREATE TABLE "phyxo_categories"
(
  "id" INTEGER  NOT NULL,
  "name" VARCHAR(255) default '' NOT NULL,
  "id_uppercat" INTEGER,
  "comment" TEXT,
  "dir" VARCHAR(255),
  "rank" INTEGER,
  "status" VARCHAR(50) default 'public',
  "site_id" INTEGER default 1,
  "visible" BOOLEAN default true,
  "representative_picture_id" INTEGER,
  "uppercats" VARCHAR(255) default '' NOT NULL,
  "commentable" BOOLEAN default true,
  "global_rank" VARCHAR(255),
  "image_order" VARCHAR(128),
  "permalink" VARCHAR(64),
  "lastmodified" TIMESTAMP NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY ("id"),
  CONSTRAINT "categories_i3" UNIQUE ("permalink")
);

-----------------------------------------------------------------------------
-- phyxo_config
-----------------------------------------------------------------------------
DROP TABLE IF EXISTS "phyxo_config";
CREATE TABLE "phyxo_config"
(
  "param" VARCHAR(40) default '' NOT NULL,
  "value" TEXT,
  "comment" VARCHAR(255),
  PRIMARY KEY ("param")
);


-----------------------------------------------------------------------------
-- phyxo_favorites
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_favorites";
CREATE TABLE "phyxo_favorites"
(
  "user_id" INTEGER default 0 NOT NULL,
  "image_id" INTEGER default 0 NOT NULL,
  PRIMARY KEY ("user_id","image_id")
);

-----------------------------------------------------------------------------
-- phyxo_group_access
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_group_access";
CREATE TABLE "phyxo_group_access"
(
  "group_id" INTEGER default 0 NOT NULL,
  "cat_id" INTEGER default 0 NOT NULL,
  PRIMARY KEY ("group_id","cat_id")
);


-----------------------------------------------------------------------------
-- phyxo_groups
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_groups";
CREATE TABLE "phyxo_groups"
(
  "id" INTEGER  NOT NULL,
  "name" VARCHAR(255) default '' NOT NULL,
  "is_default" BOOLEAN default false,
  "lastmodified" TIMESTAMP NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY ("id"),
  CONSTRAINT "groups_ui1" UNIQUE ("name")
);


-----------------------------------------------------------------------------
-- phyxo_history
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_history";
CREATE TABLE "phyxo_history"
(
  "id" INTEGER  NOT NULL,
  "date" DATE NOT NULL,
  "time" TIME NOT NULL,
  "user_id" INTEGER default 0 NOT NULL,
  "ip" VARCHAR(15) default '' NOT NULL,
  "section" HISTORY_SECTION default NULL,
  "category_id" INTEGER,
  "tag_ids" VARCHAR(50),
  "image_id" INTEGER,
  "summarized" BOOLEAN default false,
  "image_type" VARCHAR(50) default NULL,
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- phyxo_history_summary
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_history_summary";
CREATE TABLE "phyxo_history_summary"
(
  "year" INTEGER default 0 NOT NULL,
  "month" INTEGER,
  "day" INTEGER,
  "hour" INTEGER,
  "nb_pages" INTEGER,
  "id" INTEGER  NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "history_summary_ymdh" UNIQUE ("year","month","day","hour")
);



-----------------------------------------------------------------------------
-- phyxo_image_category
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_image_category";
CREATE TABLE "phyxo_image_category"
(
  "image_id" INTEGER default 0 NOT NULL,
  "category_id" INTEGER default 0 NOT NULL,
  "rank" INTEGER,
  PRIMARY KEY ("image_id","category_id")
);


-----------------------------------------------------------------------------
-- phyxo_image_tag
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_image_tag";
CREATE TABLE "phyxo_image_tag"
(
  "image_id" INTEGER default 0 NOT NULL,
  "tag_id" INTEGER default 0 NOT NULL,
  PRIMARY KEY ("image_id","tag_id")
);


-----------------------------------------------------------------------------
-- phyxo_images
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_images";
CREATE TABLE "phyxo_images"
(
  "id" INTEGER  NOT NULL,
  "file" VARCHAR(255) default '' NOT NULL,
  "date_available" TIMESTAMP NOT NULL,
  "date_creation" TIMESTAMP,
  "name" VARCHAR(255),
  "comment" TEXT,
  "author" VARCHAR(255),
  "hit" INTEGER default 0 NOT NULL,
  "filesize" INTEGER,
  "width" INTEGER,
  "height" INTEGER,
  "coi" VARCHAR(4) default NULL,
  "representative_ext" VARCHAR(4),
  "date_metadata_update" DATE,
  "rating_score" FLOAT,
  "has_high" BOOLEAN default false,
  "path" VARCHAR(255) default '' NOT NULL,
  "storage_category_id" INTEGER,
  "level" INTEGER default 0 NOT NULL,
  "md5sum" VARCHAR(32),
  "added_by" INTEGER NOT NULL default 0,
  "rotation" INTEGER default NULL,
  "latitude" FLOAT default NULL,
  "longitude" FLOAT default NULL,
  "lastmodified" TIMESTAMP NULL DEFAULT '1970-01-01 00:00:00',

  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- phyxo_languages
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_languages";
CREATE TABLE "phyxo_languages"
(
  "id" VARCHAR(64) NOT NULL default '',
  "version" VARCHAR(64) NOT NULL default '0',
  "name" VARCHAR(64) default NULL,
  PRIMARY KEY  ("id")
);


-----------------------------------------------------------------------------
-- phyxo_old_permalinks
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_old_permalinks";
CREATE TABLE "phyxo_old_permalinks"
(
  "cat_id" INTEGER default 0 NOT NULL,
  "permalink" VARCHAR(64) default '' NOT NULL,
  "date_deleted" TIMESTAMP NOT NULL,
  "last_hit" TIMESTAMP,
  "hit" INTEGER default 0 NOT NULL,
  PRIMARY KEY ("permalink")
);


-----------------------------------------------------------------------------
-- phyxo_plugins
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_plugins";
CREATE TABLE "phyxo_plugins"
(
  "id" VARCHAR(64) default '' NOT NULL,
  "state" VARCHAR(50) default 'inactive',
  "version" VARCHAR(64) default '0' NOT NULL,
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- phyxo_rate
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_rate";
CREATE TABLE "phyxo_rate"
(
  "user_id" INTEGER default 0 NOT NULL,
  "element_id" INTEGER default 0 NOT NULL,
  "anonymous_id" VARCHAR(45) default '' NOT NULL,
  "rate" INTEGER default 0 NOT NULL,
  "date" DATE  NOT NULL,
  PRIMARY KEY ("user_id","element_id","anonymous_id")
);


-----------------------------------------------------------------------------
-- phyxo_search
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_search";
CREATE TABLE "phyxo_search"
(
  "id" INTEGER  NOT NULL,
  "last_seen" DATE,
  "rules" TEXT,
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- phyxo_sessions
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_sessions";
CREATE TABLE "phyxo_sessions"
(
  "id" VARCHAR(255) default '' NOT NULL,
  "data" TEXT  NOT NULL,
  "expiration" TIMESTAMP NOT NULL,
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- phyxo_sites
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_sites";
CREATE TABLE "phyxo_sites"
(
  "id" INTEGER  NOT NULL,
  "galleries_url" VARCHAR(255) default '' NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "sites_ui1" UNIQUE ("galleries_url")
);


-----------------------------------------------------------------------------
-- phyxo_tags
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_tags";
CREATE TABLE "phyxo_tags"
(
  "id" INTEGER  NOT NULL,
  "name" VARCHAR(255) default '' NOT NULL,
  "url_name" VARCHAR(255) default '' NOT NULL,
  "lastmodified" TIMESTAMP NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- phyxo_themes
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_themes";
CREATE TABLE "phyxo_themes"
(
  "id" varchar(64) NOT NULL default '',
  "version" varchar(64) NOT NULL default '0',
  "name" varchar(64) default NULL,
  PRIMARY KEY  ("id")
);


-----------------------------------------------------------------------------
-- phyxo_upgrade
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_upgrade";
CREATE TABLE "phyxo_upgrade"
(
  "id" VARCHAR(20) default '' NOT NULL,
  "applied" TIMESTAMP NOT NULL,
  "description" VARCHAR(255),
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- phyxo_user_access
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_user_access";
CREATE TABLE "phyxo_user_access"
(
  "user_id" INTEGER default 0 NOT NULL,
  "cat_id" INTEGER default 0 NOT NULL,
  PRIMARY KEY ("user_id","cat_id")
);


-----------------------------------------------------------------------------
-- phyxo_user_cache
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_user_cache";
CREATE TABLE "phyxo_user_cache"
(
  "user_id" INTEGER default 0 NOT NULL,
  "need_update" BOOLEAN default true,
  "cache_update_time" INTEGER default 0 NOT NULL,
  "forbidden_categories" TEXT,
  "nb_total_images" INTEGER,
  "last_photo_date" date DEFAULT NULL,
  "nb_available_tags" INTEGER DEFAULT NULL,
  "nb_available_comments" INTEGER DEFAULT NULL,
  "image_access_type" VARCHAR(50) default 'NOT IN',
  "image_access_list" TEXT,
  PRIMARY KEY ("user_id")
);


-----------------------------------------------------------------------------
-- phyxo_user_cache_categories
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_user_cache_categories";
CREATE TABLE "phyxo_user_cache_categories"
(
  "user_id" INTEGER default 0 NOT NULL,
  "cat_id" INTEGER default 0 NOT NULL,
  "date_last" TIMESTAMP,
  "max_date_last" TIMESTAMP,
  "nb_images" INTEGER default 0 NOT NULL,
  "count_images" INTEGER default 0,
  "nb_categories" INTEGER default 0,
  "count_categories" INTEGER default 0,
  "user_representative_picture_id" INTEGER default NULL,
  PRIMARY KEY ("user_id","cat_id")
);


-----------------------------------------------------------------------------
-- phyxo_user_feed
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_user_feed";
CREATE TABLE "phyxo_user_feed"
(
  "id" VARCHAR(50) default '' NOT NULL,
  "user_id" INTEGER default 0 NOT NULL,
  "last_check" TIMESTAMP,
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- phyxo_user_group
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_user_group";
CREATE TABLE "phyxo_user_group"
(
  "user_id" INTEGER default 0 NOT NULL,
  "group_id" INTEGER default 0 NOT NULL,
  PRIMARY KEY ("user_id","group_id")
);


-----------------------------------------------------------------------------
-- phyxo_user_infos
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_user_infos";
CREATE TABLE "phyxo_user_infos"
(
  "user_id" INTEGER default 0 NOT NULL,
  "nb_image_page" INTEGER default 15 NOT NULL,
  "status" VARCHAR(50) default 'guest',
  "language" VARCHAR(50) default 'en_UK' NOT NULL,
  "expand" BOOLEAN default false,
  "show_nb_comments" BOOLEAN default false,
  "show_nb_hits" BOOLEAN default false,
  "recent_period" INTEGER default 7 NOT NULL,
  "theme" VARCHAR(255) default 'elegant' NOT NULL,
  "registration_date" TIMESTAMP NOT NULL,
  "enabled_high" BOOLEAN default true,
  "level" INTEGER default 0 NOT NULL,
  "activation_key" VARCHAR(255) default NULL,
  "activation_key_expire" TIMESTAMP default NULL,
  "lastmodified" TIMESTAMP NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY ("user_id"),
  CONSTRAINT "user_infos_ui1" UNIQUE ("user_id")
);


-----------------------------------------------------------------------------
-- phyxo_user_mail_notification
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_user_mail_notification";
CREATE TABLE "phyxo_user_mail_notification"
(
  "user_id" INTEGER default 0 NOT NULL,
  "check_key" VARCHAR(16) default '' NOT NULL,
  "enabled" BOOLEAN default false,
  "last_send" TIMESTAMP,
  PRIMARY KEY ("user_id"),
  CONSTRAINT "user_mail_notification_ui1" UNIQUE ("check_key")
);



-----------------------------------------------------------------------------
-- phyxo_users
-----------------------------------------------------------------------------
DROP TABLE IF EXISTS "phyxo_comments";
DROP TABLE IF EXISTS "phyxo_users";

CREATE TABLE "phyxo_users"
(
  "id" INTEGER  NOT NULL,
  "username" VARCHAR(100) default '' NOT NULL,
  "password" VARCHAR(255),
  "mail_address" VARCHAR(255),
  PRIMARY KEY ("id"),
  CONSTRAINT "users_ui1" UNIQUE ("username")
);


-----------------------------------------------------------------------------
-- phyxo_comments
-----------------------------------------------------------------------------

CREATE TABLE "phyxo_comments"
(
  "id" INTEGER  NOT NULL,
  "image_id" INTEGER default 0 NOT NULL,
  "date" TIMESTAMP  NOT NULL,
  "author" VARCHAR(255),
  "email" VARCHAR(255),
  "anonymous_id" VARCHAR(45),
  "website_url" VARCHAR(255),
  "content" TEXT,
  "validated" BOOLEAN default false,
  "validation_date" TIMESTAMP,
  "author_id" INTEGER REFERENCES "phyxo_users" (id),
  PRIMARY KEY ("id")
);
