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

namespace Phyxo\Language;

use PclZip;

class Languages
{
    private $fs_languages = array(), $db_languages = array(), $server_languages = array();
    private $fs_languages_retrieved = false, $db_languages_retrieved = false, $server_languages_retrieved = false;

    public function __construct(\Phyxo\DBLayer\DBLayer $conn=null, $target_charset=null) {
        if (!is_null($conn)) {
            $this->conn = $conn;
        }
    }

    public function setConnection(\Phyxo\DBLayer\DBLayer $conn) {
        $this->conn = $conn;
    }

    /**
     * Perform requested actions
     * @param string - action
     * @param string - language id
     * @param array - errors
     */
    function performAction($action, $language_id) {
        global $conf, $services;

        if (!$this->db_languages_retrieved) {
            $this->getDbLanguages();
        }

        if (!$this->fs_languages_retrieved) {
            $this->getFsLanguages();
        }

        if (isset($this->db_languages[$language_id])) {
            $crt_db_language = $this->db_languages[$language_id];
        }

        $errors = array();

        switch ($action) {
        case 'activate':
            if (isset($crt_db_language)) {
                $errors[] = 'CANNOT ACTIVATE - LANGUAGE IS ALREADY ACTIVATED';
                break;
            }

            $query = 'INSERT INTO '.LANGUAGES_TABLE.' (id, version, name)';
            $query .= ' VALUES(\''.$language_id.'\',';
            $query .= ' \''.$this->fs_languages[$language_id]['version'].'\',';
            $query .= ' \''.$this->fs_languages[$language_id]['name'].'\');';
            $this->conn->db_query($query);
            break;

        case 'deactivate':
            if (!isset($crt_db_language)) {
                $errors[] = 'CANNOT DEACTIVATE - LANGUAGE IS ALREADY DEACTIVATED';
                break;
            }

            if ($language_id == $services['users']->getDefaultLanguage()) {
                $errors[] = 'CANNOT DEACTIVATE - LANGUAGE IS DEFAULT LANGUAGE';
                break;
            }

            $query = 'DELETE FROM '.LANGUAGES_TABLE.' WHERE id= \''.$language_id.'\'';
            $this->conn->db_query($query);
            break;

        case 'delete':
            if (!empty($crt_db_language)) {
                $errors[] = 'CANNOT DELETE - LANGUAGE IS ACTIVATED';
                break;
            }
            if (!isset($this->fs_languages[$language_id])) {
                $errors[] = 'CANNOT DELETE - LANGUAGE DOES NOT EXIST';
                break;
            }

            // Set default language to user who are using this language
            $query = 'UPDATE '.USER_INFOS_TABLE.' SET language = \''.$services['users']->getDefaultLanguage().'\'';
            $query .= ' WHERE language = \''.$language_id.'\';';
            $this->conn->db_query($query);

            deltree(PHPWG_ROOT_PATH.'language/'.$language_id, PHPWG_ROOT_PATH.'language/trash');
            break;

        case 'set_default':
            $query = 'UPDATE '.USER_INFOS_TABLE.' SET language = \''.$language_id.'\'';
            $query .= ' WHERE user_id '.$this->conn->in(array($conf['default_user_id'], $conf['guest_id']));
            $this->conn->db_query($query);
            break;
        }

        return $errors;
    }

    // for Update/Updates
    public function getFsExtensions($target_charset=null) {
        return $this->getFsLanguages($target_charset);
    }

    /**
     *  Get languages defined in the language directory
     */
    public function getFsLanguages($target_charset=null) {
        if (!$this->fs_languages_retrieved) {
            if (empty($target_charset)) {
                $target_charset = \get_pwg_charset();
            }
            $target_charset = strtolower($target_charset);

            foreach (glob(PHPWG_LANGUAGES_PATH . '*/common.lang.php') as $common_lang) {
                $language_dir = basename(dirname($common_lang));

                if (!preg_match('`^[a-zA-Z0-9-_]+$`', $language_dir)) {
                    continue;
                }
                $language = array(
                    'name' => $language_dir,
                    'code' => $language_dir,
                    'version' => '0',
                    'uri' => '',
                    'author' => ''
                );
                $language_data = file_get_contents($common_lang, false, null, 0, 2048);

                if (preg_match("|Language Name:\\s*(.+)|", $language_data, $val)) {
                    $language['name'] = trim( $val[1] );
                    $language['name'] = \convert_charset($language['name'], 'utf-8', $target_charset);
                }
                if (preg_match("|Version:\\s*([\\w.-]+)|", $language_data, $val)) {
                    $language['version'] = trim($val[1]);
                }
                if (preg_match("|Language URI:\\s*(https?:\\/\\/.+)|", $language_data, $val)) {
                    $language['uri'] = trim($val[1]);
                }
                if (preg_match("|Author:\\s*(.+)|", $language_data, $val)) {
                    $language['author'] = trim($val[1]);
                }
                if (preg_match("|Author URI:\\s*(https?:\\/\\/.+)|", $language_data, $val)) {
                    $language['author uri'] = trim($val[1]);
                }
                if (!empty($language['uri']) and strpos($language['uri'] , 'extension_view.php?eid=')) {
                    list( , $extension) = explode('extension_view.php?eid=', $language['uri']);
                    if (is_numeric($extension)) {
                        $language['extension'] = $extension;
                    }
                }

                $this->fs_languages[$language_dir] = $language;
            }
            uasort($this->fs_languages, '\name_compare');

            $this->fs_languages_retrieved = true;
        }

        return $this->fs_languages;

    }

