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
use App\Entity\User;
use App\Form\Model\UserProfileModel;
use App\Repository\UserRepository;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class UserToUserProfileTransformer implements DataTransformerInterface
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function transform(mixed $user = null): UserProfileModel
    {
        if (is_null($user)) {
            return new UserProfileModel();
        }

        if (!$user instanceof User) {
            throw new LogicException('The UserProfileType can only be used with User objects');
        }

        $userProfileModel = new UserProfileModel();
        $userProfileModel->setId($user->getId());
        $userProfileModel->setUsername($user->getUsername());
        $userProfileModel->setCurrentPassword($user->getPassword());
        $userProfileModel->setMailAddress($user->getMailAddress());
        $userProfileModel->setUserInfos($user->getUserInfos());

        return $userProfileModel;
    }

    /**
     *  @param UserProfileModel $userProfileModel
     */
    public function reverseTransform(mixed $userProfileModel): ?User
    {
        $user = $this->userRepository->findOneBy(['id' => $userProfileModel->getId()]);

        if (is_null($user)) {
            throw new TransformationFailedException('User username not found');
        }

        if ($userProfileModel->getNewPassword()) {
            $user->setPlainPassword($userProfileModel->getNewPassword());
        }

        if ($userProfileModel->getMailAddress()) {
            $user->setMailAddress($userProfileModel->getMailAddress());
        }

        return $user;
    }
}
