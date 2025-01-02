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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PluginAssetExtension extends AbstractExtension
{
    public function __construct(private readonly AssetsManager $assetsManager, private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('publicHeadContent', $this->publicHeadContent(...), ['is_safe' => ['html']]),
            new TwigFunction('publicFooterContent', $this->publicFooterContent(...), ['is_safe' => ['html']]),

        ];
    }

    public function publicHeadContent(): string
    {
        $links = [];
        foreach ($this->assetsManager->getStylesheets() as $stylesheet) {
            $links[] = sprintf('<link rel="stylesheet" href="%s">', $this->urlGenerator->generate('plugin_asset', ['id' => $stylesheet->getId(), 'path' => $stylesheet->getPath()]));
        }

        return implode('', $links);
    }

    public function publicFooterContent(): string
    {
        $scripts = [];
        foreach ($this->assetsManager->getScripts() as $script) {
            $scripts[] = sprintf('<script src="%s"></script>', $this->urlGenerator->generate('plugin_asset', ['id' => $script->getId(), 'path' => $script->getPath()]));
        }

        return implode('', $scripts);
    }
}
