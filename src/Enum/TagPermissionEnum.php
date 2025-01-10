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

namespace App\Enum;

enum TagPermissionEnum: string
{
    case ADD = 'tags_permission_add';
    case DELETE = 'tags_permission_delete';
    case EXISTING_TAGS_ONLY = 'tags_existing_only';
    case PUBLISH_IMMEDIATELY = 'tags_publish_immediately';
    case DELETE_IMMEDIATELY = 'tags_delete_immediately';
    case SHOW_PENDING_ADDED = 'tags_show_pending_added';
    case SHOW_PENDING_DELETED = 'tags_show_pending_deleted';
}
