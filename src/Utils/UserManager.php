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

use DateTime;
use App\Repository\UserRepository;
use App\Repository\GroupRepository;
use App\Entity\User;
use App\Entity\UserInfos;
use App\Enum\UserPrivacyLevelType;
use App\Enum\UserStatusType;
use App\Repository\UserInfosRepository;

class UserManager
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserInfosRepository $userInfosRepository,
        private readonly GroupRepository $groupRepository,
        private readonly string $defaultLanguage,
        private readonly string $defaultTheme
    ) {
    }

    public function register(User $user): User
    {
        $userInfos = new UserInfos();

        if (!in_array('ROLE_NORMAL', $user->getRoles())) {
            $userInfos->setLanguage($this->defaultLanguage);
            $userInfos->setTheme($this->defaultTheme);
        } else {
            $guestUserInfos = $this->userInfosRepository->findOneBy(['status' => UserStatusType::GUEST]);
            $userInfos->fromArray($guestUserInfos->toArray());
        }

        $userInfos->setRegistrationDate(new DateTime());
        $userInfos->setLastModified(new DateTime());
        if (in_array('ROLE_WEBMASTER', $user->getRoles())) {
            $userInfos->setLevel(UserPrivacyLevelType::ADMINS);
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

    public function generateActivationKey(int $size = 50): string
    {
        return bin2hex(random_bytes($size));
    }
}
