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

namespace App\Asset;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;

class PathVersionStrategy implements VersionStrategyInterface
{
    private $manifestData = [];

    public function __construct(RequestStack $request, string $defaultTheme, KernelInterface $kernel)
    {
        if (is_null($request) || is_null($request->getCurrentRequest())) {
            return;
        }

        if (preg_match('`^/admin/`', $request->getCurrentRequest()->getPathInfo())) { // Do not add public themes on admin URLs
            $this->readManifestPath(sprintf('%s/%s/admin/theme/build/manifest.json', $kernel->getProjectDir(), $this->getPublicDirectory($kernel)));

            // need append not replace
            if (preg_match('`^/admin/theme/`', $request->getCurrentRequest()->getPathInfo())) {
                $theme = $request->getCurrentRequest()->attributes->get('_theme', $defaultTheme);
                $this->readManifestPath(sprintf('%s/%s/themes/%s/build/manifest.json', $kernel->getProjectDir(), $this->getPublicDirectory($kernel), $theme));
            }
        } else {
            $theme = $request->getCurrentRequest()->attributes->get('_theme', $defaultTheme);
            $this->readManifestPath(sprintf('%s/%s/themes/%s/build/manifest.json', $kernel->getProjectDir(), $this->getPublicDirectory($kernel), $theme));
        }
    }

    public function getVersion($path)
    {
        return $this->applyVersion($path);
    }

    public function applyVersion($path)
    {
        return $this->getManifestPath($path) ?: $path;
    }

    private function getManifestPath($path): ?string
    {
        return isset($this->manifestData[$path]) ? $this->manifestData[$path] : null;
    }

    private function readManifestPath(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $this->manifestData = array_merge($this->manifestData, json_decode(file_get_contents($path), true));
        if (json_last_error() > 0) {
            throw new \RuntimeException(sprintf('Error parsing JSON from asset manifest file "%s" - %s', $path, json_last_error_msg()));
        }
    }

    private function getPublicDirectory(KernelInterface $kernel): string
    {
        $defaultPublicDir = 'public';

        $composerFilePath = $kernel->getProjectDir() . '/composer.json';

        if (!is_readable($composerFilePath)) {
            return $defaultPublicDir;
        }

        $composerConfig = json_decode(file_get_contents($composerFilePath), true);

        if (isset($composerConfig['extra']['public-dir'])) {
            return $composerConfig['extra']['public-dir'];
        }

        return $defaultPublicDir;
    }
}
