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
use Phyxo\Ws\NamedArray;
use Phyxo\Ws\NamedStruct;

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
    public static function getFilteredList($params, &$service)
    {
        global $services;

        return self::tagsList($services['tags']->getAllTags($params['q']), $params);
    }

    /**
     * API method
     * Returns a list of tags
     * @param mixed[] $params
     *    @option bool sort_by_counter
     */
    public static function getList($params, &$service)
    {
        global $services;

        return self::tagsList($services['tags']->getAvailableTags(), $params);
    }

    /**
     * API method
     * Returns the list of tags as you can see them in administration
     * @param mixed[] $params
     *
     * Only admin can run this method and permissions are not taken into
     * account.
     */
    public static function getAdminList($params, &$service)
    {
        global $services;

        return [
            'tags' => new NamedArray(
                $services['tags']->getAllTags(),
                'tag',
                \Phyxo\Functions\Ws\Main::stdGetTagXmlAttributes()
            )
        ];
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
    public static function getImages($params, &$service)
    {
        global $conn, $services;

        // first build all the tag_ids we are interested in
        $tags = $services['tags']->findTags($params['tag_id'], $params['tag_url_name'], $params['tag_name']);
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

        $order_by = \Phyxo\Functions\Ws\Main::stdImageSqlOrder($params, 'i.');
        if (!empty($order_by)) {
            $order_by = 'ORDER BY ' . $order_by;
        }

        $image_ids = $services['tags']->getImageIdsForTags(
            $tag_ids,
            $params['tag_mode_and'] ? 'AND' : 'OR',
            $where_clauses,
            $order_by
        );

        $count_set = count($image_ids);
        $image_ids = array_slice($image_ids, $params['per_page'] * $params['page'], $params['per_page']);

        $image_tag_map = [];
        // build list of image ids with associated tags per image
        if (!empty($image_ids) and !$params['tag_mode_and']) {
            $query = 'SELECT image_id, ' . $conn->db_group_concat('tag_id') . ' AS tag_ids FROM ' . IMAGE_TAG_TABLE;
            $query .= ' WHERE tag_id ' . $conn->in($tag_ids);
            $query .= ' AND image_id ' . $conn->in($image_ids) . ' GROUP BY image_id';
            $result = $conn->db_query($query);

            while ($row = $conn->db_fetch_assoc($result)) {
                $row['image_id'] = (int)$row['image_id'];
                $image_tag_map[$row['image_id']] = explode(',', $row['tag_ids']);
            }
        }

        $images = [];
        if (!empty($image_ids)) {
            $rank_of = array_flip($image_ids);

            $query = 'SELECT * FROM ' . IMAGES_TABLE;
            $query .= ' WHERE id ' . $conn->in($image_ids);
            $result = $conn->db_query($query);

            while ($row = $conn->db_fetch_assoc($result)) {
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
                $image = array_merge($image, \Phyxo\Functions\Ws\Main::stdGetUrls($row));

                $image_tag_ids = ($params['tag_mode_and']) ? $tag_ids : $image_tag_map[$image['id']];
                $image_tags = [];
                foreach ($image_tag_ids as $tag_id) {
                    $url = \Phyxo\Functions\URL::make_index_url(
                        [
                            'section' => 'tags',
                            'tags' => [$tags_by_id[$tag_id]]
                        ]
                    );
                    $page_url = \Phyxo\Functions\URL::make_picture_url(
                        [
                            'section' => 'tags',
                            'tags' => [$tags_by_id[$tag_id]],
                            'image_id' => $row['id'],
                            'image_file' => $row['file'],
                        ]
                    );
                    $image_tags[] = [
                        'id' => (int)$tag_id,
                        'url' => $url,
                        'page_url' => $page_url,
                    ];
                }

                $image['tags'] = new NamedArray($image_tags, 'tag', \Phyxo\Functions\Ws\Main::stdGetTagXmlAttributes());
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
            'paging' => new NamedStruct(
                [
                    'page' => $params['page'],
                    'per_page' => $params['per_page'],
                    'count' => count($images),
                    'total_count' => $count_set,
                ]
            ),
            'images' => new NamedArray(
                $images,
                'image',
                \Phyxo\Functions\Ws\Main::stdGetImageXmlAttributes()
            )
        ];
    }

    /**
     * API method
     * Adds a tag
     * @param mixed[] $params
     *    @option string name
     */
    public static function add($params, &$service)
    {
        global $services;

        $creation_output = $services['tags']->createTag($params['name']);

        if (isset($creation_output['error'])) {
            return new Error(500, $creation_output['error']);
        }

        return $creation_output;
    }

    // protected methods

    public static function tagsList($tags, $params)
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
            $tags[$i]['url'] = \Phyxo\Functions\URL::make_index_url(
                [
                    'section' => 'tags',
                    'tags' => [$tags[$i]]
                ]
            );
        }

        return [
            'tags' => new NamedArray(
                $tags,
                'tag',
                \Phyxo\Functions\Ws\Main::stdGetTagXmlAttributes()
            )
        ];
    }
}
