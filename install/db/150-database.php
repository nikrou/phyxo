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

function in(array $params)
{
    if (empty($params)) {
        return '';
    }
    if (!is_array($params)) {
        if (strpos($params, ',') !== false) {
            $params = explode(',', $params);
        } else {
            $params = [$params];
        }
    }

    return ' IN(\'' . implode('\',\'', $params) . '\') ';
}

function updateType($conn, $table, string $type, array $values)
{
    $query = 'UPDATE ' . $table;
    $query .= sprintf(' SET type="%s"', $type);
    $query .= ' WHERE param ' . in($values);
    $stmt = $conn->prepare($query);
    $stmt->execute();
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
    $stmt = $conn->prepare($query);
    $stmt->execute();

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
    $stmt = $conn->prepare($query);
    $stmt->execute();

    $query = 'ALTER TABLE ' . $table;
    $columns = [];
    foreach (array_keys($fields) as $field) {
        $columns[] = sprintf(' DROP COLUMN %s', $field);
    }
    $query .= implode(', ', $columns);
    $stmt = $conn->prepare($query);
    $stmt->execute();

    $query = 'ALTER TABLE ' . $table;
    $columns = [];
    foreach (array_keys($fields) as $field) {
        $columns[] = sprintf(' RENAME COLUMN tmp_%s TO %s', $field, $field);
    }
    $query .= implode(', ', $columns);
    $stmt = $conn->prepare($query);
    $stmt->execute();
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
    $stmt = $conn->prepare($query);
    $stmt->execute();

    $query = 'ALTER TABLE ' . $table;
    if (is_null($default_value)) {
        $query .= sprintf(' CHANGE COLUMN tmp_%s tmp_%s VARCHAR(%d)', $field, $field, $size);
    } else {
        $query .= sprintf(' CHANGE COLUMN tmp_%s tmp_%s VARCHAR(%d) NOT NULL DEFAULT \'%s\'', $field, $field, $size, $default_value);
    }
    $stmt = $conn->prepare($query);
    $stmt->execute();

    $query = 'ALTER TABLE ' . $table;
    $query .= sprintf(' DROP COLUMN %s', $field);
    $stmt = $conn->prepare($query);
    $stmt->execute();

    $query = 'ALTER TABLE ' . $table;
    $query .= sprintf(' RENAME COLUMN tmp_%s TO %s', $field, $field);
    $stmt = $conn->prepare($query);
    $stmt->execute();
}

/**
 * Add new temporary fields
 * Populate temporary fields with values from current fields
 * Delete current fields
 * Rename temporary fields
 */
function convertTypeToString($conn, $table, $field, $size, $default_value = null)
{
    $query = 'ALTER TABLE ' . $table;
    $query .= sprintf(' ADD COLUMN tmp_%s VARCHAR(%d) NOT NULL DEFAULT \'%s\'', $field, $size, $default_value);
    $stmt = $conn->prepare($query);
    $stmt->execute();

    $query = 'UPDATE ' . $table;
    $query .= sprintf(' SET tmp_%s = %s', $field, $field);
    $stmt = $conn->prepare($query);
    $stmt->execute();

    $query = 'ALTER TABLE ' . $table;
    $query .= sprintf(' DROP COLUMN %s', $field);
    $stmt = $conn->prepare($query);
    $stmt->execute();

    $query = 'ALTER TABLE ' . $table;
    $query .= sprintf(' RENAME COLUMN tmp_%s TO %s', $field, $field);
    $stmt = $conn->prepare($query);
    $stmt->execute();
}

function addConstraint($conn, $table, $key, $foreing_key, $reference_key)
{
    $query = 'ALTER TABLE ' . $table;
    $query .= sprintf(' ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`id`)', $key, $foreing_key, $reference_key);
    $stmt = $conn->prepare($query);
    $stmt->execute();
}

