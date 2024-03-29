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
use App\Repository\UserRepository;
use Symfony\Component\Form\DataTransformerInterface;

class IdentifierToUserTransformer implements DataTransformerInterface
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function transform($identifier): ?string
    {
        return $identifier;
    }

    public function reverseTransform($forgotPasswordModel): ?User
    {
        return $this->userRepository->findUserByUsernameOrEmail($forgotPasswordModel->getIdentifier());
    }
}
