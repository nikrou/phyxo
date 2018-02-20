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
    die("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

$sections = explode('/', $_GET['section'] );
for ($i=0; $i<count($sections); $i++) {
    if (empty($sections[$i]) or $sections[$i]=='..') {
        unset($sections[$i]);
        $i--;
    }
}

if (count($sections)<2) {
    die('Invalid plugin URL');
}

$plugin_id = $sections[0];
if (!isset($pwg_loaded_plugins[$plugin_id])) {
    die('Invalid URL - plugin '.$plugin_id.' not active');
}

$filename = PHPWG_PLUGINS_PATH.'/'.implode('/', $sections);
if (is_readable($filename)) {
    include_once($filename);
} else {
    die('Missing file '.$filename);
}
