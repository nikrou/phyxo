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

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class ActivationKeyEvent extends Event
{
    public function __construct(private readonly string $activation_key, private readonly User $user)
    {
    }

    public function getActivationKey(): string
    {
        return $this->activation_key;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
