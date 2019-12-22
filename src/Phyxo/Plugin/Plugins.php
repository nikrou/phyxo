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

use App\DataMapper\UserMapper;
use Phyxo\Plugin\DummyPluginMaintain;
use Phyxo\Extension\Extensions;
use App\Repository\PluginRepository;
use Phyxo\DBLayer\iDBLayer;
use Symfony\Component\Filesystem\Filesystem;

class Plugins extends Extensions
{
    private $fs_plugins = [], $db_plugins = [], $server_plugins = [];
    private $fs_plugins_retrieved = false, $db_plugins_retrieved = false, $server_plugins_retrieved = false;
    private $default_plugins = [];
    private $plugins_root_path, $userMapper;
    private $conn;

    public function __construct(iDBLayer $conn, UserMapper $userMapper)
    {
        $this->conn = $conn;
        $this->userMapper = $userMapper;
    }

    public function setRootPath(string $plugins_root_path)
    {
        $this->plugins_root_path = $plugins_root_path;
    }

    /**
     * Returns the maintain class of a plugin
     * or build a new class with the procedural methods
     * @param string $plugin_id
     */
    private function buildMaintainClass($plugin_id)
    {
        $file_to_include = $this->plugins_root_path . '/' . $plugin_id . '/maintain';
        $classname = $plugin_id . '_maintain';

        if (is_readable($file_to_include . '.class.php')) {
            include_once($file_to_include . '.class.php');
            return new $classname($plugin_id);
        }

        if (is_readable($file_to_include . '.inc.php')) {
            include_once($file_to_include . '.inc.php');

            if (class_exists($classname)) {
                return new $classname($plugin_id);
            }
        }

        return new DummyPluginMaintain($plugin_id);
    }

    /**
     * Perform requested actions
     */
    public function performAction(string $action, string $plugin_id, int $revision = null):  string
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

        if ($action !== 'update') { // wait for files to be updated
            $plugin_maintain = $this->buildMaintainClass($plugin_id);
        }

        $error = '';

        switch ($action) {
            case 'install':
                if (!empty($crt_db_plugin) || !isset($this->fs_plugins[$plugin_id])) {
                    break;
                }

                $plugin_maintain->install($this->fs_plugins[$plugin_id]['version'], $error);

                if (empty($error)) {
                    (new PluginRepository($this->conn))->addPlugin($plugin_id, $this->fs_plugins[$plugin_id]['version']);
                }
                break;

            case 'update':
                $previous_version = $this->fs_plugins[$plugin_id]['version'];
                try {
                    $this->extractPluginFiles('upgrade', $revision);

                    $this->getFsPlugin($plugin_id); // refresh plugins list
                    $new_version = $this->fs_plugins[$plugin_id]['version'];

                    $plugin_maintain = $this->buildMaintainClass($plugin_id);
                    $plugin_maintain->update($previous_version, $new_version, $error);
                    if ($new_version !== 'auto') {
                        (new PluginRepository($this->conn))->updatePlugin(['version' => $new_version], ['id' => $plugin_id]);
                    }
                } catch (\Exception $e) {
                    $error = $e->getMessage();
                }
                break;

            case 'activate':
                if (!isset($crt_db_plugin)) {
                    $error = $this->performAction('install', $plugin_id);
                    $this->getDbPlugins(null, $plugin_id);
                } elseif ($crt_db_plugin['state'] === 'active') {
                    break;
                }

                if (empty($errors)) {
                    (new PluginRepository($this->conn))->updatePlugin(['state' => 'active'], ['id' => $plugin_id]);
                }
                break;

            case 'deactivate':
                if (!isset($crt_db_plugin) || $crt_db_plugin['state'] !== 'active') {
                    break;
                }
                (new PluginRepository($this->conn))->updatePlugin(['state' => 'inactive'], ['id' => $plugin_id]);
                $plugin_maintain->deactivate();
                break;

            case 'uninstall':
                if (!isset($crt_db_plugin)) {
                    break;
                }
                if ($crt_db_plugin['state'] === 'active') {
                    $this->performAction('deactivate', $plugin_id);
                }
                (new PluginRepository($this->conn))->deletePlugin($plugin_id);
                $plugin_maintain->uninstall();
                break;

            case 'restore':
                $this->performAction('uninstall', $plugin_id);
                unset($this->db_plugins[$plugin_id]);
                $error = $this->performAction('activate', $plugin_id);
                break;

            case 'delete':
                if (!empty($crt_db_plugin)) {
                    $error = $this->performAction('uninstall', $plugin_id);
                }

                $fs = new Filesystem();
                $fs->remove([$this->plugins_root_path . '/' . $plugin_id, $this->plugins_root_path . '/trash']);
                break;
        }

