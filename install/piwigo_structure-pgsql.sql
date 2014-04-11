-----------------------------------------------------------------------------
-- piwigo_caddie
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS piwigo_caddie;
CREATE TABLE "piwigo_caddie"
(
  "user_id" INTEGER default 0 NOT NULL,
  "element_id" INTEGER default 0 NOT NULL,
  PRIMARY KEY ("user_id","element_id")
);


-----------------------------------------------------------------------------
-- piwigo_categories
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_categories";
DROP TYPE IF EXISTS CATEGORIES_STATUS;

CREATE TYPE CATEGORIES_STATUS AS ENUM('public', 'private');
CREATE TABLE "piwigo_categories"
(
  "id" serial  NOT NULL,
  "name" VARCHAR(255) default '' NOT NULL,
  "id_uppercat" INTEGER,
  "comment" TEXT,
  "dir" VARCHAR(255),
  "rank" INTEGER,
  "status" CATEGORIES_STATUS default 'public'::CATEGORIES_STATUS,
  "site_id" INTEGER default 1,
  "visible" BOOLEAN default true,
  "representative_picture_id" INTEGER,
  "uppercats" VARCHAR(255) default '' NOT NULL,
  "commentable" BOOLEAN default true,
  "global_rank" VARCHAR(255),
  "image_order" VARCHAR(128),
  "permalink" VARCHAR(64),
  PRIMARY KEY ("id"),
  CONSTRAINT "categories_i3" UNIQUE ("permalink")
);

-----------------------------------------------------------------------------
-- piwigo_config
-----------------------------------------------------------------------------
DROP TABLE IF EXISTS "piwigo_config";
CREATE TABLE "piwigo_config"
(
  "param" VARCHAR(40) default '' NOT NULL,
  "value" TEXT,
  "comment" VARCHAR(255),
  PRIMARY KEY ("param")
);


-----------------------------------------------------------------------------
-- piwigo_favorites
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_favorites";
CREATE TABLE "piwigo_favorites"
(
  "user_id" INTEGER default 0 NOT NULL,
  "image_id" INTEGER default 0 NOT NULL,
  PRIMARY KEY ("user_id","image_id")
);

-----------------------------------------------------------------------------
-- piwigo_group_access
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_group_access";
CREATE TABLE "piwigo_group_access"
(
  "group_id" INTEGER default 0 NOT NULL,
  "cat_id" INTEGER default 0 NOT NULL,
  PRIMARY KEY ("group_id","cat_id")
);


-----------------------------------------------------------------------------
-- piwigo_groups
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_groups";
CREATE TABLE "piwigo_groups"
(
  "id" serial  NOT NULL,
  "name" VARCHAR(255) default '' NOT NULL,
  "is_default" BOOLEAN default false,
  PRIMARY KEY ("id"),
  CONSTRAINT "groups_ui1" UNIQUE ("name")
);


-----------------------------------------------------------------------------
-- piwigo_history
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_history";

DROP TYPE IF EXISTS HISTORY_SECTION;
CREATE TYPE HISTORY_SECTION AS ENUM('categories','tags','search','list','favorites','most_visited','best_rated','recent_pics','recent_cats');
DROP TYPE IF EXISTS HISTORY_IMAGE_TYPE;
CREATE TYPE HISTORY_IMAGE_TYPE AS ENUM('picture','high','other');

