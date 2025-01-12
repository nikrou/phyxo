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
use Phyxo\Conf;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AppUserService
{
    private User $user;
    private User $guest_user;
    private bool $guest_user_retrieved = false;

    public function __construct(private readonly Security $security, private readonly UserProvider $userProvider, private readonly Conf $conf, private readonly AuthorizationCheckerInterface $authorizationChecker)
    {
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
            $this->guest_user = $this->userProvider->loadUserByIdentifier('guest');
            $this->guest_user_retrieved = true;
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
}
