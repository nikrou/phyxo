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
use App\Repository\UserRepository;
use App\Repository\UserInfosRepository;
use App\Entity\User;
use App\Entity\UserInfos;
use App\Utils\DataTransformer;
use Phyxo\EntityManager;
use App\DataMapper\CategoryMapper;
use App\DataMapper\UserMapper;

class UserProvider implements UserProviderInterface
{
    private $em, $dataTransformer, $categoryMapper, $userMapper;

    public function __construct(EntityManager $em, DataTransformer $dataTransformer, CategoryMapper $categoryMapper, UserMapper $userMapper)
    {
        $this->em = $em;
        $this->dataTransformer = $dataTransformer;
        $this->categoryMapper = $categoryMapper;
        $this->userMapper = $userMapper;
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

    private function fetchUser(string $username)
    {
        $result = $this->em->getRepository(UserRepository::class)->findByUsername($username);
        $userData = $this->em->getConnection()->db_fetch_assoc($result);

        // pretend it returns an array on success, false if there is no user
        if ($userData) {
            $result = $this->em->getRepository(UserInfosRepository::class)->getInfos($userData['id']);
            $userInfosData = $this->dataTransformer->map($this->em->getConnection()->db_fetch_assoc($result));

            $user = new User();
            $user->setId($userData['id']);
            $user->setUsername($userData['username']);
            $user->setPassword($userData['password']);
            $user->setMailAddress($userData['mail_address']);

            $extra_infos = $this->userMapper->getUserData($userData['id'], in_array($userInfosData['status'], ['admin', 'webmaster']));
            $user_infos = new UserInfos($userInfosData);
            $user_infos->setForbiddenCategories(explode(',', $extra_infos['forbidden_categories']));
            $user_infos->setImageAccessList(explode(',', $extra_infos['image_access_list']));
            $user_infos->setImageAccessType($extra_infos['image_access_type']);
            $user->setInfos($user_infos);

            return $user;
        }

        throw new UsernameNotFoundException(
            sprintf('Username "%s" does not exist.', $username)
        );
    }
}
