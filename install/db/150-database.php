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

if (in_array($conn->getLayer(), ['mysql', 'pgsql', 'sqlite'])) {
    $query = 'ALTER TABLE ' . App\Repository\BaseRepository::CONFIG_TABLE;
    $query .= ' ADD COLUMN type VARCHAR(15) DEFAULT "string"';
    $conn->db_query($query);
}

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

if ($conn->getLayer() === 'mysql') {
} elseif ($conn->getLayer() === 'pgsql') {
} elseif ($conn->getLayer() === 'sqlite') {
}

echo "\n" . $upgrade_description . "\n";
