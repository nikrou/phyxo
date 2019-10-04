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

class TabSheet implements \IteratorAggregate
{
    private $elements = [], $position;

    public function __construct()
    {
        $this->position = 0;
    }

    public function add($name, $caption, $url, $icon = '')
    {
        if (!isset($this->elements[$name])) {
            $this->elements[$name] = [
                'caption' => $caption,
                'url' => $url,
                'selected' => false,
                'icon' => $icon,
            ];

            return true;
        }

        return false;
    }

    public function delete($name)
    {
        if (isset($this->elements[$name])) {
            unset($this->elements[$name]);

            return true;
        }

        return false;
    }

    public function select($name)
    {
        if (!empty($this->elements[$name])) {
            $this->elements[$name]['selected'] = true;
        }
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->elements);
    }
}
