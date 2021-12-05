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
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Conf;
use Phyxo\Functions\Utils;

class DefaultController extends CommonController
{
    public function home()
    {
        return $this->forward('App\Controller\AlbumController::albums');
    }

    public function action(ImageMapper $imageMapper, int $image_id, string $part, AlbumMapper $albumMapper, Conf $conf, ImageStandardParams $image_std_params, $download = false)
    {
        $image = $imageMapper->getRepository()->find($image_id);

        /* $filter['visible_categories'] and $filter['visible_images']
        /* are not used because it's not necessary (filter <> restriction)
         */
        if (!$albumMapper->getRepository()->hasAccessToImage($image_id, $this->getUser()->getUserInfos()->getForbiddenCategories())) {
            throw new AccessDeniedException('Access denied');
        }

        $file = '';
        switch ($part) {
            case 'e':
                if (!$this->getUser()->getUserInfos()->hasEnabledHigh()) {
                    $deriv = new DerivativeImage($image, $image_std_params->getByType(ImageStandardParams::IMG_XXLARGE), $image_std_params);
                    if (!$deriv->same_as_source()) {
                        throw new AccessDeniedException('Access denied');
                    }
                }
                $file = Utils::get_element_path($image->toArray());
                break;
            case 'r':
                $file = Utils::original_to_representative(Utils::get_element_path($image->toArray()), $image->getRepresentativeExt());
                break;
        }

        return $this->file($file);
    }
}
