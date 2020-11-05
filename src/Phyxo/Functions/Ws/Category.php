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

use App\Entity\Album;
use App\Entity\ImageAlbum;
use App\Entity\User;
use App\Entity\UserCacheAlbum;
use App\Entity\UserInfos;
use Phyxo\Ws\Error;
use App\Repository\CategoryRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\ImageRepository;
use Phyxo\Ws\Server;
use App\Repository\BaseRepository;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;

class Category
{
    /**
     * API method
     * Returns images per category
     * @param mixed[] $params
     *    @option int[] cat_id (optional)
     *    @option bool recursive
     *    @option int per_page
     *    @option int page
     *    @option string order (optional)
     */
    public static function getImages($params, Server $service)
    {
        $images = [];

        //------------------------------------------------- get the related categories
        $where_clauses = [];
        foreach ($params['cat_id'] as $cat_id) {
            if ($params['recursive']) {
                $where_clauses[] = 'uppercats ' . $service->getConnection()::REGEX_OPERATOR . ' \'(^|,)' . $service->getConnection()->db_real_escape_string($cat_id) . '(,|$)\'';
            } else {
                $where_clauses[] = 'id=' . $service->getConnection()->db_real_escape_string($cat_id);
            }
        }
        if (!empty($where_clauses)) {
            $where_clauses = ['(' . implode(' OR ', $where_clauses) . ')'];
        }
        $where_clauses[] = (new BaseRepository($service->getConnection()))->getSQLConditionFandF(
            $service->getUserMapper()->getUser(),
            [],
            ['forbidden_categories' => 'id'],
            null,
            true
        );

        $result = (new CategoryRepository($service->getConnection()))->findWithCondition($where_clauses);
        $cats = [];
        while ($row = $service->getConnection()->db_fetch_assoc($result)) {
            $row['id'] = (int)$row['id'];
            $cats[$row['id']] = $row;
        }

        //-------------------------------------------------------- get the images
        if (!empty($cats)) {
            $where_clauses = \Phyxo\Functions\Ws\Main::stdImageSqlFilter($params, 'i.');
            $where_clauses[] = 'category_id ' . $service->getConnection()->in(array_keys($cats));
            $where_clauses[] = (new BaseRepository($service->getConnection()))->getSQLConditionFandF(
                $service->getUserMapper()->getUser(),
                [],
                ['visible_images' => 'i.id'],
                null,
                true
            );

            $order_by = \Phyxo\Functions\Ws\Main::stdImageSqlOrder($params, 'i.', $service);
            if (empty($order_by) and count($params['cat_id']) == 1 and isset($cats[$params['cat_id'][0]]['image_order'])) {
                $order_by = $cats[$params['cat_id'][0]]['image_order'];
            }
            $order_by = empty($order_by) ? $service->getConf()['order_by'] : 'ORDER BY ' . $order_by;
            $result = (new ImageRepository($service->getConnection()))->getImagesFromCategories($where_clauses, $order_by, $params['per_page'], $params['per_page'] * $params['page']);

            while ($row = $service->getConnection()->db_fetch_assoc($result)) {
                $image = [];
                foreach (['id', 'width', 'height', 'hit'] as $k) {
                    if (isset($row[$k])) {
                        $image[$k] = (int)$row[$k];
                    }
                }
                foreach (['file', 'name', 'comment', 'date_creation', 'date_available'] as $k) {
                    $image[$k] = $row[$k];
                }
                $image = array_merge($image, \Phyxo\Functions\Ws\Main::stdGetUrls($row, $service));

                $image_cats = [];
                foreach (explode(',', $row['cat_ids']) as $cat_id) {
                    $url = $service->getRouter()->generate('album', ['category_id' => $cat_id]);
                    $page_url = $service->getRouter()->generate('picture', ['image_id' => $row['id'], 'type' => 'category', 'element_id' => $cat_id]);
                    $image_cats[] = [
                        'id' => (int)$cat_id,
                        'url' => $url,
                        'page_url' => $page_url,
                    ];
                }

                $image['categories'] = $image_cats;
                $images[] = $image;
            }
        }

        return [
            'paging' => [
                'page' => $params['page'],
                'per_page' => $params['per_page'],
                'count' => count($images)
            ],
            'images' => $images,
        ];
    }

