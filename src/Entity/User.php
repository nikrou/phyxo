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

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 * @ORM\Table(name="users")
 */
class User implements UserInterface, EquatableInterface
{
    const STATUS_WEBMASTER = 'webmaster';
    const STATUS_ADMIN = 'admin';
    const STATUS_NORMAL = 'normal';
    const STATUS_GUEST = 'guest';

    const ALL_STATUS = [self::STATUS_WEBMASTER, self::STATUS_ADMIN, self::STATUS_NORMAL, self::STATUS_GUEST];

    const STATUS_TO_ROLE = [
        self::STATUS_WEBMASTER => 'ROLE_WEBMASTER',
        self::STATUS_ADMIN => 'ROLE_ADMIN',
        self::STATUS_NORMAL => 'ROLE_NORMAL',
        self::STATUS_GUEST => 'ROLE_USER'
    ];

    private $salt;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", unique=true, length=100)
     */
    private $username;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $mail_address;

    /**
     * @ORM\OneToOne(targetEntity=UserInfos::class, mappedBy="user", cascade={"persist", "remove"})
     */
    private $userInfos;

    private $roles = [];

    /**
     * @ORM\ManyToMany(targetEntity=Group::class, mappedBy="users")
     */
    private $groups;

    /**
     * @ORM\OneToMany(targetEntity=UserCacheAlbum::class, mappedBy="user", cascade={"persist", "remove"})
     */
    private $userCacheAlbums;

    /**
     * @ORM\ManyToMany(targetEntity=Album::class, inversedBy="user_access")
     * @ORM\JoinTable(name="user_access",
     *   joinColumns={@ORM\JoinColumn(name="user_id")},
     *   inverseJoinColumns={@ORM\JoinColumn(name="cat_id")}
     * )
     */
    private $user_access;

    /**
     * @ORM\OneToOne(targetEntity=UserCache::class, mappedBy="user", cascade={"persist", "remove"})
     */
    private $userCache;

    /**
     * @ORM\OneToMany(targetEntity=Comment::class, mappedBy="user")
     */
    private $comments;

    /**
     * @ORM\OneToMany(targetEntity=Caddie::class, mappedBy="user", orphanRemoval=true)
     */
    private $caddies;

    /**
     * @ORM\OneToMany(targetEntity=Favorite::class, mappedBy="user", orphanRemoval=true)
     */
    private $favorites;

    public function __construct()
    {
        $this->roles = ['ROLE_USER'];
        $this->userInfos = new UserInfos();
        $this->groups = new ArrayCollection();
        $this->user_access = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->userCacheAlbums = new ArrayCollection();
        $this->caddies = new ArrayCollection();
        $this->favorites = new ArrayCollection();
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

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

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

    public function getUserInfos(): ?UserInfos
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

    public function addRole(string $role)
    {
        $this->roles[] = $role;
    }

    public function getRoles()
    {
        return array_unique($this->roles);
    }

    public function eraseCredentials()
    {
        //$this->password = null;
    }

    public function getSalt()
    {
        // not needed when using bcrypt or argon
    }

    public function isEqualTo(UserInterface $user)
    {
        if (!$user instanceof User) {
            return false;
        }

        if ($this->password !== $user->getPassword()) {
            return false;
        }

        if ($this->salt !== $user->getSalt()) {
            return false;
        }

        if ($this->username !== $user->getUsername()) {
            return false;
        }

        if ($this->getRoles() !== $user->getRoles()) {
            return false;
        }

        return true;
    }

    public static function getRoleFromStatus(string $status)
    {
        return isset(self::STATUS_TO_ROLE[$status]) ? self::STATUS_TO_ROLE[$status] : 'ROLE_USER';
    }

    public function getStatusFromRoles()
    {
        if (in_array('ROLE_WEBMASTER', $this->roles)) {
            return self::STATUS_WEBMASTER;
        }

        if (in_array('ROLE_ADMIN', $this->roles)) {
            return self::STATUS_ADMIN;
        }

        if (in_array('ROLE_NORMAL', $this->roles)) {
            return self::STATUS_NORMAL;
        }

        return self::STATUS_GUEST;
    }

    public function __call($method, $parameters)
    {
        $userInfos = $this->getUserInfos();
        if (method_exists($userInfos, $method)) {
            return call_user_func_array([$userInfos, $method], $parameters);
        }

        throw new \RuntimeException(sprintf('The "%s()" method does not exist in UserInfos class.', $method));
    }

    public function getLocale(): ?string
    {
        return $this->getUserInfos()->getLanguage();
    }

    public function getLang(): ?string
    {
        return preg_replace('`_.*`', '', $this->getUserInfos()->getLanguage());
    }

    // to remove ?
    public function isGuest(): bool
    {
        return $this->getRoles() === ['ROLE_USER'];
    }

    /**
     * @return Collection|Group[]
     */
    public function getGroups(): Collection
    {
        return $this->groups;
    }

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
     * @return Collection|Album[]
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

    public function setUserCache(?UserCache $userCache): self
    {
        $this->userCache = $userCache;

        // set (or unset) the owning side of the relation if necessary
        $newUser = null === $userCache ? null : $this;
        if ($userCache->getUser() !== $newUser) {
            $userCache->setUser($newUser);
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
     * @return Collection|Caddie[]
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
     * @return Collection|Favorite[]
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
}
