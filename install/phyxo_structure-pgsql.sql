-----------------------------------------------------------------------------
-- phyxo_caddie
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_caddie";
CREATE TABLE "phyxo_caddie"
(
  "user_id" INTEGER DEFAULT 0 NOT NULL,
  "element_id" INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ("user_id","element_id")
);


-----------------------------------------------------------------------------
-- phyxo_categories
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_categories";
DROP TYPE IF EXISTS CATEGORIES_STATUS;

CREATE TYPE CATEGORIES_STATUS AS ENUM('public', 'private');
CREATE TABLE "phyxo_categories"
(
  "id" serial  NOT NULL,
  "name" VARCHAR(255) DEFAULT '' NOT NULL,
  "id_uppercat" INTEGER,
  "comment" TEXT,
  "dir" VARCHAR(255),
  "rank" INTEGER,
  "status" CATEGORIES_STATUS DEFAULT 'public'::CATEGORIES_STATUS,
  "site_id" INTEGER DEFAULT 1,
  "visible" BOOLEAN DEFAULT true,
  "representative_picture_id" INTEGER,
  "uppercats" VARCHAR(255) DEFAULT '' NOT NULL,
  "commentable" BOOLEAN DEFAULT true,
  "global_rank" VARCHAR(255),
  "image_order" VARCHAR(128),
  "permalink" VARCHAR(64),
  "lastmodified" TIMESTAMP NOT NULL DEFAULT now(),
  PRIMARY KEY ("id"),
  CONSTRAINT "categories_i3" UNIQUE ("permalink")
);

-----------------------------------------------------------------------------
-- phyxo_config
-----------------------------------------------------------------------------
DROP TABLE IF EXISTS "phyxo_config";
CREATE TABLE "phyxo_config"
(
  "param" VARCHAR(40) DEFAULT '' NOT NULL,
  "type" VARCHAR(15),
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
  "user_id" INTEGER DEFAULT 0 NOT NULL,
  "image_id" INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ("user_id","image_id")
);

-----------------------------------------------------------------------------
-- phyxo_group_access
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_group_access";
CREATE TABLE "phyxo_group_access"
(
  "group_id" INTEGER DEFAULT 0 NOT NULL,
  "cat_id" INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ("group_id","cat_id")
);


-----------------------------------------------------------------------------
-- phyxo_groups
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_groups";
CREATE TABLE "phyxo_groups"
(
  "id" serial  NOT NULL,
  "name" VARCHAR(255) DEFAULT '' NOT NULL,
  "is_default" BOOLEAN DEFAULT false,
  "lastmodified" TIMESTAMP NOT NULL DEFAULT now(),
  PRIMARY KEY ("id"),
  CONSTRAINT "groups_ui1" UNIQUE ("name")
);


-----------------------------------------------------------------------------
-- phyxo_history
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_history";

DROP TYPE IF EXISTS HISTORY_SECTION;
CREATE TYPE HISTORY_SECTION AS ENUM('categories','tags','search','list','favorites','most_visited','best_rated','recent_pics','recent_cats');
DROP TYPE IF EXISTS HISTORY_IMAGE_TYPE;
CREATE TYPE HISTORY_IMAGE_TYPE AS ENUM('picture','high','other');

