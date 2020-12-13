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

use App\DataMapper\UserMapper;
use App\Entity\Theme;
use Phyxo\Extension\Extensions;
use Phyxo\Theme\DummyThemeMaintain;
use App\Repository\ThemeRepository;
use Symfony\Component\Filesystem\Filesystem;

class Themes extends Extensions
{
    private $themeRepository;
    private $themes_root_path, $userMapper;
    private $fs_themes = [], $db_themes = [], $server_themes = [];
    private $fs_themes_retrieved = false, $db_themes_retrieved = false, $server_themes_retrieved = false;

    public function __construct(ThemeRepository $themeRepository, UserMapper $userMapper)
    {
        $this->themeRepository = $themeRepository;
        $this->userMapper = $userMapper;
    }

    public function setRootPath(string $themes_root_path)
    {
        $this->themes_root_path = $themes_root_path;
    }

    /**
     * Returns the maintain class of a theme
     * or build a new class with the procedural methods
     * @param string $theme_id
     */
    private function buildMaintainClass($theme_id)
    {
        $file_to_include = $this->themes_root_path . '/' . $theme_id . '/admin/maintain.inc.php';
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
     */
    public function performAction($action, $theme_id): string
    {
        if (!$this->db_themes_retrieved) {
            $this->getDbThemes();
        }

        if (!$this->fs_themes_retrieved) {
            $this->getFsThemes();
        }

        if (isset($this->db_themes[$theme_id])) {
            $crt_db_theme = $this->db_themes[$theme_id];
        }

        $theme_maintain = $this->buildMaintainClass($theme_id);

        $error = '';

        switch ($action) {
            case 'activate':
                if (isset($crt_db_theme)) {
                    // the theme is already active
                    break;
                }

                $missing_parent = $this->missingParentTheme($theme_id);
                if (isset($missing_parent)) {
                    $error = sprintf('Impossible to activate this theme, the parent theme is missing: %s', $missing_parent);

                    break;
                }

                $theme_maintain->activate($this->fs_themes[$theme_id]['version'], $error);

                if (empty($error)) {
                    $theme = new Theme();
                    $theme->setId($theme_id);
                    $theme->setVersion($this->fs_themes[$theme_id]['version']);
                    $theme->setName($this->fs_themes[$theme_id]['name']);
                    $this->themeRepository->addTheme($theme);
                }
                break;

            case 'deactivate':
                if (!isset($crt_db_theme)) {
                    // the theme is already inactive
                    break;
                }

                // you can't deactivate the last theme
                if (count($this->db_themes) <= 1) {
                    $error = 'Impossible to deactivate this theme, you need at least one theme.';
                    break;
                }

                if ($this->userMapper->getDefaultTheme() === $theme_id) {
                    // find a random theme to replace
                    $random_theme = $this->themeRepository->findById($theme_id);
                    if (is_null($random_theme)) {
                        $new_theme = 'treflez'; // @TODO: find default theme instead
                    } else {
                        $new_theme = $random_theme->getId();
                    }

                    $this->userMapper->setDefaultTheme($new_theme);
                }

                $theme_maintain->deactivate();
                $this->themeRepository->deleteById($theme_id);
                break;

            case 'delete':
                if (!empty($crt_db_theme)) {
                    $error = 'CANNOT DELETE - THEME IS INSTALLED';
                    break;
                }
                if (!isset($this->fs_themes[$theme_id])) {
                    // nothing to do here
                    break;
                }

                $children = $this->getChildrenThemes($theme_id);
                if (count($children) > 0) {
                    $error = sprintf('Impossible to delete this theme. Other themes depends on it: %s', implode(', ', $children));
                    break;
                }

                $theme_maintain->delete();
                $fs = new Filesystem();
                $fs->remove([$this->themes_root_path . '/' . $theme_id, $this->themes_root_path . '/trash']);
                break;
        }

        return $error;
    }

    public function missingParentTheme($theme_id)
    {
        $this->getFsThemes();

        if (!isset($this->fs_themes[$theme_id]['parent'])) {
            return null;
        }

        $parent = $this->fs_themes[$theme_id]['parent'];

        if ($parent === 'treflez') {
            return null;
        }

        if (!isset($this->fs_themes[$parent])) {
            return $parent;
        }

        return $this->missingParentTheme($parent);
    }

    public function getChildrenThemes($theme_id)
    {
        $children = [];

        foreach ($this->getFsThemes() as $test_child) {
            if (isset($test_child['parent']) and $test_child['parent'] == $theme_id) {
                $children[] = $test_child['name'];
            }
        }

        return $children;
    }

    public function getDbThemes()
    {
        if (!$this->db_themes_retrieved) {
            foreach ($this->themeRepository->findAll() as $theme) {
                $this->db_themes[$theme->getId()] = $theme;
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
        if (!$this->fs_themes_retrieved) {
            foreach (glob($this->themes_root_path . '/*/themeconf.inc.php') as $themeconf) {
                $theme_dir = basename(dirname($themeconf));
                if (!preg_match('`^[a-zA-Z0-9-_]+$`', $theme_dir)) {
                    continue;
                }

                $theme = [
                    'id' => $theme_dir,
                    'name' => $theme_dir,
                    'version' => '0',
                    'uri' => '',
                    'description' => '',
                    'author' => '',
                    'mobile' => false,
                ];
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
                if ($desc = \Phyxo\Functions\Language::loadLanguageFile('description.' . $this->userMapper->getUser()->getLanguage() . '.txt', dirname($themeconf))) {
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
                    if (is_numeric($extension)) {
                        $theme['extension'] = $extension;
                    }
                }
                if (preg_match('/["\']parent["\'][^"\']+["\']([^"\']+)["\']/', $theme_data, $val)) {
                    $theme['parent'] = $val[1];
                }
                if (preg_match('/["\']activable["\'].*?(true|false)/i', $theme_data, $val)) {
                    $theme['activable'] = $val[1] !== 'false';
                }
                if (preg_match('/["\']mobile["\'].*?(true|false)/i', $theme_data, $val)) {
                    $theme['mobile'] = $val[1] !== 'false';
                }

                // screenshot
                $screenshot_path = $theme_dir . '/screenshot.png';
                if (is_readable($this->themes_root_path . '/' . $screenshot_path)) {
                    $theme['screenshot'] = "themes/$screenshot_path";
                } else {
                    $theme['screenshot'] = 'admin/theme/images/missing_screenshot.png';
                }

                $admin_file = dirname($themeconf) . '/admin/admin.inc.php';
                $theme['admin_uri'] = file_exists($admin_file);
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
                uasort($this->fs_themes, '\Phyxo\Functions\Utils::name_compare');
                break;
            case 'status':
                $this->sortThemesByState();
                break;
            case 'author':
                uasort($this->fs_themes, [$this, 'themeAuthorCompare']);
                break;
            case 'id':
                uksort($this->fs_themes, 'strcasecmp');
                break;
        }
    }

    /**
     * Retrieve PEM server datas to $server_themes
     */
    public function getServerThemes($new = false, string $pem_category, string $phyxo_version = '')
    {
        if (!$this->server_themes_retrieved) {
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

            // Themes to check
            $themes_to_check = [];
            foreach ($this->getFsThemes() as $fs_theme) {
                if (isset($fs_theme['extension'])) {
                    $themes_to_check[] = $fs_theme['extension'];
                }
            }

            // Retrieve PEM themes infos
            $url = $this->pem_url . '/api/get_revision_list.php';
            $get_data = array_merge(
                $get_data,
                [
                    'last_revision_only' => 'true',
                    'version' => implode(',', $versions_to_check),
                    'lang' => substr($this->userMapper->getUser()->getLanguage(), 0, 2),
                    'get_nb_downloads' => 'true',
                ]
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
                    return [];
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
                usort($this->server_themes, [$this, 'extensionRevisionCompare']);
                break;
            case 'name':
                uasort($this->server_themes, [$this, 'extensionNameCompare']);
                break;
            case 'author':
                uasort($this->server_themes, [$this, 'extensionAuthorCompare']);
                break;
            case 'downloads':
                usort($this->server_themes, [$this, 'extensionDownloadsCompare']);
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
    public function extractThemeFiles($action, $revision)
    {
        $archive = tempnam($this->themes_root_path, 'zip');
        $get_data = [
            'rid' => $revision,
            'origin' => 'phyxo_' . $action
        ];

        try {
            $this->download($get_data, $archive);
        } catch (\Exception $e) {
            throw new \Exception("Cannot download theme archive");
        }

        $extract_path = $this->themes_root_path;
        try {
            $this->extractZipFiles($archive, 'themeconf.inc.php', $extract_path);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        } finally {
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
            return \Phyxo\Functions\Utils::name_compare($a, $b);
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
        uasort($this->fs_themes, '\Phyxo\Functions\Utils::name_compare');

        $active_themes = [];
        $inactive_themes = [];
        $not_installed = [];

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
