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
use App\Repository\ImageTagRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImageTagRepository::class)]
class ImageTag
{
    final public const int STATUS_TO_ADD = 1;
    final public const int STATUS_TO_DELETE = 0;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Image::class, inversedBy: 'imageTags')]
    #[ORM\JoinColumn(nullable: false)]
    private Image $image;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Tag::class, inversedBy: 'imageTags')]
    #[ORM\JoinColumn(nullable: false)]
    private Tag $tag;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $validated = true;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', nullable: true)]
    private ?User $created_by = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $status = self::STATUS_TO_ADD;

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setImage(?Image $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getTag(): ?Tag
    {
        return $this->tag;
    }

    public function setTag(?Tag $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    public function isValidated(): ?bool
    {
        return $this->validated;
    }

    public function setValidated(bool $validated): self
    {
        $this->validated = $validated;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->created_by;
    }

    public function setCreatedBy(?User $created_by): self
    {
        $this->created_by = $created_by;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(?int $status): self
    {
        $this->status = $status;

        return $this;
    }
}