CREATE TABLE "phyxo_history"
(
  "id" serial  NOT NULL,
  "date" DATE NOT NULL,
  "time" TIME NOT NULL,
  "user_id" INTEGER DEFAULT 0 NOT NULL,
  "ip" VARCHAR(15) DEFAULT '' NOT NULL,
  "section" HISTORY_SECTION DEFAULT NULL,
  "category_id" INTEGER,
  "tag_ids" VARCHAR(50),
  "image_id" INTEGER,
  "summarized" BOOLEAN DEFAULT false,
  "image_type" HISTORY_IMAGE_TYPE DEFAULT NULL,
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- phyxo_history_summary
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_history_summary";
CREATE TABLE "phyxo_history_summary"
(
  "year" INTEGER DEFAULT 0 NOT NULL,
  "month" INTEGER,
  "day" INTEGER,
  "hour" INTEGER,
  "nb_pages" INTEGER,
  "id" serial  NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "history_summary_ymdh" UNIQUE ("year","month","day","hour")
);



-----------------------------------------------------------------------------
-- phyxo_image_category
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_image_category";
CREATE TABLE "phyxo_image_category"
(
  "image_id" INTEGER DEFAULT 0 NOT NULL,
  "category_id" INTEGER DEFAULT 0 NOT NULL,
  "rank" INTEGER,
  PRIMARY KEY ("image_id","category_id")
);


-----------------------------------------------------------------------------
-- phyxo_images
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_images";
CREATE TABLE "phyxo_images"
(
  "id" serial  NOT NULL,
  "file" VARCHAR(255) DEFAULT '' NOT NULL,
  "date_available" TIMESTAMP NOT NULL,
  "date_creation" TIMESTAMP,
  "name" VARCHAR(255),
  "comment" TEXT,
  "author" VARCHAR(255),
  "hit" INTEGER DEFAULT 0 NOT NULL,
  "filesize" INTEGER,
  "width" INTEGER,
  "height" INTEGER,
  "coi" VARCHAR(4) DEFAULT NULL,
  "representative_ext" VARCHAR(4),
  "date_metadata_update" DATE,
  "rating_score" FLOAT,
  "path" VARCHAR(255) DEFAULT '' NOT NULL,
  "storage_category_id" INTEGER,
  "level" INTEGER DEFAULT 0 NOT NULL,
  "md5sum" VARCHAR(32),
  "added_by" INTEGER NOT NULL DEFAULT 0,
  "rotation" INTEGER DEFAULT NULL,
  "latitude" FLOAT DEFAULT NULL,
  "longitude" FLOAT DEFAULT NULL,
  "lastmodified" TIMESTAMP NOT NULL DEFAULT now(),

  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- phyxo_languages
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_languages";
CREATE TABLE "phyxo_languages"
(
  "id" VARCHAR(64) NOT NULL DEFAULT '',
  "version" VARCHAR(64) NOT NULL DEFAULT '0',
  "name" VARCHAR(64) DEFAULT NULL,
  PRIMARY KEY  ("id")
);


-----------------------------------------------------------------------------
-- phyxo_old_permalinks
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_old_permalinks";
CREATE TABLE "phyxo_old_permalinks"
(
  "cat_id" INTEGER DEFAULT 0 NOT NULL,
  "permalink" VARCHAR(64) DEFAULT '' NOT NULL,
  "date_deleted" TIMESTAMP NOT NULL,
  "last_hit" TIMESTAMP,
  "hit" INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ("permalink")
);


-----------------------------------------------------------------------------
-- phyxo_plugins
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_plugins";
CREATE TABLE "phyxo_plugins"
(
  "id" VARCHAR(64) DEFAULT '' NOT NULL,
  "state" VARCHAR(25) DEFAULT 'inactive',
  "version" VARCHAR(64) DEFAULT '0' NOT NULL,
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- phyxo_rate
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_rate";
CREATE TABLE "phyxo_rate"
(
  "user_id" INTEGER DEFAULT 0 NOT NULL,
  "element_id" INTEGER DEFAULT 0 NOT NULL,
  "anonymous_id" VARCHAR(45) DEFAULT '' NOT NULL,
  "rate" INTEGER DEFAULT 0 NOT NULL,
  "date" DATE  NOT NULL,
  PRIMARY KEY ("user_id","element_id","anonymous_id")
);


-----------------------------------------------------------------------------
-- phyxo_search
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_search";
CREATE TABLE "phyxo_search"
(
  "id" serial  NOT NULL,
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
  "sess_id" VARCHAR(128) NOT NULL PRIMARY KEY,
  "sess_data" BYTEA NOT NULL,
  "sess_time" INTEGER NOT NULL,
  "sess_lifetime" INTEGER NOT NULL
);


-----------------------------------------------------------------------------
-- phyxo_sites
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_sites";
CREATE TABLE "phyxo_sites"
(
  "id" serial  NOT NULL,
  "galleries_url" VARCHAR(255) DEFAULT '' NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "sites_ui1" UNIQUE ("galleries_url")
);


-----------------------------------------------------------------------------
-- phyxo_tags
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_tags";
CREATE TABLE "phyxo_tags"
(
  "id" serial  NOT NULL,
  "name" VARCHAR(255) DEFAULT '' NOT NULL,
  "url_name" VARCHAR(255) DEFAULT '' NOT NULL,
  "lastmodified" TIMESTAMP NOT NULL DEFAULT now(),

  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- phyxo_themes
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_themes";
CREATE TABLE "phyxo_themes"
(
  "id" varchar(64) NOT NULL DEFAULT '',
  "version" varchar(64) NOT NULL DEFAULT '0',
  "name" varchar(64) DEFAULT NULL,
  PRIMARY KEY  ("id")
);


-----------------------------------------------------------------------------
-- phyxo_upgrade
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_upgrade";
CREATE TABLE "phyxo_upgrade"
(
  "id" VARCHAR(20) DEFAULT '' NOT NULL,
  "applied" DATE DEFAULT NULL,
  "description" VARCHAR(255),
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- phyxo_user_access
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_user_access";
CREATE TABLE "phyxo_user_access"
(
  "user_id" INTEGER DEFAULT 0 NOT NULL,
  "cat_id" INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ("user_id","cat_id")
);


-----------------------------------------------------------------------------
-- phyxo_user_cache
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_user_cache";
DROP TYPE IF EXISTS USER_CACHE_IMAGE_ACCESS_TYPE;

CREATE TYPE USER_CACHE_IMAGE_ACCESS_TYPE AS ENUM('NOT IN','IN');
CREATE TABLE "phyxo_user_cache"
(
  "user_id" INTEGER DEFAULT 0 NOT NULL,
  "need_update" BOOLEAN DEFAULT true,
  "cache_update_time" INTEGER DEFAULT 0 NOT NULL,
  "forbidden_categories" TEXT,
  "nb_total_images" INTEGER,
  "last_photo_date" date DEFAULT NULL,
  "nb_available_tags" INTEGER DEFAULT NULL,
  "nb_available_comments" INTEGER DEFAULT NULL,
  "image_access_type" USER_CACHE_IMAGE_ACCESS_TYPE DEFAULT 'NOT IN'::USER_CACHE_IMAGE_ACCESS_TYPE,
  "image_access_list" TEXT,
  PRIMARY KEY ("user_id")
);


-----------------------------------------------------------------------------
-- phyxo_user_cache_categories
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_user_cache_categories";
CREATE TABLE "phyxo_user_cache_categories"
(
  "user_id" INTEGER DEFAULT 0 NOT NULL,
  "cat_id" INTEGER DEFAULT 0 NOT NULL,
  "date_last" TIMESTAMP,
  "max_date_last" TIMESTAMP,
  "nb_images" INTEGER DEFAULT 0 NOT NULL,
  "count_images" INTEGER DEFAULT 0,
  "nb_categories" INTEGER DEFAULT 0,
  "count_categories" INTEGER DEFAULT 0,
  "user_representative_picture_id" INTEGER DEFAULT NULL,
  PRIMARY KEY ("user_id","cat_id")
);


-----------------------------------------------------------------------------
-- phyxo_user_feed
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_user_feed";
CREATE TABLE "phyxo_user_feed"
(
  "id" VARCHAR(50) DEFAULT '' NOT NULL,
  "user_id" INTEGER DEFAULT 0 NOT NULL,
  "last_check" TIMESTAMP,
  PRIMARY KEY ("id")
);


-----------------------------------------------------------------------------
-- phyxo_user_group
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_user_group";
CREATE TABLE "phyxo_user_group"
(
  "user_id" INTEGER DEFAULT 0 NOT NULL,
  "group_id" INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ("user_id","group_id")
);


-----------------------------------------------------------------------------
-- phyxo_user_infos
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_user_infos";
CREATE TABLE "phyxo_user_infos"
(
  "user_id" INTEGER DEFAULT 0 NOT NULL,
  "nb_image_page" INTEGER DEFAULT 15 NOT NULL,
  "status" VARCHAR(50) NOT NULL,
  "language" VARCHAR(50) DEFAULT 'en_GB' NOT NULL,
  "expand" BOOLEAN DEFAULT false,
  "show_nb_comments" BOOLEAN DEFAULT false,
  "show_nb_hits" BOOLEAN DEFAULT false,
  "recent_period" INTEGER DEFAULT 7 NOT NULL,
  "theme" VARCHAR(255) DEFAULT 'treflez' NOT NULL,
  "registration_date" TIMESTAMP NOT NULL,
  "enabled_high" BOOLEAN DEFAULT true,
  "level" INTEGER DEFAULT 0 NOT NULL,
  "activation_key" VARCHAR(255) DEFAULT NULL,
  "activation_key_expire" TIMESTAMP DEFAULT NULL,
  "lastmodified" TIMESTAMP NOT NULL DEFAULT now(),

  PRIMARY KEY ("user_id"),
  CONSTRAINT "user_infos_ui1" UNIQUE ("user_id")
);


-----------------------------------------------------------------------------
-- phyxo_user_mail_notification
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_user_mail_notification";
CREATE TABLE "phyxo_user_mail_notification"
(
  "user_id" INTEGER DEFAULT 0 NOT NULL,
  "check_key" VARCHAR(16) DEFAULT '' NOT NULL,
  "enabled" BOOLEAN DEFAULT false,
  "last_send" TIMESTAMP,
  PRIMARY KEY ("user_id"),
  CONSTRAINT "user_mail_notification_ui1" UNIQUE ("check_key")
);



-----------------------------------------------------------------------------
-- phyxo_users
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_users";
CREATE TABLE "phyxo_users"
(
  "id" serial  NOT NULL,
  "username" VARCHAR(100) DEFAULT '' NOT NULL,
  "password" VARCHAR(255) DEFAULT NULL,
  "mail_address" VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "users_ui1" UNIQUE ("username")
);


-----------------------------------------------------------------------------
-- phyxo_image_tag
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_image_tag";
CREATE TABLE "phyxo_image_tag"
(
  "image_id" INTEGER DEFAULT 0 NOT NULL,
  "tag_id" INTEGER DEFAULT 0 NOT NULL,
  "validated" BOOLEAN DEFAULT true,
  "created_by" INTEGER REFERENCES "phyxo_users" (id),
  "status" INTEGER DEFAULT 1,

  PRIMARY KEY ("image_id","tag_id")
);


-----------------------------------------------------------------------------
-- phyxo_comments
-----------------------------------------------------------------------------

DROP TABLE IF EXISTS "phyxo_comments";
CREATE TABLE "phyxo_comments"
(
  "id" serial  NOT NULL,
  "image_id" INTEGER DEFAULT 0 NOT NULL,
  "date" TIMESTAMP  NOT NULL,
  "author" VARCHAR(255),
  "email" VARCHAR(255),
  "anonymous_id" VARCHAR(45),
  "website_url" VARCHAR(255),
  "content" TEXT,
  "validated" BOOLEAN DEFAULT false,
  "validation_date" TIMESTAMP,
  "author_id" INTEGER REFERENCES "phyxo_users" (id),
  PRIMARY KEY ("id")
);
