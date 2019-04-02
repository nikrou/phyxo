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

use Phyxo\Theme\Themes;

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

if (empty($_GET['theme'])) {
    die('Invalid theme URL'); // @TODO: handle error instead of simple die
}

$themes = new Themes($conn);
if (!in_array($_GET['theme'], array_keys($themes->getFsThemes()))) {
    die('Invalid theme'); // @TODO: handle error instead of simple die
}

$filename = PHPWG_THEMES_PATH . '/' . $_GET['theme'] . '/admin/admin.inc.php';
if (is_file($filename)) {
    include_once($filename);
} else {
    die('Missing file ' . $filename); // @TODO: handle error instead of simple die
}
