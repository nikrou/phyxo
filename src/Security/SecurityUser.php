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

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;

class SecurityUser implements UserInterface, EquatableInterface
{
    private $id, $username, $password, $mail_address, $salt, $roles = [], $locale, $theme;

    public function __construct(User $user)
    {
        $this->id = $user->getId();
        $this->username = $user->getUsername();
        $this->password = $user->getPassword();
        $this->mail_address = $user->getMailAddress();
        $this->roles = $user->getRoles();
        $this->locale = $user->getLanguage();
        $this->theme = $user->getTheme();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getSalt(): ?string
    {
        // not needed when using bcrypt or argon
        return null;
    }

    public function getMailAddress()
    {
        return $this->mail_address;
    }

    public function getRoles(): array
    {
        return array_unique($this->roles);
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function eraseCredentials()
    {
        // $this->password = null;
    }

    public function isEqualTo(UserInterface $user)
    {
        if (!$user instanceof SecurityUser) {
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
}
