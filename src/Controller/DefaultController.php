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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use App\Repository\CategoryRepository;
use App\Repository\ImageRepository;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\SrcImage;
use Phyxo\EntityManager;
use Phyxo\Conf;

class DefaultController extends CommonController
{
    public function home(Request $request)
    {
        return $this->forward('App\Controller\AlbumController::albums');
    }

    public function action(Request $request, int $image_id, string $part, $download = false, EntityManager $em, Conf $conf, ImageStandardParams $image_std_params)
    {
        $filter = [];
        $result = $em->getRepository(ImageRepository::class)->findById($this->getUser(), $filter, $image_id);
        $element_info = $em->getConnection()->db_fetch_assoc($result);

        /* $filter['visible_categories'] and $filter['visible_images']
        /* are not used because it's not necessary (filter <> restriction)
         */
        if (!$em->getRepository(CategoryRepository::class)->hasAccessToImage($this->getUser(), $filter, $image_id)) {
            throw new AccessDeniedException('Access denied');
        }

        $file = '';
        switch ($part) {
            case 'e':
                if (!$this->getUser()->hasEnabledHigh()) {
                    $deriv = new DerivativeImage(new SrcImage($element_info, $conf['picture_ext']), $image_std_params->getByType(ImageStandardParams::IMG_XXLARGE), $image_std_params);
                    if (!$deriv->same_as_source()) {
                        throw new AccessDeniedException('Access denied');
                    }
                }
                $file = \Phyxo\Functions\Utils::get_element_path($element_info);
                break;
            case 'r':
                $file = \Phyxo\Functions\Utils::original_to_representative(\Phyxo\Functions\Utils::get_element_path($element_info), $element_info['representative_ext']);
                break;
        }

        return $this->file($file);
    }
}