    /**
     * API method
     * Returns a list of categories
     * @param mixed[] $params
     *    @option int cat_id (optional)
     *    @option bool recursive
     *    @option bool public
     *    @option bool tree_output
     *    @option bool fullname
     */
    public static function getList($params, Server $service)
    {
        $public_and_visible = false;
        $forbidden_categories = [];

        if ($params['public']) {
            $public_and_visible = true;
        } elseif ($service->getUserMapper()->isAdmin()) {
            /**  in this very specific case, we don't want to hide empty
             * albums. Method calculatePermissions will only return
             * albums that are either locked or private and not permitted
             */
            $forbidden_categories = $service->getUserMapper()->getUser()->getForbiddenCategories();
        }

        $albumsList = null;
        if (!$params['recursive']) {
            if ($params['cat_id'] > 0) {
                $albumsList = $service->getAlbumMapper()->getRepository()->findAuthorizedAlbumsAndParents(
                    $service->getUserMapper()->getUser()->getId(), $params['cat_id'], $forbidden_categories, $public_and_visible
                );
            } else {
                $albumsList = $service->getAlbumMapper()->getRepository()->findNoParentsAuthorizedAlbums(
                    $service->getUserMapper()->getUser()->getId(), $forbidden_categories, $public_and_visible
                );
            }
        } elseif ($params['cat_id'] > 0) {
            $albumsList = $service->getAlbumMapper()->getRepository()->findAuthorizedAlbumsInSubAlbums(
                $service->getUserMapper()->getUser()->getId(), $params['cat_id'], $forbidden_categories, $public_and_visible
            );
        } else {
            $albumsList = $service->getAlbumMapper()->getRepository()->findUnauthorizedAlbums(
                $service->getUserMapper()->getUser()->getId(), $forbidden_categories, $public_and_visible
            );
        }

        $albums = [];
        $image_ids = [];
        $user_representative_updates_for = [];

        list($is_child_date_last, $albums, $image_ids) = $service->getAlbumMapper()->getAlbumThumbnails($service->getUserMapper()->getUser(), $albumsList);

        // management of the album thumbnail -- starts here
        if (count($albums) > 0) {
            $infos_of_images = $service->getAlbumMapper()->getInfosOfImages($service->getUserMapper()->getUser(), $albums, $image_ids, $service->getImageMapper());
        }


        /* compared to code in include/category_cats, we only persist the new
         * user_representative if we have used $user['id'] and not the guest id,
         * or else the real guest may see thumbnail that he should not
         */
        if (!$params['public'] && count($user_representative_updates_for)) {
            foreach ($user_representative_updates_for as $album_id => $image_id) {
                $service->getManagerRegistry()->getRepository(UserCacheAlbum::class)->updateUserRepresentativePicture($service->getUserMapper()->getUser()->getId(), $album_id, $image_id);
            }
        }

        $result = [];
        if (count($albums) > 0) {
            foreach ($albums as $album) {
                $thumbnail_url = '';
                if (isset($infos_of_images[$album->getRepresentativePictureId()])) {
                    $image_infos = $infos_of_images[$album->getRepresentativePictureId()];
                    $derivative_image = new DerivativeImage(
                        $image_infos['src_image'],
                        $service->getImageStandardParams()->getByType(ImageStandardParams::IMG_THUMB),
                        $service->getImageStandardParams()
                    );
                    $thumbnail_url = $derivative_image->getUrl();
                }

                $result[] = [
                    'id' => $album->getId(),
                    'name' => $album->getName(),
                    'comment' => $album->getComment(),
                    'uppercats' => $album->getUppercats(),
                    'id_uppercat' => $album->getParent() !== null  ? $album->getParent()->getId() : null,
                    'global_rank' => $album->getGlobalRank(),
                    'nb_images' => $album->getUserCacheAlbum()->getNbImages(),
                    'nb_total_images' => $album->getUserCacheAlbum()->getCountImages(),
                    'representatitve_picture_id' => '',
                    'date_last' => $album->getUserCacheAlbum()->getDateLast()->format('c'),
                    'max_date_last' => $album->getUserCacheAlbum()->getMaxDateLast()->format('c'),
                    'nb_categories' => $album->getUserCacheAlbum()->getNbAlbums(),
                    'url' => $service->getRouter()->generate('album', ['category_id' => $album->getId()]),
                    'tn_url' => $thumbnail_url,
                ];
            }
        }

        if ($params['tree_output']) {
            return self::categoriesFlatlistToTree($result);
        }

        return ['categories' => $result];
    }

    /**
     * API method
     * Returns the list of categories as you can see them in administration
     * @param mixed[] $params
     *
     * Only admin can run this method and permissions are not taken into
     * account.
     */
    public static function getAdminList($params, Server $service)
    {
        $nb_images_of = $service->getManagerRegistry()->getRepository(ImageAlbum::class)->countImagesByAlbum();

        $albums = [];
        foreach ($service->getManagerRegistry()->getRepository(Album::class)->findAll() as $album) {
            $albums[] = [
                'id' => $album->getId(),
                'name' => $album->getName(),
                'id_uppercat' => $album->getParent() ? $album->getParent()->getId() : null,
                'comment' => $album->getComment(),
                'dir' => $album->getDir(),
                'rank' => $album->getRank(),
                'status' => $album->getStatus(),
                'site_id' => $album->getSite() ? $album->getSite()->getId() : null,
                'visible' => $album->isVisible(),
                'representative_picture_id' => $album->getRepresentativePictureId(),
                'uppercats' => $album->getUppercats(),
                'commentable' => $album->isCommentable(),
                'global_rank' => $album->getGlobalRank(),
                'image_order' => $album->getImageOrder(),
                'lastmodified' => $album->getLastModified(),
                'nb_images' => $nb_images_of[$album->getId()] ?? '',
                'fullname' => strip_tags($service->getAlbumMapper()->getAlbumsDisplayNameCache($album->getUppercats())),
            ];
        }

        // usort($albums, [$service->getAlbumMapper(), 'globalRankCompare']); // Cannot use because globalRankCompare expect Album as params

        return ['categories' => $albums];
    }

