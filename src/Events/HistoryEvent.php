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
use Symfony\Contracts\EventDispatcher\Event;

class HistoryEvent extends Event
{
    private $section, $album = null, $image = null, $ip;

    public function __construct(string $section)
    {
        $this->section = $section;
    }

    public function getSection()
    {
        return $this->section;
    }

    public function setAlbum(Album $album)
    {
        $this->album = $album;
    }

    public function getAlbum(): ?Album
    {
        return $this->album;
    }

    public function setImage(Image $image)
    {
        $this->image = $image;
    }

    public function getImage(): Image
    {
        return $this->image;
    }

    public function setIp(string $ip)
    {
        $this->ip = $ip;
    }

    public function getIp(): string
    {
        return $this->ip;
    }
}
