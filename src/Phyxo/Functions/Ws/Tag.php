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

use App\Entity\Tag as EntityTag;
use Phyxo\Ws\Error;
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
        $tags = [];
        foreach ($service->getTagMapper()->getAllTags() as $tag) {
            $tags[] = $tag->toArray();
        }

        return ['tags' => $tags];
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
        $tags_by_id = [];
        foreach ($service->getTagMapper()->getRepository()->findByIdsOrNamesOrUrlNames($params['tag_id'], $params['tag_url_name'], $params['tag_name']) as $tag) {
            $tags_by_id[$tag->getId()] = $tag;
        }
        $tag_ids = array_keys($tags_by_id);

        $image_ids = [];
        foreach ($service->getImageMapper()->getRepository()->getImageIdsForTags(
            $service->getUserMapper()->getUser()->getUserInfos()->getForbiddenCategories(),
            $tag_ids,
            $params['tag_mode_and'] ? 'AND' : 'OR'
        ) as $image) {
            $image_ids[] = $image->getId();
        }

        $count_set = count($image_ids);
        $image_ids = array_slice($image_ids, $params['per_page'] * $params['page'], $params['per_page']);

        $image_tag_map = [];
        // build list of image ids with associated tags per image
        if (count($image_ids) > 0 && !$params['tag_mode_and']) {
            $image_tag_map = [];
            foreach ($service->getTagMapper()->getRepository()->findImageTags($tag_ids, $image_ids) as $tag) {
                $image_tag_map[$tag->getImage()->getId()][] = $tag->getId();
            }
        }

        $images = [];
        if (!empty($image_ids)) {
            $rank_of = array_flip($image_ids);

            foreach ($service->getImageMapper()->getRepository()->findBy(['id' => $image_ids]) as $image) {
                $image_infos = $image->toArray();
                $image_infos['rank'] = $rank_of[$image->getId()];
                $image_infos = array_merge($image_infos, Main::stdGetUrls($image, $service));

                $image_tag_ids = ($params['tag_mode_and']) ? $tag_ids : $image_tag_map[$image->getId()];
                $image_tags = [];
                foreach ($image_tag_ids as $tag_id) {
                    $url = $service->getRouter()->generate('images_by_tags', ['tag_ids' => URL::tagToUrl($tags_by_id[$tag_id])]);
                    $page_url = $service->getRouter()->generate('picture', ['image_id' => $image->getId(), 'type' => 'tags', 'element_id' => URL::tagToUrl($tags_by_id[$tag_id])]);
                    $image_tags[] = [
                        'id' => (int)$tag_id,
                        'url' => $url,
                        'page_url' => $page_url,
                    ];
                }

                $image['tags'] = $image_tags;
                $images[$image['id']] = $image_infos;
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
        if (!is_null($service->getTagMapper()->getRepository()->findOneBy(['name' => $params['name']]))) {
            return new Error(500, "Tag already exists");
        } else {
            $tag = new EntityTag();
            $tag->setName($params['name']);
            $tag->setUrlName($params['name']);
            $tag->setLastModified(new \DateTime());
            $service->getTagMapper()->getRepository()->addOrUpdateTag($tag);
        }

        return ['info' => 'Tag was added', 'id' => $tag->getId()];
    }

    // protected methods

    public static function tagsList(array $tags, $params, Server $service)
    {
        if (!empty($params['sort_by_counter'])) {
            usort($tags, [$service->getTagMapper(), 'counterCompare']);
        } else {
            usort($tags, [$service->getTagMapper(), 'alphaCompare']);
        }

        for ($i = 0; $i < count($tags); $i++) {
            if (!empty($params['sort_by_counter'])) {
                $tags[$i]['counter'] = (int)$tags[$i]['counter'];
            }
            //$tags[$i]['url'] = $service->getRouter()->generate('images_by_tags', ['tag_ids' => $tags[$i]->toUrl()]);
        }

        return ['tags' => $tags];
    }
}
