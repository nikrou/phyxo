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
use App\Entity\Plugin;
use App\Enum\ExtensionStateType;
use App\Repository\PluginRepository;
use Exception;
use Override;
use Phyxo\Extension\Extensions;
use Phyxo\Functions\Utils;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class Plugins extends Extensions
{
    final public const string CONFIG_FILE = 'config.yaml';
    private array $fs_plugins = [];
    private $db_plugins = [];
    private array $server_plugins = [];
    private bool $fs_plugins_retrieved = false;
    private bool $db_plugins_retrieved = false;
    private bool $server_plugins_retrieved = false;
    private array $default_plugins = [];
    private ?string $plugins_root_path = null;

    public function __construct(private readonly PluginRepository $pluginRepository, private readonly UserMapper $userMapper)
    {
    }

    public function setRootPath(string $plugins_root_path): void
    {
        $this->plugins_root_path = $plugins_root_path;
    }

    /**
     * Returns the maintain class of a plugin
     * or build a new class with the procedural methods.
     */
    private function buildMaintainClass(string $plugin_id)
    {
        $file_to_include = $this->plugins_root_path . '/' . $plugin_id . '/maintain';
        $classname = $plugin_id . '_maintain';

        if (is_readable($file_to_include . '.class.php')) {
            include_once $file_to_include . '.class.php';

            return new $classname($plugin_id);
        }

        if (is_readable($file_to_include . '.inc.php')) {
            include_once $file_to_include . '.inc.php';

            if (class_exists($classname)) {
                return new $classname($plugin_id);
            }
        }

        return new DummyPluginMaintain($plugin_id);
    }

    /**
     * Perform requested actions.
     */
    public function performAction(string $action, string $plugin_id, ?int $revision = null): string
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

        $plugin_maintain = $this->buildMaintainClass($plugin_id);
        $error = '';

        switch ($action) {
            case 'install':
                if (!empty($crt_db_plugin) || !isset($this->fs_plugins[$plugin_id])) {
                    break;
                }

                $plugin_maintain->install($this->fs_plugins[$plugin_id]['version'], $error);
                $plugin = new Plugin();
                $plugin->setId($plugin_id);
                $plugin->setVersion($this->fs_plugins[$plugin_id]['version']);
                $this->pluginRepository->addPlugin($plugin);
                break;

            case 'update':
                $previous_version = $this->fs_plugins[$plugin_id]['version'];
                try {
                    $this->extractPluginFiles('upgrade', $revision);

                    $this->getFsPlugin($plugin_id); // refresh plugins list
                    $new_version = $this->fs_plugins[$plugin_id]['version'];
                    $plugin_maintain->update($previous_version, $new_version, $error);
                    $this->pluginRepository->updateVersion($plugin_id, $new_version);
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }

                break;

            case 'activate':
                if (!isset($crt_db_plugin)) {
                    $error = $this->performAction('install', $plugin_id);
                    $this->getDbPlugins(null, $plugin_id);
                } elseif ($crt_db_plugin->getState() === ExtensionStateType::ACTIVE) {
                    break;
                }

                if ($error === '') {
                    $this->pluginRepository->updateState($plugin_id, ExtensionStateType::ACTIVE);
                }

                break;

            case 'deactivate':
                if (!isset($crt_db_plugin) || $crt_db_plugin->getState() !== ExtensionStateType::ACTIVE) {
                    break;
                }

                $this->pluginRepository->updateState($plugin_id, ExtensionStateType::INACTIVE);
                $plugin_maintain->deactivate();
                break;

            case 'uninstall':
                if (!isset($crt_db_plugin)) {
                    break;
                }

                if ($crt_db_plugin->getState() === ExtensionStateType::ACTIVE) {
                    $this->performAction('deactivate', $plugin_id);
                }

                $this->pluginRepository->deleteById($plugin_id);
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

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getFsExtensions(): array
    {
        return $this->getFsPlugins();
    }

    /**
     * Get plugins defined in the plugin directory.
     *
     * @return array<string, mixed>
     */
    public function getFsPlugins(): array
    {
        if (!$this->fs_plugins_retrieved) {
            foreach (glob($this->plugins_root_path . '/*') as $plugin_dir) {
                if (!is_readable($plugin_dir . '/' . self::CONFIG_FILE)) {
                    continue;
                }

                $this->getFsPlugin(basename($plugin_dir));
            }

            $this->fs_plugins_retrieved = true;
        }

        return $this->fs_plugins;
    }

    /**
     * Load metadata of a plugin in `fs_plugins` array.
     */
    public function getFsPlugin(string $plugin_id): void
    {
        $path = $this->plugins_root_path . '/' . $plugin_id;
        $config_file = $path . '/' . self::CONFIG_FILE;

        $plugin = [
            'name' => $plugin_id,
            'version' => '0',
            'uri' => '',
            'description' => '',
            'author' => '',
        ];
        $plugin_data = Yaml::parse(file_get_contents($config_file));
        if (!empty($plugin_data['uri']) && ($pos = strpos((string) $plugin_data['uri'], 'extension_view.php?eid=')) !== false) {
            [, $extension] = explode('extension_view.php?eid=', (string) $plugin_data['uri']);
            if (is_numeric($extension)) {
                $plugin_data['extension'] = $extension;
            }
        }

        $plugin = array_merge($plugin, $plugin_data);
        $this->fs_plugins[$plugin_id] = $plugin;
    }

    /**
     * Returns an array of plugins defined in the database.
     *
     * @param ExtensionStateType $state optional filter
     * @param string             $id    returns only data about given plugin
     */
    public function getDbPlugins(?ExtensionStateType $state = null, string $id = ''): array
    {
        if (!$this->db_plugins_retrieved) {
            $this->db_plugins = [];
            foreach ($this->pluginRepository->findAllByState($state) as $plugin) {
                $this->db_plugins[$plugin->getId()] = $plugin;
            }

            $this->db_plugins_retrieved = true;
        }

        if ($id !== '' && $id !== '0') {
            return $this->db_plugins[$id] ?? [];
        } else {
            return $this->db_plugins;
        }
    }

    /**
     * Sort fs_plugins.
     */
    public function sortFsPlugins($order = 'name'): void
    {
        if (!$this->fs_plugins_retrieved) {
            $this->getFsPlugins();
        }

        switch ($order) {
            case 'name':
                uasort($this->fs_plugins, Utils::nameCompare(...));
                break;
            case 'status':
                $this->sortPluginsByState();
                break;
            case 'author':
                uasort($this->fs_plugins, $this->pluginAuthorCompare(...));
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
            if ($pem_versions !== [] && !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                $version = $pem_versions[0]['name'];
            }

            $branch = Utils::getBranchFromVersion($version);
            foreach ($pem_versions as $pem_version) {
                if (str_starts_with((string) $pem_version['name'], $branch)) {
                    $versions_to_check[] = $pem_version['id'];
                }
            }
        } catch (Exception) {
            return null; // throw new \Exception($e->getMessage());
        }

        return $versions_to_check;
    }

    /**
     * Retrieve PEM server datas to $server_plugins.
     */
    public function getServerPlugins(string $pem_category, string $core_version, $new = false): array
    {
        if (!$this->server_plugins_retrieved) {
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
                'last_revision_only' => 'true',
                'version' => implode(',', $versions_to_check),
                'lang' => $this->userMapper->getUser()->getLang(),
                'get_nb_downloads' => 'true',
            ];

            if ($plugins_to_check !== []) {
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
            } catch (Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode(), $e);
            }

            $this->server_plugins_retrieved = true;
        }

        return $this->server_plugins;
    }

    /**
     * @return mixed[]
     */
    public function getIncompatiblePlugins(string $pem_category, string $core_version): array
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
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Sort $server_plugins.
     */
    public function sortServerPlugins($order = 'date'): void
    {
        switch ($order) {
            case 'date':
                krsort($this->server_plugins);
                break;
            case 'revision':
                usort($this->server_plugins, $this->extensionRevisionCompare(...));
                break;
            case 'name':
                uasort($this->server_plugins, $this->extensionNameCompare(...));
                break;
            case 'author':
                uasort($this->server_plugins, $this->extensionAuthorCompare(...));
                break;
            case 'downloads':
                usort($this->server_plugins, $this->extensionDownloadsCompare(...));
                break;
        }
    }

    /**
     * Extract plugin files from archive.
     */
    public function extractPluginFiles(string $action, int $revision): void
    {
        $archive = tempnam($this->plugins_root_path, 'zip');
        $get_data = [
            'rid' => $revision,
            'origin' => 'phyxo_' . $action,
        ];

        try {
            $this->download($archive, $get_data);
        } catch (Exception $exception) {
            throw new Exception('Cannot download plugin file', $exception->getCode(), $exception);
        }

        $extract_path = $this->plugins_root_path;
        try {
            $this->extractZipFiles($archive, self::CONFIG_FILE, $extract_path);
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode(), $exception);
        } finally {
            unlink($archive);
        }
    }

    /**
     * Sort functions.
     */
    protected function extensionRevisionCompare(array $a, array $b): int
    {
        if ($a['revision_date'] < $b['revision_date']) {
            return 1;
        } else {
            return -1;
        }
    }

    protected function extensionNameCompare(array $a, array $b): int
    {
        return strcmp(strtolower((string) $a['extension_name']), strtolower((string) $b['extension_name']));
    }

    protected function extensionAuthorCompare(array $a, array $b): int
    {
        $r = strcasecmp((string) $a['author_name'], (string) $b['author_name']);
        if ($r == 0) {
            return $this->extensionNameCompare($a, $b);
        } else {
            return $r;
        }
    }

    protected function pluginAuthorCompare(array $a, array $b): int
    {
        $r = strcasecmp((string) $a['author'], (string) $b['author']);
        if ($r == 0) {
            return Utils::nameCompare($a, $b);
        } else {
            return $r;
        }
    }

    protected function extensionDownloadsCompare(array $a, array $b): int
    {
        if ($a['extension_nb_downloads'] < $b['extension_nb_downloads']) {
            return 1;
        } else {
            return -1;
        }
    }

    public function sortPluginsByState(): void
    {
        if (!$this->fs_plugins_retrieved) {
            $this->getFsPlugins();
        }

        uasort($this->fs_plugins, Utils::nameCompare(...));

        $active_plugins = [];
        $inactive_plugins = [];
        $not_installed = [];

        foreach ($this->fs_plugins as $plugin_id => $plugin) {
            if (isset($this->db_plugins[$plugin_id])) {
                $this->db_plugins[$plugin_id]['state'] === ExtensionStateType::ACTIVE ?
                    $active_plugins[$plugin_id] = $plugin : $inactive_plugins[$plugin_id] = $plugin;
            } else {
                $not_installed[$plugin_id] = $plugin;
            }
        }

        $this->fs_plugins = $active_plugins + $inactive_plugins + $not_installed;
    }
}
