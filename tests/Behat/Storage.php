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

namespace App\Tests\Behat;

class Storage
{
    /** @var array<string> */
    private array $data;

    public function set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    public function get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }
}
