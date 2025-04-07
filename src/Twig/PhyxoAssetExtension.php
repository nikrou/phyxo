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

use Override;
use Symfony\Component\Asset\Context\RequestStackContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PhyxoAssetExtension extends AbstractExtension
{
    public function __construct(private readonly string $publicThemesDir, private readonly RequestStackContext $requestStackContext)
    {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('phyxo_asset', $this->getAssetPath(...)),
        ];
    }

    public function getAssetPath(string $path, string $manifestRelativeFile = ''): string
    {
        if ($manifestRelativeFile === '' || $manifestRelativeFile === '0') {
            return $path;
        }

        $manifestFile = $this->publicThemesDir . '/' . $manifestRelativeFile;
        if (is_readable($manifestFile)) {
            $manifest_content = json_decode((string) file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);
        } else {
            return $manifestFile;
        }

        $assetPath = $manifest_content[$path] ?? '';

        if ($this->isAbsoluteUrl($assetPath)) {
            return $assetPath;
        }

        return $this->requestStackContext->getBasePath() . '/' . $assetPath;
    }

    protected function isAbsoluteUrl(string $url): bool
    {
        return str_contains($url, '://') || str_starts_with($url, '//');
    }
}
