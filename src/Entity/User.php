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

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;

class User implements UserInterface, EquatableInterface, \ArrayAccess
{
    private $id;
    private $username;
    private $password;
    private $mail_address;
    private $salt;

    private $user_infos = null;
    private $roles = [];

    public function __construct()
    {
        $this->roles = ['ROLE_USER'];
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setUsername(string $username)
    {
        $this->username = $username;
    }

    public function setPassword(? string $password = null)
    {
        $this->password = $password;
    }

    public function setMailAddress(? string $mail_address = null)
    {
        $this->mail_address = $mail_address;
    }

    public function setInfos(UserInfos $user_infos)
    {
        $this->user_infos = $user_infos;

        $this->setRolesByStatus();
    }

    public function getInfos()
    {
        return array_merge(
            [
                'id' => $this->id,
                'username' => $this->username,
                'mail_address' => $this->mail_address,
            ],
            $this->user_infos->asArray()
        );
    }

    protected function setRolesByStatus()
    {
        $this->roles = ['ROLE_USER'];

        if ($this->user_infos['status'] === 'admin') {
            $this->roles[] = 'ROLE_ADMIN';
        }

        if ($this->user_infos['status'] === 'webmaster') {
            $this->roles[] = 'ROLE_WEBMASTER';
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->user_infos[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->user_infos[$offset] ?? null;
    }

    public function offsetSet($offset, $value)
    {
        $this->user_infos[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->user_infos[$offset]);
    }

    public function __call(string $method, array $args)
    {
        if (method_exists($this->user_infos, $method)) {
            return $this->user_infos->$method(...$args);
        }

        return null;
    }

    public function getRoles()
    {
        return array_unique($this->roles);
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getSalt()
    {
        // not needed when using bcrypt or argon
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getMailAddress()
    {
        return $this->mail_address;
    }

    public function eraseCredentials()
    {
        //$this->password = null;
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

        return true;
    }

    public function setNbAvailableTags(int $number_of_tags)
    {
        $this->infos->setNbAvailableTags($number_of_tags);
    }
}
