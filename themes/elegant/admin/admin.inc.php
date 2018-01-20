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

// Need upgrade?
global $conf;
include(__DIR__. '/upgrade.inc.php');

load_language('theme.lang', PHPWG_THEMES_PATH.'elegant/');

$config = [
    'p_main_menu' => 'on', //on - off - disabled
    'p_pict_descr' => 'on', //on - off - disabled
    'p_pict_comment' => 'off', //on - off - disabled
];

if (isset($_POST['submit_elegant'])) {
    if (!empty($_POST['p_main_menu'])) {
        $config['p_main_menu'] = $_POST['p_main_menu'];
    }
    if (!empty($_POST['p_pict_descr'])) {
        $config['p_pict_descr'] = $_POST['p_pict_descr'];
    }
    if (!empty($_POST['p_pict_comment'])) {
        $config['p_pict_comment'] = $_POST['p_pict_comment'];
    }

    conf_update_param('elegant', $config, true);

    $page['infos'][] = l10n('Information data registered in database');
}

$template->set_filenames(array('theme_admin_content' => __DIR__ . '/admin.tpl'));

$template->assign('options', json_decode($conf['elegant'], true));

$template->assign_var_from_handle('ADMIN_CONTENT', 'theme_admin_content');
