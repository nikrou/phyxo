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

class GroupEvent extends Event
{
    /**
     * @param array<string, string|int|null> $album
     */
    public function __construct(
        private readonly int $group,
        private readonly array $album,
        private readonly string $image_url,
        private readonly string $mail_content,
    ) {
    }

    public function getGroup(): int
    {
        return $this->group;
    }

    /**
     * @return array<string, string>
     */
    public function getAlbum(): array
    {
        return $this->album;
    }

    public function getImageUrl(): string
    {
        return $this->image_url;
    }

    public function getMailContent(): string
    {
        return $this->mail_content;
    }
}
