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

use DateTime;
use App\DataMapper\AlbumMapper;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Repository\UserRepository;
use App\Entity\User;
use App\Entity\Album;
use App\Entity\UserCache;
use App\Entity\UserCacheAlbum;
use App\Repository\ImageAlbumRepository;
use App\Repository\ImageRepository;
use App\Repository\UserCacheAlbumRepository;
use App\Repository\UserCacheRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;

class UserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ImageAlbumRepository $imageAlbumRepository,
        private readonly ImageRepository $imageRepository,
        private readonly AlbumMapper $albumMapper,
        private readonly UserCacheRepository $userCacheRepository,
        private readonly UserCacheAlbumRepository $userCacheAlbumRepository
    ) {
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    // @throws UsernameNotFoundException if the user is not found
    public function loadUserByIdentifier(string $identifier): User
    {
        return $this->fetchUser($identifier);
    }

    public function loadByActivationKey(string $key): User
    {
        if (!($user = $this->fetchUserByActivationKey($key)) instanceof User) {
            throw new TokenNotFoundException(sprintf('Activation key "%s" does not exist.', $key));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): User
    {
        return $this->fetchUser($user->getUserIdentifier());
    }

    private function fetchUser(string $identifier): User
    {
        // @TODO : find a way to cache some request: if ($this->user_data === null || $force_refresh) -> tests failed
        $user = $this->userRepository->findOneBy(['username' => $identifier]);

        // pretend it returns a User on success, null if there is no user
        if (is_null($user)) {
            throw new UserNotFoundException(sprintf('User with username "%s" does not exist.', $identifier));
        }

        $userCache = $this->getUserCacheInfos($user);
        $user->getUserInfos()->setForbiddenAlbums($userCache->getForbiddenAlbums());
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
        $user->getUserInfos()->setForbiddenAlbums($userCache->getForbiddenAlbums());
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

            $forbidden_albums = $this->calculatePermissions($user->getId(), $is_admin);

            /* now we build the list of forbidden images (this list does not contain
             * images that are not in at least an authorized album)
             */

            $forbidden_image_ids = [];
            foreach ($this->imageRepository->getForbiddenImages($forbidden_albums, $user->getUserInfos()->getLevel()) as $image) {
                $forbidden_image_ids[] = $image->getId();
            }
            $userCache->setImageAccessType(UserCache::ACCESS_NOT_IN);
            $userCache->setImageAccessList($forbidden_image_ids);
            $userCache->setNbTotalImages($this->imageAlbumRepository->countTotalImages(UserCache::ACCESS_NOT_IN, $forbidden_albums, $forbidden_image_ids));

            // now we update user cache albums
            $user_cache_albums = $this->albumMapper->getComputedAlbums($user->getUserInfos()->getLevel(), $forbidden_albums);

            if (!$is_admin) { // for non admins we forbid albums with no image (feature 1053)
                $forbidden_ids = [];
                foreach ($user_cache_albums as $album_infos) {
                    if ($album_infos['count_images'] === 0) {
                        $forbidden_ids[] = $album_infos['album_id'];
                        $this->albumMapper->removeComputedAlbum($user_cache_albums, $album_infos);
                    }
                }
                if ($forbidden_ids !== []) {
                    $forbidden_albums = array_merge($forbidden_albums, $forbidden_ids);
                }
            }

            // delete user cache
            $this->userCacheAlbumRepository->deleteForUser($user->getId());

            foreach ($this->albumMapper->getRepository()->findBy(['id' => array_keys($user_cache_albums)]) as $album) {
                $userCacheAlbum = new UserCacheAlbum();
                $userCacheAlbum->setUser($user);
                $userCacheAlbum->setAlbum($album);
                if ($user_cache_albums[$album->getId()]['date_last']) {
                    $userCacheAlbum->setDateLast(new DateTime($user_cache_albums[$album->getId()]['date_last']));
                }
                if ($user_cache_albums[$album->getId()]['max_date_last']) {
                    $userCacheAlbum->setMaxDateLast(new DateTime($user_cache_albums[$album->getId()]['max_date_last']));
                }
                $userCacheAlbum->setNbImages($user_cache_albums[$album->getId()]['nb_images']);
                $userCacheAlbum->setCountImages($user_cache_albums[$album->getId()]['count_images']);
                $userCacheAlbum->setNbAlbums($user_cache_albums[$album->getId()]['nb_categories']);
                $userCacheAlbum->setCountAlbums($user_cache_albums[$album->getId()]['count_categories']);
                $this->userCacheAlbumRepository->addOrUpdateUserCacheAlbum($userCacheAlbum);
            }

            // update user cache
            $userCache->setUser($user);
            $userCache->setForbiddenAlbums($forbidden_albums);
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
    /**
     * @return int[]
     */
    protected function calculatePermissions(int $user_id, bool $is_admin = false): array
    {
        $private_albums = [];
        foreach ($this->albumMapper->getRepository()->findBy(['status' => Album::STATUS_PRIVATE]) as $album) {
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
            foreach ($this->albumMapper->getRepository()->findBy(['visible' => false]) as $album) {
                $locked_albums[] = $album->getId();
            }
            $forbidden_albums = [...$forbidden_albums, ...$locked_albums];
            $forbidden_albums = array_unique($forbidden_albums);
        }

        return $forbidden_albums;
    }
}
