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
use App\Entity\User;
use App\Entity\UserInfos;
use Phyxo\EntityManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;

class SecurityUserProvider implements UserProviderInterface
{
    private $em, $session;

    public function __construct(EntityManager $em, SessionInterface $session)
    {
        $this->em = $em;
        $this->session = $session;
    }

    // @throws UsernameNotFoundException if the user is not found
    public function loadUserByUsername($username): UserInterface
    {
        if (($user = $this->fetchUser($username)) === null) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
        }
        $this->populateSession($user);

        return $user;
    }

    public function loadByActivationKey(string $key): UserInterface
    {
        if (($user = $this->fetchUserByActivationKey($key)) === null) {
            throw new TokenNotFoundException(sprintf('Activation key "%s" does not exist.', $key));
        }
        $this->populateSession($user);

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof UserInterface) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }
        $user = $this->fetchUser($user->getUsername());
        $this->populateSession($user);

        return $user;
    }

    protected function populateSession(SecurityUser $user)
    {
        $this->session->set('_theme', $user->getTheme());
        $this->session->set('_locale', $user->getLocale());
    }

    public function supportsClass($class): bool
    {
        return SecurityUser::class === $class;
    }

    private function fetchUser(string $username): ?UserInterface
    {
        $result = $this->em->getRepository(UserRepository::class)->findByUsernameWithRoles($username);
        $userData = $this->em->getConnection()->db_fetch_assoc($result);

        // pretend it returns an array on success, false if there is no user
        if (!$userData) {
            return null;
        }

        return $this->createUserFromDb($userData);
    }

    private function fetchUserByActivationKey(string $key): ?UserInterface
    {
        $result = $this->em->getRepository(UserRepository::class)->findByActivationKey($key);
        $userData = $this->em->getConnection()->db_fetch_assoc($result);

        // pretend it returns an array on success, false if there is no user
        if (!$userData) {
            return null;
        }

        return $this->createUserFromDb($userData);
    }

    private function createUserFromDb(array $userData): UserInterface
    {
        $user = new User();
        $user->setId($userData['id']);
        $user->setUsername($userData['username']);
        $user->setPassword($userData['password']);
        $user->setMailAddress($userData['mail_address']);
        $user->setStatus($userData['status']);
        $user->setInfos(new UserInfos($userData));

        return new SecurityUser($user);
    }
}
