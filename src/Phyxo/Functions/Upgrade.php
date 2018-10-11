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

use App\Repository\UpgradeRepository;
use App\Repository\UserRepository;

class Upgrade
{
    public static function check_upgrade()
    {
        if (defined('PHPWG_IN_UPGRADE')) {
            return PHPWG_IN_UPGRADE;
        }

        return false;
    }

    // Deactivate all non-standard plugins
    public static function deactivate_non_standard_plugins()
    {
        global $page, $conn;

        $standard_plugins = [];

        $query = 'SELECT id FROM ' . PREFIX_TABLE . 'plugins WHERE state = \'active\'';
        $query .= ' AND id NOT ' . $conn->in($standard_plugins);

        $result = $conn->db_query($query);
        $plugins = [];
        while ($row = $conn->db_fetch_assoc($result)) {
            $plugins[] = $row['id'];
        }

        if (!empty($plugins)) {
            $query = 'UPDATE ' . PREFIX_TABLE . 'plugins SET state=\'inactive\'';
            $query .= ' WHERE id ' . $conn->in($plugins);
            $conn->db_query($query);

            $page['infos'][] = \Phyxo\Functions\Language::l10n('As a precaution, following plugins have been deactivated. You must check for plugins upgrade before reactiving them:') . '<p><i>' . implode(', ', $plugins) . '</i></p>';
        }
    }

    // Deactivate all non-standard themes
    public static function deactivate_non_standard_themes()
    {
        global $page, $conf, $conn;

        $standard_themes = ['elegant'];

        $query = 'SELECT id,name  FROM ' . PREFIX_TABLE . 'themes';
        $query .= ' WHERE id NOT ' . $conn->in($standard_themes);
        $result = $conn->db_query($query);
        $theme_ids = [];
        $theme_names = [];

        while ($row = $conn->db_fetch_assoc($result)) {
            $theme_ids[] = $row['id'];
            $theme_names[] = $row['name'];
        }

        if (!empty($theme_ids)) {
            $query = 'DELETE FROM ' . PREFIX_TABLE . 'themes WHERE id ' . $conn->in($theme_ids);
            $conn->db_query($query);

            $page['infos'][] = \Phyxo\Functions\Language::l10n('As a precaution, following themes have been deactivated. You must check for themes upgrade before reactiving them:') . '<p><i>' . implode(', ', $theme_names) . '</i></p>';

            // what is the default theme?
            $query = 'SELECT theme FROM ' . PREFIX_TABLE . 'user_infos';
            $query .= ' WHERE user_id = ' . $conn->db_real_escape_string($conf['default_user_id']);
            list($default_theme) = $conn->db_fetch_row($conn->db_query($query));

            // if the default theme has just been deactivated, let's set another core theme as default
            if (in_array($default_theme, $theme_ids)) {
                $query = 'UPDATE ' . PREFIX_TABLE . 'user_infos';
                $query .= ' SET theme = \'elegant\' WHERE user_id = ' . $conn->db_real_escape_string($conf['default_user_id']);
                $conn->db_query($query);
            }
        }
    }

    // Check access rights
    public static function check_upgrade_access_rights()
    {
        global $conf, $page, $current_release, $conn, $services;

        if (version_compare($current_release, '1.0', '>=') and isset($_COOKIE[session_name()])) {
            // Check if user is already connected as webmaster
            session_start();
            if (!empty($_SESSION['pwg_uid'])) {
                $query = 'SELECT status FROM ' . USER_INFOS_TABLE;
                $query .= ' WHERE user_id = ' . $conn->db_real_escape_string($_SESSION['pwg_uid']);
                $result = $conn->db_query($query);
                $row = $conn->db_fetch_assoc($result);
                if (isset($row['status']) and $row['status'] == 'webmaster') {
                    define('PHPWG_IN_UPGRADE', true);
                    return;
                }
            }
        }

        if (empty($_POST['username']) or empty($_POST['password'])) {
            return;
        }

        $username = $_POST['username'];
        $password = $_POST['password'];

        $result = (new UserRepository($conn))->getUserInfosByUsername($username);
        $row = $conn->db_fetch_assoc($result);
        if (!$services['users']->passwordVerify($password, $row['password'])) {
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Invalid password!');
        } elseif ($row['status'] != 'admin' and $row['status'] != 'webmaster') {
            $page['errors'][] = \Phyxo\Functions\Language::l10n('You do not have access rights to run upgrade');
        } else {
            define('PHPWG_IN_UPGRADE', true);
        }
    }

    /**
     * which upgrades are available ?
     *
     * @return array
     */
    public static function get_available_upgrade_ids()
    {
        $upgrades_path = PHPWG_ROOT_PATH . 'install/db';

        $available_upgrade_ids = [];

        if ($contents = opendir($upgrades_path)) {
            while (($node = readdir($contents)) !== false) {
                if (is_file($upgrades_path . '/' . $node) and preg_match('/^(.*?)-database\.php$/', $node, $match)) {
                    $available_upgrade_ids[] = $match[1];
                }
            }
        }
        natcasesort($available_upgrade_ids);

        return $available_upgrade_ids;
    }

    /**
     * returns true if there are available upgrade files
     */
    public static function check_upgrade_feed()
    {
        global $conn;

        // retrieve already applied upgrades
        $result = (new UpgradeRepository($conn))->findAll();
        $applied = $conn->result2array($result, null, 'id');

        // retrieve existing upgrades
        $existing = self::get_available_upgrade_ids();

        // which upgrades need to be applied?
        return (count(array_diff($existing, $applied)) > 0);
    }
}
