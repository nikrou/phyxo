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
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

class AppUserService
{
    private $user, $security, $userProvider, $conf;
    private $guest_user, $guest_user_retrieved = false;

    public function __construct(Security $security, UserProvider $userProvider, Conf $conf)
    {
        $this->security = $security;
        $this->userProvider = $userProvider;
        $this->conf = $conf;
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
            $this->user = $this->security->getToken()->getUser();
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
}
