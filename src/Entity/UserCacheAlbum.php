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

use App\Repository\UserCacheAlbumRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=UserCacheAlbumRepository::class)
 * @ORM\Table(name="user_cache_categories")
 *
 */
class UserCacheAlbum
{
    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="userCacheAlbums", cascade={"persist", "remove"})
     * @ORM\JoinColumn(name="user_id", nullable=false)
     */
    private $user;

    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity=Album::class, inversedBy="userCacheAlbums", cascade={"persist", "remove"})
     * @ORM\JoinColumn(name="cat_id", nullable=false)
     */
    private $album;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date_last;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $max_date_last;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $nb_images;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $count_images;

    /**
     * @ORM\Column(name="nb_categories", type="integer", nullable=true)
     */
    private $nb_albums;

    /**
     * @ORM\Column(name="count_categories", type="integer", nullable=true)
     */
    private $count_albums;

    /**
     * @ORM\Column(name="user_representative_picture_id", type="integer", nullable=true)
     */
    private $user_representative_picture;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getAlbum(): ?Album
    {
        return $this->album;
    }

    public function setAlbum(Album $album): self
    {
        $this->album = $album;

        return $this;
    }

    public function getDateLast(): ?\DateTimeInterface
    {
        return $this->date_last;
    }

    public function setDateLast(?\DateTimeInterface $date_last): self
    {
        $this->date_last = $date_last;

        return $this;
    }

    public function getMaxDateLast(): ?\DateTimeInterface
    {
        return $this->max_date_last;
    }

    public function setMaxDateLast(?\DateTimeInterface $max_date_last): self
    {
        $this->max_date_last = $max_date_last;

        return $this;
    }

    public function getNbImages(): ?int
    {
        return $this->nb_images;
    }

    public function setNbImages(?int $nb_images): self
    {
        $this->nb_images = $nb_images;

        return $this;
    }

    public function getCountImages(): ?int
    {
        return $this->count_images;
    }

    public function setCountImages(?int $count_images): self
    {
        $this->count_images = $count_images;

        return $this;
    }

    public function getNbAlbums(): ?int
    {
        return $this->nb_albums;
    }

    public function setNbAlbums(?int $nb_albums): self
    {
        $this->nb_albums = $nb_albums;

        return $this;
    }

    public function getCountAlbums(): ?int
    {
        return $this->count_albums;
    }

    public function setCountAlbums(?int $count_albums): self
    {
        $this->count_albums = $count_albums;

        return $this;
    }

    public function getUserRepresentativePicture(): ?int
    {
        return $this->user_representative_picture;
    }

    public function setUserRepresentativePicture(?int $user_representative_picture): self
    {
        $this->user_representative_picture = $user_representative_picture;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'album_id' => $this->getAlbum()->getId(),
            'max_date_last' => $this->getMaxDateLast(),
            'date_last' => $this->getDateLast(),
            'nb_images' => $this->getNbImages(),
            'count_images' => $this->getCountImages(),
            'nb_albums' => $this->getNbAlbums(),
            'count_albums' => $this->getCountAlbums(),
            'user_representative_picture' => $this->getUserRepresentativePicture(),
        ];
    }
}
