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

namespace Phyxo\Update;

use Exception;
use Phyxo\Functions\Utils;
use App\DataMapper\UserMapper;
use Phyxo\Plugin\Plugins;
use Phyxo\Theme\Themes;
use Phyxo\Language\Languages;
use PclZip;
use Phyxo\Extension\Extensions;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;

class Updates
{
    private $versions = [];

    private $version = [];

    private array $types = ['plugins' => 'plugins', 'themes' => 'themes', 'languages' => 'language'];

    private $update_url;

    private $pem_url;

    private $missing = [];

    private $core_need_update = false;

    private $extensions_need_update = [];

    public function __construct(
        private readonly UserMapper $userMapper,
        private readonly string $core_version,
        private readonly Plugins $plugins,
        private readonly Themes $themes,
        private readonly Languages $languages
    ) {
    }

    public function setUpdateUrl($url)
    {
        $this->update_url = $url;
    }

    public function setExtensionsURL(string $url)
    {
        $this->pem_url = $url;
    }

    protected function getType(string $type): ?Extensions
    {
        return match ($type) {
            'plugins' => $this->plugins,
            'themes' => $this->themes,
            'languages' => $this->languages,
            default => null,
        };
    }

    public function getAllVersions()
    {
        try {
            $client = HttpClient::create(['headers' => ['User-Agent' => 'Phyxo']]);
            $response = $client->request('GET', $this->update_url);
            if ($response->getStatusCode() == 200 && $response->getContent()) {
                $this->versions = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
                return $this->versions;
            } else {
                return false;
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function upgradeTo($version, $release = 'stable')
    {
        if (count($this->versions) === 0) {
            $this->getAllVersions();
        }

        foreach ($this->versions as $v) {
            if ($v['version'] == $version && $v['release'] == $release) {
                $this->version = $v;
            }
        }
    }

    public function removeObsoleteFiles(string $obsolete_file, string $root)
    {
        if (!is_readable($obsolete_file)) {
            return;
        }

        $fs = new Filesystem();
        $old_files = file($obsolete_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($old_files as $old_file) {
            $path = $root . '/' . $old_file;
            if (is_writable($path)) {
                $fs->remove($path);
            } elseif (is_dir($path)) {
                $fs->remove($path);
            }
        }
    }

    public function download($zip_file)
    {
        $fs = new Filesystem();
        $fs->mkdir(dirname((string) $zip_file));

        try {
            $client = HttpClient::create(['headers' => ['User-Agent' => 'Phyxo']]);
            $response = $client->request('GET', $this->getFileURL());
            if ($response->getStatusCode() == 200 && $response->getContent()) {
                file_put_contents($zip_file, $response->getContent());
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getFileURL()
    {
        return $this->version['href'];
    }

    public function upgrade($zip_file)
    {
        $zip = new PclZip($zip_file);
        $not_writable = [];
        $root = __DIR__ . '/../../../';

        foreach ($zip->listContent() as $file) {
            $filename = str_replace('phyxo/', '', (string) $file['filename']);
            $dest = $dest_dir = $root . '/' . $filename;

            if ((file_exists($dest) && !is_writable($dest)) || (!file_exists($dest) && !is_writable($dest_dir))) {
                $not_writable[] = $filename;
                continue;
            }
        }
        if (count($not_writable) > 0) {
            $message = 'Some files or directories are not writable';
            $message .= '<pre>' . implode("\n", $not_writable) . '</pre>';
            throw new Exception($message);
        }

        // @TODO: remove arobase ; extract try to make a touch on every file but sometimes failed.
        @$zip->extract(
            PCLZIP_OPT_PATH,
            __DIR__ . '/../../../',
            PCLZIP_OPT_REMOVE_PATH,
            'phyxo',
            PCLZIP_OPT_SET_CHMOD,
            0755,
            PCLZIP_OPT_REPLACE_NEWER
        );
    }

    public function getServerExtensions()
    {
        $get_data = [
            'format' => 'json',
        ];

        // Retrieve PEM versions
        $versions_to_check = [];
        $url = $this->pem_url . '/api/get_version_list.php';

        try {
            $client = HttpClient::create(['headers' => ['User-Agent' => 'Phyxo']]);
            $response = $client->request('GET', $url, $get_data);
            if ($response->getStatusCode() == 200 && $response->getContent()) {
                $pem_versions = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } else {
                throw new Exception("Reponse from server is not readable");
            }

            $version = $this->core_version;
            if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                $version = $pem_versions[0]['name'];
            }
            $branch = Utils::get_branch_from_version($version);
            foreach ($pem_versions as $pem_version) {
                if (str_starts_with((string) $pem_version['name'], $branch)) {
                    $versions_to_check[] = $pem_version['id'];
                }
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        if (empty($versions_to_check)) {
            return false;
        }

        // Extensions to check
        $ext_to_check = [];
        foreach (array_keys($this->types) as $type) {
            foreach ($this->getType($type)->getFsExtensions() as $ext) {
                if (isset($ext['extension'])) {
                    $ext_to_check[$ext['extension']] = $type;
                }
            }
        }

        // Retrieve PEM plugins infos
        $url = $this->pem_url . '/api/get_revision_list.php';
        $get_data = array_merge($get_data, [
            'last_revision_only' => 'true',
            'version' => implode(',', $versions_to_check),
            'lang' => $this->userMapper->getUser()->getLang(),
            'get_nb_downloads' => 'true',
            'format' => 'json'
        ]);
        $url .= '?' . http_build_query($get_data, '', '&');

        $post_data = [];
        if (!empty($ext_to_check)) {
            $post_data['extension_include'] = implode(',', array_keys($ext_to_check));
        }

        try {
            $client = HttpClient::create(['headers' => ['User-Agent' => 'Phyxo']]);
            $response = $client->request('POST', $url, $post_data);
            if ($response->getStatusCode() == 200 && $response->getContent()) {
                $pem_exts = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } else {
                throw new Exception("Reponse from server is not readable");
            }
            if (!is_array($pem_exts)) {
                return [];
            }

            $servers = [];
            foreach ($pem_exts as $ext) {
                if (isset($ext_to_check[$ext['extension_id']])) {
                    $type = $ext_to_check[$ext['extension_id']];

                    if (!isset($servers[$type])) {
                        $servers[$type] = [];
                    }

                    $servers[$type][$ext['extension_id']] = $ext;

                    unset($ext_to_check[$ext['extension_id']]);
                }
            }

            $this->checkMissingExtensions($ext_to_check);
            return [];
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function checkCoreUpgrade()
    {
        if (preg_match('/(\d+\.\d+)\.(\d+)$/', $this->core_version, $matches)) {
            try {
                $client = HttpClient::create(['headers' => ['User-Agent' => 'Phyxo']]);
                $response = $client->request('GET', $this->update_url);
                if ($response->getStatusCode() == 200 && $response->getContent()) {
                    $all_versions = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
                }
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
            if (!empty($all_versions)) {
                $new_version = trim((string) $all_versions[0]['version']);
                $this->core_need_update = version_compare($this->core_version, $new_version, '<');
            }
        }
    }

    // Check all extensions upgrades
    public function checkExtensions(array $updates_ignored = [])
    {
        if (!$this->getServerExtensions()) {
            return false;
        }

        foreach ($this->types as $type) {
            $ignore_list = [];

            foreach ($this->getType($type)->getFsExtensions() as $ext_id => $fs_ext) {
                if (isset($fs_ext['extension'], $this->getServerExtensions()[$fs_ext['extension']])) {
                    $ext_info = $this->getServerExtensions()[$fs_ext['extension']];

                    if (!version_compare($fs_ext['version'], $ext_info['revision_name'], '>=')) {
                        if (in_array($ext_id, $updates_ignored[$type])) {
                            $ignore_list[] = $ext_id;
                        } else {
                            $this->extensions_need_update[$type][$ext_id] = $ext_info['revision_name'];
                        }
                    }
                }
            }
            $updates_ignored[$type] = $ignore_list;
        }

        return $updates_ignored;
    }

    // Check if extension have been upgraded since last check
    public function checkUpdatedExtensions(array $updates_ignored = [])
    {
        foreach ($this->types as $type) {
            if (!empty($this->extensions_need_update[$type])) {
                foreach ($this->getType($type)->getFsExtensions() as $ext_id => $fs_ext) {
                    if (isset($this->extensions_need_update[$type][$ext_id])
                        && version_compare($fs_ext['version'], $this->extensions_need_update[$type][$ext_id], '>=')) {
                        // Extension have been upgraded
                        return $this->checkExtensions($updates_ignored);
                    }
                }
            }
        }
    }

    protected function checkMissingExtensions($missing)
    {
        foreach ($missing as $id => $type) {
            $default = 'default_' . $type;
            foreach ($this->getType($type)->getFsExtensions() as $ext_id => $ext) {
                if (isset($ext['extension']) and $id == $ext['extension']
                    and !in_array($ext_id, $this->$default)) {
                    $this->missing[$type][] = $ext;
                    break;
                }
            }
        }
    }

    public function isCoreNeedUpdate(): bool
    {
        return $this->core_need_update;
    }

    public function isExtensionsNeedUpdate(): array
    {
        return $this->extensions_need_update;
    }
}
