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

namespace Phyxo\Theme;

use Phyxo\Extension\Extensions;
use Phyxo\Theme\DummyThemeMaintain;

class Themes extends Extensions
{
    private $fs_themes = array(), $db_themes = array(), $server_themes = array();
    private $fs_themes_retrieved = false, $db_themes_retrieved = false, $server_themes_retrieved = false;

    public function __construct(\Phyxo\DBLayer\DBLayer $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Returns the maintain class of a theme
     * or build a new class with the procedural methods
     * @param string $theme_id
     */
    private static function build_maintain_class($theme_id)
    {
        $file_to_include = PHPWG_THEMES_PATH . '/' . $theme_id . '/admin/maintain.inc.php';
        $classname = $theme_id . '_maintain';

        if (file_exists($file_to_include)) {
            include_once($file_to_include);

            if (class_exists($classname)) {
                return new $classname($theme_id);
            }
        }

        return new DummyThemeMaintain($theme_id);
    }

    /**
     * Perform requested actions
     * @param string - action
     * @param string - theme id
     * @param array - errors
     */
    public function performAction($action, $theme_id)
    {
        global $conf, $services;

        if (!$this->db_themes_retrieved) {
            $this->getDbThemes();
        }

        if (!$this->fs_themes_retrieved) {
            $this->getFsThemes();
        }

        if (isset($this->db_themes[$theme_id])) {
            $crt_db_theme = $this->db_themes[$theme_id];
        }

        $theme_maintain = self::build_maintain_class($theme_id);

        $errors = array();

        switch ($action) {
            case 'activate':
                if (isset($crt_db_theme)) {
                // the theme is already active
                    break;
                }

                if ('default' == $theme_id) {
                // you can't activate the "default" theme
                    break;
                }

                $missing_parent = $this->missingParentTheme($theme_id);
                if (isset($missing_parent)) {
                    $errors[] = \Phyxo\Functions\Language::l10n(
                        'Impossible to activate this theme, the parent theme is missing: %s',
                        $missing_parent
                    );

                    break;
                }

                if ($this->fs_themes[$theme_id]['mobile'] and !empty($conf['mobile_theme']) and $conf['mobile_theme'] != $theme_id) {
                    $errors[] = \Phyxo\Functions\Language::l10n('You can activate only one mobile theme.');
                    break;
                }

                $theme_maintain->activate($this->fs_themes[$theme_id]['version'], $errors);

                if (empty($errors)) {
                    $query = 'INSERT INTO ' . THEMES_TABLE;
                    $query .= ' (id, version, name) VALUES(\'' . $theme_id . '\',';
                    $query .= ' \'' . $this->fs_themes[$theme_id]['version'] . '\',';
                    $query .= ' \'' . $this->fs_themes[$theme_id]['name'] . '\');';
                    $this->conn->db_query($query);

                    if ($this->fs_themes[$theme_id]['mobile']) {
                        \conf_update_param('mobile_theme', $theme_id);
                    }
                }
                break;

            case 'deactivate':
                if (!isset($crt_db_theme)) {
                // the theme is already inactive
                    break;
                }

            // you can't deactivate the last theme
                if (count($this->db_themes) <= 1) {
                    $errors[] = \Phyxo\Functions\Language::l10n('Impossible to deactivate this theme, you need at least one theme.');
                    break;
                }

                if ($theme_id == $services['users']->getDefaultTheme()) {
                // find a random theme to replace
                    $new_theme = null;

                    $query = 'SELECT id FROM ' . THEMES_TABLE;
                    $query .= ' WHERE id != \'' . $theme_id . '\';';
                    $result = $this->conn->db_query($query);
                    if ($this->conn->db_num_rows($result) == 0) {
                        $new_theme = 'default';
                    } else {
                        list($new_theme) = $this->conn->db_fetch_row($result);
                    }

                    $this->setDefaultTheme($new_theme);
                }

                $theme_maintain->deactivate();

                $query = 'DELETE FROM ' . THEMES_TABLE . ' WHERE id= \'' . $theme_id . '\';';
                $this->conn->db_query($query);

                if ($this->fs_themes[$theme_id]['mobile']) {
                    \conf_update_param('mobile_theme', '');
                }
                break;

            case 'delete':
                if (!empty($crt_db_theme)) {
                    $errors[] = 'CANNOT DELETE - THEME IS INSTALLED';
                    break;
                }
                if (!isset($this->fs_themes[$theme_id])) {
                // nothing to do here
                    break;
                }

                $children = $this->getChildrenThemes($theme_id);
                if (count($children) > 0) {
                    $errors[] = \Phyxo\Functions\Language::l10n(
                        'Impossible to delete this theme. Other themes depends on it: %s',
                        implode(', ', $children)
                    );
                    break;
                }

                $theme_maintain->delete();

                \deltree(PHPWG_THEMES_PATH . $theme_id, PHPWG_THEMES_PATH . 'trash');
                break;

            case 'set_default':
            // first we need to know which users are using the current default theme
                $this->setDefaultTheme($theme_id);
                break;
        }
        return $errors;
    }

    public function missingParentTheme($theme_id)
    {
        $this->getFsThemes();

        if (!isset($this->fs_themes[$theme_id]['parent'])) {
            return null;
        }

        $parent = $this->fs_themes[$theme_id]['parent'];

        if ('default' == $parent) {
            return null;
        }

        if (!isset($this->fs_themes[$parent])) {
            return $parent;
        }

        return $this->missingParentTheme($parent);
    }

    public function getChildrenThemes($theme_id)
    {
        $children = array();

        foreach ($this->getFsThemes() as $test_child) {
            if (isset($test_child['parent']) and $test_child['parent'] == $theme_id) {
                $children[] = $test_child['name'];
            }
        }

        return $children;
    }

    public function setDefaultTheme($theme_id)
    {
        global $conf, $services;

        // first we need to know which users are using the current default theme
        $default_theme = $services['users']->getDefaultTheme();

        $query = 'SELECT user_id FROM ' . USER_INFOS_TABLE;
        $query .= ' WHERE theme = \'' . $default_theme . '\';';
        $user_ids = array_unique(
            array_merge(
                $this->conn->query2array($query, null, 'user_id'),
                array($conf['guest_id'], $conf['default_user_id'])
            )
        );

        // $user_ids can't be empty, at least the default user has the default
        // theme

        $query = 'UPDATE ' . USER_INFOS_TABLE . ' SET theme = \'' . $theme_id . '\'';
        $query .= ' WHERE user_id ' . $this->conn->in($user_ids);
        $this->conn->db_query($query);
    }

    public function getDbThemes($id = '')
    {
        if (!$this->db_themes_retrieved) {
            $query = 'SELECT id, version, name FROM ' . THEMES_TABLE;

            $clauses = array();
            if (!empty($id)) {
                $clauses[] = 'id = \'' . $id . '\'';
            }
            if (count($clauses) > 0) {
                $query .= ' WHERE ' . implode(' AND ', $clauses);
            }

            $result = $this->conn->db_query($query);
            $this->db_themes = array();
            while ($row = $this->conn->db_fetch_assoc($result)) {
                $this->db_themes[$row['id']] = $row;
            }
            $this->db_themes_retrieved = true;
        }
        return $this->db_themes;
    }

    // for Update/Updates
    public function getFsExtensions()
    {
        return $this->getFsThemes();
    }

    /**
     *  Get themes defined in the theme directory
     */
    public function getFsThemes()
    {
        global $conf;

        if (!$this->fs_themes_retrieved) {
            foreach (glob(PHPWG_THEMES_PATH . '*/themeconf.inc.php') as $themeconf) {
                $theme_dir = basename(dirname($themeconf));
                if (!preg_match('`^[a-zA-Z0-9-_]+$`', $theme_dir)) {
                    continue;
                }

                $theme = array(
                    'id' => $theme_dir,
                    'name' => $theme_dir,
                    'version' => '0',
                    'uri' => '',
                    'description' => '',
                    'author' => '',
                    'mobile' => false,
                );
                $theme_data = implode('', file($themeconf));

                if (preg_match("|Theme Name:\\s*(.+)|", $theme_data, $val)) {
                    $theme['name'] = trim($val[1]);
                }
                if (preg_match("|Version:\\s*([\\w.-]+)|", $theme_data, $val)) {
                    $theme['version'] = trim($val[1]);
                }
                if (preg_match("|Theme URI:\\s*(https?:\\/\\/.+)|", $theme_data, $val)) {
                    $theme['uri'] = trim($val[1]);
                }
                if ($desc = \Phyxo\Functions\Language::load_language('description.txt', dirname($themeconf) . '/', array('return' => true))) {
                    $theme['description'] = trim($desc);
                } elseif (preg_match("|Description:\\s*(.+)|", $theme_data, $val)) {
                    $theme['description'] = trim($val[1]);
                }
                if (preg_match("|Author:\\s*(.+)|", $theme_data, $val)) {
                    $theme['author'] = trim($val[1]);
                }
                if (preg_match("|Author URI:\\s*(https?:\\/\\/.+)|", $theme_data, $val)) {
                    $theme['author uri'] = trim($val[1]);
                }
                if (!empty($theme['uri']) and strpos($theme['uri'], 'extension_view.php?eid=')) {
                    list(, $extension) = explode('extension_view.php?eid=', $theme['uri']);
                    if (is_numeric($extension)) $theme['extension'] = $extension;
                }
                if (preg_match('/["\']parent["\'][^"\']+["\']([^"\']+)["\']/', $theme_data, $val)) {
                    $theme['parent'] = $val[1];
                }
                if (preg_match('/["\']activable["\'].*?(true|false)/i', $theme_data, $val)) {
                    $theme['activable'] = $this->conn->get_boolean($val[1]);
                }
                if (preg_match('/["\']mobile["\'].*?(true|false)/i', $theme_data, $val)) {
                    $theme['mobile'] = $this->conn->get_boolean($val[1]);
                }

                // screenshot
                $screenshot_path = dirname($themeconf) . '/screenshot.png';
                if (file_exists($screenshot_path)) {
                    $theme['screenshot'] = $screenshot_path;
                } else {
                    $theme['screenshot'] = \Phyxo\Functions\URL::get_root_url() . 'admin/theme/images/missing_screenshot.png';
                }

                $admin_file = dirname($themeconf) . '/admin/admin.inc.php';
                if (file_exists($admin_file)) {
                    $theme['admin_uri'] = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=theme&theme=' . $theme_dir;
                }

                $this->fs_themes[$theme_dir] = $theme;
            }
            $this->fs_themes_retrieved = true;
        }

        return $this->fs_themes;
    }

    /**
     * Sort fs_themes
     */
    public function sortFsThemes($order = 'name')
    {
        if (!$this->fs_themes_retrieved) {
            $this->getFsThemes();
        }

        switch ($order) {
            case 'name':
                uasort($this->fs_themes, '\name_compare');
                break;
            case 'status':
                $this->sortThemesByState();
                break;
            case 'author':
                uasort($this->fs_themes, array($this, 'themeAuthorCompare'));
                break;
            case 'id':
                uksort($this->fs_themes, 'strcasecmp');
                break;
        }
    }

    /**
     * Retrieve PEM server datas to $server_themes
     */
    public function getServerThemes($new = false)
    {
        global $user, $conf;

        if (!$this->server_themes_retrieved) {
            $get_data = array(
                'category_id' => $conf['pem_themes_category'],
            );

            // Retrieve PEM versions
            $version = PHPWG_VERSION;
            $versions_to_check = array();
            $url = PEM_URL . '/api/get_version_list.php';

            try {
                $pem_versions = $this->getJsonFromServer($url, $get_data);
                if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                    $version = $pem_versions[0]['name'];
                }
                $branch = \get_branch_from_version($version);
                foreach ($pem_versions as $pem_version) {
                    if (strpos($pem_version['name'], $branch) === 0) {
                        $versions_to_check[] = $pem_version['id'];
                    }
                }
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }

            if (empty($versions_to_check)) {
                return array();
            }

            // Themes to check
            $themes_to_check = array();
            foreach ($this->getFsThemes() as $fs_theme) {
                if (isset($fs_theme['extension'])) {
                    $themes_to_check[] = $fs_theme['extension'];
                }
            }

            // Retrieve PEM themes infos
            $url = PEM_URL . '/api/get_revision_list.php';
            $get_data = array_merge(
                $get_data,
                array(
                    'last_revision_only' => 'true',
                    'version' => implode(',', $versions_to_check),
                    'lang' => substr($user['language'], 0, 2),
                    'get_nb_downloads' => 'true',
                )
            );

            if (!empty($themes_to_check)) {
                if ($new) {
                    $get_data['extension_exclude'] = implode(',', $themes_to_check);
                } else {
                    $get_data['extension_include'] = implode(',', $themes_to_check);
                }
            }
            try {
                $pem_themes = $this->getJsonFromServer($url, $get_data);
                if (!is_array($pem_themes)) {
                    return array();
                }

                foreach ($pem_themes as $theme) {
                    $this->server_themes[$theme['extension_id']] = $theme;
                }
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
            $this->server_themes_retrieved = true;
        }
        return $this->server_themes;
    }

    /**
     * Sort $server_themes
     */
    public function sortServerThemes($order = 'date')
    {
        switch ($order) {
            case 'date':
                krsort($this->server_themes);
                break;
            case 'revision':
                usort($this->server_themes, array($this, 'extensionRevisionCompare'));
                break;
            case 'name':
                uasort($this->server_themes, array($this, 'extensionNameCompare'));
                break;
            case 'author':
                uasort($this->server_themes, array($this, 'extensionAuthorCompare'));
                break;
            case 'downloads':
                usort($this->server_themes, array($this, 'extensionDownloadsCompare'));
                break;
        }
    }

    /**
     * Extract theme files from archive
     *
     * @param string - install or upgrade
     * @param string - remote revision identifier (numeric)
     * @param string - theme id or extension id
     */
    public function extractThemeFiles($action, $revision, $dest)
    {
        $archive = tempnam(PHPWG_THEMES_PATH, 'zip');
        $get_data = array(
            'rid' => $revision,
            'origin' => 'phyxo_' . $action
        );

        try {
            $this->download($get_data, $archive);
        } catch (\Exception $e) {
            throw new \Exception("Cannot download theme archive");
        }

        $extract_path = PHPWG_THEMES_PATH;
        try {
            $this->extractZipFiles($archive, 'themeconf.inc.php', $extract_path);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        finally {
            unlink($archive);
        }
    }

    /**
     * Sort functions
     */
    public function extensionRevisionCompare($a, $b)
    {
        if ($a['revision_date'] < $b['revision_date']) {
            return 1;
        } else {
            return -1;
        }
    }

    public function extensionNameCompare($a, $b)
    {
        return strcmp(strtolower($a['extension_name']), strtolower($b['extension_name']));
    }

    public function extensionAuthorCompare($a, $b)
    {
        $r = strcasecmp($a['author_name'], $b['author_name']);
        if ($r == 0) {
            return $this->extensionNameCompare($a, $b);
        } else {
            return $r;
        }
    }

    public function themeAuthorCompare($a, $b)
    {
        $r = strcasecmp($a['author'], $b['author']);
        if ($r == 0) {
            return \name_compare($a, $b);
        } else {
            return $r;
        }
    }

    public function extensionDownloadsCompare($a, $b)
    {
        if ($a['extension_nb_downloads'] < $b['extension_nb_downloads']) {
            return 1;
        } else {
            return -1;
        }
    }

    public function sortThemesByState()
    {
        uasort($this->fs_themes, '\name_compare');

        $active_themes = array();
        $inactive_themes = array();
        $not_installed = array();

        foreach ($this->fs_themes as $theme_id => $theme) {
            if (isset($this->db_themes[$theme_id])) {
                $this->db_themes[$theme_id]['state'] == 'active' ?
                    $active_themes[$theme_id] = $theme : $inactive_themes[$theme_id] = $theme;
            } else {
                $not_installed[$theme_id] = $theme;
            }
        }
        $this->fs_themes = $active_themes + $inactive_themes + $not_installed;
    }
}
