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

use Symfony\Contracts\EventDispatcher\Event;

class CommentEvent extends Event
{
    /**
     * @param array<string, mixed> $comment
     */
    public function __construct(private readonly array $comment, private readonly string $action)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getComment(): array
    {
        return $this->comment;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
