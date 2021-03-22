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
    private $comment, $action;

    public function __construct(array $comment, string $action)
    {
        $this->comment = $comment;
        $this->action = $action;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
