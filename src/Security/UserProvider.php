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
use App\Repository\UserInfosRepository;
use App\Entity\User;
use App\Entity\UserInfos;
use App\Utils\DataTransformer;
use Phyxo\EntityManager;
use App\DataMapper\CategoryMapper;
use App\Repository\CategoryRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\UserAccessRepository;
use App\Repository\UserCacheCategoriesRepository;
use App\Repository\UserCacheRepository;
use App\Repository\UserGroupRepository;
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
    private $user, $em, $session, $dataTransformer, $categoryMapper, $tokenStorage, $conf, $user_data;

    public function __construct(EntityManager $em, SessionInterface $session, DataTransformer $dataTransformer, CategoryMapper $categoryMapper, TokenStorageInterface $tokenStorage, Conf $conf)
    {
        $this->em = $em;
        $this->session = $session;
        $this->dataTransformer = $dataTransformer;
        $this->categoryMapper = $categoryMapper;
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

    public function fromToken(TokenInterface $token): ?UserInterface
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

        $user = $this->fetchUser($user->getUsername(), $force_refresh = true);
        $this->populateSession($user);

        return $user;
    }

    public function supportsClass($class): bool
    {
        return User::class === $class;
    }

    private function fetchUser(string $username, bool $force_refresh = false): ?UserInterface
    {
        // @TODO : find a way to cash some request: if ($this->user_data === null || $force_refresh) -> tests failed
        $result = $this->em->getRepository(UserRepository::class)->findByUsername($username);
        $userData = $this->em->getConnection()->db_fetch_assoc($result);

        // pretend it returns an array on success, false if there is no user
        if (!$userData) {
            return null;
        }

        $this->user_data = $this->createUserFromDb($userData);

        return $this->user_data;
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

    private function createUserFromDb(array $userData): User
    {
        $result = $this->em->getRepository(UserInfosRepository::class)->getInfos($userData['id']);
        $userInfosData = $this->dataTransformer->map($this->em->getConnection()->db_fetch_assoc($result));

        $user = new User();
        $user->setId($userData['id']);
        $user->setUsername($userData['username']);
        $user->setPassword($userData['password']);
        $user->setMailAddress($userData['mail_address']);

        $extra_infos = $this->getUserData($userData['id'], in_array($userInfosData['status'], ['admin', 'webmaster']));
        $user_infos = new UserInfos($userInfosData);
        $user_infos->setForbiddenCategories(empty($extra_infos['forbidden_categories']) ? [] : explode(',', $extra_infos['forbidden_categories']));
        $user_infos->setImageAccessList(empty($extra_infos['image_access_list']) ? [] : explode(',', $extra_infos['image_access_list']));
        $user_infos->setImageAccessType($extra_infos['image_access_type']);
        $user->setInfos($user_infos);

        return $user;
    }

    /**
     * Finds informations related to the user identifier.
     */
    public function getUserData(int $user_id, bool $is_admin = false): array
    {
        $result = $this->em->getRepository(UserInfosRepository::class)->getCompleteUserInfos($user_id);
        $userdata = $this->dataTransformer->map($this->em->getConnection()->db_fetch_assoc($result));
        $userdata['id'] = $user_id;

        // @TODO : cache in appropriate table use uc.cache_update_time
        if (!isset($userdata['need_update']) || !is_bool($userdata['need_update']) || $userdata['need_update'] === true) {
            $userdata['cache_update_time'] = time();

            // Set need update are done
            $userdata['need_update'] = false;
            $userdata['forbidden_categories'] = $this->calculatePermissions($user_id, $is_admin);

            /* now we build the list of forbidden images (this list does not contain
             * images that are not in at least an authorized category)
             */
            $forbidden_categories = [];
            if (!empty($userdata['forbidden_categories'])) {
                $forbidden_categories = $userdata['forbidden_categories'];
            }

            $result = $this->em->getRepository(ImageRepository::class)->getForbiddenImages($forbidden_categories, $userdata['level']);
            $forbidden_ids = $this->em->getConnection()->result2array($result, null, 'id');

            if (empty($forbidden_ids)) {
                $forbidden_ids[] = 0;
            }

            $userdata['image_access_type'] = 'NOT IN';
            $userdata['image_access_list'] = implode(',', $forbidden_ids);
            $userdata['nb_total_images'] = $this->em->getRepository(ImageCategoryRepository::class)->countTotalImages($forbidden_categories, $userdata['image_access_type'], $forbidden_ids);

            // now we update user cache categories
            $user_cache_cats = $this->categoryMapper->getComputedCategories($userdata, null);

            if (!$is_admin) { // for non admins we forbid categories with no image (feature 1053)
                $forbidden_ids = [];
                foreach ($user_cache_cats as $cat_id => $cat) {
                    if ($cat['count_images'] === 0) {
                        $forbidden_ids[] = $cat_id;
                        $this->categoryMapper->removeComputedCategory($user_cache_cats, $cat);
                    }
                }
                if (!empty($forbidden_ids)) {
                    if (empty($userdata['forbidden_categories'])) {
                        $userdata['forbidden_categories'] = $forbidden_ids;
                    } else {
                        $userdata['forbidden_categories'] = array_merge($userdata['forbidden_categories'], $forbidden_ids);
                    }
                }
            }
            foreach ($user_cache_cats as $cat_id => &$cat) {
                $cat['user_id'] = $userdata['user_id'];
            }

            // delete user cache
            $this->em->getConnection()->db_write_lock(\App\Repository\BaseRepository::USER_CACHE_CATEGORIES_TABLE);
            $this->em->getRepository(UserCacheCategoriesRepository::class)->deleteByUserIds([$userdata['user_id']]);
            $this->em->getRepository(UserCacheCategoriesRepository::class)->insertUserCacheCategories(
                ['user_id', 'cat_id', 'date_last', 'max_date_last', 'nb_images', 'count_images', 'nb_categories', 'count_categories'],
                $user_cache_cats
            );
            $this->em->getConnection()->db_unlock();

            $userdata['forbidden_categories'] = implode(',', $userdata['forbidden_categories']);
            // update user cache
            $this->em->getConnection()->db_start_transaction();
            try {
                $this->em->getRepository(UserCacheRepository::class)->deleteUserCache($userdata['user_id']);
                $this->em->getRepository(UserCacheRepository::class)->insertUserCache(
                    [
                        'user_id' => $userdata['user_id'],
                        'need_update' => $userdata['need_update'],
                        'cache_update_time' => $userdata['cache_update_time'],
                        'forbidden_categories' => $userdata['forbidden_categories'],
                        'nb_total_images' => $userdata['nb_total_images'],
                        'last_photo_date' => !empty($userdata['last_photo_date']) ? $userdata['last_photo_date'] : '',
                        'image_access_type' => $userdata['image_access_type'],
                        'image_access_list' => $userdata['image_access_list']
                    ]
                );

                $this->em->getConnection()->db_commit();
            } catch (\Exception $e) {
                $this->em->getConnection()->db_rollback();
            }
        }

        return $userdata;
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
        $result = $this->em->getRepository(CategoryRepository::class)->findByField('status', 'private');
        $private_array = $this->em->getConnection()->result2array($result, null, 'id');

        // retrieve category ids directly authorized to the user
        $result = $this->em->getRepository(UserAccessRepository::class)->findByUserId($user_id);
        $authorized_array = $this->em->getConnection()->result2array($result, null, 'cat_id');


        $result = $this->em->getRepository(UserGroupRepository::class)->findCategoryAuthorizedToTheGroupTheUserBelongs($user_id);
        $authorized_array = array_merge($authorized_array, $this->em->getConnection()->result2array($result, null, 'cat_id'));

        // uniquify ids : some private categories might be authorized for the groups and for the user
        $authorized_array = array_unique($authorized_array);

        // only unauthorized private categories are forbidden
        $forbidden_array = array_diff($private_array, $authorized_array);

        // if user is not an admin, locked categories are forbidden
        if (!$is_admin) {
            $result = $this->em->getRepository(CategoryRepository::class)->findByField('visible', false);
            $forbidden_array = array_merge($forbidden_array, $this->em->getConnection()->result2array($result, null, 'id'));
            $forbidden_array = array_unique($forbidden_array);
        }

        return $forbidden_array;
    }
}
