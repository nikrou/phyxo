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
use App\Entity\ImageAlbum;
use App\Entity\ImageTag;
use App\Entity\User;
use Phyxo\Ws\Server;
use Phyxo\Ws\Error;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\SrcImage;

class Main
{
    /**
     * API method
     * Returns a list of missing derivatives (not generated yet)
     * @param mixed[] $params
     *    @option string types (optional)
     *    @option int[] ids
     *    @option int max_urls
     *    @option int prev_page (optional)
     */
    public static function getMissingDerivatives($params, Server $service)
    {
        if (empty($params['types'])) {
            $types = array_keys($service->getImageStandardParams()->getDefinedTypeMap());
        } else {
            $types = array_intersect(array_keys($service->getImageStandardParams()->getDefinedTypeMap()), $params['types']);
            if (count($types) == 0) {
                return new Error(Server::WS_ERR_INVALID_PARAM, "Invalid types");
            }
        }

        $max_urls = $params['max_urls'];
        list($max_id, $image_count) = $service->getImageMapper()->getRepository()->findMaxIdAndCount();

        if ($image_count === 0) {
            return [];
        }

        $start_id = $params['prev_page'];
        if ($start_id <= 0) {
            $start_id = $max_id;
        }

        $uid = '&b=' . time();

        $conf['question_mark_in_urls'] = $service->getConf()['php_extension_in_urls'] = true;
        $conf['derivative_url_style'] = 2; //script

        $qlimit = min(5000, ceil(max($image_count / 500, $max_urls / count($types))));
        $where_clauses[] = self::stdImageSqlFilter($params, '');

        if (!empty($params['ids'])) {
            // $where_clauses[] = 'id ' . $service->getConnection()->in($params['ids']);
        }

        $urls = [];
        do {
            foreach ($service->getImageMapper()->getRepository()->findBy(['id' => $params['ids']], null, $start_id, $qlimit) as $image) {
                $start_id = $image->getId();
                $src_image = new SrcImage($image->toArray(), $service->getConf()['picture_ext']);
                if ($src_image->is_mimetype()) {
                    continue;
                }

                foreach ($types as $type) {
                    $derivative = new DerivativeImage($src_image, $type, $service->getImageStandardParams());
                    if ($type != $derivative->get_type()) {
                        continue;
                    }
                    // if (@filemtime($derivative->get_path()) === false) {
                    //     $urls[] = $derivative->getUrl() . $uid;
                    // }
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
     * Returns Phyxo version
     * @param mixed[] $params
     */
    public static function getVersion($params, Server $service)
    {
        return $service->getCoreVersion();
    }

    /**
     * API method
     * Returns general informations about the installation
     * @param mixed[] $params
     */
    public static function getInfos($params, Server $service)
    {
        $infos['version'] = $service->getCoreVersion();
        $infos['nb_elements'] = $service->getImageMapper()->getRepository()->count([]);
        $infos['nb_categories'] = $service->getAlbumMapper()->getRepository()->count([]);
        $infos['nb_virtual'] = $service->getAlbumMapper()->getRepository()->countByType($virtual = true);
        $infos['nb_physical'] = $service->getAlbumMapper()->getRepository()->countByType($virtual = false);
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
     * filters the images (images table only)
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
     * in a standard way by different web service methods
     */
    public static function stdGetUrls(array $image_row, Server $service)
    {
        $ret = [];
        $ret['page_url'] = $service->getRouter()->generate('picture', ['image_id' => $image_row['id'], 'type' => 'file', 'element_id' => $image_row['file']]);
        $src_image = new \Phyxo\Image\SrcImage($image_row, $service->getConf()['picture_ext']);

        if ($src_image->is_original()) { // we have a photo
            if ($service->getUserMapper()->getUser()->getUserInfos()->hasEnabledHigh()) {
                $ret['element_url'] = $src_image->getUrl();
            }
        } else {
            $ret['element_url'] = \Phyxo\Functions\URL::get_element_url($image_row);
        }

        $derivatives = $service->getImageStandardParams()->getAll($src_image);
        $derivatives_arr = [];
        foreach ($derivatives as $type => $derivative) {
            $size = $derivative->get_size();
            /** @phpstan-ignore-next-line */
            $size != null or $size = [null, null];
            $derivatives_arr[$type] = ['url' => $derivative->getUrl(), 'width' => $size[0], 'height' => $size[1]];
        }
        $ret['derivatives'] = $derivatives_arr;

        return $ret;
    }
}
