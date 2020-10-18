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

use Phyxo\Ws\Error;
use App\Repository\TagRepository;
use App\Repository\ImageTagRepository;
use App\Repository\ImageRepository;
use Phyxo\Functions\URL;
use Phyxo\Ws\Server;

class Tag
{
    /**
     * API method
     * Returns al list of all tags even associated to no image.
     * The list can be can be filtered with option "q".
     * @param mixed[] $params
     *    @option q     subtsring of tag o search for
     *    @option limit limit the number of tags to retrieved
     */
    public static function getFilteredList($params, Server $service)
    {
        return self::tagsList($service->getTagMapper()->getAllTags($params['q']), $params, $service);
    }

    /**
     * API method
     * Returns a list of tags
     * @param mixed[] $params
     *    @option bool sort_by_counter
     */
    public static function getList($params, Server $service)
    {
        return self::tagsList($service->getTagMapper()->getAvailableTags($service->getUserMapper()->getUser()), $params, $service);
    }

    /**
     * API method
     * Returns the list of tags as you can see them in administration
     * @param mixed[] $params
     *
     * Only admin can run this method and permissions are not taken into
     * account.
     */
    public static function getAdminList($params, Server $service)
    {
        return ['tags' => $service->getTagMapper()->getAllTags()];
    }

    /**
     * API method
     * Returns a list of images for tags
     * @param mixed[] $params
     *    @option int[] tag_id (optional)
     *    @option string[] tag_url_name (optional)
     *    @option string[] tag_name (optional)
     *    @option bool tag_mode_and
     *    @option int per_page
     *    @option int page
     *    @option string order
     */
    public static function getImages($params, Server $service)
    {
        // first build all the tag_ids we are interested in
        $tags = $service->getConnection()->result2array((new TagRepository($service->getConnection()))->findTags($params['tag_id'], $params['tag_url_name'], $params['tag_name']));
        $tags_by_id = [];
        foreach ($tags as $tag) {
            $tags['id'] = (int)$tag['id'];
            $tags_by_id[$tag['id']] = $tag;
        }
        unset($tags);
        $tag_ids = array_keys($tags_by_id);

        $where_clauses = \Phyxo\Functions\Ws\Main::stdImageSqlFilter($params);
        if (!empty($where_clauses)) {
            $where_clauses = implode(' AND ', $where_clauses);
        }

        $order_by = \Phyxo\Functions\Ws\Main::stdImageSqlOrder($params, 'i.', $service);
        if (!empty($order_by)) {
            $order_by = 'ORDER BY ' . $order_by;
        }

        $image_ids = $service->getConnection()->result2array(
            (new TagRepository($service->getConnection()))->getImageIdsForTags(
                $service->getUserMapper()->getUser(),
                [],
                $tag_ids,
                $params['tag_mode_and'] ? 'AND' : 'OR',
                $where_clauses,
                $order_by
            ),
            null,
            'id'
        );

        $count_set = count($image_ids);
        $image_ids = array_slice($image_ids, $params['per_page'] * $params['page'], $params['per_page']);

        $image_tag_map = [];
        // build list of image ids with associated tags per image
        if (!empty($image_ids) and !$params['tag_mode_and']) {
            $result = (new ImageTagRepository($service->getConnection()))->findImageTags($tag_ids, $image_ids);

            while ($row = $service->getConnection()->db_fetch_assoc($result)) {
                $row['image_id'] = (int)$row['image_id'];
                $image_tag_map[$row['image_id']] = explode(',', $row['tag_ids']);
            }
        }

        $images = [];
        if (!empty($image_ids)) {
            $rank_of = array_flip($image_ids);

            $result = (new ImageRepository($service->getConnection()))->findByIds($image_ids);
            while ($row = $service->getConnection()->db_fetch_assoc($result)) {
                $image = [];
                $image['rank'] = $rank_of[$row['id']];

                foreach (['id', 'width', 'height', 'hit'] as $k) {
                    if (isset($row[$k])) {
                        $image[$k] = (int)$row[$k];
                    }
                }
                foreach (['file', 'name', 'comment', 'date_creation', 'date_available'] as $k) {
                    $image[$k] = $row[$k];
                }
                $image = array_merge($image, \Phyxo\Functions\Ws\Main::stdGetUrls($row, $service));

                $image_tag_ids = ($params['tag_mode_and']) ? $tag_ids : $image_tag_map[$image['id']];
                $image_tags = [];
                foreach ($image_tag_ids as $tag_id) {
                    $url = $service->getRouter()->generate('images_by_tags', ['tag_ids' => URL::tagToUrl($tags_by_id[$tag_id])]);
                    $page_url = $service->getRouter()->generate('picture', ['image_id' => $row['id'], 'type' => 'tags', 'element_id' => URL::tagToUrl($tags_by_id[$tag_id])]);
                    $image_tags[] = [
                        'id' => (int)$tag_id,
                        'url' => $url,
                        'page_url' => $page_url,
                    ];
                }

                $image['tags'] = $image_tags;
                $images[$image['id']] = $image;
            }
            // postgresql does not understand order by field(field_name, id1, id2, id3,...)
            $tmp = [];
            foreach ($image_ids as $image_id) {
                $tmp[] = $images[$image_id];
            }
            $images = $tmp;
            unset($tmp);

            usort($images, '\Phyxo\Functions\Utils::rank_compare');
            unset($rank_of);
        }

        return [
            'paging' => [
                'page' => $params['page'],
                'per_page' => $params['per_page'],
                'count' => count($images),
                'total_count' => $count_set,
            ],
            'images' => $images,
        ];
    }

    /**
     * API method
     * Adds a tag
     * @param mixed[] $params
     *    @option string name
     */
    public static function add($params, Server $service)
    {
        $creation_output = $service->getTagMapper()->createTag($params['name']);

        if (isset($creation_output['error'])) {
            return new Error(500, $creation_output['error']);
        }

        return $creation_output;
    }

    // protected methods

    public static function tagsList($tags, $params, Server $service)
    {
        if (!empty($params['sort_by_counter'])) {
            usort(
                $tags,
                function ($a, $b) {
                    return -$a['counter'] + $b['counter'];
                }
            );
        } else {
            usort($tags, '\Phyxo\Functions\Utils::tag_alpha_compare');
        }

        for ($i = 0; $i < count($tags); $i++) {
            $tags[$i]['id'] = (int)$tags[$i]['id'];
            if (!empty($params['sort_by_counter'])) {
                $tags[$i]['counter'] = (int)$tags[$i]['counter'];
            }
            $tags[$i]['url'] = $service->getRouter()->generate('images_by_tags', ['tag_ids' => URL::tagToUrl($tags[$i])]);
        }

        return ['tags' => $tags];
    }
}
