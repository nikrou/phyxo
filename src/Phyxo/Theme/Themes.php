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

use Phyxo\Functions\Utils;
use Exception;
use App\DataMapper\UserMapper;
use App\Entity\Theme;
use Phyxo\Extension\Extensions;
use App\Repository\ThemeRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * @phpstan-type ThemeParameters array{name: string, description: ?string, parent?: string, screenshot?: string, version: string, uri: ?string, author: string, admin_uri: ?bool}
 */
class Themes extends Extensions
{
    final public const CONFIG_FILE = 'config.yaml';
    private ?string $themes_root_path = null;
    private array $fs_themes = [];
    private $db_themes = [];
    private array $server_themes = [];
    private bool $fs_themes_retrieved = false;
    private bool $db_themes_retrieved = false;
    private bool $server_themes_retrieved = false;

    public function __construct(private readonly ThemeRepository $themeRepository, private readonly UserMapper $userMapper)
    {
    }

    public function setRootPath(string $themes_root_path): void
    {
        $this->themes_root_path = $themes_root_path;
    }

    /**
     * Returns the maintain class of a theme
     * or build a new class with the procedural methods
     */
    private function buildMaintainClass(string $theme_id)
    {
        $file_to_include = $this->themes_root_path . '/' . $theme_id . '/admin/maintain.inc.php';
        $classname = $theme_id . '_maintain';

        if (file_exists($file_to_include)) {
            include_once $file_to_include;

            if (class_exists($classname)) {
                return new $classname($theme_id);
            }
        }

        return new DummyThemeMaintain($theme_id);
    }

    /**
     * Perform requested actions
     */
    public function performAction($action, string $theme_id): string
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
                $theme = new Theme();
                $theme->setId($theme_id);
                $theme->setVersion($this->fs_themes[$theme_id]['version']);
                $theme->setName($this->fs_themes[$theme_id]['name']);
                $this->themeRepository->addTheme($theme);
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
                    $random_theme = $this->themeRepository->find($theme_id);
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
                if ((is_countable($children) ? count($children) : 0) > 0) {
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

    /**
     * @return mixed[]
     */
    public function getChildrenThemes($theme_id): array
    {
        $children = [];

        foreach ($this->getFsThemes() as $test_child) {
            if (isset($test_child['parent']) && $test_child['parent'] == $theme_id) {
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

    /**
     * @return array<string, mixed>
     */
    public function getFsExtensions(): array
    {
        return $this->getFsThemes();
    }

    /**
     *  Get themes defined in the theme directory
     *
     * @return array<string, mixed>
     */
    public function getFsThemes(): array
    {
        if (!$this->fs_themes_retrieved) {
            foreach (glob($this->themes_root_path . '/*') as $theme_dir) {
                if (!is_readable($theme_dir . '/' . self::CONFIG_FILE)) {
                    continue;
                }

                $this->getFsTheme(basename($theme_dir));
            }

            $this->fs_themes_retrieved = true;
        }

        return $this->fs_themes;
    }

    public function getFsTheme(string $theme_id): void
    {
        $path = $this->themes_root_path . '/' . $theme_id;
        $config_file = $path . '/' . self::CONFIG_FILE;

        $this->fs_themes[$theme_id] = self::loadThemeParameters($config_file, $theme_id, $this->themes_root_path);
    }

    /**
     * @return ThemeParameters
     */
    public static function loadThemeParameters(string $config_file, string $theme_id, string $themes_root_path): array
    {
        $theme = [
            'id' => $theme_id,
            'name' => $theme_id,
            'version' => '0',
            'uri' => '',
            'description' => '',
            'author' => '',
            'admin_uri' => false,
        ];

        $theme_data = Yaml::parse(file_get_contents($config_file));
        if (!empty($theme_data['uri']) && ($pos = strpos((string) $theme_data['uri'], 'extension_view.php?eid=')) !== false) {
            [, $extension] = explode('extension_view.php?eid=', (string) $theme_data['uri']);
            if (is_numeric($extension)) {
                $theme_data['extension'] = (int) $extension;
            }
        }

        $screenshot_path = $theme_id . '/screenshot.png';
        if (is_readable($themes_root_path . '/' . $screenshot_path)) {
            $theme['screenshot'] = 'themes/' . $screenshot_path;
        }

        return array_merge($theme, $theme_data);
    }

    /**
     * Sort fs_themes
     */
    public function sortFsThemes($order = 'name'): void
    {
        if (!$this->fs_themes_retrieved) {
            $this->getFsThemes();
        }

        switch ($order) {
            case 'name':
                uasort($this->fs_themes, Utils::nameCompare(...));
                break;
            case 'status':
                $this->sortThemesByState();
                break;
            case 'author':
                uasort($this->fs_themes, $this->themeAuthorCompare(...));
                break;
            case 'id':
                uksort($this->fs_themes, 'strcasecmp');
                break;
        }
    }

    /**
     * Retrieve PEM server datas to $server_themes
     */
    public function getServerThemes(string $pem_category, string $phyxo_version, $new = false): array
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
                if ($pem_versions !== [] && !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                    $version = $pem_versions[0]['name'];
                }

                $branch = Utils::getBranchFromVersion($version);
                foreach ($pem_versions as $pem_version) {
                    if (str_starts_with((string) $pem_version['name'], $branch)) {
                        $versions_to_check[] = $pem_version['id'];
                    }
                }
            } catch (Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode(), $e);
            }

            if ($versions_to_check === []) {
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
                    'lang' => $this->userMapper->getUser()->getLang(),
                    'get_nb_downloads' => 'true',
                ]
            );

            if ($themes_to_check !== []) {
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
            } catch (Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode(), $e);
            }

