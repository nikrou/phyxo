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

namespace App\Controller;

use App\DataMapper\AlbumMapper;
use App\DataMapper\ImageMapper;
use App\Enum\ImageSizeType;
use App\Security\AppUserService;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends AbstractController
{
    public function home(): Response
    {
        return $this->forward(AlbumController::class . '::albums');
    }

    public function download(
        ImageMapper $imageMapper,
        int $image_id,
        AlbumMapper $albumMapper,
        AppUserService $appUserService,
        ImageStandardParams $image_std_params,
        string $rootProjectDir
    ): Response {
        $image = $imageMapper->getRepository()->find($image_id);

        if (!$albumMapper->getRepository()->hasAccessToImage($image_id, $appUserService->getUser()->getUserInfos()->getForbiddenAlbums())) {
            throw new AccessDeniedException('Access denied');
        }

        if (!$appUserService->getUser()->getUserInfos()->hasEnabledHigh()) {
            $deriv = new DerivativeImage($image, $image_std_params->getByType(ImageSizeType::XXLARGE), $image_std_params);
            if (!$deriv->sameAsSource()) {
                throw new AccessDeniedException('Access denied');
            }
        }

        $file = sprintf('%s/%s', $rootProjectDir, $image->getPath());

        return $this->file($file);
    }
}
