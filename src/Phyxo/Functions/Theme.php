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

namespace Phyxo\Functions;

class Theme
{
    /**
     * check if a theme is installed (directory exsists)
     *
     * @param string $theme_id
     * @return bool
     */
    public static function check_theme_installed($theme_id)
    {
        global $conf;

        return file_exists(PHPWG_THEMES_PATH . '/' . $theme_id . '/' . 'themeconf.inc.php');
    }

    /**
     * returns the corresponding value from $themeconf if existing or an empty string
     *
     * @param string $key
     * @return string
     */
    public static function get_themeconf($key)
    {
        global $template;

        return $template->get_themeconf($key);
    }
}
