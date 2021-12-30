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

namespace App\Utils;

use App\Repository\UserRepository;
use App\Repository\GroupRepository;
use App\Entity\User;
use App\Entity\UserInfos;
use App\Repository\UserInfosRepository;

class UserManager
{
    private $userRepository, $userInfosRepository, $groupRepository, $defaultLanguage, $defautlTheme;

    public function __construct(
        UserRepository $userRepository,
        UserInfosRepository $userInfosRepository,
        GroupRepository $groupRepository,
        string $defaultLanguage,
        string $defaultTheme
    )
    {
        $this->userRepository = $userRepository;
        $this->userInfosRepository = $userInfosRepository;
        $this->groupRepository = $groupRepository;
        $this->defaultLanguage = $defaultLanguage;
        $this->defautlTheme = $defaultTheme;
    }

    public function register(User $user): User
    {
        $userInfos = new UserInfos();

        if (!in_array('ROLE_NORMAL', $user->getRoles())) {
            $userInfos->setLanguage($this->defaultLanguage);
            $userInfos->setTheme($this->defautlTheme);
        } else {
            $guestUserInfos = $this->userInfosRepository->findOneByStatus(User::STATUS_GUEST);
            $userInfos->fromArray($guestUserInfos->toArray());
        }

        $userInfos->setRegistrationDate(new \DateTime());
        $userInfos->setLastModified(new \DateTime());
        if (in_array('ROLE_WEBMASTER', $user->getRoles())) {
            $userInfos->setLevel(10); // @FIX: find a way to only inject that param instead of conf ; max($this->conf['available_permission_levels']);
        }
        $userInfos->setStatus($user->getStatusFromRoles());
        $user->setUserInfos($userInfos);

        // find default groups
        foreach ($this->groupRepository->findDefaultGroups() as $group) {
            $user->addGroup($group);
        }

        $this->userRepository->addUser($user);

        return $user;
    }

    public function generateActivationKey(int $size = 50)
    {
        return bin2hex(random_bytes($size));
    }
}
