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

/**
 * Retrieves an url for a plugin page.
 * @param string file - php script full name
 */
function get_admin_plugin_menu_link($file)
{
    global $page;
    $real_file = realpath($file);
    $url = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=plugin';
    if (false !== $real_file) {
        $real_plugin_path = rtrim(realpath(PHPWG_PLUGINS_PATH), '\\/');
        $file = substr($real_file, strlen($real_plugin_path) + 1);
        $file = str_replace('\\', '/', $file);//Windows
        $url .= '&amp;section=' . urlencode($file);
    } elseif (isset($page['errors'])) {
        $page['errors'][] = 'PLUGIN ERROR: "' . $file . '" is not a valid file';
    }

    return $url;
}