if (in_array($conn->getDriver()->getName(), ['pdo_mysql', 'pdo_pgsql', 'pdo_sqlite'])) {
    $query = 'ALTER TABLE ' . "{$default_prefix}config";
    $query .= ' ADD COLUMN type VARCHAR(15) DEFAULT "string"';
    $stmt = $conn->prepare($query);
    $stmt->execute();

    updateType($conn, "{$default_prefix}config", 'json', ['blk_menubar', 'picture_informations', 'treflez']);
    updateType($conn, "{$default_prefix}config", 'base64', ['derivatives']);
    updateType($conn, "{$default_prefix}config", 'integer', ['nb_categories_page', 'nb_comment_page', 'original_resize_maxheight', 'original_resize_maxwidth', 'original_resize_quality']);

    $boolean_types = [
        'activate_comments', 'allow_user_customization', 'allow_user_registration', 'comments_author_mandatory', 'comments_email_mandatory',
        'comments_enable_website', 'comments_forall', 'comments_validation', 'email_admin_on_comment', 'email_admin_on_comment_deletion',
        'email_admin_on_comment_edition', 'email_admin_on_comment_validation', 'gallery_locked', 'history_guest', 'index_created_date_icon',
        'index_flat_icon', 'index_new_icon', 'index_posted_date_icon', 'index_slideshow_icon', 'index_sort_order_input', 'log', 'menubar_filter_icon',
        'nbm_send_detailed_content', 'nbm_send_html_mail', 'nbm_send_recent_post_dates', 'original_resize', 'picture_download_icon', 'picture_favorite_icon',
        'picture_menu', 'picture_metadata_icon', 'picture_navigation_icons', 'picture_navigation_thumb', 'picture_slideshow_icon', 'rate', 'rate_anonymous',
        'user_can_delete_comment', 'user_can_edit_comment'
    ];
    updateType($conn, "{$default_prefix}config", 'boolean', $boolean_types);
}


