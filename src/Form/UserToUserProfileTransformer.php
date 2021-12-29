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

namespace App\Form;

use App\Entity\User;
use App\Form\Model\UserProfileModel;
use App\Repository\LanguageRepository;
use App\Repository\ThemeRepository;
use App\Repository\UserRepository;
use Symfony\Component\Form\DataTransformerInterface;

class UserToUserProfileTransformer implements DataTransformerInterface
{
    private $languageRepository, $themeRepository, $userRepository;

    public function __construct(LanguageRepository $languageRepository, ThemeRepository $themeRepository, UserRepository $userRepository)
    {
        $this->languageRepository = $languageRepository;
        $this->themeRepository = $themeRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @param User $user
     */
    public function transform($user): UserProfileModel
    {
        if (!$user instanceof User) {
            throw new \LogicException('The UserProfileType can only be used with User objects');
        }

        $userProfileModel = new UserProfileModel();
        $userProfileModel->setUsername($user->getUsername());
        $userProfileModel->setCurrentPassword($user->getPassword());
        $userProfileModel->setMailAddress($user->getMailAddress());

        $userProfileModel->setTheme($this->themeRepository->findOneBy(['id' => $user->getUserInfos()->getTheme()]));
        $userProfileModel->setLanguage($this->languageRepository->findOneBy(['id' => $user->getUserInfos()->getLanguage()]));
        $userProfileModel->setRecentPeriod($user->getUserInfos()->getRecentPeriod());
        $userProfileModel->setNbImagePage($user->getUserInfos()->getNbImagePage());
        $userProfileModel->setExpand($user->getUserInfos()->wantExpand());
        $userProfileModel->setShowNbComments($user->getUserInfos()->getShowNbComments());
        $userProfileModel->setShowNbHits($user->getUserInfos()->getShowNbHits());

        return $userProfileModel;
    }

    /**
     *  @param UserProfileModel $userProfileModel
     */
    public function reverseTransform($userProfileModel): User
    {
        $user = $this->userRepository->findOneBy(['username' => $userProfileModel->getUsername()]);

        if ($userProfileModel->getNewPassword()) {
            $user->setPlainPassword($userProfileModel->getNewPassword());
        }

        if ($userProfileModel->getMailAddress()) {
            $user->setMailAddress($userProfileModel->getMailAddress());
        }

        if ($userProfileModel->getTheme()) {
            $user->getUserInfos()->setTheme($userProfileModel->getTheme()->getId());
        }

        if ($userProfileModel->getLanguage()) {
            $user->getUserInfos()->setLanguage($userProfileModel->getLanguage()->getId());
        }

        if ($userProfileModel->getRecentPeriod()) {
            $user->getUserInfos()->setRecentPeriod($userProfileModel->getRecentPeriod());
        }

        if ($userProfileModel->getNbImagePage()) {
            $user->getUserInfos()->setNbImagePage($userProfileModel->getNbImagePage());
        }

        $user->getUserInfos()->setExpand($userProfileModel->getExpand());

        $user->getUserInfos()->setShowNbComments($userProfileModel->getShowNbComments());

        $user->getUserInfos()->setShowNbHits($userProfileModel->getShowNbHits());

        return $user;
    }
}
