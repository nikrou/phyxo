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

use LogicException;
use App\Entity\Image;
use App\Entity\User;
use Phyxo\Conf;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Voter to allow manage tags on image
 */
class TagVoter extends Voter
{
    final public const ADD = 'add-atg';
    final public const DELETE = 'delete-tag';

    public function __construct(private readonly Security $security, private readonly Conf $conf)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::ADD, self::DELETE])) {
            return false;
        }

        if (!$subject instanceof Image) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        if ($this->security->isGranted('ROLE_WEBMASTER')) {
            return true;
        }
        return match ($attribute) {
            self::ADD => $this->canAddTag(),
            self::DELETE => $this->canDeleteTag(),
            default => throw new LogicException('This code should not be reached!'),
        };
    }

    private function canAddTag(): bool
    {
        if (empty($this->conf['tags_permission_add'])) {
            return false;
        }
        return $this->security->isGranted(User::getRoleFromStatus($this->conf['tags_permission_add']));
    }

    private function canDeleteTag(): bool
    {
        if (empty($this->conf['tags_permission_delete'])) {
            return false;
        }
        return $this->security->isGranted(User::getRoleFromStatus($this->conf['tags_permission_delete']));
    }
}
