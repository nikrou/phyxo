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

namespace App\Controller;

use Phyxo\Conf;
use Phyxo\Image\ImageStandardParams;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class CommonController extends AbstractController
{
    protected ImageStandardParams $image_std_params;
    protected string $defaultTheme;
    protected string $themesDir;
    protected Conf $conf;
    protected string $phyxoVersion;
    protected string $phyxoWebsite;

    public function __construct(string $defaultTheme, string $themesDir, Conf $conf, string $phyxoVersion, string $phyxoWebsite)
    {
        $this->defaultTheme = $defaultTheme;
        $this->themesDir = $themesDir;
        $this->conf = $conf;
        $this->phyxoVersion = $phyxoVersion;
        $this->phyxoWebsite = $phyxoWebsite;
    }

    /** @phpstan-ignore-next-line */ // @FIX: define return type
    protected function loadThemeConf(string $theme = null, Conf $core_conf = null): array
    {
        if (empty($theme)) {
            $theme = $this->defaultTheme;
        }

        $themeconf_filename = sprintf('%s/%s/themeconf.inc.php', $this->themesDir, $theme);
        if (!is_readable($themeconf_filename)) {
            return [];
        }

        $extra_params = [];
        ob_start();
        // inject variables and objects in loaded theme
        $conf = $core_conf;
        $extra_params = require $themeconf_filename;
        ob_end_clean();

        return $extra_params;
    }

    /** @phpstan-ignore-next-line */ // @FIX: define return type
    public function addThemeParams(Conf $conf): array
    {
        $tpl_params = [];

        $tpl_params['GALLERY_TITLE'] = $conf['gallery_title'];
        $tpl_params['PAGE_TITLE'] = '';
        $tpl_params['CONTENT_ENCODING'] = 'utf-8';
        $tpl_params['LEVEL_SEPARATOR'] = $conf['level_separator'];
        $tpl_params['category_view'] = 'grid';

        return $tpl_params;
    }
}
