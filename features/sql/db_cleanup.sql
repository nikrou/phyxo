DELETE FROM phyxo_history;
DELETE FROM phyxo_history_summary;
DELETE FROM phyxo_comments;
DELETE FROM phyxo_favorites;
DELETE FROM phyxo_rate;
DELETE FROM phyxo_plugins;
DELETE FROM phyxo_themes;
DELETE FROM phyxo_user_cache;
DELETE FROM phyxo_user_cache_categories;
DELETE FROM phyxo_image_tag;
DELETE FROM phyxo_caddie;
DELETE FROM phyxo_tags;
DELETE FROM phyxo_user_group;
DELETE FROM phyxo_group_access;
DELETE FROM phyxo_groups;
DELETE FROM phyxo_user_access;
DELETE FROM phyxo_image_category;
DELETE FROM phyxo_categories where id_uppercat IS NOT NULL;
DELETE FROM phyxo_categories;
DELETE FROM phyxo_images;
DELETE FROM phyxo_user_feed;
DELETE FROM phyxo_sessions;
DELETE FROM phyxo_user_mail_notification;
DELETE FROM phyxo_user_infos where status != 'guest';
DELETE FROM phyxo_users where username != 'guest';
-- special keys for config
DELETE FROM phyxo_config WHERE param in ('tags_permission_add', 'tags_permission_delete', 'publish_tags_immediately', 'delete_tags_immediately', 'show_pending_added_tags',
'show_pending_deleted_tags', 'tags_existing_tags_only', 'guest_access');

-- languages
DELETE FROM phyxo_languages;
INSERT INTO phyxo_languages (id,version,name) VALUES('en_GB', '2.7.0', 'English [GB]');
INSERT INTO phyxo_languages (id,version,name) VALUES('fr_FR', '2.7.0', 'Français [FR]');

-- phyxo_themes
INSERT INTO phyxo_themes (id, name, version) VALUES('treflez', 'Treflez', '0.1.0');