        return $error;
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
            foreach (glob($this->plugins_root_path . '/*/main.inc.php') as $main_file) {
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
        $path = $this->plugins_root_path . '/' . $plugin_id;
        $main_file = $path . '/main.inc.php';

        if (!is_dir($path) && !is_readable($main_file)) {
            return false;
        }

        $plugin = [
            'name' => $plugin_id,
            'version' => '0',
            'uri' => '',
            'description' => '',
            'author' => '',
        ];
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
        if ($desc = \Phyxo\Functions\Language::loadLanguageFile('description.' . $this->userMapper->getUser()->getLanguage() . '.txt', dirname($main_file))) {
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

        if (!empty($plugin['uri']) && ($pos = strpos($plugin['uri'], 'extension_view.php?eid=')) !== false) {
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
    public function getDbPlugins($state = null, $id = '')
    {
        if (!$this->db_plugins_retrieved) {
            $result = (new PluginRepository($this->conn))->findAll($state);
            $this->db_plugins = $this->conn->result2array($result, 'id');
            $this->db_plugins_retrieved = true;
        }

        if (!empty($id)) {
            return isset($this->db_plugins[$id]) ? $this->db_plugins[$id] : [];
        } else {
            return $this->db_plugins;
        }
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
                uasort($this->fs_plugins, [$this, 'pluginAuthorCompare']);
                break;
            case 'id':
                uksort($this->fs_plugins, 'strcasecmp');
                break;
        }
    }

    // Retrieve PEM versions
    public function getVersionsToCheck(string $pem_category, string $version)
    {
        $versions_to_check = [];
        $url = $this->pem_url . '/api/get_version_list.php?category_id=' . $pem_category;
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
    public function getServerPlugins($new = false, string $pem_category, string $core_version)
    {
        if (!$this->server_plugins_retrieved) {
            $versions_to_check = $this->getVersionsToCheck($core_version, $pem_category);
            if (empty($versions_to_check)) {
                return [];
            }

            // Plugins to check
            $plugins_to_check = [];
            foreach ($this->getFsPlugins() as $fs_plugin) {
                if (isset($fs_plugin['extension'])) {
                    $plugins_to_check[] = $fs_plugin['extension'];
                }
            }

            // Retrieve PEM plugins infos
            $url = $this->pem_url . '/api/get_revision_list.php';
            $get_data = [
                'category_id' => $pem_category,
                'last_revision_only' => 'true',
                'version' => implode(',', $versions_to_check),
                'lang' => substr($this->userMapper->getUser()->getLanguage(), 0, 2),
                'get_nb_downloads' => 'true',
            ];

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
                    return [];
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

    public function getIncompatiblePlugins(string $pem_category, string $core_version)
    {
        $versions_to_check = $this->getVersionsToCheck($pem_category, $core_version);
        if (empty($versions_to_check)) {
            return [];
        }

        // Plugins to check
        $plugins_to_check = [];
        foreach ($this->getFsPlugins() as $fs_plugin) {
            if (isset($fs_plugin['extension'])) {
                $plugins_to_check[] = $fs_plugin['extension'];
            }
        }

        // Retrieve PEM plugins infos
        $url = $this->pem_url . '/api/get_revision_list.php';
        $get_data = [
            'category_id' => $pem_category,
            'version' => implode(',', $versions_to_check),
            'extension_include' => implode(',', $plugins_to_check),
        ];

        try {
            $pem_plugins = $this->getJsonFromServer($url, $get_data);

            if (!is_array($pem_plugins)) {
                return [];
            }

            $server_plugins = [];
            foreach ($pem_plugins as $plugin) {
                if (!isset($server_plugins[$plugin['extension_id']])) {
                    $server_plugins[$plugin['extension_id']] = [];
                }
                $server_plugins[$plugin['extension_id']][] = $plugin['revision_name'];
            }

            $incompatible_plugins = [];
            foreach ($this->getFsPlugins() as $plugin_id => $fs_plugin) {
                if (isset($fs_plugin['extension']) && !in_array($plugin_id, $this->default_plugins)
                    && $fs_plugin['version'] != 'auto' && (!isset($server_plugins[$fs_plugin['extension']])
                    || !in_array($fs_plugin['version'], $server_plugins[$fs_plugin['extension']]))) {
                    $incompatible_plugins[$plugin_id] = $fs_plugin['version'];
                }
            }

            return $incompatible_plugins;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return [];
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
                usort($this->server_plugins, [$this, 'extensionRevisionCompare']);
                break;
            case 'name':
                uasort($this->server_plugins, [$this, 'extensionNameCompare']);
                break;
            case 'author':
                uasort($this->server_plugins, [$this, 'extensionAuthorCompare']);
                break;
            case 'downloads':
                usort($this->server_plugins, [$this, 'extensionDownloadsCompare']);
                break;
        }
    }

    /**
     * Extract plugin files from archive
     */
    public function extractPluginFiles(string $action, int $revision)
    {
        $archive = tempnam($this->plugins_root_path, 'zip');
        $get_data = [
            'rid' => $revision,
            'origin' => 'piwigo_' . $action,
        ];

        try {
            $this->download($get_data, $archive);
        } catch (\Exception $e) {
            throw new \Exception("Cannot download plugin file");
        }

        $extract_path = $this->plugins_root_path;
        try {
            $this->extractZipFiles($archive, 'main.inc.php', $extract_path);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        } finally {
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

        $active_plugins = [];
        $inactive_plugins = [];
        $not_installed = [];

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
