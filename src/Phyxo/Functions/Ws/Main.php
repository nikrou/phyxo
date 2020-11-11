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
use App\Entity\ImageAlbum;
use Phyxo\Ws\Server;
use Phyxo\Ws\Error;
use App\Repository\TagRepository;
use App\Repository\ImageTagRepository;
use App\Repository\ImageRepository;

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
        $result = (new ImageRepository($service->getConnection()))->findMaxIdAndCount();
        list($max_id, $image_count) = $service->getConnection()->db_fetch_row($result);

        if (0 == $image_count) {
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
        $where_clauses[] = \Phyxo\Functions\Ws\Main::stdImageSqlFilter($params, '');

        if (!empty($params['ids'])) {
            $where_clauses[] = 'id ' . $service->getConnection()->in($params['ids']);
        }

        $urls = [];
        do {
            $result = (new ImageRepository($service->getConnection()))->findWithConditions($where_clauses, $start_id, $qlimit);
            $is_last = $service->getConnection()->db_num_rows($result) < $qlimit;

            while ($row = $service->getConnection()->db_fetch_assoc($result)) {
                $start_id = $row['id'];
                $src_image = new \Phyxo\Image\SrcImage($row, $service->getConf()['picture_ext']);
                if ($src_image->is_mimetype()) {
                    continue;
                }

                foreach ($types as $type) {
                    $derivative = new \Phyxo\Image\DerivativeImage($src_image, $type, $service->getImageStandardParams());
                    if ($type != $derivative->get_type()) {
                        continue;
                    }
                    if (@filemtime($derivative->get_path()) === false) {
                        $urls[] = $derivative->getUrl() . $uid;
                    }
                }

                if (count($urls) >= $max_urls && !$is_last) {
                    break;
                }
            }
            if ($is_last) {
                $start_id = 0;
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
        $infos['nb_elements'] = (new ImageRepository($service->getConnection()))->count();
        $infos['nb_categories'] = $service->getAlbumMapper()->getRepository()->count([]);
        $infos['nb_virtual'] = $service->getAlbumMapper()->getRepository()->countByType($virtual = true);
        $infos['nb_physical'] = $service->getAlbumMapper()->getRepository()->countByType($virtual = false);
        $infos['nb_image_category'] = $service->getManagerRegistry()->getRepository(ImageAlbum::class)->count([]);
        $infos['nb_tags'] = (new TagRepository($service->getConnection()))->count();
        $infos['nb_image_tag'] = (new ImageTagRepository($service->getConnection()))->count();
        $infos['nb_users'] = $service->getManagerRegistry->getRepository(User::class)->count([]);
        $infos['nb_groups'] = $service->getManagerRegistry->getRepository(Group::class)->count([]);
        $infos['nb_comments'] = $service->getManagerRegistry()->getRepository(Comment::class)->count([]);

        // first element
        if ($infos['nb_elements'] > 0) {
            $infos['first_date'] = (new ImageRepository($service->getConnection()))->findFirstDate();
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
     * returns a "standard" (for our web service) ORDER BY sql clause for images
     */
    public static function stdImageSqlOrder(array $params, string $tbl_name = '', Server $service)
    {
        $ret = '';
        if (empty($params['order'])) {
            return $ret;
        }
        $matches = [];
        preg_match_all('/([a-z_]+) *(?:(asc|desc)(?:ending)?)? *(?:, *|$)/i', $params['order'], $matches);
        for ($i = 0; $i < count($matches[1]); $i++) {
            switch ($matches[1][$i]) {
                case 'date_created':
                    $matches[1][$i] = 'date_creation';
                    break;
                case 'date_posted':
                    $matches[1][$i] = 'date_available';
                    break;
                case 'rand':
                case 'random':
                    $matches[1][$i] = $service->getConnection()::RANDOM_FUNCTION . '()';
                    break;
            }
            $sortable_fields = [
                'id', 'file', 'name', 'hit', 'rating_score',
                'date_creation', 'date_available', $service->getConnection()::RANDOM_FUNCTION . '()'
            ];
            if (in_array($matches[1][$i], $sortable_fields)) {
                if (!empty($ret)) {
                    $ret .= ', ';
                }
                if ($matches[1][$i] != $service->getConnection()::RANDOM_FUNCTION . '()') {
                    $ret .= $tbl_name;
                }
                $ret .= $matches[1][$i];
                $ret .= ' ' . $matches[2][$i];
            }
        }

        return $ret;
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
            if ($service->getUserMapper()->getUser()->hasEnabledHigh()) {
                $ret['element_url'] = $src_image->getUrl();
            }
        } else {
            $ret['element_url'] = \Phyxo\Functions\URL::get_element_url($image_row);
        }

        $derivatives = $service->getImageStandardParams()->getAll($src_image);
        $derivatives_arr = [];
        foreach ($derivatives as $type => $derivative) {
            $size = $derivative->get_size();
            $size != null or $size = [null, null];
            $derivatives_arr[$type] = ['url' => $derivative->getUrl(), 'width' => $size[0], 'height' => $size[1]];
        }
        $ret['derivatives'] = $derivatives_arr;

        return $ret;
    }

    /**
     * returns an array of image attributes that are to be encoded as xml attributes
     * instead of xml elements
     */
    public static function stdGetImageXmlAttributes()
    {
        return ['id', 'element_url', 'page_url', 'file', 'width', 'height', 'hit', 'date_available', 'date_creation'];
    }

    public static function stdGetCategoryXmlAttributes()
    {
        return ['id', 'url', 'nb_images', 'total_nb_images', 'nb_categories', 'date_last', 'max_date_last'];
    }

    public static function stdGetTagXmlAttributes()
    {
        return ['id', 'name', 'url_name', 'counter', 'url', 'page_url'];
    }
}
