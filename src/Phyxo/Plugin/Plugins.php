<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire              http://www.phyxo.net/ |
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

namespace Phyxo\Plugin;

use Phyxo\Plugin\DummyPluginMaintain;
use PclZip;

class Plugins
{
    public $fs_plugins = array();
    public $db_plugins_by_id = array();
    public $server_plugins = array();
    public $default_plugins = array('LocalFilesEditor', 'language_switch');

    /**
     * Initialize $fs_plugins and $db_plugins_by_id
     */
    public function __construct(\Phyxo\DBLayer\DBLayer $conn) {
        $this->conn = $conn;
        $this->get_fs_plugins();

        foreach ($this->get_db_plugins() as $db_plugin) {
            $this->db_plugins_by_id[$db_plugin['id']] = $db_plugin;
        }
    }

    /**
     * Returns the maintain class of a plugin
     * or build a new class with the procedural methods
     * @param string $plugin_id
     */
    private static function build_maintain_class($plugin_id) {
        $file_to_include = PHPWG_PLUGINS_PATH . $plugin_id . '/maintain';
        $classname = $plugin_id.'_maintain';

        if (file_exists($file_to_include.'.class.php')) {
            include_once($file_to_include.'.class.php');
            return new $classname($plugin_id);
        }

        if (file_exists($file_to_include.'.inc.php')) {
            include_once($file_to_include.'.inc.php');

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
    public function perform_action($action, $plugin_id, $options=array()) {
        if (isset($this->db_plugins_by_id[$plugin_id])) {
            $crt_db_plugin = $this->db_plugins_by_id[$plugin_id];
        }

        if ($action!='update') { // wait for files to be updated
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
                $query = 'INSERT INTO '. PLUGINS_TABLE .' (id,version)';
                $query .= ' VALUES (\''. $plugin_id .'\', \''. $this->fs_plugins[$plugin_id]['version'] .'\');';
                $this->conn->db_query($query);
            }
            break;

           case 'update':
               $previous_version = $this->fs_plugins[$plugin_id]['version'];
               $errors[0] = $this->extract_plugin_files('upgrade', $options['revision'], $plugin_id);
               if ($errors[0] === 'ok') {
                   $this->get_fs_plugin($plugin_id); // refresh plugins list
                   $new_version = $this->fs_plugins[$plugin_id]['version'];

                   $plugin_maintain = self::build_maintain_class($plugin_id);
                   $plugin_maintain->update($previous_version, $new_version, $errors);
                   if ($new_version != 'auto') {
                       $query = 'UPDATE '. PLUGINS_TABLE .' SET version=\''. $new_version .'\'';
                       $query .= ' WHERE id=\''. $plugin_id .'\'';
                       $this->conn->db_query($query);
                   }
               }
               break;

        case 'activate':
            if (!isset($crt_db_plugin)) {
                $errors = $this->perform_action('install', $plugin_id);
                list($crt_db_plugin) = $this->get_db_plugins(null, $plugin_id);
                load_conf_from_db();
            } elseif ($crt_db_plugin['state'] == 'active') {
                break;
            }

            if (empty($errors)) {
                $plugin_maintain->activate($crt_db_plugin['version'], $errors);
            }

            if (empty($errors)) {
                $query = 'UPDATE '. PLUGINS_TABLE .' SET state=\'active\'';
                $query .= ' WHERE id=\''. $plugin_id .'\';';
                $this->conn->db_query($query);
            }
            break;

        case 'deactivate':
            if (!isset($crt_db_plugin) or $crt_db_plugin['state'] != 'active') {
                break;
            }

            $query = 'UPDATE '. PLUGINS_TABLE .' SET state=\'inactive\'';
            $query .= ' WHERE id=\''. $plugin_id .'\';';
            $this->conn->db_query($query);

            $plugin_maintain->deactivate();
            break;

        case 'uninstall':
            if (!isset($crt_db_plugin)) {
                break;
            }
            if ($crt_db_plugin['state'] == 'active') {
                $this->perform_action('deactivate', $plugin_id);
            }

            $query = 'DELETE FROM '. PLUGINS_TABLE .' WHERE id=\''. $plugin_id .'\';';
            $this->conn->db_query($query);

            $plugin_maintain->uninstall();
            break;

        case 'restore':
            $this->perform_action('uninstall', $plugin_id);
            unset($this->db_plugins_by_id[$plugin_id]);
            $errors = $this->perform_action('activate', $plugin_id);
            break;

        case 'delete':
            if (!empty($crt_db_plugin)) {
                $this->perform_action('uninstall', $plugin_id);
            }
            if (!isset($this->fs_plugins[$plugin_id])) {
                break;
            }

            deltree(PHPWG_PLUGINS_PATH . $plugin_id, PHPWG_PLUGINS_PATH . 'trash');
            break;
        }

        return $errors;
    }

    /**
     * Get plugins defined in the plugin directory
     */
    public function get_fs_plugins() {
        foreach (glob(PHPWG_PLUGINS_PATH . '*/main.inc.php') as $main_file) {
            $plugin_dir = basename(dirname($main_file));
            if (preg_match('`^[a-zA-Z0-9-_]+$`', $plugin_dir)) {
                $this->get_fs_plugin($plugin_dir);
            }
        }
    }

    /**
     * Load metadata of a plugin in `fs_plugins` array
     * @from 2.7
     * @param $plugin_id
     * @return false|array
     */
    public function get_fs_plugin($plugin_id) {
	    $path = PHPWG_PLUGINS_PATH.$plugin_id;
        $main_file = $path.'/main.inc.php';

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
            $plugin['name'] = trim( $val[1] );
        }
        if (preg_match("|Version:\\s*([\\w.-]+)|", $plugin_data, $val)) {
            $plugin['version'] = trim($val[1]);
        }
        if (preg_match("|Plugin URI:\\s*(https?:\\/\\/.+)|", $plugin_data, $val)) {
            $plugin['uri'] = trim($val[1]);
        }
        if ($desc = load_language('description.txt', dirname($main_file).'/', array('return' => true))) {
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
        if (!empty($plugin['uri']) and strpos($plugin['uri'] , 'extension_view.php?eid=')) {
            list( , $extension) = explode('extension_view.php?eid=', $plugin['uri']);
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
    public function get_db_plugins($state='', $id='') {
        $query = 'SELECT id, state, version FROM '.PLUGINS_TABLE;
        $clauses = array();

        if (!empty($state)) {
            $clauses[] = 'state=\''.$state.'\'';
        }
        if (!empty($id)) {
            $clauses[] = 'id = \''.$id.'\'';
        }
        if (count($clauses)) {
            $query .= ' WHERE '. implode(' AND ', $clauses);
        }

        return $this->conn->query2array($query);
    }

    /**
     * Sort fs_plugins
     */
    public function sort_fs_plugins($order='name') {
        switch ($order) {
        case 'name':
            uasort($this->fs_plugins, '\name_compare');
            break;
        case 'status':
            $this->sort_plugins_by_state();
            break;
        case 'author':
            uasort($this->fs_plugins, array($this, 'plugin_author_compare'));
            break;
        case 'id':
            uksort($this->fs_plugins, 'strcasecmp');
            break;
        }
    }

    // Retrieve PEM versions
    public function get_versions_to_check($version=PHPWG_VERSION) {
        global $conf;

        $versions_to_check = array();
        $url = PEM_URL . '/api/get_version_list.php?category_id='. $conf['pem_plugins_category'] .'&format=php';
        if (fetchRemote($url, $result) and $pem_versions = @unserialize($result)) {
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

        return $versions_to_check;
    }

    /**
     * Retrieve PEM server datas to $server_plugins
     */
    public function get_server_plugins($new=false) {
        global $user, $conf;

        $versions_to_check = $this->get_versions_to_check();
        if (empty($versions_to_check)) {
            return false;
        }

        // Plugins to check
        $plugins_to_check = array();
        foreach($this->fs_plugins as $fs_plugin) {
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
        if (fetchRemote($url, $result, $get_data)) {
            $pem_plugins = @unserialize($result);
            if (!is_array($pem_plugins)) {
                return false;
            }
            foreach ($pem_plugins as $plugin) {
                $this->server_plugins[$plugin['extension_id']] = $plugin;
            }
            return true;
        }

        return false;
    }

    public function get_incompatible_plugins($actualize=false) {
        global $conf;

        if (isset($_SESSION['incompatible_plugins']) and !$actualize
        and $_SESSION['incompatible_plugins']['~~expire~~'] > time()) {
            return $_SESSION['incompatible_plugins'];
        }

        $_SESSION['incompatible_plugins'] = array('~~expire~~' => time() + 300);

        $versions_to_check = $this->get_versions_to_check();
        if (empty($versions_to_check)) {
            return false;
        }


        // Plugins to check
        $plugins_to_check = array();
        foreach($this->fs_plugins as $fs_plugin) {
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

        if (fetchRemote($url, $result, $get_data)) {
            $pem_plugins = @unserialize($result);
            if (!is_array($pem_plugins)) {
                return false;
            }

            $server_plugins = array();
            foreach ($pem_plugins as $plugin) {
                if (!isset($server_plugins[$plugin['extension_id']])) {
                    $server_plugins[$plugin['extension_id']] = array();
                }
                $server_plugins[$plugin['extension_id']][] = $plugin['revision_name'];
            }

            foreach ($this->fs_plugins as $plugin_id => $fs_plugin) {
                if (isset($fs_plugin['extension'])
                and !in_array($plugin_id, $this->default_plugins)
                and $fs_plugin['version'] != 'auto'
                and (!isset($server_plugins[$fs_plugin['extension']])
                or !in_array($fs_plugin['version'], $server_plugins[$fs_plugin['extension']]))) {
                    $_SESSION['incompatible_plugins'][$plugin_id] = $fs_plugin['version'];
                }
            }
            return $_SESSION['incompatible_plugins'];
        }
        return false;
    }

    /**
     * Sort $server_plugins
     */
    public function sort_server_plugins($order='date') {
        switch ($order)
            {
            case 'date':
                krsort($this->server_plugins);
                break;
            case 'revision':
                usort($this->server_plugins, array($this, 'extension_revision_compare'));
                break;
            case 'name':
                uasort($this->server_plugins, array($this, 'extension_name_compare'));
                break;
            case 'author':
                uasort($this->server_plugins, array($this, 'extension_author_compare'));
                break;
            case 'downloads':
                usort($this->server_plugins, array($this, 'extension_downloads_compare'));
                break;
            }
    }

    /**
     * Extract plugin files from archive
     * @param string - install or upgrade
     *  @param string - archive URL
     * @param string - plugin id or extension id
     */
    public function extract_plugin_files($action, $revision, $dest, &$plugin_id=null) {
        if ($archive = tempnam( PHPWG_PLUGINS_PATH, 'zip')) {
            $url = PEM_URL . '/download.php';
            $get_data = array(
                'rid' => $revision,
                'origin' => 'piwigo_'.$action,
            );

            if ($handle = @fopen($archive, 'wb') and fetchRemote($url, $handle, $get_data)) {
                fclose($handle);
                $zip = new PclZip($archive);
                if ($list = $zip->listContent()) {
                    foreach ($list as $file) {
                        // we search main.inc.php in archive
                        if (basename($file['filename']) == 'main.inc.php'
                        and (!isset($main_filepath)
                        or strlen($file['filename']) < strlen($main_filepath))) {
                            $main_filepath = $file['filename'];
                        }
                    }
                    if (isset($main_filepath)) {
                        $root = dirname($main_filepath); // main.inc.php path in archive
                        if ($action == 'upgrade') {
                            $plugin_id = $dest;
                        } else {
                            $plugin_id = ($root == '.' ? 'extension_' . $dest : basename($root));
                        }
                        $extract_path = PHPWG_PLUGINS_PATH . $plugin_id;

                        if($result = $zip->extract(PCLZIP_OPT_PATH, $extract_path,
                        PCLZIP_OPT_REMOVE_PATH, $root,
                        PCLZIP_OPT_REPLACE_NEWER)) {
                            foreach ($result as $file) {
                                if ($file['stored_filename'] == $main_filepath) {
                                    $status = $file['status'];
                                    break;
                                }
                            }
                            if (file_exists($extract_path.'/obsolete.list')
                            and $old_files = file($extract_path.'/obsolete.list', FILE_IGNORE_NEW_LINES)
                            and !empty($old_files)) {
                                $old_files[] = 'obsolete.list';
                                foreach($old_files as $old_file) {
                                    $path = $extract_path.'/'.$old_file;
                                    if (is_file($path)) {
                                        @unlink($path);
                                    } elseif (is_dir($path)) {
                                        deltree($path, PHPWG_PLUGINS_PATH . 'trash');
                                    }
                                }
                            }
                        } else {
                            $status = 'extract_error';
                        }
                    } else {
                        $status = 'archive_error';
                    }
                } else {
                    $status = 'archive_error';
                }
            } else {
                $status = 'dl_archive_error';
            }
        } else {
            $status = 'temp_path_error';
        }

        @unlink($archive);
        return $status;
    }

    public function get_merged_extensions($version=PHPWG_VERSION) {
        $file = PHPWG_ROOT_PATH.'install/obsolete_extensions.list';
        $merged_extensions = array();

        if (file_exists($file) and $obsolete_ext = file($file, FILE_IGNORE_NEW_LINES) and !empty($obsolete_ext)) {
            foreach ($obsolete_ext as $ext) {
                if (preg_match('/^(\d+) ?: ?(.*?)$/', $ext, $matches)) {
                    $merged_extensions[$matches[1]] = $matches[2];
                }
            }
        }
        return $merged_extensions;
    }

    /**
     * Sort functions
     */
    protected function extension_revision_compare($a, $b) {
        if ($a['revision_date'] < $b['revision_date']) {
            return 1;
        } else {
            return -1;
        }
    }

    protected function extension_name_compare($a, $b) {
        return strcmp(strtolower($a['extension_name']), strtolower($b['extension_name']));
    }

    protected function extension_author_compare($a, $b) {
        $r = strcasecmp($a['author_name'], $b['author_name']);
        if ($r == 0) {
            return $this->extension_name_compare($a, $b);
        } else {
            return $r;
        }
    }

    protected function plugin_author_compare($a, $b) {
        $r = strcasecmp($a['author'], $b['author']);
        if ($r == 0) {
            return \name_compare($a, $b);
        } else {
            return $r;
        }
    }

    protected function extension_downloads_compare($a, $b) {
        if ($a['extension_nb_downloads'] < $b['extension_nb_downloads']) {
            return 1;
        } else {
            return -1;
        }
    }

    public function sort_plugins_by_state() {
        uasort($this->fs_plugins, '\name_compare');

        $active_plugins = array();
        $inactive_plugins = array();
        $not_installed = array();

        foreach($this->fs_plugins as $plugin_id => $plugin) {
            if (isset($this->db_plugins_by_id[$plugin_id])) {
                $this->db_plugins_by_id[$plugin_id]['state'] == 'active' ?
                    $active_plugins[$plugin_id] = $plugin : $inactive_plugins[$plugin_id] = $plugin;
            } else {
                $not_installed[$plugin_id] = $plugin;
            }
        }
        $this->fs_plugins = $active_plugins + $inactive_plugins + $not_installed;
    }
}