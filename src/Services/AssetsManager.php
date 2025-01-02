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

namespace App\Services;

use App\Model\AssetModel;

class AssetsManager
{
    /** @var AssetModel[] */
    private array $stylesheets = [];

    /** @var AssetModel[] */
    private array $scripts = [];

    public function addStylesheet(string $extension_type, string $extension_id, string $path): void
    {
        $this->stylesheets[] = new AssetModel(id: $extension_id, type: $extension_type, path: $path);
    }

    /**
     * @return AssetModel[]
     */
    public function getStylesheets(): array
    {
        return $this->stylesheets;
    }

    public function addScript(string $extension_type, string $extension_id, string $path): void
    {
        $this->scripts[] = new AssetModel(id: $extension_id, type: $extension_type, path: $path);
    }

    /**
     * @return AssetModel[]
     */
    public function getScripts(): array
    {
        return $this->scripts;
    }
}
