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

if (!defined('TAGS_BASE_URL')) {
    die('Hacking attempt!');
}

$status_options[null] = '----------';
foreach ($conn->get_enums(\App\Repository\BaseRepository::USER_INFOS_TABLE, 'status') as $status) {
    $status_options[$status] = \Phyxo\Functions\Language::l10n('user_status_' . $status);
}

$Permissions = [];
$Permissions['add'] = $conf['tags_permission_add'];
$Permissions['delete'] = $conf['tags_permission_delete'];
$Permissions['existing_tags_only'] = $conf['tags_existing_tags_only'];
$Permissions['publish_tags_immediately'] = $conf['publish_tags_immediately'];
$Permissions['delete_tags_immediately'] = $conf['delete_tags_immediately'];
$Permissions['show_pending_added_tags'] = $conf['show_pending_added_tags'];
$Permissions['show_pending_deleted_tags'] = $conf['show_pending_deleted_tags'];


if (!empty($_POST['submit'])) {
    if (isset($_POST['permission_add'], $status_options[$_POST['permission_add']])) {
        $Permissions['add'] = $_POST['permission_add'];
        $conf['tags_permission_add'] = $Permissions['add'];
    }

    $Permissions['existing_tags_only'] = empty($_POST['existing_tags_only']) ? 0 : 1;
    $conf['tags_existing_tags_only'] = $Permissions['existing_tags_only'];

    if (isset($_POST['permission_delete'], $status_options[$_POST['permission_delete']])) {
        $Permissions['delete'] = $_POST['permission_delete'];
        $conf['tags_permission_delete'] = $_POST['permission_delete'];
    }

    $Permissions['publish_tags_immediately'] = empty($_POST['publish_tags_immediately']) ? 1 : 0;
    $conf['publish_tags_immediately'] = $Permissions['publish_tags_immediately'];

    $Permissions['delete_tags_immediately'] = empty($_POST['delete_tags_immediately']) ? 1 : 0;
    $conf['delete_tags_immediately'] = $Permissions['delete_tags_immediately'];

    $Permissions['show_pending_added_tags'] = empty($_POST['show_pending_added_tags']) ? 0 : 1;
    $conf['show_pending_added_tags'] = $Permissions['show_pending_added_tags'];

    $Permissions['show_pending_deleted_tags'] = empty($_POST['show_pending_deleted_tags']) ? 0 : 1;
    $conf['show_pending_deleted_tags'] = $Permissions['show_pending_deleted_tags'];

    \Phyxo\Functions\Utils::invalidate_user_cache_nb_tags();

    $page['infos'][] = \Phyxo\Functions\Language::l10n('Settings have been updated');
}

$template->assign('PERMISSIONS', $Permissions);
$template->assign('STATUS_OPTIONS', $status_options);
