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

use App\Repository\HistoryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=HistoryRepository::class)
 */
class History
{
    const SECTION_ALBUMS = 'categories';
    const SECTION_TAGS = 'tags';
    const SECTION_SEARCH = 'search';
    const SECTION_LIST = 'list';
    const SECTION_FAVORITES = 'favorites';
    const SECTION_MOST_VISITED = 'most_visited';
    const SECTION_BEST_RATED = 'best_rated';
    const SECTION_RECENT_PICS = 'recent_pics';
    const SECTION_RECENT_ALBUMS = 'recent_cats';

    const IMAGE_TYPE_PICTURE = 'picture';
    const IMAGE_TYPE_HIGH = 'high';
    const IMAGE_TYPE_OTHER = 'other';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="date")
     */
    private $date;

    /**
     * @ORM\Column(type="time")
     */
    private $time;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $ip;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $section;

    /**
     * @ORM\ManyToOne(targetEntity=Album::class)
     * @ORM\JoinColumn(name="category_id", nullable=true)
     */
    private $album = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $tag_ids = '';

    /**
     * @ORM\ManyToOne(targetEntity=Image::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $image;

    /**
     * @ORM\Column(type="boolean")
     */
    private $summarized = false;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $image_type = self::IMAGE_TYPE_PICTURE;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getTime(): ?\DateTimeInterface
    {
        return $this->time;
    }

    public function setTime(\DateTimeInterface $time): self
    {
        $this->time = $time;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getSection(): ?string
    {
        return $this->section;
    }

    public function setSection(string $section): self
    {
        $this->section = $section;

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

    public function getTagIds(): ?string
    {
        return $this->tag_ids;
    }

    public function setTagIds(string $tag_ids): self
    {
        $this->tag_ids = $tag_ids;

        return $this;
    }

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setImage(?Image $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getSummarized(): ?bool
    {
        return $this->summarized;
    }

    public function setSummarized(bool $summarized): self
    {
        $this->summarized = $summarized;

        return $this;
    }

    public function getImageType(): ?string
    {
        return $this->image_type;
    }

    public function setImageType(string $image_type): self
    {
        $this->image_type = $image_type;

        return $this;
    }
}
