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

use App\DataMapper\AlbumMapper;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use App\Repository\UserRepository;
use App\Entity\User;
use App\Entity\Album;
use App\Entity\UserCache;
use App\Entity\UserCacheAlbum;
use App\Repository\ImageAlbumRepository;
use App\Repository\ImageRepository;
use App\Repository\UserCacheAlbumRepository;
use App\Repository\UserCacheRepository;
use Phyxo\Conf;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;

class UserProvider implements UserProviderInterface
{
    private $user, $session, $albumMapper, $tokenStorage, $conf, $userRepository, $userCacheRepository, $userCacheAlbumRepository, $imageAlbumRepository;
    private $imageRepository;

    public function __construct(UserRepository $userRepository, SessionInterface $session, TokenStorageInterface $tokenStorage,
                                Conf $conf, AlbumMapper $albumMapper, UserCacheRepository $userCacheRepository, UserCacheAlbumRepository $userCacheAlbumRepository,
                                ImageAlbumRepository $imageAlbumRepository, ImageRepository $imageRepository)
    {
        $this->userRepository = $userRepository;
        $this->session = $session;
        $this->albumMapper = $albumMapper;
        $this->tokenStorage = $tokenStorage;
        $this->conf = $conf;
        $this->userCacheRepository = $userCacheRepository;
        $this->userCacheAlbumRepository = $userCacheAlbumRepository;
        $this->imageAlbumRepository = $imageAlbumRepository;
        $this->imageRepository = $imageRepository;
    }

    protected function populateSession(User $user)
    {
        $this->session->set('_theme', $user->getTheme());
        $this->session->set('_locale', $user->getLocale());
    }

    public function getUser(): ?User
    {
        if (!$this->user) {
            $this->user = $this->fromToken($this->tokenStorage->getToken());
        }

        return $this->user;
    }

    public function fromToken(?TokenInterface $token): ?User
    {
        if (is_null($token)) {
            return null;
        }

        if (!$this->user) {
            if ($this->conf['guest_access']) {
                if (!($token instanceof AnonymousToken) && !($token->getUser() instanceof UserInterface)) {
                    return null;
                }
            } else {
                if (!$token->getUser() instanceof UserInterface) {
                    throw new AccessDeniedException('Access denied to guest');
                }
            }

            try {
                if ($token instanceof AnonymousToken) {
                    $token->setUser('guest');
                    $this->user = $this->loadUserByUsername($token->getUser());
                } else {
                    $this->user = $this->loadUserByUsername($token->getUser()->getUsername());
                }
            } catch (UsernameNotFoundException $exception) {
                throw  new CustomUserMessageAuthenticationException(sprintf('Username "%s" does not exist.', $token->getUser()->getUsername()));
            }
        }

        return $this->user;
    }

    // @throws UsernameNotFoundException if the user is not found
    public function loadUserByUsername($username): User
    {
        if (($user = $this->fetchUser($username)) === null) {
            throw new UsernameNotFoundException(sprintf('User with username "%s" does not exist.', $username));
        }
        $this->populateSession($user);

        return $user;
    }

    public function loadByActivationKey(string $key): User
    {
        if (($user = $this->fetchUserByActivationKey($key)) === null) {
            throw new TokenNotFoundException(sprintf('Activation key "%s" does not exist.', $key));
        }
        $this->populateSession($user);

        return $user;
    }

    public function refreshUser(UserInterface $user): User
    {
        $user = $this->fetchUser($user->getUsername(), $force_refresh = true);
        $this->populateSession($user);

        return $user;
    }

    public function supportsClass($class): bool
    {
        return User::class === $class;
    }

    private function fetchUser(string $username, bool $force_refresh = false): ?User
    {
        // @TODO : find a way to cash some request: if ($this->user_data === null || $force_refresh) -> tests failed
        $user = $this->userRepository->findOneByUsername($username);

        // pretend it returns a User on success, null if there is no user
        if (is_null($user)) {
            return null;
        }

        $userCache = $this->getUserCacheInfos($user);
        $user->getUserInfos()->setForbiddenCategories($userCache->getForbiddenCategories());
        $user->getUserInfos()->setImageAccessList($userCache->getImageAccessList());
        $user->getUserInfos()->setImageAccessType($userCache->getImageAccessType());
        $user->getUserInfos()->setNbTotalImages($userCache->getNbTotalImages());

        return $user;
    }

    private function fetchUserByActivationKey(string $key): ?User
    {
        $user = $this->userRepository->findOneByActivationKey($key);

        // pretend it returns a User on success, null if there is no user
        if (is_null($user)) {
            return null;
        }

        $userCache = $this->getUserCacheInfos($user);
        $user->getUserInfos()->setForbiddenCategories($userCache->getForbiddenCategories());
        $user->getUserInfos()->setImageAccessList($userCache->getImageAccessList());
        $user->getUserInfos()->setImageAccessType($userCache->getImageAccessType());
        $user->getUserInfos()->setNbTotalImages($userCache->getNbTotalImages());

        return $user;
    }

