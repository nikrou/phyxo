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

if (!defined('PHOTOS_ADD_BASE_URL')) {
    die("Hacking attempt!");
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->assign(
    'FTP_HELP_CONTENT',
    \Phyxo\Functions\Language::load_language(
        'help/photos_add_ftp.html',
        '',
        array('return' => true)
    )
);
