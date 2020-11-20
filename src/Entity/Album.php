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

use App\Repository\AlbumRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AlbumRepository::class)
 * @ORM\Table(name="categories")
 */
class Album
{
    const STATUS_PUBLIC = 'public';
    const STATUS_PRIVATE = 'private';

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
     * @ORM\ManyToOne(targetEntity=Album::class, inversedBy="children", cascade={"persist", "remove"})
     * @ORM\JoinColumn(name="id_uppercat", nullable=true)
     */
    private $parent;

    /**
     * @ORM\OneToMany(targetEntity=Album::class, mappedBy="parent")
     */
    private $children;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $dir = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $rank = null;

    /**
     * @ORM\Column(type="string", length=25)
     */
    private $status = self::STATUS_PUBLIC;

    /**
     * @ORM\Column(type="boolean")
     */
    private $visible = true;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $representative_picture_id = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $uppercats = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private $commentable = true;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $global_rank;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $image_order = null;

    /**
     * @ORM\Column(type="string", unique=true, length=255, nullable=true)
     */
    private $permalink = null;

    /**
     * @ORM\Column(name="lastmodified", type="datetime", nullable=true)
     */
    private $last_modified;

    /**
     * @ORM\OneToOne(targetEntity=Site::class, cascade={"persist", "remove"})
     */
    private $site;

    /**
     * @ORM\ManyToMany(targetEntity=Group::class, mappedBy="group_access", cascade={"persist", "remove"})
     */
    private $group_access;

    /**
     * @ORM\ManyToMany(targetEntity=User::class, mappedBy="user_access")
     */
    private $user_access;

    /**
     * @ORM\OneToMany(targetEntity=ImageAlbum::class, mappedBy="album")
     */
    private $imageAlbums;

    /**
     * @ORM\OneToMany(targetEntity=UserCacheAlbum::class, mappedBy="album")
     */
    private $userCacheAlbums;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->group_access = new ArrayCollection();
        $this->user_access = new ArrayCollection();
        $this->imageAlbums = new ArrayCollection();
        $this->userCacheAlbums = new ArrayCollection();
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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getDir(): ?string
    {
        return $this->dir;
    }

    public function setDir(?string $dir): self
    {
        $this->dir = $dir;

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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isVisible(): ?bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function getRepresentativePictureId(): ?int
    {
        return $this->representative_picture_id;
    }

    public function setRepresentativePictureId(?int $representative_picture_id): self
    {
        $this->representative_picture_id = $representative_picture_id;

        return $this;
    }

    public function getUppercats(): ?string
    {
        return $this->uppercats;
    }

    public function setUppercats(?string $uppercats): self
    {
        $this->uppercats = $uppercats;

        return $this;
    }

    public function isCommentable(): ?bool
    {
        return $this->commentable;
    }

    public function setCommentable(bool $commentable): self
    {
        $this->commentable = $commentable;

        return $this;
    }

    public function getGlobalRank(): ?string
    {
        return $this->global_rank;
    }

    public function setGlobalRank(string $global_rank): self
    {
        $this->global_rank = $global_rank;

        return $this;
    }

    public function getImageOrder(): ?string
    {
        return $this->image_order;
    }

    public function setImageOrder(string $image_order): self
    {
        $this->image_order = $image_order;

        return $this;
    }

    public function getPermalink(): ?string
    {
        return $this->permalink;
    }

    public function setPermalink(string $permalink): self
    {
        $this->permalink = $permalink;

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

    public function getSite(): ?Site
    {
        return $this->site;
    }

    public function setSite(?Site $site): self
    {
        $this->site = $site;

        return $this;
    }

    public function isVirtual(): bool
    {
        return !$this->dir;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection|self[]
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children[] = $child;
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): self
    {
        if ($this->children->contains($child)) {
            $this->children->removeElement($child);
            // set the owning side to null (unless already changed)
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Group[]
     */
    public function getGroupAccess(): Collection
    {
        return $this->group_access;
    }

    public function addGroupAccess(Group $group): self
    {
        if (!$this->group_access->contains($group)) {
            $this->group_access[] = $group;
            $group->addGroupAccess($this);
        }

        return $this;
    }

    public function removeGroupAccess(Group $group): self
    {
        if ($this->group_access->contains($group)) {
            $this->group_access->removeElement($group);
            $group->removeGroupAccess($this);
        }

        return $this;
    }

    public function clearAllGroupAccess(): self
    {
        foreach ($this->group_access as $group) {
            $this->group_access->removeElement($group);
            $group->removeGroupAccess($this);
        }

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getUserAccess(): Collection
    {
        return $this->user_access;
    }

    public function addUserAccess(User $user): self
    {
        if (!$this->user_access->contains($user)) {
            $this->user_access[] = $user;
            $user->addUserAccess($this);
        }

        return $this;
    }

    public function removeUserAccess(User $user): self
    {
        if ($this->user_access->contains($user)) {
            $this->user_access->removeElement($user);
            $user->removeUserAccess($this);
        }

        return $this;
    }

    public function clearAllUserAccess(): self
    {
        foreach ($this->user_access as $user) {
            $this->user_access->removeElement($user);
            $user->removeUserAccess($this);
        }

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
            $imageAlbum->setAlbum($this);
        }

        return $this;
    }

    public function removeImageAlbum(ImageAlbum $imageAlbum): self
    {
        if ($this->imageAlbums->contains($imageAlbum)) {
            $this->imageAlbums->removeElement($imageAlbum);
            // set the owning side to null (unless already changed)
            if ($imageAlbum->getAlbum() === $this) {
                $imageAlbum->setAlbum(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|UserCacheAlbum[]
     */
    public function getUserCacheAlbums(): Collection
    {
        return $this->userCacheAlbums;
    }

    public function addUserCacheAlbum(UserCacheAlbum $userCacheAlbum): self
    {
        if (!$this->userCacheAlbums->contains($userCacheAlbum)) {
            $this->userCacheAlbums[] = $userCacheAlbum;
            $userCacheAlbum->setAlbum($this);
        }

        return $this;
    }

    public function removeUserCacheAlbum(UserCacheAlbum $userCacheAlbum): self
    {
        if ($this->userCacheAlbums->contains($userCacheAlbum)) {
            $this->userCacheAlbums->removeElement($userCacheAlbum);
            // set the owning side to null (unless already changed)
            if ($userCacheAlbum->getAlbum() === $this) {
                $userCacheAlbum->setAlbum($this);
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'global_rank' => $this->getGlobalRank(),
            'uppercats' => $this->getUppercats(),
            'comment' => $this->getComment(),
            'commentable' => $this->isCommentable(),
            'status' => $this->getStatus(),
            'rank' => $this->getRank(),
            'visible' => $this->isVisible(),
            'representative_picture_id' => $this->getRepresentativePictureId(),
            'image_order' => $this->getImageOrder()
        ];
    }
}
