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
use Phyxo\EntityManager;
use App\DataMapper\CategoryMapper;
use App\Entity\Album;
use App\Repository\ImageCategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\UserCacheCategoriesRepository;
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
    private $user, $em, $session, $categoryMapper, $albumMapper, $tokenStorage, $conf, $userRepository;

    public function __construct(EntityManager $em, UserRepository $userRepository, SessionInterface $session, CategoryMapper $categoryMapper, TokenStorageInterface $tokenStorage,
                                Conf $conf, AlbumMapper $albumMapper)
    {
        $this->em = $em;
        $this->userRepository = $userRepository;
        $this->session = $session;
        $this->categoryMapper = $categoryMapper;
        $this->albumMapper = $albumMapper;
        $this->tokenStorage = $tokenStorage;
        $this->conf = $conf;
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

    public function fromToken(TokenInterface $token): ?User
    {
        if (!$token) {
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
        if (!$user instanceof UserInterface) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

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

        $extra_infos = $this->getExtraUserInfos($user);
        $user->getUserInfos()->setForbiddenCategories(empty($extra_infos['forbidden_categories']) ? [] : explode(',', $extra_infos['forbidden_categories']));
        $user->getUserInfos()->setImageAccessList(empty($extra_infos['image_access_list']) ? [] : explode(',', $extra_infos['image_access_list']));
        $user->getUserInfos()->setImageAccessType($extra_infos['image_access_type']);
        $user->getUserInfos()->setNbTotalImages($extra_infos['nb_total_images']);

        return $user;
    }

    private function fetchUserByActivationKey(string $key): ?User
    {
        $user = $this->userRepository->findOneByActivationKey($key);

        // pretend it returns a User on success, null if there is no user
        if (is_null($user)) {
            return null;
        }

        $extra_infos = $this->getExtraUserInfos($user);
        $user->getUserInfos()->setForbiddenCategories(empty($extra_infos['forbidden_categories']) ? [] : explode(',', $extra_infos['forbidden_categories']));
        $user->getUserInfos()->setImageAccessList(empty($extra_infos['image_access_list']) ? [] : explode(',', $extra_infos['image_access_list']));
        $user->getUserInfos()->setImageAccessType($extra_infos['image_access_type']);
        $user->getUserInfos()->setNbTotalImages($extra_infos['nb_total_images']);

        return $user;
    }

    private function getExtraUserInfos(User $user): array
    {
        $is_admin = in_array('ROLE_ADMIN', $user->getRoles());

        $extra_infos = ['need_update' => true, 'last_photo_date' => null];

        $result = $this->em->getRepository(UserCacheRepository::class)->getUserCacheData($user->getId());
        $user_cache_data = $this->em->getConnection()->result2array($result);

        if (!empty($user_cache_data)) {
            $extra_infos = $user_cache_data[0];
            $extra_infos['need_update'] = $extra_infos['need_update'] === 'f' || $extra_infos['need_update'] === 'false';
            unset($user_cache_data);
        }

        if ($extra_infos['need_update']) {
            $extra_infos['cache_update_time'] = time();
            $extra_infos['need_update'] = false;
            $extra_infos['forbidden_categories'] = $this->calculatePermissions($user->getId(), $is_admin);

            /* now we build the list of forbidden images (this list does not contain
             * images that are not in at least an authorized category)
             */
            $forbidden_categories = [];
            if (!empty($extra_infos['forbidden_categories'])) {
                $forbidden_categories = $extra_infos['forbidden_categories'];
            }

            $result = $this->em->getRepository(ImageRepository::class)->getForbiddenImages($forbidden_categories, $user->getUserInfos()->getLevel());
            $forbidden_image_ids = $this->em->getConnection()->result2array($result, null, 'id');

            if (empty($forbidden_image_ids)) {
                $forbidden_image_ids[] = 0;
            }

            $extra_infos['image_access_type'] = 'NOT IN';
            $extra_infos['image_access_list'] = implode(',', $forbidden_image_ids);
            $extra_infos['nb_total_images'] = $this->em->getRepository(ImageCategoryRepository::class)->countTotalImages($forbidden_categories, $extra_infos['image_access_type'], $forbidden_image_ids);

            // now we update user cache categories
            $user_cache_cats = $this->categoryMapper->getComputedCategories(
                ['level' => $user->getUserInfos()->getLevel(), 'forbidden_categories' => $forbidden_categories]
            );

            if (!$is_admin) { // for non admins we forbid categories with no image (feature 1053)
                $forbidden_ids = [];
                foreach ($user_cache_cats as $cat_id => $cat) {
                    if ($cat['count_images'] === 0) {
                        $forbidden_ids[] = $cat_id;
                        $this->categoryMapper->removeComputedCategory($user_cache_cats, $cat);
                    }
                }
                if (!empty($forbidden_ids)) {
                    if (empty($extra_infos['forbidden_categories'])) {
                        $extra_infos['forbidden_categories'] = $forbidden_ids;
                    } else {
                        $extra_infos['forbidden_categories'] = array_merge($extra_infos['forbidden_categories'], $forbidden_ids);
                    }
                }
            }
            foreach ($user_cache_cats as $cat_id => &$cat) {
                $cat['user_id'] = $user->getId();
            }

            // delete user cache
            $this->em->getConnection()->db_write_lock(\App\Repository\BaseRepository::USER_CACHE_CATEGORIES_TABLE);
            $this->em->getRepository(UserCacheCategoriesRepository::class)->deleteByUserIds([$user->getId()]);

            $this->em->getRepository(UserCacheCategoriesRepository::class)->insertUserCacheCategories(
                ['user_id', 'cat_id', 'date_last', 'max_date_last', 'nb_images', 'count_images', 'nb_categories', 'count_categories'],
                $user_cache_cats
            );
            $this->em->getConnection()->db_unlock();

            $extra_infos['forbidden_categories'] = implode(',', $extra_infos['forbidden_categories']);
            // update user cache
            $this->em->getConnection()->db_start_transaction();

            $user_cache_data = [
                'user_id' => $user->getId(),
                'need_update' => $extra_infos['need_update'],
                'cache_update_time' => $extra_infos['cache_update_time'],
                'forbidden_categories' => $extra_infos['forbidden_categories'],
                'nb_total_images' => $extra_infos['nb_total_images'],
                'image_access_type' => $extra_infos['image_access_type'],
                'image_access_list' => $extra_infos['image_access_list']
            ];
            if (isset($extra_infos['last_photo_date'])) {
                $user_cache_data['last_photo_date'] = $extra_infos['last_photo_date'];
            }

            try {
                $this->em->getRepository(UserCacheRepository::class)->deleteUserCache($user->getId());
                $this->em->getRepository(UserCacheRepository::class)->insertUserCache($user_cache_data);

                $this->em->getConnection()->db_commit();
            } catch (\Exception $e) {
                $this->em->getConnection()->db_rollback();
            }
        }

        return $extra_infos;
    }

    /**
     * Calculates the list of forbidden categories for a given user.
     *
     * Calculation is based on private categories minus categories authorized to
     * the groups the user belongs to minus the categories directly authorized
     * to the user.
     */
    public function calculatePermissions(int $user_id, bool $is_admin = false): array
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

        // uniquify ids : some private categories might be authorized for the groups and for the user
        $authorized_albums = array_unique($authorized_albums);

        // only unauthorized private categories are forbidden
        $forbidden_albums = array_diff($private_albums, $authorized_albums);

        // if user is not an admin, locked categories are forbidden
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