    /**
     * API method
     * Adds a category
     * @param mixed[] $params
     *    @option string name
     *    @option int parent (optional)
     *    @option string comment (optional)
     *    @option bool visible
     *    @option string status (optional)
     *    @option bool commentable
     */
    public static function add($params, Server $service)
    {
        $options = [];
        if (!empty($params['status']) && in_array($params['status'], ['private', 'public'])) {
            $options['status'] = $params['status'];
        }

        if (!empty($params['comment'])) {
            $options['comment'] = $params['comment'];
        }

        if (preg_match('/^\s*$/', $params['name'])) {
            return new Error(500, 'The name of an album must not be empty');
        }

        $admin_ids = [];
        if ($service->getUserMapper()->isAdmin()) {
            foreach ($service->getManagerRegistry()->getRepository(UserInfos::class)->findBy(['status' => [User::STATUS_WEBMASTER, User::STATUS_ADMIN]]) as $userInfos) {
                $admin_ids[] = $userInfos->getUser()->getId();
            }
        }

        $parent = null;
        if ((int) $params['parent'] !== 0) {
            $parent = (int) $params['parent'];
        }

        $category_id = $service->getAlbumMapper()->createAlbum($params['name'], $parent, $service->getUserMapper()->getUser()->getId(), $admin_ids, $options);

        $service->getUserMapper()->invalidateUserCache();

        return ['info' => 'Virtual album added', 'id' => $category_id];
    }

    /**
     * API method
     * Sets details of a category
     * @param mixed[] $params
     *    @option int cat_id
     *    @option string name (optional)
     *    @option string comment (optional)
     */
    public static function setInfo($params, Server $service)
    {
        $update = [
            'id' => $params['category_id'],
        ];

        $info_columns = ['name', 'comment', ];

        $perform_update = false;
        foreach ($info_columns as $key) {
            if (isset($params[$key])) {
                $perform_update = true;
                $update[$key] = $params[$key];
            }
        }

        if ($perform_update) {
            (new CategoryRepository($service->getConnection()))->updateCategory($update, $update['id']);
        }
    }

    /**
     * API method
     * Sets representative image of a category
     * @param mixed[] $params
     *    @option int category_id
     *    @option int image_id
     */
    public static function setRepresentative($params, Server $service)
    {
        // does the category really exist?
        $result = (new CategoryRepository($service->getConnection()))->findById($params['category_id']);
        list($count) = $service->getConnection()->db_fetch_row($result);
        if ($count == 0) {
            return new Error(404, 'category_id not found');
        }

        // does the image really exist?
        if (!(new ImageRepository($service->getConnection()))->isImageExists($params['image_id'])) {
            return new Error(404, 'image_id not found');
        }

        // apply change
        (new CategoryRepository($service->getConnection()))->updateCategory(['representative_picture_id' => $params['image_id']], $params['category_id']);

        $service->getManagerRegistry()->getRepository(UserCacheAlbum::class)->unsetUserRepresentativePictureForAlbum($params['category_id']);
    }

