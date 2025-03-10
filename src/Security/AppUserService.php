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
use App\Entity\UserInfos;
use App\Enum\UserPrivacyLevelType;
use App\Enum\UserStatusType;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use Phyxo\Conf;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use DateTime;
use Exception;

class AppUserService
{
    private User $user;
    private User $guest_user;
    private bool $guest_user_retrieved = false;

    public function __construct(
        private readonly Security $security,
        private readonly UserProvider $userProvider,
        private readonly Conf $conf,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly string $defaultLanguage,
        private readonly string $defaultTheme,
        private readonly UserRepository $userRepository,
        private readonly GroupRepository $groupRepository
    ) {
    }

    public function getUser(): ?User
    {
        if (is_null($this->security->getToken())) {
            if ($this->conf['guest_access']) {
                $this->user = $this->getDefaultUser();
            } else {
                throw new AccessDeniedException('Access denied to guest');
            }
        } else {
            /** @var User $security_user */
            $security_user = $this->security->getUser();

            $this->user = $security_user;
        }

        return $this->user;
    }

    public function getDefaultUser(): User
    {
        if (!$this->guest_user_retrieved) {
            try {
                $this->guest_user = $this->userProvider->loadUserByIdentifier('guest');
                $this->guest_user_retrieved = true;
            } catch (Exception) {
                throw new Exception('Cannot find guest user');
            }
        }

        return $this->guest_user;
    }

    public function isGuest(): bool
    {
        return $this->getUser()->getId() === $this->getDefaultUser()->getId();
    }

    public function isClassicUser(): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_NORMAL');
    }

    public function isAdmin(): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_ADMIN');
    }

    public function isWebmaster(): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_WEBMASTER');
    }

    /**
     * Returns if current user can edit/delete/validate a comment.
     */
    public function canManageComment(string $action, int $comment_author_id): bool
    {
        if ($this->isGuest()) {
            return false;
        }

        if (!in_array($action, ['delete', 'edit', 'validate'])) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        if (($action === 'edit' && $this->conf['user_can_edit_comment']) && ($comment_author_id === $this->getUser()->getId())) {
            return true;
        }

        return $action === 'delete' && $this->conf['user_can_delete_comment'] && $comment_author_id == $this->getUser()->getId();
    }

    public function register(User $user): User
    {
        $userInfos = new UserInfos();

        if (!in_array('ROLE_NORMAL', $user->getRoles())) {
            $userInfos->setLanguage($this->defaultLanguage);
            $userInfos->setTheme($this->defaultTheme);
        } else {
            try {
                $guest_user = $this->userRepository->findOneByStatus(UserStatusType::GUEST);
                $userInfos->fromArray($guest_user->getUserInfos()->toArray());
            } catch (Exception) {
            }
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
        return hash('sha256', openssl_random_pseudo_bytes($size));
    }
}
