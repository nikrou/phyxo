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
 * Represents a menu block registered in a BlockManager object.
 */
class RegisteredBlock
{
    private $dataCallback;

    public function __construct(private readonly string $id, private readonly string $name, private readonly string $owner, ?callable $dataCallback = null)
    {
        $this->dataCallback = $dataCallback;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function applyData(DisplayBlock $block): void
    {
        if (!is_null($this->dataCallback)) {
            call_user_func($this->dataCallback, $block);
        }
    }
}