    public function getDbLanguages() {
        if (!$this->db_languages_retrieved) {
            $query = 'SELECT id, name FROM '.LANGUAGES_TABLE.' ORDER BY name ASC;';
            $result = $this->conn->db_query($query);

            while ($row = $this->conn->db_fetch_assoc($result)) {
                $this->db_languages[$row['id']] = $row['name'];
            }

            $this->db_languages_retrieved = true;
        }

        return $this->db_languages;
    }

    /**
     * Retrieve PEM server datas to $server_languages
     */
    public function getServerLanguages($new=false) {
        global $user, $conf;

        if (!$this->server_languages_retrieved) {
            $get_data = array(
                'category_id' => $conf['pem_languages_category'],
                'format' => 'php',
            );

            // Retrieve PEM versions
            $version = PHPWG_VERSION;
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

            // Languages to check
            $languages_to_check = array();
            foreach($this->getFsLanguages() as $fs_language) {
                if (isset($fs_language['extension'])) {
                    $languages_to_check[] = $fs_language['extension'];
                }
            }

            // Retrieve PEM languages infos
            $url = PEM_URL . '/api/get_revision_list.php';
            $get_data = array_merge($get_data, array(
                'last_revision_only' => 'true',
                'version' => implode(',', $versions_to_check),
                'lang' => $user['language'],
                'get_nb_downloads' => 'true',
                'format' => 'json'
            ));
            if (!empty($languages_to_check)) {
                if ($new) {
                    $get_data['extension_exclude'] = implode(',', $languages_to_check);
                } else {
                    $get_data['extension_include'] = implode(',', $languages_to_check);
                }
            }

            if (fetchRemote($url, $result, $get_data)) {
                $pem_languages = json_decode($result, true);

                if (!is_array($pem_languages)) {
                    return array();
                }

                foreach ($pem_languages as $language) {
                    if (preg_match('/^.*? \[[A-Z]{2}\]$/', $language['extension_name'])) {
                        $this->server_languages[$language['extension_id']] = $language;
                    }
                }
                uasort($this->server_languages, array($this, 'extensionNameCompare'));
            }

            $this->server_languages_retrieved = true;
        }
        return $this->server_languages;
    }

    /**
     * Extract language files from archive
     *
     * @param string - install or upgrade
     * @param string - remote revision identifier (numeric)
     * @param string - language id or extension id
     */
    public function extractLanguageFiles($action, $revision, $dest='') {
        if ($archive = tempnam( PHPWG_ROOT_PATH.'language', 'zip')) {
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
                        // we search common.lang.php in archive
                        if (basename($file['filename']) == 'common.lang.php'
                            and (!isset($main_filepath)
                                 or strlen($file['filename']) < strlen($main_filepath))) {
                            $main_filepath = $file['filename'];
                        }
                    }
                    if (isset($main_filepath)) {
                        $root = basename(dirname($main_filepath)); // common.lang.php path in archive
                        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $root)) {
                            if ($action == 'install') {
                                $dest = $root;
                            }
                            $extract_path = PHPWG_ROOT_PATH.'language/'.$dest;
                            if ($result = $zip->extract(PCLZIP_OPT_PATH, $extract_path,
                                                        PCLZIP_OPT_REMOVE_PATH, $root, PCLZIP_OPT_REPLACE_NEWER)) {
                                foreach ($result as $file) {
                                    if ($file['stored_filename'] == $main_filepath) {
                                        $status = $file['status'];
                                        break;
                                    }
                                }
                                if ($status == 'ok') {
                                    $this->getFsLanguages();
                                    if ($action == 'install') {
                                        $this->performAction('activate', $dest);
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
                                            deltree($path, PHPWG_ROOT_PATH.'language/trash');
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

    /**
     * Sort functions
     */
    public function extensionNameCompare($a, $b) {
        return strcmp(strtolower($a['extension_name']), strtolower($b['extension_name']));
    }
}
