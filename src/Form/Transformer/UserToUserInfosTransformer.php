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

namespace App\Form\Transformer;

use LogicException;
use App\Entity\UserInfos;
use App\Form\Model\UserInfosModel;
use App\Repository\LanguageRepository;
use App\Repository\ThemeRepository;
use App\Repository\UserRepository;
use Symfony\Component\Form\DataTransformerInterface;

class UserToUserInfosTransformer implements DataTransformerInterface
{
    private $languageRepository, $themeRepository, $userRepository;

    public function __construct(LanguageRepository $languageRepository, ThemeRepository $themeRepository, UserRepository $userRepository)
    {
        $this->languageRepository = $languageRepository;
        $this->themeRepository = $themeRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @param UserInfos $userInfos
     */
    public function transform($userInfos): UserInfosModel
    {
        if (!$userInfos instanceof UserInfos) {
            throw new LogicException('The UserInfosType can only be used with UserInfos objects');
        }

        $userInfosModel = new userInfosModel();
        $userInfosModel->setUsername($userInfos->getUser()->getUserIdentifier());
        $userInfosModel->setTheme($this->themeRepository->findOneBy(['id' => $userInfos->getTheme()]));
        $userInfosModel->setLanguage($this->languageRepository->findOneBy(['id' => $userInfos->getLanguage()]));
        $userInfosModel->setRecentPeriod($userInfos->getRecentPeriod());
        $userInfosModel->setNbImagePage($userInfos->getNbImagePage());
        $userInfosModel->setExpand($userInfos->wantExpand());
        $userInfosModel->setShowNbComments($userInfos->getShowNbComments());
        $userInfosModel->setShowNbHits($userInfos->getShowNbHits());

        return $userInfosModel;
    }

    /**
     *  @param userInfosModel $userInfosModel
     */
    public function reverseTransform($userInfosModel): UserInfos
    {
        $userInfos = $this->userRepository->findOneBy(['username' => $userInfosModel->getUsername()])->getUserInfos();

        if ($userInfosModel->getTheme()) {
            $userInfos->setTheme($userInfosModel->getTheme()->getId());
        }

        if ($userInfosModel->getLanguage()) {
            $userInfos->setLanguage($userInfosModel->getLanguage()->getId());
        }

        if ($userInfosModel->getRecentPeriod()) {
            $userInfos->setRecentPeriod($userInfosModel->getRecentPeriod());
        }

        if ($userInfosModel->getNbImagePage()) {
            $userInfos->setNbImagePage($userInfosModel->getNbImagePage());
        }

        $userInfos->setExpand($userInfosModel->getExpand());

        $userInfos->setShowNbComments($userInfosModel->getShowNbComments());

        $userInfos->setShowNbHits($userInfosModel->getShowNbHits());

        return $userInfos;
    }
}
