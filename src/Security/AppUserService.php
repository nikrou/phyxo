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
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class AppUserService
{
    private $user, $security, $userProvider, $conf;

    public function __construct(Security $security, UserProvider $userProvider, Conf $conf)
    {
        $this->security = $security;
        $this->userProvider = $userProvider;
        $this->conf = $conf;
    }

    public function getUser(): ?User
    {
        if (is_null($this->security->getToken())) {
            return null;
        }

        if (!$this->user) {
            if ($this->conf['guest_access']) {
                if (!($this->security->getToken() instanceof AnonymousToken) && !($this->security->getToken()->getUser() instanceof UserInterface)) {
                    return null;
                }
            } else {
                if (!$this->security->getToken()->getUser() instanceof UserInterface) {
                    throw new AccessDeniedException('Access denied to guest');
                }
            }

            try {
                if ($this->security->getToken() instanceof AnonymousToken) {
                    $this->user = $this->userProvider->loadUserByIdentifier('guest');
                    $this->security->getToken()->setUser($this->user);
                } else {
                    $this->user = $this->security->getToken()->getUser();
                }
            } catch (UserNotFoundException $exception) {
                throw  new $exception;
            }
        }

        return $this->user;
    }
}
