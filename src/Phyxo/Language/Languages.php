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

namespace Phyxo\Language;

use App\DataMapper\UserMapper;
use Phyxo\Extension\Extensions;
use App\Repository\LanguageRepository;
use App\Repository\UserInfosRepository;
use Phyxo\DBLayer\iDBLayer;

class Languages extends Extensions
{
    private $conn;
    private static $languages_root_path, $userMapper;
    private $fs_languages = [], $db_languages = [], $server_languages = [];
    private $fs_languages_retrieved = false, $db_languages_retrieved = false, $server_languages_retrieved = false;

    public function __construct(iDBLayer $conn = null, UserMapper $userMapper = null)
    {
        if (!is_null($conn)) {
            $this->conn = $conn;
        }

        self::$userMapper = $userMapper;
    }

    public static function setRootPath(string $languages_root_path)
    {
        self::$languages_root_path = $languages_root_path;
    }

    public function setConnection(\Phyxo\DBLayer\iDBLayer $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Perform requested actions
     * @param string - action
     * @param string - language id
     * @param array - errors
     */
    function performAction($action, $language_id)
    {
        global $conf;

        if (!$this->db_languages_retrieved) {
            $this->getDbLanguages();
        }

        if (!$this->fs_languages_retrieved) {
            $this->getFsLanguages();
        }

        if (isset($this->db_languages[$language_id])) {
            $crt_db_language = $this->db_languages[$language_id];
        }

        $errors = [];

        switch ($action) {
            case 'activate':
                if (isset($crt_db_language)) {
                    $errors[] = 'CANNOT ACTIVATE - LANGUAGE IS ALREADY ACTIVATED';
                    break;
                }

                (new LanguageRepository($this->conn))->addLanguage($language_id, $this->fs_languages[$language_id]['name'], $this->fs_languages[$language_id]['version']);
                break;

            case 'deactivate':
                if (!isset($crt_db_language)) {
                    $errors[] = 'CANNOT DEACTIVATE - LANGUAGE IS ALREADY DEACTIVATED';
                    break;
                }

                if ($language_id == self::$userMapper->getDefaultLanguage()) {
                    $errors[] = 'CANNOT DEACTIVATE - LANGUAGE IS DEFAULT LANGUAGE';
                    break;
                }

                (new LanguageRepository($this->conn))->deleteLanguage($language_id);
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
                (new LanguageRepository($this->conn))->updateLanguage(['language' => self::$userMapper->getDefaultLanguage()], ['id' => $language_id]);
                \Phyxo\Functions\Utils::deltree(self::$languages_root_path . '/language/' . $language_id, self::$languages_root_path . '/language/trash');
                break;

            case 'set_default':
                (new UserInfosRepository($this->conn))->updateFieldForUsers('language', $language_id, [$conf['default_user_id'], $conf['guest_id']]);
                break;
        }

        return $errors;
    }

    // for Update/Updates
    public function getFsExtensions($target_charset = null)
    {
        return $this->getFsLanguages($target_charset);
    }

    /**
     *  Get languages defined in the language directory
     */
    public function getFsLanguages($target_charset = null)
    {
        if (!$this->fs_languages_retrieved) {
            if (empty($target_charset)) {
                $target_charset = \Phyxo\Functions\Utils::get_charset();
            }
            $target_charset = strtolower($target_charset);

            foreach (glob(self::$languages_root_path . '/*/common.lang.php') as $common_lang) {
                $language_dir = basename(dirname($common_lang));

                if (!preg_match('`^[a-zA-Z0-9-_]+$`', $language_dir)) {
                    continue;
                }
                $language = [
                    'name' => $language_dir,
                    'code' => $language_dir,
                    'version' => '0',
                    'uri' => '',
                    'author' => ''
                ];
                $language_data = file_get_contents($common_lang, false, null, 0, 2048);

                if (preg_match("|Language Name:\\s*(.+)|", $language_data, $val)) {
                    $language['name'] = trim($val[1]);
                    $language['name'] = \Phyxo\Functions\Utils::convert_charset($language['name'], 'utf-8', $target_charset);
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
                if (!empty($language['uri']) and strpos($language['uri'], 'extension_view.php?eid=')) {
                    list(, $extension) = explode('extension_view.php?eid=', $language['uri']);
                    if (is_numeric($extension)) {
                        $language['extension'] = $extension;
                    }
                }

                $this->fs_languages[$language_dir] = $language;
            }
            uasort($this->fs_languages, '\Phyxo\Functions\Utils::name_compare');

            $this->fs_languages_retrieved = true;
        }

        return $this->fs_languages;

    }

    public function getDbLanguages()
    {
        if (!$this->db_languages_retrieved) {
            $this->db_languages = $this->conn->result2array((new LanguageRepository($this->conn))->findAll(), 'id', 'name');
            $this->db_languages_retrieved = true;
        }

        return $this->db_languages;
    }

    /**
     * Retrieve PEM server datas to $server_languages
     */
    public function getServerLanguages($new = false, string $phyxo_version = PHPWG_VERSION)
    {
        global $conf;

        if (!$this->server_languages_retrieved) {
            $get_data = [
                'category_id' => $conf['pem_languages_category'],
            ];

            // Retrieve PEM versions
            $version = $phyxo_version;
            $versions_to_check = [];
            $url = $this->pem_url . '/api/get_version_list.php';

            try {
                $pem_versions = $this->getJsonFromServer($url, $get_data);
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

            if (empty($versions_to_check)) {
                return [];
            }

            // Languages to check
            $languages_to_check = [];
            foreach ($this->getFsLanguages() as $fs_language) {
                if (isset($fs_language['extension'])) {
                    $languages_to_check[] = $fs_language['extension'];
                }
            }

            // Retrieve PEM languages infos
            $url = $this->pem_url . '/api/get_revision_list.php';
            $get_data = array_merge($get_data, [
                'last_revision_only' => 'true',
                'version' => implode(',', $versions_to_check),
                'lang' => self::$userMapper->getUser()->getLanguage(),
                'get_nb_downloads' => 'true',
            ]);
            if (!empty($languages_to_check)) {
                if ($new) {
                    $get_data['extension_exclude'] = implode(',', $languages_to_check);
                } else {
                    $get_data['extension_include'] = implode(',', $languages_to_check);
                }
            }

            try {
                $pem_languages = $this->getJsonFromServer($url, $get_data);
                if (!is_array($pem_languages)) {
                    return [];
                }

                foreach ($pem_languages as $language) {
                    if (preg_match('/^.*? \[[A-Z]{2}\]$/', $language['extension_name'])) {
                        $this->server_languages[$language['extension_id']] = $language;
                    }
                }
                uasort($this->server_languages, [$this, 'extensionNameCompare']);
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
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
    public function extractLanguageFiles($action, $revision, $dest = '')
    {
        $archive = tempnam(self::$languages_root_path . '/language', 'zip');
        $get_data = [
            'rid' => $revision,
            'origin' => 'phyxo_' . $action,
        ];

        $this->directory_pattern = '/^[a-z]{2}_[A-Z]{2}$/';
        try {
            $this->download($get_data, $archive);
        } catch (\Exception $e) {
            throw new \Exception("Cannot download language archive");
        }

        $extract_path = self::$languages_root_path . '/language';
        try {
            $this->extractZipFiles($archive, 'common.lang.php', $extract_path);
            $this->getFsLanguages();
            if ($action == 'install') {
                $this->performAction('activate', $dest);
            }
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
    public function extensionNameCompare($a, $b)
    {
        return strcmp(strtolower($a['extension_name']), strtolower($b['extension_name']));
    }
}
