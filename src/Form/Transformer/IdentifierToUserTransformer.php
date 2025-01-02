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

use App\Entity\User;
use App\Form\Model\ForgotPasswordModel;
use App\Repository\UserRepository;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<string, ForgotPasswordModel>
 */
class IdentifierToUserTransformer implements DataTransformerInterface
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function transform($identifier): ?string
    {
        return $identifier;
    }

    /**
     *  @param ForgotPasswordModel $forgotPasswordModel
     */
    public function reverseTransform(mixed $forgotPasswordModel): ?User
    {
        return $this->userRepository->findUserByUsernameOrEmail($forgotPasswordModel->getIdentifier());
    }
}
