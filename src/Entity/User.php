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
    const STATUS_WEBMASTER = 'webmaster';
    const STATUS_ADMIN = 'admin';
    const STATUS_NORMAL = 'normal';
    const STATUS_GUEST = 'guest';

    const ALL_STATUS = [self::STATUS_WEBMASTER, self::STATUS_ADMIN, self::STATUS_NORMAL, self::STATUS_GUEST];

    protected $id;
    protected $username;
    protected $password;
    protected $mail_address;
    protected $salt;

    protected $user_infos = null;
    protected $roles = [];

    public function __construct()
    {
        $this->roles = ['ROLE_USER'];
        $this->setInfos(new UserInfos(['status' => self::STATUS_NORMAL]));
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

        if ($this->user_infos['status'] === self::STATUS_NORMAL) {
            $this->roles[] = 'ROLE_NORMAL';
        }

        if ($this->user_infos['status'] === self::STATUS_ADMIN) {
            $this->roles[] = 'ROLE_ADMIN';
        }

        if ($this->user_infos['status'] === self::STATUS_WEBMASTER) {
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

    public function setStatus(string $status)
    {
        $this->user_infos['status'] = $status;
        $this->setRolesByStatus();
    }

    public function addRole(string $role)
    {
        $this->roles[] = $role;
    }

    public function getRoles()
    {
        return array_unique($this->roles);
    }

    public function isGuest(): bool {
        return $this->getRoles() === ['ROLE_USER'];
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
}
