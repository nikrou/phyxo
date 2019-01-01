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

/*
Theme Name: Elegant
Version: 3.0.4
Description: Dark background, grayscale.
Theme URI: https://ext.phyxo.net/extension_view.php?eid=4
Author: Nicolas Roudaire
Author URI: https://www.phyxo.net

The theme is based on the original one for piwigo.
 */

$themeconf = [
    'name' => 'elegant',
    'parent' => 'legacy',
    'local_head' => 'local_head.tpl'
];

// Need upgrade?
global $conf;
include(__DIR__ . '/admin/upgrade.inc.php');

\Phyxo\Functions\Plugin::add_event_handler('init', 'set_config_values_elegant');
function set_config_values_elegant()
{
    global $conf, $template;

    if (is_array($conf['elegant'])) {
        $config = $conf['elegant'];
    } else {
        $config = json_decode($conf['elegant'], true);
    }
    $template->assign('elegant', $config);
}
