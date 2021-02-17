<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$upgrade_description = 'Update database schema to used with Doctrine';

function updateType($conn, string $type, array $values)
{
    $query = 'UPDATE ' . App\Repository\BaseRepository::CONFIG_TABLE;
    $query .= sprintf(' SET type="%s"', $type);
    $query .= ' WHERE param ' . $conn->in($values);
    $conn->db_query($query);
}

/**
 * Add new temporary fields
 * Populate temporary fields with values from current fields converted (true => 1, false => 0)
 * Delete current fields
 * Rename temporary fields
 */
function convertEnumToBoolean($conn, $table, $fields)
{
    $query = 'ALTER TABLE ' . $table;
    $columns = [];
    foreach (array_keys($fields) as $field) {
        $columns[] = sprintf(' ADD COLUMN tmp_%s TINYINT(1) NOT NULL DEFAULT IF(%s = \'true\', 1, 0)', $field, $field);
    }
    $query .= implode(', ', $columns);
    $conn->db_query($query);

    $query = 'ALTER TABLE ' . $table;
    $columns = [];
    foreach ($fields as $field => $default_value) {
        if ($field !== 0) {
            $columns[] = sprintf(' CHANGE COLUMN tmp_%s tmp_%s TINYINT(1) NOT NULL DEFAULT %s', $field, $field, $default_value);
        } else {
            $columns[] = sprintf(' CHANGE COLUMN tmp_%s tmp_%s TINYINT(1) NOT NULL', $default_value, $default_value);
        }
    }
    $query .= implode(', ', $columns);
    $conn->db_query($query);

    $query = 'ALTER TABLE ' . $table;
    $columns = [];
    foreach (array_keys($fields) as $field) {
        $columns[] = sprintf(' DROP COLUMN %s', $field);
    }
    $query .= implode(', ', $columns);
    $conn->db_query($query);

    $query = 'ALTER TABLE ' . $table;
    $columns = [];
    foreach (array_keys($fields) as $field) {
        $columns[] = sprintf(' RENAME COLUMN tmp_%s TO %s', $field, $field);
    }
    $query .= implode(', ', $columns);
    $conn->db_query($query);
}

/**
 * Add new temporary fields
 * Populate temporary fields with values from current fields
 * Delete current fields
 * Rename temporary fields
 */
function convertEnumToString($conn, $table, $field, $size, $default_value = null)
{
    $query = 'ALTER TABLE ' . $table;
    $query .= sprintf(' ADD COLUMN tmp_%s VARCHAR(%d) NOT NULL DEFAULT %s', $field, $size, $field);
    $conn->db_query($query);

    $query = 'ALTER TABLE ' . $table;
    if (is_null($default_value)) {
        $query .= sprintf(' CHANGE COLUMN tmp_%s tmp_%s VARCHAR(%d)', $field, $field, $size);
    } else {
        $query .= sprintf(' CHANGE COLUMN tmp_%s tmp_%s VARCHAR(%d) NOT NULL DEFAULT \'%s\'', $field, $field, $size, $default_value);
    }
    $conn->db_query($query);

    $query = 'ALTER TABLE ' . $table;
    $query .= sprintf(' DROP COLUMN %s', $field);
    $conn->db_query($query);

    $query = 'ALTER TABLE ' . $table;
    $query .= sprintf(' RENAME COLUMN tmp_%s TO %s', $field, $field);
    $conn->db_query($query);
}

function addConstraint($conn, $table, $key, $foreing_key, $reference_key)
{
    $query = 'ALTER TABLE ' . $table;
    $query .= sprintf(' ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`id`)', $key, $foreing_key, $reference_key);
    $conn->db_query($query);
}

if (in_array($conn->getLayer(), ['mysql', 'pgsql', 'sqlite'])) {
    $query = 'ALTER TABLE ' . App\Repository\BaseRepository::CONFIG_TABLE;
    $query .= ' ADD COLUMN type VARCHAR(15) DEFAULT "string"';
    $conn->db_query($query);

    updateType($conn, 'json', ['blk_menubar', 'picture_informations', 'treflez']);
    updateType($conn, 'base64', ['derivatives']);
    updateType($conn, 'integer', ['nb_categories_page', 'nb_comment_page', 'original_resize_maxheight', 'original_resize_maxwidth', 'original_resize_quality']);

    $boolean_types = [
        'activate_comments', 'allow_user_customization', 'allow_user_registration', 'comments_author_mandatory', 'comments_email_mandatory',
        'comments_enable_website', 'comments_forall', 'comments_validation', 'email_admin_on_comment', 'email_admin_on_comment_deletion',
        'email_admin_on_comment_edition', 'email_admin_on_comment_validation', 'gallery_locked', 'history_guest', 'index_created_date_icon',
        'index_flat_icon', 'index_new_icon', 'index_posted_date_icon', 'index_slideshow_icon', 'index_sort_order_input', 'log', 'menubar_filter_icon',
        'nbm_send_detailed_content', 'nbm_send_html_mail', 'nbm_send_recent_post_dates', 'original_resize', 'picture_download_icon', 'picture_favorite_icon',
        'picture_menu', 'picture_metadata_icon', 'picture_navigation_icons', 'picture_navigation_thumb', 'picture_slideshow_icon', 'rate', 'rate_anonymous',
        'user_can_delete_comment', 'user_can_edit_comment'
    ];
    updateType($conn, 'boolean', $boolean_types);
}

