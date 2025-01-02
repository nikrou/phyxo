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

use App\Enum\UserStatusType;
use Doctrine\DBAL\Types\Types;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Table(name: 'users')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::STRING, unique: true, length: 100)]
    private string $username;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $password = null;
    private ?string $plain_password = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $mail_address = null;

    #[ORM\OneToOne(targetEntity: UserInfos::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?UserInfos $userInfos;

    /**
     * @var array<string>
     */
    private array $roles = ['ROLE_USER'];

    /**
     * @var Collection<int, Group>
     */
    #[ORM\ManyToMany(targetEntity: Group::class, mappedBy: 'users')]
    private Collection $groups;

    /**
     * @var Collection<int, UserCacheAlbum>
     */
    #[ORM\OneToMany(targetEntity: UserCacheAlbum::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $userCacheAlbums;

    /**
     *
     * @var Collection<int, Album>
     */
    #[ORM\JoinTable(name: 'user_access')]
    #[ORM\JoinColumn(name: 'user_id')]
    #[ORM\InverseJoinColumn(name: 'cat_id')]
    #[ORM\ManyToMany(targetEntity: Album::class, inversedBy: 'user_access')]
    private Collection $user_access;

    #[ORM\OneToOne(targetEntity: UserCache::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?UserCache $userCache = null;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $comments;

    /**
     * @var Collection<int, Caddie>
     */
    #[ORM\OneToMany(targetEntity: Caddie::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $caddies;

    /**
     * @var Collection<int, Favorite>
     */
    #[ORM\OneToMany(targetEntity: Favorite::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $favorites;

    /**
     * @var Collection<int, Rate>
     */
    #[ORM\OneToMany(targetEntity: Rate::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $rates;

    public function __construct()
    {
        $this->userInfos = new UserInfos();
        $this->groups = new ArrayCollection();
        $this->user_access = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->userCacheAlbums = new ArrayCollection();
        $this->caddies = new ArrayCollection();
        $this->favorites = new ArrayCollection();
        $this->rates = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plain_password;
    }

    public function setPlainPassword(?string $plain_password): self
    {
        $this->plain_password = $plain_password;

        return $this;
    }

    public function getMailAddress(): ?string
    {
        return $this->mail_address;
    }

    public function setMailAddress(?string $mail_address): self
    {
        $this->mail_address = $mail_address;

        return $this;
    }

    public function getUserInfos(): UserInfos
    {
        return $this->userInfos;
    }

    public function setUserInfos(UserInfos $userInfos): self
    {
        $this->userInfos = $userInfos;

        // set the owning side of the relation if necessary
        if ($userInfos->getUser() !== $this) {
            $userInfos->setUser($this);
        }

        return $this;
    }

    public function addRole(string $role): void
    {
        $this->roles[] = $role;
    }

    public function getRoles(): array
    {
        return array_unique($this->roles);
    }

    public function eraseCredentials(): void
    {
        $this->plain_password = null;
    }

    public function getSalt(): ?string
    {
        // not needed when using bcrypt or argon
        return null;
    }

    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        if ($this->username !== $user->getUsername()) {
            return false;
        }
        return $this->getRoles() === $user->getRoles();
    }

    public static function getRoleFromStatus(UserStatusType $status): string
    {
        return 'ROLE_' . strtoupper($status->value);
    }

    public function getStatusFromRoles(): UserStatusType
    {
        if (in_array('ROLE_WEBMASTER', $this->roles)) {
            return UserStatusType::WEBMASTER;
        }

        if (in_array('ROLE_ADMIN', $this->roles)) {
            return UserStatusType::ADMIN;
        }

        if (in_array('ROLE_NORMAL', $this->roles)) {
            return UserStatusType::NORMAL;
        }

        return UserStatusType::GUEST;
    }

    /**
     * @return Collection<int, Group>
     */
    public function getGroups(): Collection
    {
        return $this->groups;
    }

    /**
     * @param Group[] $groups
     */
    public function setGroups(array $groups): self
    {
        $this->groups = new ArrayCollection();
        foreach ($groups as $group) {
            $this->addGroup($group);
        }

        return $this;
    }

    public function addGroup(Group $group): self
    {
        if (!$this->groups->contains($group)) {
            $this->groups[] = $group;
            $group->addUser($this);
        }

        return $this;
    }

    public function removeGroup(Group $group): self
    {
        if ($this->groups->contains($group)) {
            $this->groups->removeElement($group);
            $group->removeUser($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Album>
     */
    public function getUserAccess(): Collection
    {
        return $this->user_access;
    }

    public function addUserAccess(Album $album): self
    {
        if (!$this->user_access->contains($album)) {
            $this->user_access[] = $album;
        }

        return $this;
    }

    public function removeUserAccess(Album $album): self
    {
        if ($this->user_access->contains($album)) {
            $this->user_access->removeElement($album);
        }

        return $this;
    }

    public function getUserCache(): ?UserCache
    {
        return $this->userCache;
    }

    public function setUserCache(UserCache $userCache): self
    {
        $this->userCache = $userCache;

        // set the owning side of the relation if necessary
        if ($userCache->getUser() !== $this) {
            $userCache->setUser($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments[] = $comment;
            $comment->setUser($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): self
    {
        if ($this->comments->contains($comment)) {
            $this->comments->removeElement($comment);
            // set the owning side to null (unless already changed)
            if ($comment->getUser() === $this) {
                $comment->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserCacheAlbum>
     */
    public function getUserCacheAlbums(): Collection
    {
        return $this->userCacheAlbums;
    }

    public function addUserCacheAlbum(UserCacheAlbum $userCacheAlbum): self
    {
        if (!$this->userCacheAlbums->contains($userCacheAlbum)) {
            $this->userCacheAlbums[] = $userCacheAlbum;
            $userCacheAlbum->setUser($this);
        }

        return $this;
    }

    public function removeUserCacheAlbum(UserCacheAlbum $userCacheAlbum): self
    {
        if ($this->userCacheAlbums->contains($userCacheAlbum)) {
            $this->userCacheAlbums->removeElement($userCacheAlbum);
            // set the owning side to null (unless already changed)
            if ($userCacheAlbum->getUser() === $this) {
                $userCacheAlbum->setUser($this);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Caddie>
     */
    public function getCaddies(): Collection
    {
        return $this->caddies;
    }

    public function addCaddie(Caddie $caddie): self
    {
        if (!$this->caddies->contains($caddie)) {
            $this->caddies[] = $caddie;
            $caddie->setUser($this);
        }

        return $this;
    }

    public function removeCaddie(Caddie $caddie): self
    {
        if ($this->caddies->contains($caddie)) {
            $this->caddies->removeElement($caddie);
            // set the owning side to null (unless already changed)
            if ($caddie->getUser() === $this) {
                $caddie->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Favorite>
     */
    public function getFavorites(): Collection
    {
        return $this->favorites;
    }

    public function addFavorite(Favorite $favorite): self
    {
        if (!$this->favorites->contains($favorite)) {
            $this->favorites[] = $favorite;
            $favorite->setUser($this);
        }

        return $this;
    }

    public function removeFavorite(Favorite $favorite): self
    {
        if ($this->favorites->contains($favorite)) {
            $this->favorites->removeElement($favorite);
            // set the owning side to null (unless already changed)
            if ($favorite->getUser() === $this) {
                $favorite->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Rate>
     */
    public function getRates(): Collection
    {
        return $this->rates;
    }

    public function addRate(Rate $rate): self
    {
        if (!$this->rates->contains($rate)) {
            $this->rates[] = $rate;
            $rate->setUser($this);
        }

        return $this;
    }

    public function removeRate(Rate $rate): self
    {
        if ($this->rates->contains($rate)) {
            $this->rates->removeElement($rate);
        }

        return $this;
    }

    /**
     * @param array<string, string> $roles
     */
    public function fromArray(int $id, string $username, string $password, array $roles): void
    {
        $this->id = $id;
        $this->username = $username;
        $this->password = $password;
        $this->roles = $roles;
    }

    // proxy methods
    public function getLocale(): ?string
    {
        return $this->getUserInfos()->getLanguage();
    }

    public function getLang(): ?string
    {
        return preg_replace('`_.*`', '', (string) $this->getUserInfos()->getLanguage());
    }

    public function getTheme(): ?string
    {
        return $this->userInfos->getTheme();
    }

    /**
     * @return array<string, string|int|null>
     */
    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'password' => $this->password,
            'roles' => json_encode($this->roles, JSON_THROW_ON_ERROR)
        ];
    }

    /**
     * @param array<string, string|int|null> $data
     */
    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
        $this->username = $data['username'];
        $this->password = $data['password'];
        $this->roles = json_decode((string) $data['roles'], true, 512, JSON_THROW_ON_ERROR);
    }
}
