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

use App\Enum\PictureSectionType;
use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use App\Repository\HistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'history')]
#[ORM\Entity(repositoryClass: HistoryRepository::class)]
class History
{
    final public const string IMAGE_TYPE_PICTURE = 'picture';
    final public const string IMAGE_TYPE_HIGH = 'high';
    final public const string IMAGE_TYPE_OTHER = 'other';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private DateTimeInterface $date;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private DateTimeInterface $time;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $ip;

    #[ORM\Column(type: Types::STRING, length: 255, enumType: PictureSectionType::class)]
    private PictureSectionType $section;

    #[ORM\ManyToOne(targetEntity: Album::class)]
    #[ORM\JoinColumn(name: 'category_id', nullable: true)]
    private ?Album $album = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $tag_ids = '';

    #[ORM\ManyToOne(targetEntity: Image::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Image $image = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $summarized = false;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $image_type = self::IMAGE_TYPE_PICTURE;

    public function getId(): int
    {
        return $this->id;
    }

    public function getDate(): ?DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getTime(): ?DateTimeInterface
    {
        return $this->time;
    }

    public function setTime(DateTimeInterface $time): self
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

    public function getSection(): ?PictureSectionType
    {
        return $this->section;
    }

    public function setSection(PictureSectionType $section): self
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
