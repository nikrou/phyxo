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
use App\Entity\UserInfos;
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
    private User $default_user;
    private bool $default_user_retrieved = false;
    private User $webmaster;
    private bool $webmaster_retrieved = false;

    public function __construct(private AuthorizationCheckerInterface $authorizationChecker, private ThemeRepository $themeRepository, private UserRepository $userRepository, private UserInfosRepository $userInfosRepository, private string $defaultTheme, private CommentRepository $commentRepository, private TagMapper $tagMapper, private string $defaultLanguage, private string $themesDir, private AppUserService $appUserService, private UserMailNotificationRepository $userMailNotificationRepository, private UserFeedRepository $userFeedRepository, private UserCacheRepository $userCacheRepository, private UserCacheAlbumRepository $userCacheAlbumRepository, private CaddieRepository $caddieRepository, private FavoriteRepository $favoriteRepository, private ImageTagRepository $imageTagRepository)
    {
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
            $this->default_user = $this->userInfosRepository->findOneBy(['status' => User::STATUS_GUEST])->getUser();
            $this->default_user_retrieved = true;
        }

        return $this->default_user;
    }

    public function setDefaultTheme(string $theme_id): void
    {
        $this->userInfosRepository->updateFieldForUsers('theme', $theme_id, [$this->getDefaultUser()->getId()]);
    }

    /**
     * Returns the default theme.
     * If the default theme is not available it returns the first available one.
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

    public function isClassicUser(): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_NORMAL');
    }

    public function isAdmin(): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_ADMIN');
    }

    public function isWebmaster(): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_WEBMASTER');
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
        $number_of_available_comments = $this->commentRepository->countAvailableComments($this->getUser()->getUserInfos()->getForbiddenAlbums(), $this->isAdmin());

        $this->userCacheRepository->invalidateNumberAvailableComments($this->getUser()->getId());

        return $number_of_available_comments;
    }

    /**
     * Invalidates cached data (permissions and category counts) for all users.
     */
    public function invalidateUserCache(bool $full = true): void
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
    public function deleteUser(int $user_id): void
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
