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

namespace Phyxo\Extension;

use App\Services\AssetsManager;
use App\Twig\ThemeLoader;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractPlugin implements EventSubscriberInterface
{
    private const CLASSNAME_FORMAT = '\\Plugins\\%s\\%s';
    private const COMMAND_CLASSNAME_FORMAT = 'Plugins\%s\Command';

    public function __construct(protected AssetsManager $assetsManager, private readonly ThemeLoader $themeLoader)
    {
    }

    public static function getClassName(string $plugin_id): string
    {
        return sprintf(self::CLASSNAME_FORMAT, $plugin_id, ucfirst($plugin_id));
    }

    public static function getCommandClassName(string $plugin_id): string
    {
        return sprintf(self::COMMAND_CLASSNAME_FORMAT, $plugin_id);
    }

    public function addPluginCss(string $plugin_id, string $css): void
    {
        $this->addCss('plugin', $plugin_id, $css);
    }

    public function addThemeCss(string $theme_id, string $css): void
    {
        $this->addCss('theme', $theme_id, $css);
    }

    private function addCss(string $extension_type, string $extension_id, string $css): void
    {
        $this->assetsManager->addStylesheet($extension_type, $extension_id, $css);
    }

    public function addPluginScript(string $plugin_id, string $js): void
    {
        $this->addJs('plugin', $plugin_id, $js);
    }

    public function addThemeScript(string $theme_id, string $js): void
    {
        $this->addJs('theme', $theme_id, $js);
    }

    private function addJs(string $extension_type, string $extension_id, string $js): void
    {
        $this->assetsManager->addScript($extension_type, $extension_id, $js);
    }

    public function getThemeLoader(): ThemeLoader
    {
        return $this->themeLoader;
    }
}
