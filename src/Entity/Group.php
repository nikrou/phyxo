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

use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=GroupRepository::class)
 * @ORM\Table(name="groups")
 */
class Group
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", unique=true, length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="boolean")
     */
    private $is_default = false;

    /**
     * @ORM\Column(name="lastmodified", type="datetime", nullable=true)
     */
    private $last_modified;

    /**
     * @ORM\ManyToMany(targetEntity=User::class, inversedBy="groups", cascade={"persist", "remove"})
     * @ORM\JoinTable(name="user_group")
     */
    private $users;

    /**
     * @ORM\ManyToMany(targetEntity=Album::class, inversedBy="group_access", cascade={"persist", "remove"})
     * @ORM\JoinTable(name="group_access",
     *   joinColumns={@ORM\JoinColumn(name="group_id")},
     *   inverseJoinColumns={@ORM\JoinColumn(name="cat_id")}
     * )
     */
    private $group_access;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->setLastModified(new \DateTime());
        $this->group_access = new ArrayCollection();
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

    public function isDefault(): ?bool
    {
        return $this->is_default;
    }

    public function setIsDefault(bool $is_default): self
    {
        $this->is_default = $is_default;

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
     * @return Collection|User[]
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->contains($user)) {
            $this->users->removeElement($user);
        }

        return $this;
    }

    /**
     * @return Collection|Album[]
     */
    public function getGroupAccess(): Collection
    {
        return $this->group_access;
    }

    public function addGroupAccess(Album $album): self
    {
        if (!$this->group_access->contains($album)) {
            $this->group_access[] = $album;
        }

        return $this;
    }

    public function removeGroupAccess(Album $album): self
    {
        if ($this->group_access->contains($album)) {
            $this->group_access->removeElement($album);
        }

        return $this;
    }
}
