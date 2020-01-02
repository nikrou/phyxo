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
use PclZip;
use Phyxo\EntityManager;
use Symfony\Component\Filesystem\Filesystem;

class Languages extends Extensions
{
    private $em;
    private $languages_root_path, $userMapper;
    private $fs_languages = [], $db_languages = [], $server_languages = [];
    private $fs_languages_retrieved = false, $db_languages_retrieved = false, $server_languages_retrieved = false;

    public function __construct(EntityManager $em = null, UserMapper $userMapper = null)
    {
        if (!is_null($em)) {
            $this->em = $em;
        }

        $this->userMapper = $userMapper;
    }

    public function setRootPath(string $languages_root_path)
    {
        $this->languages_root_path = $languages_root_path;
    }

    public function setEnityManager(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Perform requested actions
     * @param string - action
     * @param string - language id
     * @param array - errors
     */
    function performAction(string $action, string $language_id, array $user_ids = [])
    {
        if (!$this->db_languages_retrieved) {
            $this->getDbLanguages();
        }

        if (!$this->fs_languages_retrieved) {
            $this->getFsLanguages();
        }

        if (isset($this->db_languages[$language_id])) {
            $crt_db_language = $this->db_languages[$language_id];
        }

        $error = '';

        switch ($action) {
            case 'activate':
                if (isset($crt_db_language)) {
                    $error = 'CANNOT ACTIVATE - LANGUAGE IS ALREADY ACTIVATED';
                    break;
                }

                $this->em->getRepository(LanguageRepository::class)->addLanguage($language_id, $this->fs_languages[$language_id]['name'], $this->fs_languages[$language_id]['version']);
                break;

            case 'update':
                try {
                    $new_version = $this->getFsLanguages()[$language_id]['version'];
                    $this->em->getRepository(LanguageRepository::class)->updateLanguage(['version' => $new_version], ['id' => $language_id]);
                } catch (\Exception $e) {
                    $error = $e->getMessage();
                }
                break;

            case 'deactivate':
                if (!isset($crt_db_language)) {
                    $error = 'CANNOT DEACTIVATE - LANGUAGE IS ALREADY DEACTIVATED';
                    break;
                }

                if ($language_id == $this->userMapper->getDefaultLanguage()) {
                    $error = 'CANNOT DEACTIVATE - LANGUAGE IS DEFAULT LANGUAGE';
                    break;
                }

                $this->em->getRepository(LanguageRepository::class)->deleteLanguage($language_id);
                break;

            case 'delete':
                if (!empty($crt_db_language)) {
                    $error = 'CANNOT DELETE - LANGUAGE IS ACTIVATED';
                    break;
                }
                if (!isset($this->fs_languages[$language_id])) {
                    $error = 'CANNOT DELETE - LANGUAGE DOES NOT EXIST';
                    break;
                }

                // Set default language to user who are using this language
                $this->em->getRepository(LanguageRepository::class)->deleteLanguage($language_id);

                // find associated files
                $translation_files = glob($this->languages_root_path . '/*+intl-icu.' . $language_id . '.php');
                $fs = new Filesystem();
                $fs->remove($translation_files);
                break;

            case 'set_default':
                $this->em->getRepository(UserInfosRepository::class)->updateFieldForUsers('language', $language_id, $user_ids);
                break;
        }

        return $error;
    }

    // for Update/Updates
    public function getFsExtensions($target_charset = null)
    {
        return $this->getFsLanguages($target_charset);
    }

    /**
     *  Get languages defined in the language directory
     */
    public function getFsLanguages(): array
    {
        if (!$this->fs_languages_retrieved) {
            foreach (glob($this->languages_root_path . '/messages+intl-icu.*.php') as $messages_file) {
                if (!preg_match('`.*messages\+intl\-icu\.([a-zA-Z0-9-_]+)\.php`', $messages_file, $matches)) {
                    continue;
                }
                $language_code = $matches[1];

                $language = [
                    'name' => $language_code,
                    'code' => $language_code,
                    'version' => '0',
                    'uri' => '',
                    'author' => ''
                ];
                $language_data = file_get_contents($messages_file, false, null, 0, 2048);

                if (preg_match("|Language Name:\\s*(.+)|", $language_data, $val)) {
                    $language['name'] = trim($val[1]);
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

                $this->fs_languages[$language_code] = $language;
            }
            uasort($this->fs_languages, '\Phyxo\Functions\Utils::name_compare');

            $this->fs_languages_retrieved = true;
        }

        return $this->fs_languages;
    }

    public function getDbLanguages(): array
    {
        if (!$this->db_languages_retrieved) {
            $result = $this->em->getRepository(LanguageRepository::class)->findAll();
            $this->db_languages = $this->em->getConnection()->result2array($result, 'id');
            $this->db_languages_retrieved = true;
        }

        return $this->db_languages;
    }

    /**
     * Retrieve PEM server datas to $server_languages
     */
    public function getServerLanguages($new = false, string $pem_category, string $phyxo_version = ''): array
    {
        if (!$this->server_languages_retrieved) {
            $get_data = [
                'category_id' => $pem_category,
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
                'lang' => $this->userMapper->getUser()->getLanguage(),
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
     */
    public function extractLanguageFiles($action, $revision)
    {
        $archive = tempnam($this->languages_root_path, 'zip');
        $get_data = [
            'rid' => $revision,
            'origin' => 'phyxo_' . $action,
        ];

        $this->directory_pattern = '/^$/';
        try {
            $this->download($get_data, $archive);
        } catch (\Exception $e) {
            throw new \Exception("Cannot download language archive");
        }

        try {
            $language_id = $this->extractLanguageZipFiles($archive, $this->languages_root_path);
            $this->getFsLanguages();
            if ($action === 'install') {
                $this->performAction('activate', $language_id);
            } elseif ($action === 'upgrade') {
                $this->performAction('update', $language_id);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        } finally {
            unlink($archive);
        }
    }

    protected function extractLanguageZipFiles(string $zip_file, string $extract_path): string
    {
        // main file is messages+intl-icu.[LANGUAGE_CODE].php

        $language_id = '';
        $zip = new PclZip($zip_file);
        if ($list = $zip->listContent()) {
            // find main file
            foreach ($list as $file) {
                if (isset($file['filename']) && preg_match('`.*messages\+intl\-icu\.([a-zA-Z0-9-_]+)\.php`', basename($file['filename']), $matches)) {
                    $main_filepath = $file['filename'];
                    $language_id = $matches[1];
                }
            }

            if (!empty($main_filepath)) {
                // @TODO: use native zip library ; use arobase before
                if ($results = @$zip->extract(PCLZIP_OPT_PATH, $extract_path, PCLZIP_OPT_REMOVE_PATH, $extract_path, PCLZIP_OPT_REPLACE_NEWER)) {
                    $errors = array_filter($results, function($f) {
                        return ($f['status'] !== 'ok' && $f['status'] !== 'filtered') && $f['status'] !== 'already_a_directory';
                    });
                    if (count($errors) > 0) {
                        throw new \Exception("Error while extracting some files from archive");
                    }
                } else {
                    throw new \Exception("Error while extracting archive");
                }
            }

            return $language_id;
        } else {
            throw new \Exception("Can't read or extract archive.");
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
