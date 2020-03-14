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

class PathVersionStrategy implements VersionStrategyInterface
{
    private $manifestPath, $manifestData;

    public function __construct(RequestStack $request, string $themesDir, string $defaultTheme, string $adminThemeDir)
    {
        if (is_null($request) || is_null($request->getCurrentRequest())) {
            return;
        }
        if (preg_match('`^/admin/`', $request->getCurrentRequest()->getPathInfo())) { // Do not add public themes on admin URLs
            $this->manifestPath = sprintf('%s/build/manifest.json', $adminThemeDir);
        } else {
            $theme = $request->getCurrentRequest()->attributes->get('_theme', $defaultTheme);
            $this->manifestPath = sprintf('%s/%s/build/manifest.json', $themesDir, $theme);
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

    private function getManifestPath($path)
    {
        if ($this->manifestData === null) {
            if (!is_readable($this->manifestPath)) {
                return null;
            }

            $this->manifestData = json_decode(file_get_contents($this->manifestPath), true);
            if (json_last_error() > 0) {
                throw new \RuntimeException(sprintf('Error parsing JSON from asset manifest file "%s" - %s', $this->manifestPath, json_last_error_msg()));
            }
        }

        return isset($this->manifestData[$path]) ? $this->manifestData[$path] : null;
    }
}
