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

namespace Phyxo\Plugin;

use Phyxo\Plugin\DummyPluginMaintain;
use Phyxo\Extension\Extensions;

class Plugins extends Extensions
{
    private $fs_plugins = array(), $db_plugins = array(), $server_plugins = array();
    private $fs_plugins_retrieved = false, $db_plugins_retrieved = false, $server_plugins_retrieved = false;
    private $default_plugins = array();
    private static $plugins_root_path = PHPWG_PLUGINS_PATH;

    public function __construct(\Phyxo\DBLayer\DBLayer $conn, $plugins_root_path = PHPWG_PLUGINS_PATH)
    {
        self::$plugins_root_path = $plugins_root_path;
        $this->conn = $conn;
    }

    /**
     * Returns the maintain class of a plugin
     * or build a new class with the procedural methods
     * @param string $plugin_id
     */
    private static function build_maintain_class($plugin_id)
    {
        $file_to_include = self::$plugins_root_path . $plugin_id . '/maintain';
        $classname = $plugin_id . '_maintain';

        if (file_exists($file_to_include . '.class.php')) {
            include_once($file_to_include . '.class.php');
            return new $classname($plugin_id);
        }

        if (file_exists($file_to_include . '.inc.php')) {
            include_once($file_to_include . '.inc.php');

            if (class_exists($classname)) {
                return new $classname($plugin_id);
            }
        }

        return new DummyPluginMaintain($plugin_id);
    }