            $this->server_themes_retrieved = true;
        }

        return $this->server_themes;
    }

    /**
     * Sort $server_themes
     */
    public function sortServerThemes($order = 'date'): void
    {
        switch ($order) {
            case 'date':
                krsort($this->server_themes);
                break;
            case 'revision':
                usort($this->server_themes, $this->extensionRevisionCompare(...));
                break;
            case 'name':
                uasort($this->server_themes, $this->extensionNameCompare(...));
                break;
            case 'author':
                uasort($this->server_themes, $this->extensionAuthorCompare(...));
                break;
            case 'downloads':
                usort($this->server_themes, $this->extensionDownloadsCompare(...));
                break;
        }
    }

    /**
     * Extract theme files from archive
     */
    public function extractThemeFiles(string $action, int $revision): void
    {
        $archive = tempnam($this->themes_root_path, 'zip');
        $get_data = [
            'rid' => $revision,
            'origin' => 'phyxo_' . $action
        ];

        try {
            $this->download($archive, $get_data);
        } catch (Exception $exception) {
            throw new Exception("Cannot download theme archive", $exception->getCode(), $exception);
        }

        $extract_path = $this->themes_root_path;
        try {
            $this->extractZipFiles($archive, self::CONFIG_FILE, $extract_path);
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode(), $exception);
        } finally {
            unlink($archive);
        }
    }

    /**
     * Sort functions
     */
    public function extensionRevisionCompare(array $a, array $b): int
    {
        if ($a['revision_date'] < $b['revision_date']) {
            return 1;
        } else {
            return -1;
        }
    }

    public function extensionNameCompare(array $a, array $b): int
    {
        return strcmp(strtolower((string) $a['extension_name']), strtolower((string) $b['extension_name']));
    }

    public function extensionAuthorCompare(array $a, array $b): int
    {
        $r = strcasecmp((string) $a['author_name'], (string) $b['author_name']);
        if ($r == 0) {
            return $this->extensionNameCompare($a, $b);
        } else {
            return $r;
        }
    }

    public function themeAuthorCompare(array $a, array $b): int
    {
        $r = strcasecmp((string) $a['author'], (string) $b['author']);
        if ($r == 0) {
            return Utils::nameCompare($a, $b);
        } else {
            return $r;
        }
    }

    public function extensionDownloadsCompare(array $a, array $b): int
    {
        if ($a['extension_nb_downloads'] < $b['extension_nb_downloads']) {
            return 1;
        } else {
            return -1;
        }
    }

    public function sortThemesByState(): void
    {
        uasort($this->fs_themes, Utils::nameCompare(...));

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
