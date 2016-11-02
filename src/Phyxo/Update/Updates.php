<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2016 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

namespace Phyxo\Update;

use Phyxo\Plugin\Plugins;
use Phyxo\Theme\Themes;
use Phyxo\Language\Languages;
use PclZip;
use GuzzleHttp\Client;

class Updates
{
    private $versions = array(), $version = array();
    private $types = array(), $merged_extensions = array();

    public function __construct(\Phyxo\DBLayer\DBLayer $conn=null, $page='updates') {
        $this->types = array('plugins', 'themes', 'languages');

        if (in_array($page, $this->types)) {
            $this->types = array($page);
        }
        $this->default_themes = array('clear', 'dark', 'Sylvia', 'elegant', 'default');
        $this->default_plugins = array();
        $this->default_languages = array();

        foreach ($this->types as $type) {
            $typeClassName = sprintf('\Phyxo\%s\%s', ucfirst(substr($type, 0, -1)), ucfirst($type));
            $this->$type = new $typeClassName($conn);
        }
    }

    public function setUpdateUrl($url) {
        $this->update_url = $url;
    }

    public function getType($type) {
        if (!in_array($type, $this->types)) {
            return null;
        }

        return $this->$type;
    }

    public function getAllVersions() {
        try {
            $client = new Client();
            $request = $client->createRequest('GET', $this->update_url);
            $response = $client->send($request);
            if ($response->getStatusCode()==200 && $response->getBody()->isReadable()) {
                $this->versions = json_decode($response->getBody(), true);
                return $this->versions;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function upgradeTo($version, $release='stable') {
        foreach ($this->versions as $v) {
            if ($v['version']==$version && $v['release']==$release) {
                $this->version = $v;
            }
        }
    }

    public function download($zip_file) {
        @mkgetdir(dirname($zip_file)); // @TODO: remove arobase and use a fs library

        try {
            $client = new Client();
            $request = $client->createRequest('GET', $this->getFileURL());
            $response = $client->send($request);
            if ($response->getStatusCode()==200 && $response->getBody()->isReadable()) {
                file_put_contents($zip_file, $response->getBody());
            }
        } catch (\Exception $e) {

        }
    }

    public function getFileURL() {
        return $this->version['href'];
    }

    public function upgrade($zip_file) {
        $zip = new PclZip($zip_file);
        $zip_files = array();
		$not_writable = array();
        $root = PHPWG_ROOT_PATH;

        foreach ($zip->listContent() as $file) {
            $filename = str_replace('phyxo/', '', $file['filename']);
            $dest = $dest_dir = $root.'/'.$filename;
			while (!is_dir($dest_dir = dirname($dest_dir)));

			if ((file_exists($dest) && !is_writable($dest)) ||
                (!file_exists($dest) && !is_writable($dest_dir))) {
				$not_writable[] = $filename;
				continue;
			}
        }
        if (!empty($not_writable)) {
            $e = new \Exception('Some files or directories are not writable');
            $e->not_writable = $not_writable;
            throw $e;
        }

        // @TODO: remove arobase ; extract try to make a touch on every file but sometimes failed.
        $result = @$zip->extract(PCLZIP_OPT_PATH, PHPWG_ROOT_PATH,
                                 PCLZIP_OPT_REMOVE_PATH, 'phyxo',
                                 PCLZIP_OPT_SET_CHMOD, 0755,
                                 PCLZIP_OPT_REPLACE_NEWER
        );
    }

    public function getServerExtensions($version=PHPWG_VERSION) {
        global $user;

        $get_data = array(
            'format' => 'php',
        );

        // Retrieve PEM versions
        $versions_to_check = array();
        $url = PEM_URL . '/api/get_version_list.php';
        if (fetchRemote($url, $result, $get_data) and $pem_versions = @unserialize($result)) {
            if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                $version = $pem_versions[0]['name'];
            }
            $branch = get_branch_from_version($version);
            foreach ($pem_versions as $pem_version) {
                if (strpos($pem_version['name'], $branch) === 0) {
                    $versions_to_check[] = $pem_version['id'];
                }
            }
        }
        if (empty($versions_to_check)) {
            return array();
        }

        // Extensions to check
        $ext_to_check = array();
        foreach ($this->types as $type) {
            foreach ($this->getType($type)->getFsExtensions() as $ext) {
                if (isset($ext['extension'])) {
                    $ext_to_check[$ext['extension']] = $type;
                }
            }
        }

        // Retrieve PEM plugins infos
        $url = PEM_URL . '/api/get_revision_list.php';
        $get_data = array_merge($get_data, array(
            'last_revision_only' => 'true',
            'version' => implode(',', $versions_to_check),
            'lang' => substr($user['language'], 0, 2),
            'get_nb_downloads' => 'true',
        )
        );

        $post_data = array();
        if (!empty($ext_to_check)) {
            $post_data['extension_include'] = implode(',', array_keys($ext_to_check));
        }

        if (fetchRemote($url, $result, $get_data, $post_data)) {
            $pem_exts = @unserialize($result);
            if (!is_array($pem_exts)) {
                return array();
            }

            $servers = array();

            foreach ($pem_exts as $ext) {
                if (isset($ext_to_check[$ext['extension_id']])) {
                    $type = $ext_to_check[$ext['extension_id']];

                    if (!isset($servers[$type])) {
                        $servers[$type] = array();
                    }

                    $servers[$type][$ext['extension_id']] = $ext;

                    unset($ext_to_check[$ext['extension_id']]);
                }
            }

            $this->checkMissingExtensions($ext_to_check);
            return array();
        }

        return array();
    }

    public function checkCoreUpgrade() {
        $_SESSION['need_update'] = null;

        if (preg_match('/(\d+\.\d+)\.(\d+)/', PHPWG_VERSION, $matches)
            && @fetchRemote(PHPWG_URL.'/download/all_versions.php?rand='.md5(uniqid(rand(), true)), $result)) {
            $all_versions = @explode("\n", $result);
            $new_version = trim($all_versions[0]);
            $_SESSION['need_update'] = version_compare(PHPWG_VERSION, $new_version, '<');
        }
    }

    // Check all extensions upgrades
    protected function checkExtensions() {
        global $conf;

        if (!$this->getServerExtensions()) {
            return false;
        }

        $_SESSION['extensions_need_update'] = array();

        foreach ($this->types as $type) {
            $fs = 'fs_'.$type;
            $server = 'server_'.$type;
            $server_ext = $this->$type->$server;
            $fs_ext = $this->$type->$fs;

            $ignore_list = array();
            $need_upgrade = array();

            foreach($fs_ext as $ext_id => $fs_ext) {
                if (isset($fs_ext['extension']) and isset($server_ext[$fs_ext['extension']])) {
                    $ext_info = $server_ext[$fs_ext['extension']];

                    if (!safe_version_compare($fs_ext['version'], $ext_info['revision_name'], '>=')) {
                        if (in_array($ext_id, $conf['updates_ignored'][$type])) {
                            $ignore_list[] = $ext_id;
                        } else {
                            $_SESSION['extensions_need_update'][$type][$ext_id] = $ext_info['revision_name'];
                        }
                    }
                }
            }
            $conf['updates_ignored'][$type] = $ignore_list;
        }
        conf_update_param('updates_ignored', pwg_db_real_escape_string(serialize($conf['updates_ignored'])));
    }

    // Check if extension have been upgraded since last check
    protected function checkUpdatedExtensions() {
        foreach ($this->types as $type) {
            if (!empty($_SESSION['extensions_need_update'][$type])) {
                foreach($this->getType($type)->getFsExtensions() as $ext_id => $fs_ext) {
                    if (isset($_SESSION['extensions_need_update'][$type][$ext_id])
                        and safe_version_compare($fs_ext['version'], $_SESSION['extensions_need_update'][$type][$ext_id], '>=')) {
                        // Extension have been upgraded
                        $this->checkExtensions();
                        break;
                    }
                }
            }
        }
    }

    protected function checkMissingExtensions($missing) {
        foreach ($missing as $id => $type) {
            $default = 'default_'.$type;
            foreach ($this->getType($type)->getFsExtensions() as $ext_id => $ext) {
                if (isset($ext['extension']) and $id == $ext['extension']
                    and !in_array($ext_id, $this->$default)
                    and !in_array($ext['extension'], $this->merged_extensions)) {
                    $this->missing[$type][] = $ext;
                    break;
                }
            }
        }
    }

    protected function getMergedExtensions($version) {
        if (fetchRemote($this->merged_extension_url, $result)) {
            $rows = explode("\n", $result);
            foreach ($rows as $row) {
                if (preg_match('/^(\d+\.\d+): *(.*)$/', $row, $match)) {
                    if (version_compare($version, $match[1], '>=')) {
                        $extensions = explode(',', trim($match[2]));
                        $this->merged_extensions = array_merge($this->merged_extensions, $extensions);
                    }
                }
            }
        }
    }
}
