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

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

global $prefixeTable, $conf;

$default_config = [
    'p_main_menu' => 'on', //on - off - disabled
    'p_pict_descr' => 'on', //on - off - disabled
    'p_pict_comment' => 'off', //on - off - disabled
];

if (!isset($conf['elegant'])) {
    $conf['elegant'] = $default_config;
} else {
    $config = json_decode($conf['elegant'], true);
    if (count($config) != 3) {
        $new_config = array_merge($default_config, $config);

        $conf['elegant'] = $new_config;
    }
}
