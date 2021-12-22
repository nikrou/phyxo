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

use Phyxo\Conf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GuestAuthenticator extends AbstractAuthenticator
{
    private $security, $conf;

    public function __construct(Security $security, Conf $conf)
    {
        $this->security = $security;
        $this->conf = $conf;
    }

    public function supports(Request $request): ?bool
    {
        if (!$this->conf['guest_access']) {
            return false;
        }

        if ($request->attributes->get('_route') === 'login') {
            return false;
        }

        if ($this->security->isGranted('ROLE_NORMAL')) {
            return false;
        }

        return true;
    }

    public function authenticate(Request $request): PassportInterface
    {
        return new  SelfValidatingPassport(new UserBadge('guest'));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
