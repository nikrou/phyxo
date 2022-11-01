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

namespace Phyxo\Block;

/**
 * Represents a menu block ready for display in the BlockManager object.
 */
class DisplayBlock
{
    protected $position;
    protected $title;
    public $data = [];
    public string $template;

    public function __construct(protected RegisteredBlock $registeredBlock)
    {
    }

    public function getBlock(): RegisteredBlock
    {
        return $this->registeredBlock;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getTitle(): string
    {
        if (isset($this->title)) {
            return $this->title;
        } else {
            return $this->registeredBlock->getName();
        }
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
