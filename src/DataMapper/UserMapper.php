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

namespace App\DataMapper;

use Phyxo\Conf;
use Phyxo\Functions\Plugin;
use App\Repository\UserCacheCategoriesRepository;
use App\Repository\UserCacheRepository;
use App\Repository\CategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\GroupRepository;
use App\Repository\UserGroupRepository;
use App\Repository\UserRepository;
use App\Repository\UserInfosRepository;
use App\Repository\UserAccessRepository;
use App\Repository\ThemeRepository;
use App\Entity\User;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Repository\BaseRepository;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Phyxo\EntityManager;
use App\Entity\GuestUser;
use App\Utils\DataTransformer;
use App\Repository\UserMailNotificationRepository;
use App\Repository\UserFeedRepository;
use App\Repository\FavoriteRepository;
use App\Repository\CaddieRepository;
use App\Repository\ImageTagRepository;

class UserMapper
{
    private $em, $conf, $autorizationChecker, $security, $user, $dataTransformer, $categoryMapper, $tagMapper;
    private $passwordEncoder, $defaultLanguage, $themesPath;

    public function __construct(EntityManager $em, Conf $conf, Security $security, AuthorizationCheckerInterface $autorizationChecker, DataTransformer $dataTransformer,
                                CategoryMapper $categoryMapper, TagMapper $tagMapper, string $defaultLanguage, string $themesPath)
    {
        $this->em = $em;
        $this->conf = $conf;
        $this->security = $security;
        $this->autorizationChecker = $autorizationChecker;
        $this->dataTransformer = $dataTransformer;
        $this->categoryMapper = $categoryMapper;
        $this->tagMapper = $tagMapper;
        $this->defaultLanguage = $defaultLanguage;
        $this->themesPath = $themesPath;
    }

    public function getUser()
    {
        if ($this->user instanceof User) {
            return $this->user;
        }

        if ($this->security->getToken() instanceof AnonymousToken) {
            $this->user = new GuestUser();
        } else {
            $this->user = $this->security->getUser();
        }

        return $this->user;
    }

