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

namespace App\Events;

use Phyxo\Block\BlockManager;
use Symfony\Contracts\EventDispatcher\Event;

class BlockEvent extends Event
{
    public function __construct(private readonly BlockManager $menu)
    {
    }

    public function getMenu(): BlockManager
    {
        return $this->menu;
    }
}
