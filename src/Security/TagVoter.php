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

use App\Entity\Image;
use App\Entity\User;
use Phyxo\Conf;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

/**
 * Voter to allow manage tags on image
 */
class TagVoter extends Voter
{
    private $security, $conf;

    const ADD = 'add-atg';
    const DELETE = 'delete-tag';

    public function __construct(Security $security, Conf $conf)
    {
        $this->security = $security;
        $this->conf = $conf;
    }

    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, [self::ADD, self::DELETE])) {
            return false;
        }

        if (!$subject instanceof Image) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($this->security->isGranted('ROLE_WEBMASTER')) {
            return true;
        }

        $image = $subject;

        switch ($attribute) {
            case self::ADD:
                return $this->canAddTag($image, $user);
            case self::DELETE:
                return $this->canDeleteTag($image, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canAddTag(Image $image, User $user)
    {
        if (empty($this->conf['tags_permission_add'])) {
            return false;
        }

        return $this->security->isGranted(User::getRoleFromStatus($this->conf['tags_permission_add']));
    }

    private function canDeleteTag(Image $image, User $user)
    {
        if (empty($this->conf['tags_permission_delete'])) {
            return false;
        }

        return $this->security->isGranted(User::getRoleFromStatus($this->conf['tags_permission_delete']));
    }
}
