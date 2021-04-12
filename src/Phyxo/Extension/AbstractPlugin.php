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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractPlugin implements EventSubscriberInterface
{
    const CLASSNAME_FORMAT = '\\Plugins\\%s\\%s';

    protected $assetsManager;

    public function __construct(AssetsManager $assetsManager)
    {
        $this->assetsManager = $assetsManager;
    }

    public function addPluginCss(string $plugin_id, string $css)
    {
        $this->addCss('plugin', $plugin_id, $css);
    }

    public function addThemeCss(string $theme_id, string $css)
    {
        $this->addCss('theme', $theme_id, $css);
    }

    private function addCss(string $extension_type, string $extension_id, string $css)
    {
        $this->assetsManager->addStylesheet($extension_type, $extension_id, $css);
    }
}
