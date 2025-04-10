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
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'groups')]
#[ORM\Entity(repositoryClass: GroupRepository::class)]
class Group
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::STRING, unique: true, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $is_default = false;

    #[ORM\Column(name: 'lastmodified', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $last_modified;

    /**
     * @var Collection<int, User>
     */
    #[ORM\JoinTable(name: 'user_group')]
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'groups', cascade: ['persist', 'remove'])]
    private Collection $users;

    /**
     * @var Collection<int, Album>
     */
    #[ORM\JoinTable(name: 'group_access')]
    #[ORM\JoinColumn(name: 'group_id')]
    #[ORM\InverseJoinColumn(name: 'cat_id')]
    #[ORM\ManyToMany(targetEntity: Album::class, inversedBy: 'group_access', cascade: ['persist', 'remove'])]
    private Collection $group_access;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->setLastModified(new DateTime());
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

    public function getLastModified(): ?DateTimeInterface
    {
        return $this->last_modified;
    }

    public function setLastModified(DateTimeInterface $last_modified): self
    {
        $this->last_modified = $last_modified;

        return $this;
    }

    /**
     * @return Collection<int, User>
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
     * @return Collection<int, Album>
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
