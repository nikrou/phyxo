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

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use App\Repository\ImageAlbumRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'image_category')]
#[ORM\Entity(repositoryClass: ImageAlbumRepository::class)]
class ImageAlbum
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Image::class, inversedBy: 'imageAlbums')]
    #[ORM\JoinColumn(name: 'image_id', nullable: false)]
    private Image $image;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Album::class, inversedBy: 'imageAlbums')]
    #[ORM\JoinColumn(name: 'category_id', nullable: false)]
    private Album $album;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $rank = null;

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setImage(?Image $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getAlbum(): ?Album
    {
        return $this->album;
    }

    public function setAlbum(?Album $album): self
    {
        $this->album = $album;

        return $this;
    }

    public function getRank(): ?int
    {
        return $this->rank;
    }

    public function setRank(?int $rank): self
    {
        $this->rank = $rank;

        return $this;
    }
}
