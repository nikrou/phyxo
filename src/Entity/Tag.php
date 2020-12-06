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

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TagRepository::class)
 * @ORM\Table(name="tags")
 */
class Tag
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $url_name;

    /**
     * @ORM\Column(name="lastmodified", type="datetime", nullable=true)
     */
    private $last_modified;

    /**
     * @ORM\OneToMany(targetEntity=ImageTag::class, mappedBy="tag", cascade={"persist", "remove"})
     */
    private $imageTags;

    private $counter = 0;
    private $level = 0;
    // validated, status, created_by
    private $related_image_tag_infos = [];

    public function __construct()
    {
        $this->imageTags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getUrlName(): ?string
    {
        return $this->url_name;
    }

    public function setUrlName(string $url_name): self
    {
        $this->url_name = $url_name;

        return $this;
    }

    public function getLastModified(): ?\DateTimeInterface
    {
        return $this->last_modified;
    }

    public function setLastModified(?\DateTimeInterface $last_modified): self
    {
        $this->last_modified = $last_modified;

        return $this;
    }

    public function setCounter(int $counter)
    {
        $this->counter = $counter;

        return $this;
    }

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function setLevel(int $level)
    {
        $this->level = $level;

        return $this;
    }

    public function setRelatedImageTagInfos(ImageTag $imageTag): self
    {
        $this->related_image_tag_infos = [
            'status' => $imageTag->getStatus(),
            'validated' => $imageTag->isValidated(),
            'created_by' => $imageTag->getCreatedBy()->getId()
        ];

        return $this;
    }

    public function getRelatedImageTagInfos(): array
    {
        return $this->related_image_tag_infos;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @return Collection|ImageTag[]
     */
    public function getImageTags(): Collection
    {
        return $this->imageTags;
    }

    public function addImageTag(ImageTag $imageTag): self
    {
        if (!$this->imageTags->contains($imageTag)) {
            $this->imageTags[] = $imageTag;
            $imageTag->setTag($this);
        }

        return $this;
    }

    public function removeImageTag(ImageTag $imageTag): self
    {
        if ($this->imageTags->contains($imageTag)) {
            $this->imageTags->removeElement($imageTag);
            // set the owning side to null (unless already changed)
            if ($imageTag->getTag() === $this) {
                $imageTag->setTag(null);
            }
        }

        return $this;
    }

    public function toUrl(string $tag_url_style = 'id-tag'): string
    {
        $url_tag = $this->getId();

        if (($tag_url_style === 'id-tag') && $this->getUrlName() !== '') {
            $url_tag .= '-' . $this->getUrlName();
        }

        return $url_tag;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'url_name' => $this->getUrlName(),
            'last_modified' => $this->getLastModified(),
            'counter' => $this->getCounter(),
            'level' => $this->getLevel(),
            'related_image_tag_infos' => $this->getRelatedImageTagInfos(),
        ];
    }
}
