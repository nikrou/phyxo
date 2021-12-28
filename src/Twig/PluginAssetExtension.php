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

 namespace App\Twig;

use App\Services\AssetsManager;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PluginAssetExtension extends AbstractExtension
{
    private $assetsManager, $router;

    public function __construct(AssetsManager $assetsManager, RouterInterface $router)
    {
        $this->assetsManager = $assetsManager;
        $this->router = $router;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('publicHeadContent', [$this, 'publicHeadContent'], ['is_safe' => ['html']]),
            new TwigFunction('publicFooterContent', [$this, 'publicFooterContent'], ['is_safe' => ['html']]),

        ];
    }

    public function publicHeadContent()
    {
        $links = [];
        foreach ($this->assetsManager->getStylesheets() as $stylesheet) {
            $links[] = sprintf('<link rel="stylesheet" href="%s">', $this->router->generate('plugin_asset', ['id' => $stylesheet['id'], 'path' => $stylesheet['path']]));
        }

        return implode('', $links);
    }

    public function publicFooterContent()
    {
        $scripts = [];
        foreach ($this->assetsManager->getScripts() as $script) {
            $scripts[] = sprintf('<script src="%s"></script>', $this->router->generate('plugin_asset', ['id' => $script['id'], 'path' => $script['path']]));
        }

        return implode('', $scripts);
    }
}
