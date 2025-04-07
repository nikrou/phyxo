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

use App\Model\ItemModel;
use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<ItemModel>
 */
class TabSheet implements IteratorAggregate
{
    /**
     * @var array<string, ItemModel>
     */
    private array $elements = [];

    public function add(string $name, string $caption, string $url, string $icon = ''): void
    {
        if (!isset($this->elements[$name])) {
            $this->elements[$name] = new ItemModel(caption: $caption, url: $url, selected: false, icon: $icon);
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
            $this->elements[$name]->setSelected(true);
        }
    }

    /**
     * @return ArrayIterator<string, ItemModel>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->elements);
    }
}