    private function getUserCacheInfos(User $user): UserCache
    {
        $is_admin = in_array('ROLE_ADMIN', $user->getRoles());

        $userCache = $user->getUserCache();

        if (is_null($userCache) || $userCache->isNeedUpdate()) {
            $userCache = new UserCache();
            $userCache->setCacheUpdateTime(time());
            $userCache->setNeedUpdate(false);

            $forbidden_categories = $this->calculatePermissions($user->getId(), $is_admin);

            /* now we build the list of forbidden images (this list does not contain
             * images that are not in at least an authorized category)
             */

            $forbidden_image_ids = [];
            foreach ($this->imageRepository->getForbiddenImages($forbidden_categories, $user->getUserInfos()->getLevel()) as $image) {
                $forbidden_image_ids[] = $image->getId();
            }
            $userCache->setImageAccessType(UserCache::ACCESS_NOT_IN);
            $userCache->setImageAccessList($forbidden_image_ids);
            $userCache->setNbTotalImages($this->imageAlbumRepository->countTotalImages(UserCache::ACCESS_NOT_IN, $forbidden_categories, $forbidden_image_ids));

            // now we update user cache albums
            $user_cache_albums = $this->albumMapper->getComputedAlbums($user->getUserInfos()->getLevel(), $forbidden_categories);

            if (!$is_admin) { // for non admins we forbid albums with no image (feature 1053)
                $forbidden_ids = [];
                foreach ($user_cache_albums as $album_infos) {
                    if ($album_infos['count_images'] === 0) {
                        $forbidden_ids[] = $album_infos['album_id'];
                        $this->albumMapper->removeComputedAlbum($user_cache_albums, $album_infos);
                    }
                }
                if (count($forbidden_ids) > 0) {
                    $forbidden_categories = array_merge($forbidden_categories, $forbidden_ids);
                }
            }

            // delete user cache
            $this->userCacheAlbumRepository->deleteForUser($user->getId());

            foreach ($this->albumMapper->getRepository()->findBy(['id' => array_keys($user_cache_albums)]) as $album) {
                $userCacheAlbum = new UserCacheAlbum();
                $userCacheAlbum->setUser($user);
                $userCacheAlbum->setAlbum($album);
                $userCacheAlbum->setDateLast(new \DateTime($user_cache_albums[$album->getId()]['date_last']));
                $userCacheAlbum->setMaxDateLast(new \DateTime($user_cache_albums[$album->getId()]['max_date_last']));
                $userCacheAlbum->setNbImages($user_cache_albums[$album->getId()]['nb_images']);
                $userCacheAlbum->setCountImages($user_cache_albums[$album->getId()]['count_images']);
                $userCacheAlbum->setNbAlbums($user_cache_albums[$album->getId()]['nb_categories']);
                $userCacheAlbum->setCountAlbums($user_cache_albums[$album->getId()]['count_categories']);
                $this->userCacheAlbumRepository->addOrUpdateUserCacheAlbum($userCacheAlbum);
            }

            // update user cache
            $userCache->setUser($user);
            $userCache->setForbiddenCategories($forbidden_categories);
            $this->userCacheRepository->addOrUpdateUserCache($userCache);
        }

        return $userCache;
    }

    /**
     * Calculates the list of forbidden albums for a given user.
     *
     * Calculation is based on private albums minus albums authorized to
     * the groups the user belongs to minus the albums directly authorized
     * to the user.
     */
    protected function calculatePermissions(int $user_id, bool $is_admin = false): array
    {
        $private_albums = [];
        foreach ($this->albumMapper->getRepository()->findByStatus(Album::STATUS_PRIVATE) as $album) {
            $private_albums[] = $album->getId();
        }

        // retrieve albums ids directly authorized to the user
        $authorized_albums = [];
        foreach ($this->albumMapper->getRepository()->findAuthorizedToUser($user_id) as $album) {
            $authorized_albums[] = $album->getId();
        }

        foreach ($this->albumMapper->getRepository()->findAuthorizedToTheGroupTheUserBelongs($user_id) as $album) {
            $authorized_albums[] = $album->getId();
        }

        // uniquify ids : some private albums might be authorized for the groups and for the user
        $authorized_albums = array_unique($authorized_albums);

        // only unauthorized private albums are forbidden
        $forbidden_albums = array_diff($private_albums, $authorized_albums);

        // if user is not an admin, locked albums are forbidden
        if (!$is_admin) {
            $locked_albums = [];
            foreach ($this->albumMapper->getRepository()->findByVisible(false) as $album) {
                $locked_albums[] = $album->getId();
            }
            $forbidden_albums = array_merge($forbidden_albums, $locked_albums);
            $forbidden_albums = array_unique($forbidden_albums);
        }

        return $forbidden_albums;
    }
}
