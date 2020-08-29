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

use App\Entity\User;
use Phyxo\Conf;
use App\Repository\UserCacheCategoriesRepository;
use App\Repository\UserCacheRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\UserGroupRepository;
use App\Repository\UserRepository;
use App\Repository\UserInfosRepository;
use App\Repository\ThemeRepository;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Phyxo\EntityManager;
use App\Repository\UserMailNotificationRepository;
use App\Repository\UserFeedRepository;
use App\Repository\FavoriteRepository;
use App\Repository\CaddieRepository;
use App\Repository\CommentRepository;
use App\Repository\ImageTagRepository;
use App\Security\UserProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserMapper
{
    private $em, $conf, $autorizationChecker, $tagMapper, $themeRepository;
    private $defaultLanguage, $themesDir, $userProvider, $translator;

    public function __construct(EntityManager $em, Conf $conf, AuthorizationCheckerInterface $autorizationChecker, ThemeRepository $themeRepository,
                                TagMapper $tagMapper, string $defaultLanguage, string $themesDir, UserProvider $userProvider, TranslatorInterface $translator)
    {
        $this->em = $em;
        $this->themeRepository = $themeRepository;
        $this->conf = $conf;
        $this->autorizationChecker = $autorizationChecker;
        $this->tagMapper = $tagMapper;
        $this->defaultLanguage = $defaultLanguage;
        $this->themesDir = $themesDir;
        $this->userProvider = $userProvider;
        $this->translator = $translator;
    }

    public function getUser()//: ?User @TODO : modify tests or implementation
    {
        return $this->userProvider->getUser();
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
            return $this->translator->trans('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
        }

        if (!empty($mail_address) && $this->em->getRepository(UserRepository::class)->isEmailExistsExceptUser($mail_address, $user_id)) {
            return $this->translator->trans('this email address is already in use');
        }
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
        $result = $this->em->getRepository(UserRepository::class)->findByStatus(User::STATUS_WEBMASTER);
        $row = $this->em->getConnection()->db_fetch_assoc($result);

        return $row['mail_address'];
    }

    /**
     * Returns webmaster user
     */
    public function getWebmaster(): array
    {
        $result = $this->em->getRepository(UserRepository::class)->findByStatus(User::STATUS_WEBMASTER);
        $row = $this->em->getConnection()->db_fetch_assoc($result);

        return $row;
    }

    /**
     * Returns webmaster mail address
     */
    public function getWebmasterUsername(): string
    {
        $result = $this->em->getRepository(UserRepository::class)->findByStatus(User::STATUS_WEBMASTER);
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
        $result = $this->em->getRepository(UserInfosRepository::class)->findByStatuses([User::STATUS_GUEST]);
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
        if (is_readable($this->themesDir . '/' . $theme . '/' . 'themeconf.inc.php')) {
            return $theme;
        }

        // let's find the first available theme
        $themes = $this->themeRepository->findAll();

        return $themes->first()->getName();
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

        $this->em->getRepository(CommentRepository::class)->deleteByUserId($user_id);
        // remove  created_by user in image_tag
        $this->em->getRepository(ImageTagRepository::class)->removeCreatedByKey($user_id);

        // destruction of the user
        $this->em->getRepository(UserRepository::class)->deleteById($user_id);
    }
}
