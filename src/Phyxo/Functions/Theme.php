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
     * returns available themes
     *
     * @param bool $show_mobile
     * @return array
     */
    public static function get_themes($show_mobile = false)
    {
        global $conf, $conn;

        $themes = array();
        $query = 'SELECT id, name FROM ' . THEMES_TABLE . ' ORDER BY name ASC;';
        $result = $conn->db_query($query);
        while ($row = $conn->db_fetch_assoc($result)) {
            if (self::check_theme_installed($row['id'])) {
                $themes[$row['id']] = $row['name'];
            }
        }

        // plugins want remove some themes based on user status maybe?
        $themes = \Phyxo\Functions\Plugin::trigger_change('get_themes', $themes);

        return $themes;
    }

    /**
     * check if a theme is installed (directory exsists)
     *
     * @param string $theme_id
     * @return bool
     */
    public static function check_theme_installed($theme_id)
    {
        global $conf;

        return file_exists($conf['themes_dir'] . '/' . $theme_id . '/' . 'themeconf.inc.php');
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
