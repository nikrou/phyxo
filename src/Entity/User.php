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

    public function __construct()
    {
        $this->roles = ['ROLE_USER'];
        $this->userInfos = new UserInfos();
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
}
