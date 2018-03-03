-- when behat failed or is stopped cleanup is made at startup
DELETE FROM phyxo_comments;
DELETE FROM phyxo_favorites;
DELETE FROM phyxo_rate;
DELETE FROM phyxo_plugins;
DELETE FROM phyxo_themes;
DELETE FROM phyxo_user_cache;
DELETE FROM phyxo_user_cache_categories;
DELETE FROM phyxo_tags;
DELETE FROM phyxo_image_tag;
DELETE FROM phyxo_users WHERE id != 1 AND id != 2;
DELETE FROM phyxo_user_infos WHERE user_id != 1 AND user_id != 2;
DELETE FROM phyxo_groups;
DELETE FROM phyxo_user_group;
DELETE FROM phyxo_categories;
DELETE FROM phyxo_group_access;
DELETE FROM phyxo_user_access;
DELETE FROM phyxo_images;
DELETE FROM phyxo_image_category;
DELETE FROM phyxo_sessions;
-- special keys for config
DELETE FROM phyxo_config WHERE param in ('tags_permission_add', 'tags_permission_delete', 'publish_tags_immediately', 'delete_tags_immediately');

-- languages
DELETE FROM phyxo_languages;
INSERT INTO phyxo_languages (id,version,name) VALUES('en_GB', '2.7.0', 'English [GB]');
INSERT INTO phyxo_languages (id,version,name) VALUES('fr_FR', '2.7.0', 'Français [FR]');

UPDATE phyxo_user_infos SET language = 'en_GB';
