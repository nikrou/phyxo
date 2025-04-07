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

namespace Phyxo\Functions\Ws;

use App\Entity\Comment;
use App\Entity\Group;
use App\Entity\Image;
use App\Entity\ImageAlbum;
use App\Entity\ImageTag;
use App\Entity\User;
use Phyxo\Image\DerivativeImage;
use Phyxo\Ws\Error;
use Phyxo\Ws\Server;

class Main
{
    /**
     * API method
     * Returns a list of missing derivatives (not generated yet).
     *
     * @param mixed[] $params
     *
     *    @option string types (optional)
     *    @option int[] ids
     *    @option int max_urls
     *    @option int prev_page (optional)
     */
    public static function getMissingDerivatives($params, Server $service)
    {
        $where_clauses = [];
        if (empty($params['types'])) {
            $types = array_keys($service->getImageStandardParams()->getDefinedTypeMap());
        } else {
            $types = array_intersect(array_keys($service->getImageStandardParams()->getDefinedTypeMap()), $params['types']);
            if (count($types) == 0) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'Invalid types');
            }
        }

        $max_urls = $params['max_urls'];
        [$max_id, $image_count] = $service->getImageMapper()->getRepository()->getMaxLastModified();

        if ($image_count === 0) {
            return [];
        }

        $start_id = $params['prev_page'];
        if ($start_id <= 0) {
            $start_id = $max_id;
        }

        $qlimit = min(5000, ceil(max($image_count / 500, $max_urls / count($types))));
        $where_clauses[] = self::stdImageSqlFilter($params, '');

        if (!empty($params['ids'])) {
            // $where_clauses[] = 'id ' . $service->getConnection()->in($params['ids']);
        }

        $urls = [];
        do {
            foreach ($service->getImageMapper()->getRepository()->findBy(['id' => $params['ids']], null, $start_id, $qlimit) as $image) {
                $start_id = $image->getId();
                foreach ($types as $type) {
                    $derivative = new DerivativeImage($image, $type, $service->getImageStandardParams());
                    if ($derivative->getType() !== $type) {
                        continue;
                    }
                }

                if (count($urls) >= $max_urls) {
                    break;
                }
            }
        } while (count($urls) < $max_urls && $start_id);

        $ret = [];
        if ($start_id) {
            $ret['next_page'] = $start_id;
        }
        $ret['urls'] = $urls;

        return $ret;
    }

    /**
     * API method
     * Returns Phyxo version.
     *
     * @param mixed[] $params
     */
    public static function getVersion($params, Server $service)
    {
        return $service->getCoreVersion();
    }

    /**
     * API method
     * Returns general informations about the installation.
     *
     * @param mixed[] $params
     */
    public static function getInfos($params, Server $service)
    {
        $infos = [];
        $infos['version'] = $service->getCoreVersion();
        $infos['nb_elements'] = $service->getImageMapper()->getRepository()->count([]);
        $infos['nb_categories'] = $service->getAlbumMapper()->getRepository()->count([]);
        $infos['nb_image_category'] = $service->getManagerRegistry()->getRepository(ImageAlbum::class)->count([]);
        $infos['nb_tags'] = $service->getTagMapper()->getRepository()->count([]);
        $infos['nb_image_tag'] = $service->getManagerRegistry()->getRepository(ImageTag::class)->count([]);
        $infos['nb_users'] = $service->getManagerRegistry()->getRepository(User::class)->count([]);
        $infos['nb_groups'] = $service->getManagerRegistry()->getRepository(Group::class)->count([]);
        $infos['nb_comments'] = $service->getManagerRegistry()->getRepository(Comment::class)->count([]);

        // first element
        if ($infos['nb_elements'] > 0) {
            $infos['first_date'] = $service->getImageMapper()->getRepository()->findFirstDate();
        }

        // unvalidated comments
        if ($infos['nb_comments'] > 0) {
            $infos['nb_unvalidated_comments'] = $service->getManagerRegistry()->getRepository(Comment::class)->count(['validated' => false]);
        }

        foreach ($infos as $name => $value) {
            $output[] = [
                'name' => $name,
                'value' => $value,
            ];
        }

        return ['infos' => $output];
    }

    /**
     * returns a "standard" (for our web service) array of sql where clauses that
     * filters the images (images table only).
     */
    public static function stdImageSqlFilter($params, $tbl_name = '')
    {
        $clauses = [];
        if (is_numeric($params['f_min_rate'])) {
            $clauses[] = $tbl_name . 'rating_score>=' . $params['f_min_rate'];
        }
        if (is_numeric($params['f_max_rate'])) {
            $clauses[] = $tbl_name . 'rating_score<=' . $params['f_max_rate'];
        }
        if (is_numeric($params['f_min_hit'])) {
            $clauses[] = $tbl_name . 'hit>=' . $params['f_min_hit'];
        }
        if (is_numeric($params['f_max_hit'])) {
            $clauses[] = $tbl_name . 'hit<=' . $params['f_max_hit'];
        }
        if (isset($params['f_min_date_available'])) {
            $clauses[] = $tbl_name . "date_available>='" . $params['f_min_date_available'] . "'";
        }
        if (isset($params['f_max_date_available'])) {
            $clauses[] = $tbl_name . "date_available<'" . $params['f_max_date_available'] . "'";
        }
        if (isset($params['f_min_date_created'])) {
            $clauses[] = $tbl_name . "date_creation>='" . $params['f_min_date_created'] . "'";
        }
        if (isset($params['f_max_date_created'])) {
            $clauses[] = $tbl_name . "date_creation<'" . $params['f_max_date_created'] . "'";
        }
        if (is_numeric($params['f_min_ratio'])) {
            $clauses[] = $tbl_name . 'width/' . $tbl_name . 'height>=' . $params['f_min_ratio'];
        }
        if (is_numeric($params['f_max_ratio'])) {
            $clauses[] = $tbl_name . 'width/' . $tbl_name . 'height<=' . $params['f_max_ratio'];
        }
        if (is_numeric($params['f_max_level'])) {
            $clauses[] = $tbl_name . 'level <= ' . $params['f_max_level'];
        }

        return $clauses;
    }

    /**
     * returns an array map of urls (thumb/element) for image_row - to be returned
     * in a standard way by different web service methods.
     */
    public static function stdGetUrls(Image $image, Server $service)
    {
        $ret = [];
        $ret['page_url'] = $service->getRouter()->generate('picture', ['image_id' => $image->getId(), 'type' => 'file', 'element_id' => $image->getFile()]);

        if (in_array($image->getExtension(), $service->getConf()['picture_ext'])) { // we have a photo
            if ($service->getUserMapper()->getUser()->getUserInfos()->hasEnabledHigh()) {
                $ret['element_url'] = $service->getRouter()->generate('admin_media', ['path' => $image->getPath(), 'derivative' => '', 'image_extension' => $image->getExtension()]);
            }
        } else {
            $ret['element_url'] = $image->getPath();
        }

        $derivatives = $service->getImageStandardParams()->getAll($image);
        $derivatives_arr = [];
        foreach ($derivatives as $type => $derivative) {
            $size = $derivative->getSize();
            if ($size == null) {
                $size = [null, null];
            }
            $derivatives_arr[$type] = [
                'url' => $service->getRouter()->generate('admin_media', ['path' => $image->getPathBasename(), 'derivative' => $derivative->getUrlType(), 'image_extension' => $image->getExtension()]),
                'width' => $size[0],
                'height' => $size[1],
            ];
        }
        $ret['derivatives'] = $derivatives_arr;

        return $ret;
    }
}