CREATE TABLE "piwigo_history"
(
  "id" serial  NOT NULL,
  "date" DATE NOT NULL,
  "time" TIME NOT NULL,
  "user_id" INTEGER default 0 NOT NULL,
  "ip" VARCHAR(15) default '' NOT NULL,
  "section" HISTORY_SECTION default NULL,
  "category_id" INTEGER,
  "tag_ids" VARCHAR(50),
  "image_id" INTEGER,
  "summarized" BOOLEAN default false,
  "image_type" HISTORY_IMAGE_TYPE default NULL,
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- piwigo_history_summary
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_history_summary";
CREATE TABLE "piwigo_history_summary"
(
  "year" INTEGER default 0 NOT NULL,
  "month" INTEGER,
  "day" INTEGER,
  "hour" INTEGER,
  "nb_pages" INTEGER,
  "id" serial  NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "history_summary_ymdh" UNIQUE ("year","month","day","hour")
);



-----------------------------------------------------------------------------
-- piwigo_image_category
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_image_category";
CREATE TABLE "piwigo_image_category"
(
  "image_id" INTEGER default 0 NOT NULL,
  "category_id" INTEGER default 0 NOT NULL,
  "rank" INTEGER,
  PRIMARY KEY ("image_id","category_id")
);


-----------------------------------------------------------------------------
-- piwigo_image_tag
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_image_tag";
CREATE TABLE "piwigo_image_tag"
(
  "image_id" INTEGER default 0 NOT NULL,
  "tag_id" INTEGER default 0 NOT NULL,
  PRIMARY KEY ("image_id","tag_id")
);


-----------------------------------------------------------------------------
-- piwigo_images
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_images";
CREATE TABLE "piwigo_images"
(
  "id" serial  NOT NULL,
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

  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- piwigo_languages
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_languages";
CREATE TABLE "piwigo_languages"
(
  "id" VARCHAR(64) NOT NULL default '',
  "version" VARCHAR(64) NOT NULL default '0',
  "name" VARCHAR(64) default NULL,
  PRIMARY KEY  ("id")
);


-----------------------------------------------------------------------------
-- piwigo_old_permalinks
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_old_permalinks";
CREATE TABLE "piwigo_old_permalinks"
(
  "cat_id" INTEGER default 0 NOT NULL,
  "permalink" VARCHAR(64) default '' NOT NULL,
  "date_deleted" TIMESTAMP NOT NULL,
  "last_hit" TIMESTAMP,
  "hit" INTEGER default 0 NOT NULL,
  PRIMARY KEY ("permalink")
);


-----------------------------------------------------------------------------
-- piwigo_plugins
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_plugins";
DROP TYPE IF EXISTS PLUGINS_STATE;

CREATE TYPE PLUGINS_STATE AS ENUM('active', 'inactive');
CREATE TABLE "piwigo_plugins"
(
  "id" VARCHAR(64) default '' NOT NULL,
  "state" PLUGINS_STATE default 'inactive'::PLUGINS_STATE,
  "version" VARCHAR(64) default '0' NOT NULL,
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- piwigo_rate
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_rate";
CREATE TABLE "piwigo_rate"
(
  "user_id" INTEGER default 0 NOT NULL,
  "element_id" INTEGER default 0 NOT NULL,
  "anonymous_id" VARCHAR(45) default '' NOT NULL,
  "rate" INTEGER default 0 NOT NULL,
  "date" DATE  NOT NULL,
  PRIMARY KEY ("user_id","element_id","anonymous_id")
);


-----------------------------------------------------------------------------
-- piwigo_search
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_search";
CREATE TABLE "piwigo_search"
(
  "id" serial  NOT NULL,
  "last_seen" DATE,
  "rules" TEXT,
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- piwigo_sessions
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_sessions";
CREATE TABLE "piwigo_sessions"
(
  "id" VARCHAR(255) default '' NOT NULL,
  "data" TEXT  NOT NULL,
  "expiration" TIMESTAMP NOT NULL,
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- piwigo_sites
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_sites";
CREATE TABLE "piwigo_sites"
(
  "id" serial  NOT NULL,
  "galleries_url" VARCHAR(255) default '' NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "sites_ui1" UNIQUE ("galleries_url")
);


-----------------------------------------------------------------------------
-- piwigo_tags
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_tags";
CREATE TABLE "piwigo_tags"
(
  "id" serial  NOT NULL,
  "name" VARCHAR(255) default '' NOT NULL,
  "url_name" VARCHAR(255) default '' NOT NULL,
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- piwigo_themes
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_themes";
CREATE TABLE "piwigo_themes" 
(
  "id" varchar(64) NOT NULL default '',
  "version" varchar(64) NOT NULL default '0',
  "name" varchar(64) default NULL,
  PRIMARY KEY  ("id")
);


-----------------------------------------------------------------------------
-- piwigo_upgrade
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_upgrade";
CREATE TABLE "piwigo_upgrade"
(
  "id" VARCHAR(20) default '' NOT NULL,
  "applied" TIMESTAMP NOT NULL,
  "description" VARCHAR(255),
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- piwigo_user_access
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_user_access";
CREATE TABLE "piwigo_user_access"
(
  "user_id" INTEGER default 0 NOT NULL,
  "cat_id" INTEGER default 0 NOT NULL,
  PRIMARY KEY ("user_id","cat_id")
);


-----------------------------------------------------------------------------
-- piwigo_user_cache
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_user_cache";
DROP TYPE IF EXISTS USER_CACHE_IMAGE_ACCESS_TYPE;

CREATE TYPE USER_CACHE_IMAGE_ACCESS_TYPE AS ENUM('NOT IN','IN');
CREATE TABLE "piwigo_user_cache"
(
  "user_id" INTEGER default 0 NOT NULL,
  "need_update" BOOLEAN default true,
  "cache_update_time" INTEGER default 0 NOT NULL,
  "forbidden_categories" TEXT,
  "nb_total_images" INTEGER,
  "last_photo_date" date DEFAULT NULL,
  "nb_available_tags" INTEGER DEFAULT NULL,
  "nb_available_comments" INTEGER DEFAULT NULL,
  "image_access_type" USER_CACHE_IMAGE_ACCESS_TYPE default 'NOT IN'::USER_CACHE_IMAGE_ACCESS_TYPE,
  "image_access_list" TEXT,
  PRIMARY KEY ("user_id")
);


-----------------------------------------------------------------------------
-- piwigo_user_cache_categories
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_user_cache_categories";
CREATE TABLE "piwigo_user_cache_categories"
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
-- piwigo_user_feed
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_user_feed";
CREATE TABLE "piwigo_user_feed"
(
  "id" VARCHAR(50) default '' NOT NULL,
  "user_id" INTEGER default 0 NOT NULL,
  "last_check" TIMESTAMP,
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- piwigo_user_group
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_user_group";
CREATE TABLE "piwigo_user_group"
(
  "user_id" INTEGER default 0 NOT NULL,
  "group_id" INTEGER default 0 NOT NULL,
  PRIMARY KEY ("user_id","group_id")
);


-----------------------------------------------------------------------------
-- piwigo_user_infos
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_user_infos";
DROP TYPE IF EXISTS USER_INFOS_STATUS;

CREATE TYPE USER_INFOS_STATUS AS ENUM('webmaster','admin','normal','generic','guest');
CREATE TABLE "piwigo_user_infos"
(
  "user_id" INTEGER default 0 NOT NULL,
  "nb_image_page" INTEGER default 15 NOT NULL,
  "status" USER_INFOS_STATUS default 'guest'::USER_INFOS_STATUS,
  "language" VARCHAR(50) default 'en_UK' NOT NULL,
  "expand" BOOLEAN default false,
  "show_nb_comments" BOOLEAN default false,
  "show_nb_hits" BOOLEAN default false,
  "recent_period" INTEGER default 7 NOT NULL,
  "theme" VARCHAR(255) default 'elegant' NOT NULL,
  "registration_date" TIMESTAMP NOT NULL,
  "enabled_high" BOOLEAN default true,
  "level" INTEGER default 0 NOT NULL,
  "activation_key" VARCHAR(20) default NULL,
  PRIMARY KEY ("user_id"),
  CONSTRAINT "user_infos_ui1" UNIQUE ("user_id")
);


-----------------------------------------------------------------------------
-- piwigo_user_mail_notification
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "piwigo_user_mail_notification";
CREATE TABLE "piwigo_user_mail_notification"
(
  "user_id" INTEGER default 0 NOT NULL,
  "check_key" VARCHAR(16) default '' NOT NULL,
  "enabled" BOOLEAN default false,
  "last_send" TIMESTAMP,
  PRIMARY KEY ("user_id"),
  CONSTRAINT "user_mail_notification_ui1" UNIQUE ("check_key")
);



-----------------------------------------------------------------------------
-- piwigo_users
-----------------------------------------------------------------------------
DROP TABLE IF EXISTS "piwigo_comments";
DROP TABLE IF EXISTS "piwigo_users";

CREATE TABLE "piwigo_users"
(
  "id" serial  NOT NULL,
  "username" VARCHAR(100) default '' NOT NULL,
  "password" VARCHAR(255),
  "mail_address" VARCHAR(255),
  PRIMARY KEY ("id"),
  CONSTRAINT "users_ui1" UNIQUE ("username")
);


-----------------------------------------------------------------------------
-- piwigo_comments
-----------------------------------------------------------------------------

CREATE TABLE "piwigo_comments"
(
  "id" serial  NOT NULL,
  "image_id" INTEGER default 0 NOT NULL,
  "date" TIMESTAMP  NOT NULL,
  "author" VARCHAR(255),
  "email" VARCHAR(255),
  "anonymous_id" VARCHAR(45),
  "website_url" VARCHAR(255),
  "content" TEXT,
  "validated" BOOLEAN default false,
  "validation_date" TIMESTAMP,
  "author_id" INTEGER REFERENCES "piwigo_users" (id),
  PRIMARY KEY ("id")
);