    /**
     * Perform requested actions
     * @param string - action
     * @param string - plugin id
     * @param array - errors
     */
    public function performAction($action, $plugin_id, $options = array())
    {
        if (!$this->db_plugins_retrieved) {
            $this->getDbPlugins();
        }

        if (!$this->fs_plugins_retrieved) {
            $this->getFsPlugins();
        }

        if (isset($this->db_plugins[$plugin_id])) {
            $crt_db_plugin = $this->db_plugins[$plugin_id];
        }

        if ($action != 'update') { // wait for files to be updated
            $plugin_maintain = self::build_maintain_class($plugin_id);
        }

        $errors = array();

        switch ($action) {
            case 'install':
                if (!empty($crt_db_plugin) or !isset($this->fs_plugins[$plugin_id])) {
                    break;
                }

                $plugin_maintain->install($this->fs_plugins[$plugin_id]['version'], $errors);

                if (empty($errors)) {
                    $query = 'INSERT INTO ' . PLUGINS_TABLE . ' (id,version)';
                    $query .= ' VALUES (\'' . $plugin_id . '\', \'' . $this->fs_plugins[$plugin_id]['version'] . '\');';
                    $this->conn->db_query($query);
                }
                break;

            case 'update':
                $previous_version = $this->fs_plugins[$plugin_id]['version'];
                try {
                    $this->extractPluginFiles('upgrade', $options['revision'], $plugin_id);

                    $this->getFsPlugin($plugin_id); // refresh plugins list
                    $new_version = $this->fs_plugins[$plugin_id]['version'];

                    $plugin_maintain = self::build_maintain_class($plugin_id);
                    $plugin_maintain->update($previous_version, $new_version, $errors);
                    if ($new_version != 'auto') {
                        $query = 'UPDATE ' . PLUGINS_TABLE . ' SET version=\'' . $new_version . '\'';
                        $query .= ' WHERE id=\'' . $plugin_id . '\'';
                        $this->conn->db_query($query);
                    }
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
                break;

            case 'activate':
                if (!isset($crt_db_plugin)) {
                    $errors = $this->performAction('install', $plugin_id);
                    list($crt_db_plugin) = $this->getDbPlugins(null, $plugin_id);
                    \Phyxo\Functions\Conf::load_conf_from_db();
                } elseif ($crt_db_plugin['state'] == 'active') {
                    break;
                }

                if (empty($errors)) {
                    $plugin_maintain->activate($crt_db_plugin['version'], $errors);
                }

                if (empty($errors)) {
                    $query = 'UPDATE ' . PLUGINS_TABLE . ' SET state=\'active\'';
                    $query .= ' WHERE id=\'' . $plugin_id . '\';';
                    $this->conn->db_query($query);
                }
                break;

            case 'deactivate':
                if (!isset($crt_db_plugin) or $crt_db_plugin['state'] != 'active') {
                    break;
                }

                $query = 'UPDATE ' . PLUGINS_TABLE . ' SET state=\'inactive\'';
                $query .= ' WHERE id=\'' . $plugin_id . '\';';
                $this->conn->db_query($query);

                $plugin_maintain->deactivate();
                break;

            case 'uninstall':
                if (!isset($crt_db_plugin)) {
                    break;
                }
                if ($crt_db_plugin['state'] == 'active') {
                    $this->performAction('deactivate', $plugin_id);
                }

                $query = 'DELETE FROM ' . PLUGINS_TABLE . ' WHERE id=\'' . $plugin_id . '\';';
                $this->conn->db_query($query);

                $plugin_maintain->uninstall();
                break;

            case 'restore':
                $this->performAction('uninstall', $plugin_id);
                unset($this->db_plugins[$plugin_id]);
                $errors = $this->performAction('activate', $plugin_id);
                break;

            case 'delete':
                if (!empty($crt_db_plugin)) {
                    $this->performAction('uninstall', $plugin_id);
                }
                if (!isset($this->fs_plugins[$plugin_id])) {
                    break;
                }

                \deltree(self::$plugins_root_path . $plugin_id, self::$plugins_root_path . 'trash');
                break;
        }

        return $errors;
    }

    public function getFsExtensions()
    {
        return $this->getFsPlugins();
    }

    /**
     * Get plugins defined in the plugin directory
     */
    public function getFsPlugins()
    {
        if (!$this->fs_plugins_retrieved) {
            foreach (glob(PHPWG_PLUGINS_PATH . '/*/main.inc.php') as $main_file) {
                $plugin_dir = basename(dirname($main_file));
                if (preg_match('`^[a-zA-Z0-9-_]+$`', $plugin_dir)) {
                    $this->getFsPlugin($plugin_dir);
                }
            }
            $this->fs_plugins_retrieved = true;
        }
        return $this->fs_plugins;
    }

    /**
     * Load metadata of a plugin in `fs_plugins` array
     * @param $plugin_id
     * @return false|array
     */
    public function getFsPlugin($plugin_id)
    {
        $path = self::$plugins_root_path . '/' . $plugin_id;
        $main_file = $path . '/main.inc.php';

        if (!is_dir($path) && !is_readable($main_file)) {
            return false;
        }

        $plugin = array(
            'name' => $plugin_id,
            'version' => '0',
            'uri' => '',
            'description' => '',
            'author' => '',
        );
        $plugin_data = file_get_contents($main_file, false, null, 0, 2048);

        if (preg_match("|Plugin Name:\\s*(.+)|", $plugin_data, $val)) {
            $plugin['name'] = trim($val[1]);
        }
        if (preg_match("|Version:\\s*([\\w.-]+)|", $plugin_data, $val)) {
            $plugin['version'] = trim($val[1]);
        }
        if (preg_match("|Plugin URI:\\s*(https?:\\/\\/.+)|", $plugin_data, $val)) {
            $plugin['uri'] = trim($val[1]);
        }
        if ($desc = \Phyxo\Functions\Language::load_language('description.txt', dirname($main_file) . '/', array('return' => true))) {
            $plugin['description'] = trim($desc);
        } elseif (preg_match("|Description:\\s*(.+)|", $plugin_data, $val)) {
            $plugin['description'] = trim($val[1]);
        }
        if (preg_match("|Author:\\s*(.+)|", $plugin_data, $val)) {
            $plugin['author'] = trim($val[1]);
        }
        if (preg_match("|Author URI:\\s*(https?:\\/\\/.+)|", $plugin_data, $val)) {
            $plugin['author uri'] = trim($val[1]);
        }
        if (!empty($plugin['uri']) and strpos($plugin['uri'], 'extension_view.php?eid=')) {
            list(, $extension) = explode('extension_view.php?eid=', $plugin['uri']);
            if (is_numeric($extension)) {
                $plugin['extension'] = $extension;
            }
        }
        $this->fs_plugins[$plugin_id] = $plugin;

        return $plugin;
    }

    /**
     * Returns an array of plugins defined in the database.
     *
     * @param string $state optional filter
     * @param string $id returns only data about given plugin
     * @return array
     */
    public function getDbPlugins($state = '', $id = '')
    {
        if (!$this->db_plugins_retrieved) {
            $query = 'SELECT id, state, version FROM ' . PLUGINS_TABLE;
            $clauses = array();

            if (!empty($state)) {
                $clauses[] = 'state=\'' . $state . '\'';
            }
            if (!empty($id)) {
                $clauses[] = 'id = \'' . $id . '\'';
            }
            if (count($clauses)) {
                $query .= ' WHERE ' . implode(' AND ', $clauses);
            }

            $this->db_plugins = $this->conn->query2array($query, 'id');
            $this->db_plugins_retrieved = true;
        }

        return $this->db_plugins;
    }

    /**
     * Sort fs_plugins
     */
    public function sortFsPlugins($order = 'name')
    {
        if (!$this->fs_plugins_retrieved) {
            $this->getFsPlugins();
        }
        switch ($order) {
            case 'name':
                uasort($this->fs_plugins, '\Phyxo\Functions\Utils::name_compare');
                break;
            case 'status':
                $this->sortPluginsByState();
                break;
            case 'author':
                uasort($this->fs_plugins, array($this, 'pluginAuthorCompare'));
                break;
            case 'id':
                uksort($this->fs_plugins, 'strcasecmp');
                break;
        }
    }

    // Retrieve PEM versions
    public function getVersionsToCheck($version = PHPWG_VERSION)
    {
        global $conf;

        $versions_to_check = array();
        $url = PEM_URL . '/api/get_version_list.php?category_id=' . $conf['pem_plugins_category'];
        try {
            $pem_versions = $this->getJsonFromServer($url);
            if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                $version = $pem_versions[0]['name'];
            }
            $branch = \Phyxo\Functions\Utils::get_branch_from_version($version);
            foreach ($pem_versions as $pem_version) {
                if (strpos($pem_version['name'], $branch) === 0) {
                    $versions_to_check[] = $pem_version['id'];
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return $versions_to_check;
    }

    /**
     * Retrieve PEM server datas to $server_plugins
     */
    public function getServerPlugins($new = false)
    {
        global $user, $conf;

        if (!$this->server_plugins_retrieved) {
            $versions_to_check = $this->getVersionsToCheck();
            if (empty($versions_to_check)) {
                return array();
            }

            // Plugins to check
            $plugins_to_check = array();
            foreach ($this->getFsPlugins() as $fs_plugin) {
                if (isset($fs_plugin['extension'])) {
                    $plugins_to_check[] = $fs_plugin['extension'];
                }
            }

            // Retrieve PEM plugins infos
            $url = PEM_URL . '/api/get_revision_list.php';
            $get_data = array(
                'category_id' => $conf['pem_plugins_category'],
                'format' => 'php',
                'last_revision_only' => 'true',
                'version' => implode(',', $versions_to_check),
                'lang' => substr($user['language'], 0, 2),
                'get_nb_downloads' => 'true',
            );

            if (!empty($plugins_to_check)) {
                if ($new) {
                    $get_data['extension_exclude'] = implode(',', $plugins_to_check);
                } else {
                    $get_data['extension_include'] = implode(',', $plugins_to_check);
                }
            }
            try {
                $pem_plugins = $this->getJsonFromServer($url, $get_data);

                if (!is_array($pem_plugins)) {
                    return array();
                }
                foreach ($pem_plugins as $plugin) {
                    $this->server_plugins[$plugin['extension_id']] = $plugin;
                }
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }

            $this->server_plugins_retrieved = true;
        }

        return $this->server_plugins;
    }

    public function getIncompatiblePlugins($actualize = false)
    {
        global $conf;

        if (isset($_SESSION['incompatible_plugins']) and !$actualize
            and $_SESSION['incompatible_plugins']['~~expire~~'] > time()) {
            return $_SESSION['incompatible_plugins'];
        }

        $_SESSION['incompatible_plugins'] = array('~~expire~~' => time() + 300);

        $versions_to_check = $this->getVersionsToCheck();
        if (empty($versions_to_check)) {
            return array();
        }


        // Plugins to check
        $plugins_to_check = array();
        foreach ($this->getFsPlugins() as $fs_plugin) {
            if (isset($fs_plugin['extension'])) {
                $plugins_to_check[] = $fs_plugin['extension'];
            }
        }

        // Retrieve PEM plugins infos
        $url = PEM_URL . '/api/get_revision_list.php';
        $get_data = array(
            'category_id' => $conf['pem_plugins_category'],
            'format' => 'php',
            'version' => implode(',', $versions_to_check),
            'extension_include' => implode(',', $plugins_to_check),
        );

        try {
            $pem_plugins = $this->getJsonFromServer($url, $get_data);

            if (!is_array($pem_plugins)) {
                return array();
            }

            $server_plugins = array();
            foreach ($pem_plugins as $plugin) {
                if (!isset($server_plugins[$plugin['extension_id']])) {
                    $server_plugins[$plugin['extension_id']] = array();
                }
                $server_plugins[$plugin['extension_id']][] = $plugin['revision_name'];
            }

            foreach ($this->getFsPlugins() as $plugin_id => $fs_plugin) {
                if (isset($fs_plugin['extension'])
                    and !in_array($plugin_id, $this->default_plugins)
                    and $fs_plugin['version'] != 'auto'
                    and (!isset($server_plugins[$fs_plugin['extension']])
                    or !in_array($fs_plugin['version'], $server_plugins[$fs_plugin['extension']]))) {
                    $_SESSION['incompatible_plugins'][$plugin_id] = $fs_plugin['version'];
                }
            }
            return $_SESSION['incompatible_plugins'];
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return array();
    }

    /**
     * Sort $server_plugins
     */
    public function sortServerPlugins($order = 'date')
    {
        switch ($order) {
            case 'date':
                krsort($this->server_plugins);
                break;
            case 'revision':
                usort($this->server_plugins, array($this, 'extensionRevisionCompare'));
                break;
            case 'name':
                uasort($this->server_plugins, array($this, 'extensionNameCompare'));
                break;
            case 'author':
                uasort($this->server_plugins, array($this, 'extensionAuthorCompare'));
                break;
            case 'downloads':
                usort($this->server_plugins, array($this, 'extensionDownloadsCompare'));
                break;
        }
    }

    /**
     * Extract plugin files from archive
     * @param string - install or upgrade
     *  @param string - archive URL
     * @param string - plugin id or extension id
     */
    public function extractPluginFiles($action, $revision, $dest, &$plugin_id = null)
    {
        $archive = tempnam(self::$plugins_root_path, 'zip');
        $get_data = array(
            'rid' => $revision,
            'origin' => 'piwigo_' . $action,
        );

        try {
            $this->download($get_data, $archive);
        } catch (\Exception $e) {
            throw new \Exception("Cannot download plugin file");
        }

        $extract_path = self::$plugins_root_path;
        try {
            $this->extractZipFiles($archive, 'main.inc.php', $extract_path);
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
    protected function extensionRevisionCompare($a, $b)
    {
        if ($a['revision_date'] < $b['revision_date']) {
            return 1;
        } else {
            return -1;
        }
    }

    protected function extensionNameCompare($a, $b)
    {
        return strcmp(strtolower($a['extension_name']), strtolower($b['extension_name']));
    }

    protected function extensionAuthorCompare($a, $b)
    {
        $r = strcasecmp($a['author_name'], $b['author_name']);
        if ($r == 0) {
            return $this->extensionNameCompare($a, $b);
        } else {
            return $r;
        }
    }

    protected function pluginAuthorCompare($a, $b)
    {
        $r = strcasecmp($a['author'], $b['author']);
        if ($r == 0) {
            return \Phyxo\Functions\Utils::name_compare($a, $b);
        } else {
            return $r;
        }
    }

    protected function extensionDownloadsCompare($a, $b)
    {
        if ($a['extension_nb_downloads'] < $b['extension_nb_downloads']) {
            return 1;
        } else {
            return -1;
        }
    }

    public function sortPluginsByState()
    {
        if (!$this->fs_plugins_retrieved) {
            $this->getFsPlugins();
        }

        uasort($this->fs_plugins, '\Phyxo\Functions\Utils::name_compare');

        $active_plugins = array();
        $inactive_plugins = array();
        $not_installed = array();

        foreach ($this->fs_plugins as $plugin_id => $plugin) {
            if (isset($this->db_plugins[$plugin_id])) {
                $this->db_plugins[$plugin_id]['state'] == 'active' ?
                    $active_plugins[$plugin_id] = $plugin : $inactive_plugins[$plugin_id] = $plugin;
            } else {
                $not_installed[$plugin_id] = $plugin;
            }
        }
        $this->fs_plugins = $active_plugins + $inactive_plugins + $not_installed;
    }
}