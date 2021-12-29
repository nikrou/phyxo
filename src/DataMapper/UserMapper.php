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
use App\Repository\UserCacheRepository;
use App\Repository\UserRepository;
use App\Repository\UserInfosRepository;
use App\Repository\ThemeRepository;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use App\Repository\UserMailNotificationRepository;
use App\Repository\UserFeedRepository;
use App\Repository\FavoriteRepository;
use App\Repository\CaddieRepository;
use App\Repository\CommentRepository;
use App\Repository\ImageTagRepository;
use App\Repository\UserCacheAlbumRepository;
use App\Security\AppUserService;

class UserMapper
{
    private $conf, $appUserService, $autorizationChecker, $tagMapper, $themeRepository, $userRepository, $userInfosRepository, $userMailNotificationRepository;
    private $defaultLanguage, $defaultTheme, $themesDir, $default_user, $default_user_retrieved = false, $commentRepository, $imageTagRepository;
    private $webmaster, $webmaster_retrieved = false, $userFeedRepository, $userCacheRepository, $userCacheAlbumRepository, $caddieRepository, $favoriteRepository;

    public function __construct(
        Conf $conf,
        AuthorizationCheckerInterface $autorizationChecker,
        ThemeRepository $themeRepository,
        UserRepository $userRepository,
        UserInfosRepository $userInfosRepository,
        string $defaultTheme,
        CommentRepository $commentRepository,
        TagMapper $tagMapper,
        string $defaultLanguage,
        string $themesDir,
        AppUserService $appUserService,
        UserMailNotificationRepository $userMailNotificationRepository,
        UserFeedRepository $userFeedRepository,
        UserCacheRepository $userCacheRepository,
        UserCacheAlbumRepository $userCacheAlbumRepository,
        CaddieRepository $caddieRepository,
        FavoriteRepository $favoriteRepository,
        ImageTagRepository $imageTagRepository
    ) {
        $this->themeRepository = $themeRepository;
        $this->userRepository = $userRepository;
        $this->userInfosRepository = $userInfosRepository;
        $this->userMailNotificationRepository = $userMailNotificationRepository;
        $this->userFeedRepository = $userFeedRepository;
        $this->conf = $conf;
        $this->autorizationChecker = $autorizationChecker;
        $this->tagMapper = $tagMapper;
        $this->defaultLanguage = $defaultLanguage;
        $this->defaultTheme = $defaultTheme;
        $this->themesDir = $themesDir;
        $this->appUserService = $appUserService;
        $this->userCacheRepository = $userCacheRepository;
        $this->userCacheAlbumRepository = $userCacheAlbumRepository;
        $this->commentRepository = $commentRepository;
        $this->caddieRepository = $caddieRepository;
        $this->favoriteRepository = $favoriteRepository;
        $this->imageTagRepository = $imageTagRepository;
    }

    public function getRepository(): UserRepository
    {
        return $this->userRepository;
    }

    public function getUser(): ?User
    {
        return $this->appUserService->getUser();
    }

    /**
     * Returns webmaster user
     */
    public function getWebmaster(): User
    {
        if (!$this->webmaster_retrieved) {
            $this->webmaster = $this->userRepository->findOneByStatus(User::STATUS_WEBMASTER);
            $this->webmaster_retrieved = true;
        }

        return $this->webmaster;
    }

    public function getDefaultUser(): ?User
    {
        if (!$this->default_user_retrieved) {
            $this->default_user = $this->userInfosRepository->findOneByStatus(User::STATUS_GUEST);
            $this->default_user_retrieved = true;
        }

        return $this->default_user->getUser();
    }

    public function setDefaultTheme(string $theme_id): void
    {
        $this->userInfosRepository->updateFieldForUsers('theme', $theme_id, [$this->getDefaultUser()->getId()]);
    }

    /**
     * Returns the default theme.
     * If the default theme is not available it returns the first available one.
     *
     * @return string
     */
    public function getDefaultTheme(): string
    {
        $theme = is_null($this->getDefaultUser()) ? $this->defaultTheme : $this->getDefaultUser()->getUserInfos()->getTheme();
        if (is_readable($this->themesDir . '/' . $theme . '/' . 'themeconf.inc.php')) {
            return $theme;
        }

        // let's find the first available theme
        $theme = $this->themeRepository->findOneBy([]);

        return $theme->getName();
    }

    /**
     * Returns the default language.
     */
    public function getDefaultLanguage(): string
    {
        return is_null($this->getDefaultUser()) ? $this->defaultLanguage : $this->getDefaultUser()->getUserInfos()->getLanguage();
    }

    public function isGuest(): bool
    {
        return $this->autorizationChecker->isGranted('ROLE_USER');
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
        $number_of_available_tags = count($this->tagMapper->getAvailableTags($this->getUser()));

        $this->userCacheRepository->invalidateNumberbAvailableTags($this->getUser()->getId());

        return $number_of_available_tags;
    }

    /**
     * returns the number of available comments for the connected user
     */
    public function getNumberAvailableComments(): int
    {
        $number_of_available_comments = $this->commentRepository->countAvailableComments($this->getUser()->getUserInfos()->getForbiddenCategories(), $this->isAdmin());

        $this->userCacheRepository->invalidateNumberAvailableComments($this->getUser()->getId());

        return $number_of_available_comments;
    }

    /**
     * Invalidates cached data (permissions and category counts) for all users.
     */
    public function invalidateUserCache(bool $full = true)
    {
        if ($full) {
            $this->userCacheAlbumRepository->deleteAll();
            $this->userCacheRepository->deleteAll();
        } else {
            $this->userCacheRepository->forceRefresh();
        }
    }

    /**
     * Deletes an user.
     * It also deletes all related data (accesses, favorites, permissions, etc.)
     * @todo : accept array input
     */
    public function deleteUser(int $user_id)
    {
        // deletion of calculated permissions linked to the user
        $this->userCacheAlbumRepository->deleteForUser($user_id);
        $this->userCacheRepository->deleteForUser($user_id);

        // destruction of the favorites associated with the user
        $this->favoriteRepository->deleteAllUserFavorites($user_id);

        // destruction of the caddie associated with the user
        $this->caddieRepository->emptyCaddies($user_id);

        $this->commentRepository->deleteByUserId($user_id);

        // remove  created_by user in image_tag
        $this->imageTagRepository->removeCreatedByKey($user_id);

        // destruction of data RSS notification for this user
        $this->userFeedRepository->deleteByUser($user_id);

        // // deletion of phyxo specific informations
        $this->userInfosRepository->deleteByUserId($user_id);

        // destruction of data notification by mail for this user
        $this->userMailNotificationRepository->deleteByUserId($user_id);

        // destruction of the user
        $this->userRepository->deleteById($user_id);
    }
}
