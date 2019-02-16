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

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Phyxo\DBLayer\DBLayer;
use App\Repository\UserRepository;
use App\Repository\UserInfosRepository;
use App\Entity\User;
use App\Entity\UserInfos;

class UserProvider implements UserProviderInterface
{
    private $conn;

    public function __construct(DBLayer $conn)
    {
        $this->conn = $conn;
    }

    public function loadUserByUsername($username)
    {
        return $this->fetchUser($username);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof UserInterface) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        $username = $user->getUsername();

        return $this->fetchUser($username);
    }

    public function supportsClass($class)
    {
        return User::class === $class;
    }

    private function fetchUser($username)
    {
        $result = (new UserRepository($this->conn))->findByUsername($username);
        $userData = $this->conn->db_fetch_assoc($result);

        // pretend it returns an array on success, false if there is no user
        if ($userData) {
            $result = (new UserInfosRepository($this->conn))->getInfos($userData['id']);
            $user_infos = $this->conn->db_fetch_assoc($result);

            $user = new User($userData['id'], $username, $userData['password'], null);
            $user->setInfos(new UserInfos($user_infos));

            return $user;
        }

        throw new UsernameNotFoundException(
            sprintf('Username "%s" does not exist.', $username)
        );
    }
}
