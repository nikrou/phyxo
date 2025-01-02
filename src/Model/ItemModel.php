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

namespace App\Model;

class ItemModel
{
    public function __construct(private readonly string $caption, private readonly string $url, private bool $selected, private readonly string $icon)
    {
    }

    public function getCaption(): string
    {
        return $this->caption;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setSelected(bool $selected): self
    {
        $this->selected = $selected;

        return $this;
    }

    public function getSelected(): bool
    {
        return $this->selected;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }
}
