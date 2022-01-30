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

use Phyxo\Conf;
use App\Form\Model\DisplayConfigurationModel;
use Symfony\Component\Form\DataTransformerInterface;

class ConfToDisplayConfigurationTransformer implements DataTransformerInterface
{
    /**
     * @param Conf $conf
     */
    public function transform($conf): DisplayConfigurationModel
    {
        $model = new DisplayConfigurationModel();
        $model->setMenubarFilterIcon($conf['menubar_filter_icon']);
        $model->setIndexNewIcon($conf['index_new_icon']);
        $model->setIndexFlatIcon($conf['index_flat_icon']);
        $model->setIndexSortOrderInput($conf['index_sort_order_input']);
        $model->setIndexPostedDateIcon($conf['index_posted_date_icon']);
        $model->setIndexCreatedDateIcon($conf['index_created_date_icon']);
        $model->setNbCategoriesPage($conf['nb_categories_page']);

        $model->setPictureMetadataIcon($conf['picture_metadata_icon']);
        $model->setPictureDownloadIcon($conf['picture_download_icon']);
        $model->setPictureFavoriteIcon($conf['picture_favorite_icon']);
        $model->setPictureNavigationIcons($conf['picture_navigation_icons']);
        $model->setPictureNavigationThumb($conf['picture_navigation_thumb']);
        $model->setPictureMenu($conf['picture_menu']);

        $model->setPictureInformations($conf['picture_informations']);

        return $model;
    }

    /**
     *  @param DisplayConfigurationModel $displayConfigurationModel
     */
    public function reverseTransform($displayConfigurationModel): array
    {
        $partial_conf = [];
        $partial_conf['menubar_filter_icon'] = ['value' => $displayConfigurationModel->getMenubarFilterIcon(), 'type' => 'boolean'];
        $partial_conf['index_new_icon'] = ['value' => $displayConfigurationModel->getIndexNewIcon(), 'type' => 'boolean'];
        $partial_conf['index_flat_icon'] = ['value' => $displayConfigurationModel->getIndexFlatIcon(), 'type' => 'boolean'];
        $partial_conf['index_sort_order_input'] = ['value' => $displayConfigurationModel->getIndexSortOrderInput(), 'type' => 'boolean'];
        $partial_conf['index_posted_date_icon'] = ['value' => $displayConfigurationModel->getIndexPostedDateIcon(), 'type' => 'boolean'];
        $partial_conf['index_created_date_icon'] = ['value' => $displayConfigurationModel->getIndexCreatedDateIcon(), 'type' => 'boolean'];
        $partial_conf['nb_categories_page'] = ['value' => $displayConfigurationModel->getNbCategoriesPage(), 'type' => 'integer'];

        $partial_conf['picture_metadata_icon'] = ['value' => $displayConfigurationModel->getPictureMetadataIcon(), 'type' => 'boolean'];
        $partial_conf['picture_download_icon'] = ['value' => $displayConfigurationModel->getPictureDownloadIcon(), 'type' => 'boolean'];
        $partial_conf['picture_favorite_icon'] = ['value' => $displayConfigurationModel->getPictureFavoriteIcon(), 'type' => 'boolean'];
        $partial_conf['picture_navigation_icons'] = ['value' => $displayConfigurationModel->getPictureNavigationIcons(), 'type' => 'boolean'];
        $partial_conf['picture_navigation_thumb'] = ['value' => $displayConfigurationModel->getPictureNavigationThumb(), 'type' => 'boolean'];
        $partial_conf['picture_menu'] = ['value' => $displayConfigurationModel->getPictureMenu(), 'type' => 'boolean'];

        $partial_conf['picture_informations'] = ['value' => $displayConfigurationModel->getPictureInformations(), 'type' => 'json'];

        return $partial_conf;
    }
}
