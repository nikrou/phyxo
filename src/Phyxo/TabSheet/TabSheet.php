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

namespace Phyxo\TabSheet;

use Traversable;

/**
 * @phpstan-type Item array{caption: string, url: string, selected: bool, icon: string}
 */
class TabSheet implements \IteratorAggregate
{
    /**
     * @var array<string, Item>
     */
    private $elements = [];

    public function add(string $name, string $caption, string $url, string $icon = ''): void
    {
        if (!isset($this->elements[$name])) {
            $this->elements[$name] = [
                'caption' => $caption,
                'url' => $url,
                'selected' => false,
                'icon' => $icon,
            ];
        }
    }

    public function delete(string $name): void
    {
        if (isset($this->elements[$name])) {
            unset($this->elements[$name]);
        }
    }

    public function select(string $name): void
    {
        if (!empty($this->elements[$name])) {
            $this->elements[$name]['selected'] = true;
        }
    }

    /**
     * @return \ArrayIterator<string, Item>
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->elements);
    }
}
