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

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Caddie;
use App\Model\AddImagesToCaddieInput;
use App\Repository\CaddieRepository;
use App\Repository\ImageRepository;
use App\Security\AppUserService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProcessorInterface<AddImagesToCaddieInput, void>
 */
class AddImagesToCaddieProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CaddieRepository $caddieRepository,
        private readonly ImageRepository $imageRepository,
        private readonly AppUserService $appUserService,
    ) {
    }

    /**
     * @param array<string, mixed>&array{request?: Request, previous_data?: mixed, resource_class?: string|null, original_data?: mixed} $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->appUserService->getUser();
        foreach ($this->imageRepository->getList($data->imageIds) as $image) {
            $caddy = new Caddie();
            $caddy->setImage($image);
            $caddy->setUser($user);
            $this->caddieRepository->addOrUpdateCaddie($caddy);
            $user->addCaddie($caddy);
        }
    }
}
