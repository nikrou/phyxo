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

use App\Repository\ImageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ImageRepository::class)
 * @ORM\Table(name="images")
 */
class Image
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $file;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date_available;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date_creation;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $author = '';

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $hit = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $filesize = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $width = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $height = 0;

    /**
     * @ORM\Column(type="string", length=4, nullable=true)
     */
    private $coi;

    /**
     * @ORM\Column(type="string", length=4, nullable=true)
     */
    private $representative_ext;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date_metadata_update;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $rating_score;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $path;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $storage_category_id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $level = 0;

    /**
     * @ORM\Column(type="string", length=32)
     */
    private $md5sum;

    /**
     * @ORM\Column(type="integer")
     */
    private $added_by;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $rotation;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $latitude;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $longitude;

    /**
     * @ORM\Column(name="lastmodified", type="datetime", nullable=true)
     */
    private $last_modified;

    /**
     * @ORM\OneToMany(targetEntity=ImageAlbum::class, mappedBy="image")
     */
    private $imageAlbums;

    /**
     * @ORM\OneToMany(targetEntity=Comment::class, mappedBy="image")
     */
    private $comments;

    /**
     * @ORM\OneToMany(targetEntity=Rate::class, mappedBy="image", orphanRemoval=true)
     */
    private $rates;

    public function __construct()
    {
        $this->imageAlbums = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->rates = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(string $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function getDateAvailable(): ?\DateTimeInterface
    {
        return $this->date_available;
    }

    public function setDateAvailable(\DateTimeInterface $date_available): self
    {
        $this->date_available = $date_available;

        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDateCreation(?\DateTimeInterface $date_creation): self
    {
        $this->date_creation = $date_creation;

        return $this;
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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(string $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getHit(): ?int
    {
        return $this->hit;
    }

    public function setHit(int $hit): self
    {
        $this->hit = $hit;

        return $this;
    }

    public function getFilesize(): ?int
    {
        return $this->filesize;
    }

    public function setFilesize(int $filesize): self
    {
        $this->filesize = $filesize;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getCoi(): ?string
    {
        return $this->coi;
    }

    public function setCoi(string $coi): self
    {
        $this->coi = $coi;

        return $this;
    }

    public function getRepresentativeExt(): ?string
    {
        return $this->representative_ext;
    }

    public function setRepresentativeExt(string $representative_ext): self
    {
        $this->representative_ext = $representative_ext;

        return $this;
    }

    public function getDateMetadataUpdate(): ?\DateTimeInterface
    {
        return $this->date_metadata_update;
    }

    public function setDateMetadataUpdate(\DateTimeInterface $date_metadata_update): self
    {
        $this->date_metadata_update = $date_metadata_update;

        return $this;
    }

    public function getRatingScore(): ?float
    {
        return $this->rating_score;
    }

    public function setRatingScore(float $rating_score): self
    {
        $this->rating_score = $rating_score;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getStorageCategoryId(): ?int
    {
        return $this->storage_category_id;
    }

    public function setStorageCategoryId(int $storage_category_id): self
    {
        $this->storage_category_id = $storage_category_id;

        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(int $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function getMd5sum(): ?string
    {
        return $this->md5sum;
    }

    public function setMd5sum(string $md5sum): self
    {
        $this->md5sum = $md5sum;

        return $this;
    }

    public function getAddedBy(): ?int
    {
        return $this->added_by;
    }

    public function setAddedBy(int $added_by): self
    {
        $this->added_by = $added_by;

        return $this;
    }

    public function getRotation(): ?int
    {
        return $this->rotation;
    }

    public function setRotation(int $rotation): self
    {
        $this->rotation = $rotation;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getLastModified(): ?\DateTimeInterface
    {
        return $this->last_modified;
    }

    public function setLastModified(\DateTimeInterface $last_modified): self
    {
        $this->last_modified = $last_modified;

        return $this;
    }

    /**
     * @return Collection|ImageAlbum[]
     */
    public function getImageAlbums(): Collection
    {
        return $this->imageAlbums;
    }

    public function addImageAlbum(ImageAlbum $imageAlbum): self
    {
        if (!$this->imageAlbums->contains($imageAlbum)) {
            $this->imageAlbums[] = $imageAlbum;
            $imageAlbum->setImage($this);
        }

        return $this;
    }

    public function removeImageAlbum(ImageAlbum $imageAlbum): self
    {
        if ($this->imageAlbums->contains($imageAlbum)) {
            $this->imageAlbums->removeElement($imageAlbum);
            // set the owning side to null (unless already changed)
            if ($imageAlbum->getImage() === $this) {
                $imageAlbum->setImage(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Comment[]
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments[] = $comment;
            $comment->setImage($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): self
    {
        if ($this->comments->contains($comment)) {
            $this->comments->removeElement($comment);
            // set the owning side to null (unless already changed)
            if ($comment->getImage() === $this) {
                $comment->setImage(null);
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'path' => $this->getPath(),
            'representative_ext' => $this->getRepresentativeExt(),
            'width' => $this->getWidth(),
            'height' => $this->getHeight(),
            'rotation' => $this->getRotation(),
            'hit' => $this->getHit(),
            'file' => $this->getFile(),
            'name' => $this->getName(),
            'comment' => $this->getComment(),
            'rating_score' => $this->getRatingScore(),
            'date_creation' => $this->getDateCreation(),
            'date_available' => $this->getDateAvailable(),
        ];
    }

    public function fromArray(array $values = [])
    {
        if (isset($values['path'])) {
            $this->setPath($values['path']);
        }

        if (isset($values['representative_ext'])) {
            $this->setRepresentativeExt($values['representative_ext']);
        }

        if (isset($values['width'])) {
            $this->setWidth($values['width']);
        }

        if (isset($values['height'])) {
            $this->setHeight($values['height']);
        }

        if (isset($values['rotation'])) {
            $this->setRotation($values['rotation']);
        }

        if (isset($values['hit'])) {
            $this->setHit($values['hit']);
        }

        if (isset($values['file'])) {
            $this->setFile($values['file']);
        }

        if (isset($values['name'])) {
            $this->setName($values['name']);
        }

        if (isset($values['comment'])) {
            $this->setComment($values['comment']);
        }

        if (isset($values['rating_score'])) {
            $this->setRatingScore($values['rating_score']);
        }

        if (isset($values['date_creation'])) {
            $this->setDateCreation($values['date_creation']);
        }

        if (isset($values['date_available'])) {
            $this->setDateAvailable($values['date_available']);
        }
    }

    /**
     * @return Collection|Rate[]
     */
    public function getRates(): Collection
    {
        return $this->rates;
    }

    public function addRate(Rate $rate): self
    {
        if (!$this->rates->contains($rate)) {
            $this->rates[] = $rate;
            $rate->setImage($this);
        }

        return $this;
    }

    public function removeRate(Rate $rate): self
    {
        if ($this->rates->contains($rate)) {
            $this->rates->removeElement($rate);
            // set the owning side to null (unless already changed)
            if ($rate->getImage() === $this) {
                $rate->setImage(null);
            }
        }

        return $this;
    }
}
