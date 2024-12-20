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

use App\Entity\Album;
use App\Entity\Image;
use App\Enum\PictureSectionType;
use Symfony\Contracts\EventDispatcher\Event;

class HistoryEvent extends Event
{
    private ?Album $album = null;
    private ?Image $image = null;
    private string $ip;
    private string $tagIds = '';

    public function __construct(private readonly PictureSectionType $section)
    {
    }

    public function getSection(): PictureSectionType
    {
        return $this->section;
    }

    public function setAlbum(Album $album): void
    {
        $this->album = $album;
    }

    public function getAlbum(): ?Album
    {
        return $this->album;
    }

    public function setImage(Image $image): void
    {
        $this->image = $image;
    }

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setIp(string $ip): void
    {
        $this->ip = $ip;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setTagIds(string $tagIds): void
    {
        $this->tagIds = $tagIds;
    }

    public function getTagIds(): string
    {
        return $this->tagIds;
    }
}