    public function setPasswordEncoder(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * Checks if an email is well formed and not already in use.
     *
     * @param int $user_id
     * @param string $mail_address
     * @return string|void error message or nothing
     */
    public function validateMailAddress($user_id, $mail_address)
    {
        if (empty($mail_address) && !($this->conf['obligatory_user_mail_address']
            && in_array(\Phyxo\Functions\Utils::script_basename(), ['register', 'profile']))) {
            return;
        }

        if (!\Phyxo\Functions\Utils::email_check_format($mail_address)) {
            return \Phyxo\Functions\Language::l10n('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
        }

        if (!empty($mail_address) && $this->em->getRepository(UserRepository::class)->isEmailExistsExceptUser($mail_address, $user_id)) {
            return \Phyxo\Functions\Language::l10n('this email address is already in use');
        }
    }

    /**
     * Fetches user data from database.
     * Same that getUserData() but with additional tests for guest.
     *
     * @param int $user_id
     * @param boolean $user_cache
     * @return array
     */
    public function buildUser($user_id, $use_cache = true)
    {
        $user['id'] = $user_id;
        $user = array_merge($user, $this->getUserData($user_id, $use_cache));

        if ($user['id'] == $this->conf['guest_id'] and $user['status'] != 'guest') {
            $user['status'] = 'guest';
            $user['internal_status']['guest_must_be_guest'] = true;
        }

        // Check user theme
        if (!isset($user['theme_name'])) {
            $user['theme'] = $this->getDefaultTheme();
        }

        return $user;
    }

    /**
     * Finds informations related to the user identifier.
     */
    public function getUserData(int $user_id, bool $is_admin = false): array
    {
        $result = $this->em->getRepository(UserInfosRepository::class)->getCompleteUserInfos($user_id);
        $userdata = $this->dataTransformer->map($this->em->getConnection()->db_fetch_assoc($result));

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
                $forbidden_categories = explode(',', $userdata['forbidden_categories']);
            }
            $result = $this->em->getRepository(ImageRepository::class)->getForbiddenImages($forbidden_categories, $userdata['level']);
            $forbidden_ids = $this->em->getConnection()->result2array($result, null, 'id');

            if (empty($forbidden_ids)) {
                $forbidden_ids[] = 0;
            }

            $userdata['image_access_type'] = 'NOT IN';
            $userdata['image_access_list'] = implode(',', $forbidden_ids);

            $userdata['nb_total_images'] = $this->em->getRepository(ImageCategoryRepository::class)->countTotalImages(
                $forbidden_categories,
                $userdata['image_access_type'],
                $forbidden_ids
            );

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
                        $userdata['forbidden_categories'] = implode(',', $forbidden_ids);
                    } else {
                        $userdata['forbidden_categories'] .= ',' . implode(',', $forbidden_ids);
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
    public function calculatePermissions(int $user_id, bool $is_admin = false): string
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

        return implode(',', $forbidden_array);
    }

    /**
     * Returns user identifier thanks to his name.
     *
     * @param string $username
     * @param int|false
     */
    public function getUserId($username)
    {
        $result = $this->em->getRepository(UserRepository::class)->findByUsername($username);
        if ($this->em->getConnection()->db_num_rows($result) === 0) {
            return false;
        } else {
            $user = $this->em->getConnection()->db_fetch_assoc($result);

            return $user['id'];
        }
    }

    /**
     * Returns user identifier thanks to his email.
     *
     * @param string $email
     * @param int|false
     */
    public function getUserIdByEmail($email)
    {
        $result = $this->em->getRepository(UserRepository::class)->findByEmail($email);
        if ($this->em->getConnection()->db_num_rows($result) == 0) {
            return false;
        } else {
            $user = $this->em->getConnection()->db_fetch_assoc($result);

            return $user['id'];
        }
    }

    public function getUsernameFromId(int $id)
    {
        $result = $this->em->getRepository(UserRepository::class)->findById($id);
        if ($this->em->getConnection()->db_num_rows($result) > 0) {
            $row = $this->em->getConnection()->db_fetch_assoc($result);

            return $row['username'];
        } else {
            return false;
        }
    }

    public function getWebmasterEmail()
    {
        $result = $this->em->getRepository(UserRepository::class)->findById($this->conf['webmaster_id']);
        $row = $this->em->getConnection()->db_fetch_assoc($result);

        return $row['mail_address'];
    }

    /**
     * Returns webmaster user depending on $conf['webmaster_id']
     */
    public function getWebmaster(): array
    {
        $result = $this->em->getRepository(UserRepository::class)->findById($this->conf['webmaster_id']);
        $row = $this->em->getConnection()->db_fetch_assoc($result);

        return $row;
    }

    /**
     * Returns webmaster mail address depending on $conf['webmaster_id']
     */
    public function getWebmasterUsername(): string
    {
        $result = $this->em->getRepository(UserRepository::class)->findById($this->conf['webmaster_id']);
        $row = $this->em->getConnection()->db_fetch_assoc($result);
        return $row['username'];
    }

    /**
     * Returns a array with default user valuees.
     *
     * @param convert_str ceonferts 'true' and 'false' into booleans
     * @return array
     */
    public function getDefaultUserInfo($convert_str = true)
    {
        $result = $this->em->getRepository(UserInfosRepository::class)->findByUserId($this->conf['default_user_id']);
        if ($this->em->getConnection()->db_num_rows($result) > 0) {
            $default_user = $this->em->getConnection()->db_fetch_assoc($result);

            unset($default_user['user_id'], $default_user['status'], $default_user['registration_date']);
            foreach ($default_user as &$value) {
                // If the field is true or false, the variable is transformed into a boolean value.
                if (!is_null($value) && $this->em->getConnection()->is_boolean($value)) {
                    $value = $this->em->getConnection()->get_boolean($value);
                }
            }

            return $default_user;
        } else {
            return false;
        }
    }

    /**
     * Returns a default user value.
     *
     * @param string $value_name
     * @param mixed $default
     * @return mixed
     */
    public function getDefaultUserValue($value_name, $default)
    {
        $default_user = $this->getDefaultUserInfo(true);
        if ($default_user === false or empty($default_user[$value_name])) {
            return $default;
        } else {
            return $default_user[$value_name];
        }
    }

    /**
     * Returns the default theme.
     * If the default theme is not available it returns the first available one.
     *
     * @return string
     */
    public function getDefaultTheme()
    {
        $theme = $this->getDefaultUserValue('theme', $this->defaultLanguage);
        if (is_readable($this->themesPath . '/' . $theme . '/' . 'themeconf.inc.php')) {
            return $theme;
        }

        // let's find the first available theme
        $result = $this->em->getRepository(ThemeRepository::class)->findAll();
        $active_themes = array_keys($this->em->getConnection()->result2array($result, 'id', 'name'));

        return $active_themes[0];
    }

    /**
     * Returns the default language.
     *
     * @return string
     */
    public function getDefaultLanguage()
    {
        return $this->getDefaultUserValue('language', $this->defaultLanguage);
    }

    public function isGuest(): bool
    {
        return $this->getUser()->isGuest();
    }

    public function isClassicUser(): bool
    {
        return $this->autorizationChecker->isGranted('ROLE_NORMAL');
    }

    public function isAdmin(): bool
    {
        return $this->autorizationChecker->isGranted('ROLE_ADMIN');
    }

    public function isWebmaster(): bool
    {
        return $this->autorizationChecker->isGranted('ROLE_WEBMASTER');
    }

    /**
     * Returns if current user can edit/delete/validate a comment.
     *
     * @param string $action edit/delete/validate
     * @param int $comment_author_id
     * @return bool
     */
    public function canManageComment($action, $comment_author_id)
    {
        if ($this->isGuest()) {
            return false;
        }

        if (!in_array($action, ['delete', 'edit', 'validate'])) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        if ('edit' == $action and $this->conf['user_can_edit_comment']) {
            if ($comment_author_id == $this->getUser()->getId()) {
                return true;
            }
        }

        if ('delete' == $action and $this->conf['user_can_delete_comment']) {
            if ($comment_author_id == $this->getUser()->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the number of available tags for the connected user.
     */
    public function getNumberAvailableTags(): int
    {
        $filter = [];
        $number_of_available_tags = count($this->tagMapper->getAvailableTags($this->getUser(), $filter));

        $this->em->getRepository(UserCacheRepository::class)->updateUserCache(
            ['nb_available_tags' => $number_of_available_tags],
            ['user_id' => $this->getUser()->getId()]
        );

        return $number_of_available_tags;
    }

    /**
     * returns the number of available comments for the connected user
     */
    public function getNumberAvailableComments(): int
    {
        $filter = [];
        $number_of_available_comments = $this->em->getRepository(ImageCategoryRepository::class)->countAvailableComments($this->getUser(), $filter, $this->isAdmin());

        $this->em->getRepository(UserCacheRepository::class)->updateUserCache(
            ['nb_available_comments' => $number_of_available_comments],
            ['user_id' => $this->getUser()->getId()]
        );

        return $number_of_available_comments;
    }

    /**
     * Invalidates cached data (permissions and category counts) for all users.
     */
    public function invalidateUserCache(bool $full = true)
    {
        if ($full) {
            $this->em->getRepository(UserCacheRepository::class)->deleteUserCache();
            $this->em->getRepository(UserCacheCategoriesRepository::class)->deleteUserCacheCategories();
        } else {
            $this->em->getRepository(UserCacheRepository::class)->updateUserCache(['need_update' => true]);
        }
        \Phyxo\Functions\Plugin::trigger_notify('UserMapper::invalidateUserCache', $full);
    }

    /**
     * Deletes an user.
     * It also deletes all related data (accesses, favorites, permissions, etc.)
     * @todo : accept array input
     */
    public function deleteUser(int $user_id)
    {
        // destruction of the group links for this user
        $this->em->getRepository(UserGroupRepository::class)->deleteByUserId($user_id);
        // destruction of the access linked to the user(new UserAccessRepository($conn))->deleteByUserId($user_id);
        // deletion of phyxo specific informations
        $this->em->getRepository(UserInfosRepository::class)->deleteByUserId($user_id);
        // destruction of data notification by mail for this user
        $this->em->getRepository(UserMailNotificationRepository::class)->deleteByUserId($user_id);
        // destruction of data RSS notification for this user
        $this->em->getRepository(UserFeedRepository::class)->deleteUserOnes($user_id);
        // deletion of calculated permissions linked to the user
        $this->em->getRepository(UserCacheRepository::class)->deleteUserCache($user_id);
        // deletion of computed cache data linked to the user
        $this->em->getRepository(UserCacheCategoriesRepository::class)->deleteUserCacheCategories($user_id);
        // destruction of the favorites associated with the user
        $this->em->getRepository(FavoriteRepository::class)->removeAllFavorites($user_id);
        // destruction of the caddie associated with the user
        $this->em->getRepository(CaddieRepository::class)->emptyCaddie($user_id);

        // remove  created_by user in image_tag
        $this->em->getRepository(ImageTagRepository::class)->removeCreatedByKey($user_id);

        // destruction of the user
        $this->em->getRepository(UserRepository::class)->deleteById($user_id);

        \Phyxo\Functions\Plugin::trigger_notify('UserMapper::deleteUser', $user_id);
    }
}