if ($conn->getLayer() === 'mysql') {
    $tables = [
        APP\Repository\BaseRepository::CATEGORIES_TABLE,
        APP\Repository\BaseRepository::COMMENTS_TABLE,
        APP\Repository\BaseRepository::CONFIG_TABLE,
        APP\Repository\BaseRepository::FAVORITES_TABLE,
        APP\Repository\BaseRepository::GROUP_ACCESS_TABLE,
        APP\Repository\BaseRepository::GROUPS_TABLE,
        APP\Repository\BaseRepository::HISTORY_TABLE,
        APP\Repository\BaseRepository::HISTORY_SUMMARY_TABLE,
        APP\Repository\BaseRepository::IMAGE_CATEGORY_TABLE,
        APP\Repository\BaseRepository::IMAGES_TABLE,
        APP\Repository\BaseRepository::SITES_TABLE,
        APP\Repository\BaseRepository::USER_ACCESS_TABLE,
        APP\Repository\BaseRepository::USER_GROUP_TABLE,
        APP\Repository\BaseRepository::USERS_TABLE,
        APP\Repository\BaseRepository::USER_INFOS_TABLE,
        APP\Repository\BaseRepository::USER_FEED_TABLE,
        APP\Repository\BaseRepository::RATE_TABLE,
        APP\Repository\BaseRepository::USER_CACHE_TABLE,
        APP\Repository\BaseRepository::USER_CACHE_CATEGORIES_TABLE,
        APP\Repository\BaseRepository::CADDIE_TABLE,
        APP\Repository\BaseRepository::UPGRADE_TABLE,
        APP\Repository\BaseRepository::SEARCH_TABLE,
        APP\Repository\BaseRepository::USER_MAIL_NOTIFICATION_TABLE,
        APP\Repository\BaseRepository::TAGS_TABLE,
        APP\Repository\BaseRepository::IMAGE_TAG_TABLE,
        APP\Repository\BaseRepository::PLUGINS_TABLE,
        APP\Repository\BaseRepository::OLD_PERMALINKS_TABLE,
        APP\Repository\BaseRepository::THEMES_TABLE,
        APP\Repository\BaseRepository::LANGUAGES_TABLE
    ];

    convertEnumToBoolean($conn, App\Repository\BaseRepository::CATEGORIES_TABLE, ['visible' => 1, 'commentable' => 1]);
    convertEnumToBoolean($conn, App\Repository\BaseRepository::COMMENTS_TABLE, ['validated' => 0]);
    convertEnumToBoolean($conn, App\Repository\BaseRepository::GROUPS_TABLE, ['is_default' => 0]);
    convertEnumToBoolean($conn, App\Repository\BaseRepository::HISTORY_TABLE, ['summarized' => 0]);
    convertEnumToBoolean($conn, App\Repository\BaseRepository::IMAGE_TAG_TABLE, ['validated' => 1]);
    convertEnumToBoolean($conn, App\Repository\BaseRepository::USER_CACHE_TABLE, ['need_update' => 1]);
    convertEnumToBoolean($conn, App\Repository\BaseRepository::USER_INFOS_TABLE, ['expand' => 0, 'show_nb_comments' => 0, 'show_nb_hits' => 0, 'enabled_high' => 1]);
    convertEnumToBoolean($conn, App\Repository\BaseRepository::USER_MAIL_NOTIFICATION_TABLE, ['enabled' => 0]);

    convertEnumToString($conn, App\Repository\BaseRepository::CATEGORIES_TABLE, 'status', 25, 'public');
    convertEnumToString($conn, App\Repository\BaseRepository::HISTORY_TABLE, 'section', 255);
    convertEnumToString($conn, App\Repository\BaseRepository::HISTORY_TABLE, 'image_type', 255);
    convertEnumToString($conn, App\Repository\BaseRepository::PLUGINS_TABLE, 'state', 25, 'inactive');
    convertEnumToString($conn, App\Repository\BaseRepository::USER_CACHE_TABLE, 'image_access_type', 25, 'NOT IN');
    convertEnumToString($conn, App\Repository\BaseRepository::USER_INFOS_TABLE, 'status', 50, 'guest');

    // add integrity constraints
    addConstraint($conn, App\Repository\BaseRepository::COMMENTS_TABLE, 'FK_259D537BF675F31B', 'author_id', App\Repository\BaseRepository::USERS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::COMMENTS_TABLE, 'FK_259D537B3DA5256D', 'image_id', App\Repository\BaseRepository::IMAGES_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::USER_MAIL_NOTIFICATION_TABLE, 'FK_6E424936A76ED395', 'user_id', App\Repository\BaseRepository::USERS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::USER_INFOS_TABLE, 'FK_44A6591CA76ED395', 'user_id', App\Repository\BaseRepository::USERS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::FAVORITES_TABLE, 'FK_F87F0252A76ED395', 'user_id', App\Repository\BaseRepository::USERS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::FAVORITES_TABLE, 'FK_F87F02523DA5256D', 'image_id', App\Repository\BaseRepository::IMAGES_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::USER_CACHE_TABLE, 'FK_EB2BB096A76ED395', 'user_id', App\Repository\BaseRepository::USERS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::CADDIE_TABLE, 'FK_70B6B1A8A76ED395', 'user_id', App\Repository\BaseRepository::USERS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::CADDIE_TABLE, 'FK_70B6B1A81F1F2A24', 'element_id', App\Repository\BaseRepository::IMAGES_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::USER_GROUP_TABLE, 'FK_C7AC9FB4FE54D947', 'group_id', App\Repository\BaseRepository::GROUPS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::USER_GROUP_TABLE, 'FK_C7AC9FB4A76ED395', 'user_id', App\Repository\BaseRepository::USERS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::GROUP_ACCESS_TABLE, 'FK_AAC70409FE54D947', 'group_id', App\Repository\BaseRepository::GROUPS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::GROUP_ACCESS_TABLE, 'FK_AAC70409E6ADA943', 'cat_id', App\Repository\BaseRepository::CATEGORIES_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::IMAGE_TAG_TABLE, 'FK_477505773DA5256D', 'image_id', App\Repository\BaseRepository::IMAGES_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::IMAGE_TAG_TABLE, 'FK_47750577BAD26311', 'tag_id', App\Repository\BaseRepository::TAGS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::IMAGE_TAG_TABLE, 'FK_47750577DE12AB56', 'created_by', App\Repository\BaseRepository::USERS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::IMAGE_CATEGORY_TABLE, 'FK_244869F83DA5256D', 'image_id', App\Repository\BaseRepository::IMAGES_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::IMAGE_CATEGORY_TABLE, 'FK_244869F812469DE2', 'category_id', App\Repository\BaseRepository::CATEGORIES_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::HISTORY_TABLE, 'FK_4E2589C0A76ED395', 'user_id', App\Repository\BaseRepository::USERS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::HISTORY_TABLE, 'FK_4E2589C012469DE2', 'category_id', App\Repository\BaseRepository::CATEGORIES_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::HISTORY_TABLE, 'FK_4E2589C03DA5256D', 'image_id', App\Repository\BaseRepository::IMAGES_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::USER_ACCESS_TABLE, 'FK_21C10625A76ED395', 'user_id', App\Repository\BaseRepository::USERS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::USER_ACCESS_TABLE, 'FK_21C10625E6ADA943', 'cat_id', App\Repository\BaseRepository::CATEGORIES_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::CATEGORIES_TABLE, 'FK_725D6641C7F87B72', 'id_uppercat', App\Repository\BaseRepository::CATEGORIES_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::CATEGORIES_TABLE, 'FK_725D6641F6BD1646', 'site_id', App\Repository\BaseRepository::SITES_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::RATE_TABLE, 'FK_23A9DF15A76ED395', 'user_id', App\Repository\BaseRepository::USERS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::RATE_TABLE, 'FK_23A9DF151F1F2A24', 'element_id', App\Repository\BaseRepository::IMAGES_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::USER_FEED_TABLE, 'FK_45D76AC5A76ED395', 'user_id', App\Repository\BaseRepository::USERS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::USER_CACHE_CATEGORIES_TABLE, 'FK_38F22377A76ED395', 'user_id', App\Repository\BaseRepository::USERS_TABLE);
    addConstraint($conn, App\Repository\BaseRepository::USER_CACHE_CATEGORIES_TABLE, 'FK_38F22377E6ADA943', 'cat_id', App\Repository\BaseRepository::CATEGORIES_TABLE);

    foreach ($tables as $table) {
        $query = sprintf('ALTER TABLE %s ENGINE=InnoDB', $table);
        $conn->db_query($query);
    }
}

if (in_array($conn->getLayer(), ['mysql', 'pgsql', 'sqlite'])) {
    (new App\Repository\UserCacheRepository($conn))->deleteUserCache();
}

echo "\n" . $upgrade_description . "\n";
