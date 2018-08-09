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

if (!defined("PHPWG_ROOT_PATH")) {
    die('Hacking attempt!');
}

use Phyxo\Model\Repository\Tags;
use Phyxo\Model\Repository\Comments;
use Phyxo\Model\Repository\Users;

$services = array();
$services['tags'] = new Tags($conn, 'Phyxo\Model\Entity\Tag', TAGS_TABLE);
$services['comments'] = new Comments($conn, 'Phyxo\Model\Entity\Comment', COMMENTS_TABLE);
$services['users'] = new Users($conn, 'Phyxo\Model\Entity\User', USERS_TABLE);

// @TODO : find a better place
\Phyxo\Functions\Plugin::add_event_handler('user_comment_check', array($services['comments'], 'userCommentCheck'));

// temporary hack for password_*
function pwg_password_verify($password, $hash, $user_id = null)
{
    global $services;

    return $services['users']->passwordVerify($password, $hash, $user_id);
}

function pwg_password_hash($password)
{
    global $services;

    return $services['users']->passwordHash($password);
}