    /**
     * API method
     * Deletes a category
     * @param mixed[] $params
     *    @option string|int[] category_id
     *    @option string photo_deletion_mode
     *    @option string pwg_token
     */
    public static function delete($params, Server $service)
    {
        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        $modes = ['no_delete', 'delete_orphans', 'force_delete'];
        if (!in_array($params['photo_deletion_mode'], $modes)) {
            return new Error(
                500,
                '[\Phyxo\Functions\Ws\Categories::delete]'
                    . ' invalid parameter photo_deletion_mode "' . $params['photo_deletion_mode'] . '"'
                    . ', possible values are {' . implode(', ', $modes) . '}.'
            );
        }

        if (!is_array($params['category_id'])) {
            $params['category_id'] = preg_split(
                '/[\s,;\|]/',
                $params['category_id'],
                -1,
                PREG_SPLIT_NO_EMPTY
            );
        }
        $params['category_id'] = array_map('intval', $params['category_id']);

        $category_ids = [];
        foreach ($params['category_id'] as $category_id) {
            if ($category_id > 0) {
                $category_ids[] = $category_id;
            }
        }

        if (count($category_ids) == 0) {
            return;
        }

        $service->getAlbumMapper()->deleteAlbums($category_ids);

        // now, should we delete photos that are virtually linked to the category?
        if ($params['photo_deletion_mode'] === 'delete_orphans' || $params['photo_deletion_mode'] === 'force_delete') {
            $result = $service->getEntityManager()->getRepository(ImageCategoryRepository::class)->getImageIdsLinked($category_ids);
            $image_ids_linked = $service->getEntityManager()->getConnection()->result2array($result, null, 'image_id');

            if (count($image_ids_linked) > 0) {
                if ($params['photo_deletion_mode'] === 'delete_orphans') {
                    $result = $$service->getEntityManager->getRepository(ImageCategoryRepository::class)->getImageIdsNotOrphans($image_ids_linked, $category_ids);

                    $image_ids_not_orphans = $service->getEntityManager()->getConnection()->result2array($result, null, 'image_id');
                    $image_ids_to_delete = array_diff($image_ids_linked, $image_ids_not_orphans);
                }

                if ($params['photo_deletion_mode'] === 'force_delete') {
                    $image_ids_to_delete = $image_ids_linked;
                }

                $service->getImageMapper()->deleteElements($image_ids_to_delete, true);
            }
        }

        // destruction of all photos physically linked to the category
        $result = $service->getEntityManager()->getRepository(ImageRepository::class)->findByFields('storage_category_id', $category_ids);
        $element_ids = $service->getEntityManager()->getConnection()->result2array($result, null, 'id');
        $service->getImageMapper()->deleteElements($element_ids);

        $service->getCategoryMapper()->updateGlobalRank();
    }

    /**
     * API method
     * Moves a category
     * @param mixed[] $params
     *    @option string|int[] category_id
     *    @option int parent
     *    @option string pwg_token
     */
    public static function move($params, Server $service)
    {
        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        if (!is_array($params['category_id'])) {
            $params['category_id'] = preg_split(
                '/[\s,;\|]/',
                $params['category_id'],
                -1,
                PREG_SPLIT_NO_EMPTY
            );
        }
        $params['category_id'] = array_map('intval', $params['category_id']);

        $category_ids = [];
        foreach ($params['category_id'] as $category_id) {
            if ($category_id > 0) {
                $category_ids[] = $category_id;
            }
        }

        if (count($category_ids) == 0) {
            return new Error(403, 'Invalid category_id input parameter, no category to move');
        }

        // we can't move physical categories
        $categories_in_db = [];

        $result = (new CategoryRepository($service->getConnection()))->findByIds($category_ids);
        while ($row = $service->getConnection()->db_fetch_assoc($result)) {
            $categories_in_db[$row['id']] = $row;

            // we break on error at first physical category detected
            if (!empty($row['dir'])) {
                $row['name'] = strip_tags($row['name']);

                return new Error(
                    403,
                    sprintf(
                        'Category %s (%u) is not a virtual category, you cannot move it',
                        $row['name'],
                        $row['id']
                    )
                );
            }
        }

        if (count($categories_in_db) != count($category_ids)) {
            $unknown_category_ids = array_diff($category_ids, array_keys($categories_in_db));

            return new Error(403, sprintf('Category %u does not exist', $unknown_category_ids[0]));
        }

        /* does this parent exists? This check should be made in the
         * CategoryMapper::moveCategories function, not here
         * 0 as parent means "move categories at gallery root"
         */
        if (0 != $params['parent']) {
            $subcat_ids = (new CategoryRepository($service->getConnection()))->getSubcatIds([$params['parent']]);
            if (count($subcat_ids) == 0) {
                return new Error(403, 'Unknown parent category id');
            }
        }

        $page['infos'] = [];
        $page['errors'] = [];

        $service->getCategoryMapper()->moveCategories($category_ids, $params['parent']);
        $service->getUserMapper()->invalidateUserCache();

        if (count($page['errors']) != 0) {
            return new Error(403, implode('; ', $page['errors']));
        }
    }

    /**
     * create a tree from a flat list of categories, no recursivity for high speed
     */
    protected static function categoriesFlatlistToTree($categories)
    {
        $tree = [];
        $key_of_cat = [];

        foreach ($categories as $key => &$node) {
            $key_of_cat[$node['id']] = $key;

            if (!isset($node['id_uppercat'])) {
                $tree[] = &$node;
            } else {
                if (!isset($categories[$key_of_cat[$node['id_uppercat']]]['sub_categories'])) {
                    $categories[$key_of_cat[$node['id_uppercat']]]['sub_categories'] = [];
                }

                $categories[$key_of_cat[$node['id_uppercat']]]['sub_categories'][] = &$node;
            }
        }

        return $tree;
    }
}