$tables = [
    "{$default_prefix}categories",
    "{$default_prefix}comments",
    "{$default_prefix}config",
    "{$default_prefix}favorites",
    "{$default_prefix}group_access",
    "{$default_prefix}groups",
    "{$default_prefix}history",
    "{$default_prefix}history_summary",
    "{$default_prefix}image_category",
    "{$default_prefix}images",
    "{$default_prefix}sites",
    "{$default_prefix}user_access",
    "{$default_prefix}user_group",
    "{$default_prefix}users",
    "{$default_prefix}user_infos",
    "{$default_prefix}user_feed",
    "{$default_prefix}rate",
    "{$default_prefix}user_cache",
    "{$default_prefix}user_cache_categories",
    "{$default_prefix}caddie",
    "{$default_prefix}upgrade",
    "{$default_prefix}search",
    "{$default_prefix}user_mail_notification",
    "{$default_prefix}tags",
    "{$default_prefix}image_tag",
    "{$default_prefix}plugins",
    "{$default_prefix}old_permalinks",
    "{$default_prefix}themes",
    "{$default_prefix}languages"
];
if ($conn->getDriver()->getName() === 'pdo_mysql') {
    convertEnumToBoolean($conn, "{$default_prefix}categories", ['visible' => 1, 'commentable' => 1]);
    convertEnumToBoolean($conn, "{$default_prefix}comments", ['validated' => 0]);
    convertEnumToBoolean($conn, "{$default_prefix}groups", ['is_default' => 0]);
    convertEnumToBoolean($conn, "{$default_prefix}history", ['summarized' => 0]);
    convertEnumToBoolean($conn, "{$default_prefix}image_tag", ['validated' => 1]);
    convertEnumToBoolean($conn, "{$default_prefix}user_cache", ['need_update' => 1]);
    convertEnumToBoolean($conn, "{$default_prefix}user_infos", ['expand' => 0, 'show_nb_comments' => 0, 'show_nb_hits' => 0, 'enabled_high' => 1]);
    convertEnumToBoolean($conn, "{$default_prefix}user_mail_notification", ['enabled' => 0]);

    convertEnumToString($conn, "{$default_prefix}categories", 'status', 25, 'public');
    convertEnumToString($conn, "{$default_prefix}history", 'section', 255);
    convertEnumToString($conn, "{$default_prefix}history", 'image_type', 255);
    convertEnumToString($conn, "{$default_prefix}plugins", 'state', 25, 'inactive');
    convertEnumToString($conn, "{$default_prefix}user_cache", 'image_access_type', 25, 'NOT IN');
    convertEnumToString($conn, "{$default_prefix}user_infos", 'status', 50, 'guest');

    // add integrity constraints
    addConstraint($conn, "{$default_prefix}comments", 'FK_259D537BF675F31B', 'author_id', "{$default_prefix}users");
    addConstraint($conn, "{$default_prefix}comments", 'FK_259D537B3DA5256D', 'image_id', "{$default_prefix}images");
    addConstraint($conn, "{$default_prefix}user_mail_notification", 'FK_6E424936A76ED395', 'user_id', "{$default_prefix}users");
    addConstraint($conn, "{$default_prefix}user_infos", 'FK_44A6591CA76ED395', 'user_id', "{$default_prefix}users");
    addConstraint($conn, "{$default_prefix}favorites", 'FK_F87F0252A76ED395', 'user_id', "{$default_prefix}users");
    addConstraint($conn, "{$default_prefix}favorites", 'FK_F87F02523DA5256D', 'image_id', "{$default_prefix}images");
    addConstraint($conn, "{$default_prefix}user_cache", 'FK_EB2BB096A76ED395', 'user_id', "{$default_prefix}users");
    addConstraint($conn, "{$default_prefix}caddie", 'FK_70B6B1A8A76ED395', 'user_id', "{$default_prefix}users");
    addConstraint($conn, "{$default_prefix}caddie", 'FK_70B6B1A81F1F2A24', 'element_id', "{$default_prefix}images");
    addConstraint($conn, "{$default_prefix}user_group", 'FK_C7AC9FB4FE54D947', 'group_id', "{$default_prefix}groups");
    addConstraint($conn, "{$default_prefix}user_group", 'FK_C7AC9FB4A76ED395', 'user_id', "{$default_prefix}users");
    addConstraint($conn, "{$default_prefix}group_access", 'FK_AAC70409FE54D947', 'group_id', "{$default_prefix}groups");
    addConstraint($conn, "{$default_prefix}group_access", 'FK_AAC70409E6ADA943', 'cat_id', "{$default_prefix}categories");
    addConstraint($conn, "{$default_prefix}image_tag", 'FK_477505773DA5256D', 'image_id', "{$default_prefix}images");
    addConstraint($conn, "{$default_prefix}image_tag", 'FK_47750577BAD26311', 'tag_id', "{$default_prefix}tags");
    addConstraint($conn, "{$default_prefix}image_tag", 'FK_47750577DE12AB56', 'created_by', "{$default_prefix}users");
    addConstraint($conn, "{$default_prefix}image_category", 'FK_244869F83DA5256D', 'image_id', "{$default_prefix}images");
    addConstraint($conn, "{$default_prefix}image_category", 'FK_244869F812469DE2', 'category_id', "{$default_prefix}categories");
    addConstraint($conn, "{$default_prefix}history", 'FK_4E2589C0A76ED395', 'user_id', "{$default_prefix}users");
    addConstraint($conn, "{$default_prefix}history", 'FK_4E2589C012469DE2', 'category_id', "{$default_prefix}categories");
    addConstraint($conn, "{$default_prefix}history", 'FK_4E2589C03DA5256D', 'image_id', "{$default_prefix}images");
    addConstraint($conn, "{$default_prefix}user_access", 'FK_21C10625A76ED395', 'user_id', "{$default_prefix}users");
    addConstraint($conn, "{$default_prefix}user_access", 'FK_21C10625E6ADA943', 'cat_id', "{$default_prefix}categories");
    addConstraint($conn, "{$default_prefix}categories", 'FK_725D6641C7F87B72', 'id_uppercat', "{$default_prefix}categories");
    addConstraint($conn, "{$default_prefix}categories", 'FK_725D6641F6BD1646', 'site_id', "{$default_prefix}sites");
    addConstraint($conn, "{$default_prefix}rate", 'FK_23A9DF15A76ED395', 'user_id', "{$default_prefix}users");
    addConstraint($conn, "{$default_prefix}rate", 'FK_23A9DF151F1F2A24', 'element_id', "{$default_prefix}images");
    addConstraint($conn, "{$default_prefix}user_feed", 'FK_45D76AC5A76ED395', 'user_id', "{$default_prefix}users");
    addConstraint($conn, "{$default_prefix}user_cache_categories", 'FK_38F22377A76ED395', 'user_id', "{$default_prefix}users");
    addConstraint($conn, "{$default_prefix}user_cache_categories", 'FK_38F22377E6ADA943', 'cat_id', "{$default_prefix}categories");

    foreach ($tables as $table) {
        $query = sprintf('ALTER TABLE %s ENGINE=InnoDB', $table);
        $stmt = $conn->prepare($query);
        $stmt->execute();
    }
}

if (in_array($conn->getDriver()->getName(), ['pdo_pgsql', 'pdo_sqlite'])) {
    convertTypeToString($conn, "{$default_prefix}categories", 'status', 25, 'public');
    convertTypeToString($conn, "{$default_prefix}history", 'section', 255);
    convertTypeToString($conn, "{$default_prefix}history", 'image_type', 255);
    convertTypeToString($conn, "{$default_prefix}plugins", 'state', 25, 'inactive');
    convertTypeToString($conn, "{$default_prefix}user_cache", 'image_access_type', 25, 'NOT IN');
    convertTypeToString($conn, "{$default_prefix}user_infos", 'status', 50, 'guest');
}

// if (in_array($conn->getDriver()->getName(), ['pdo_mysql', 'pdo_pgsql', 'pdo_sqlite'])) {
//     (new App\Repository\UserCacheRepository($conn))->deleteUserCache();
// }

echo "\n" . $upgrade_description . "\n";
