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

use App\Repository\UpgradeRepository;

if (!defined('PHPWG_ROOT_PATH')) {
    die('This page cannot be loaded directly, load upgrade.php');
} else {
    if (!defined('PHPWG_IN_UPGRADE') or !PHPWG_IN_UPGRADE) {
        die('Hacking attempt!');
    }
}

$release_from = '1.8.0';

$filesToRemove = [
    'about.php', 'action.php', 'comments.php', 'feed.php', 'identification.php',
    'install.php', 'notification.php', 'password.php', 'picture.php', 'popuphelp.php',
    'profile.php', 'qsearch.php', 'random.php', 'register.php', 'search.php',
    'search_rules.php', 'tags.php', 'upgrade.php', 'upgrade_feed.php'
];
foreach ($filesToRemove as $file_to_remove) {
    unlink($file_to_remove);
}
