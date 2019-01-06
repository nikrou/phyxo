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
use App\Repository\CategoryRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\UserCacheCategoriesRepository;
use App\Repository\ImageRepository;

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
    public static function getImages($params, &$service)
    {
        global $user, $conf, $conn;

        $images = [];

        //------------------------------------------------- get the related categories
        $where_clauses = [];
        foreach ($params['cat_id'] as $cat_id) {
            if ($params['recursive']) {
                $where_clauses[] = 'uppercats ' . $conn::REGEX_OPERATOR . ' \'(^|,)' . $conn->db_real_escape_string($cat_id) . '(,|$)\'';
            } else {
                $where_clauses[] = 'id=' . $conn->db_real_escape_string($cat_id);
            }
        }
        if (!empty($where_clauses)) {
            $where_clauses = ['(' . implode(' OR ', $where_clauses) . ')'];
        }
        $where_clauses[] = \Phyxo\Functions\SQL::get_sql_condition_FandF(
            ['forbidden_categories' => 'id'],
            null,
            true
        );

        $result = (new CategoryRepository($conn))->findWithCondtion($where_clauses);
        $cats = [];
        while ($row = $conn->db_fetch_assoc($result)) {
            $row['id'] = (int)$row['id'];
            $cats[$row['id']] = $row;
        }

        //-------------------------------------------------------- get the images
        if (!empty($cats)) {
            $where_clauses = \Phyxo\Functions\Ws\Main::stdImageSqlFilter($params, 'i.');
            $where_clauses[] = 'category_id ' . $conn->in(array_keys($cats));
            $where_clauses[] = \Phyxo\Functions\SQL::get_sql_condition_FandF(
                ['visible_images' => 'i.id'],
                null,
                true
            );

            $order_by = \Phyxo\Functions\Ws\Main::stdImageSqlOrder($params, 'i.');
            if (empty($order_by) and count($params['cat_id']) == 1 and isset($cats[$params['cat_id'][0]]['image_order'])) {
                $order_by = $cats[$params['cat_id'][0]]['image_order'];
            }
            $order_by = empty($order_by) ? $conf['order_by'] : 'ORDER BY ' . $order_by;
            $result = (new ImageRepository($conn))->getImagesFromCategories($where_clauses, $order_by, $params['per_page'], $params['per_page'] * $params['page']);

            while ($row = $conn->db_fetch_assoc($result)) {
                $image = [];
                foreach (['id', 'width', 'height', 'hit'] as $k) {
                    if (isset($row[$k])) {
                        $image[$k] = (int)$row[$k];
                    }
                }
                foreach (['file', 'name', 'comment', 'date_creation', 'date_available'] as $k) {
                    $image[$k] = $row[$k];
                }
                $image = array_merge($image, \Phyxo\Functions\Ws\Main::stdGetUrls($row));

                $image_cats = [];
                foreach (explode(',', $row['cat_ids']) as $cat_id) {
                    $url = \Phyxo\Functions\URL::make_index_url(['category' => $cats[$cat_id]]);
                    $page_url = \Phyxo\Functions\URL::make_picture_url(
                        [
                            'category' => $cats[$cat_id],
                            'image_id' => $row['id'],
                            'image_file' => $row['file'],
                        ]
                    );
                    $image_cats[] = [
                        'id' => (int)$cat_id,
                        'url' => $url,
                        'page_url' => $page_url,
                    ];
                }

                $image['categories'] = new NamedArray(
                    $image_cats,
                    'category',
                    ['id', 'url', 'page_url']
                );
                $images[] = $image;
            }
        }

        return [
            'paging' => new NamedStruct(
                [
                    'page' => $params['page'],
                    'per_page' => $params['per_page'],
                    'count' => count($images)
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
     * Returns a list of categories
     * @param mixed[] $params
     *    @option int cat_id (optional)
     *    @option bool recursive
     *    @option bool public
     *    @option bool tree_output
     *    @option bool fullname
     */
    public static function getList($params, &$service)
    {
        global $user, $conf, $conn, $services;

        $where = ['1=1'];
        $join_type = 'INNER';
        $join_user = $user['id'];

        if (!$params['recursive']) {
            if ($params['cat_id'] > 0) {
                $where[] = '(id_uppercat = ' . (int)($params['cat_id']) . ' OR id=' . (int)($params['cat_id']) . ')';
            } else {
                $where[] = 'id_uppercat IS NULL';
            }
        } elseif ($params['cat_id'] > 0) {
            $where[] = 'uppercats ' . $conn::REGEX_OPERATOR . ' \'(^|,)' . (int)($params['cat_id']) . '(,|$)\'';
        }

        if ($params['public']) {
            $where[] = 'status = \'public\'';
            $where[] = 'visible = \'' . $conn->boolean_to_db(true) . '\'';

            $join_user = $conf['guest_id'];
        } elseif ($services['users']->isAdmin()) {
            /* in this very specific case, we don't want to hide empty
             * categories. Method calculatePermissions will only return
             * categories that are either locked or private and not permitted
             *
             * calculatePermissions does not consider empty categories as forbidden
             * @TODO : modify calculatePermissions. It must return an array to apply DBLayer::in
             */
            $forbidden_categories = $services['users']->calculatePermissions($user['id'], $user['status']);
            $where[] = 'id NOT IN (' . $forbidden_categories . ')';
            $join_type = 'LEFT';
        }

        $result = (new CategoryRepository($conn))->findWithUserAndCondtion($join_user, $where);

        // management of the album thumbnail -- starts here
        $image_ids = [];
        $categories = [];
        $user_representative_updates_for = [];
        // management of the album thumbnail -- stops here

        $cats = [];
        while ($row = $conn->db_fetch_assoc($result)) {
            $row['url'] = \Phyxo\Functions\URL::make_index_url(
                [
                    'category' => $row
                ]
            );
            foreach (['id', 'nb_images', 'total_nb_images', 'nb_categories'] as $key) {
                $row[$key] = (int)$row[$key];
            }

            if ($params['fullname']) {
                $row['name'] = strip_tags(\Phyxo\Functions\Category::get_cat_display_name_cache($row['uppercats'], null));
            } else {
                $row['name'] = strip_tags(
                    \Phyxo\Functions\Plugin::trigger_change(
                        'render_category_name',
                        $row['name'],
                        '\Phyxo\Functions\WS\Categories::getList'
                    )
                );
            }

            $row['comment'] = strip_tags(
                \Phyxo\Functions\Plugin::trigger_change(
                    'render_category_description',
                    $row['comment'],
                    '\Phyxo\Functions\Ws\Categories::getList'
                )
            );

            /* management of the album thumbnail -- starts here
             *
             * on branch 2.3, the algorithm is duplicated from
             * include/category_cats, but we should use a common code for Piwigo 2.4
             *
             * warning : if the API method is called with $params['public'], the
             * album thumbnail may be not accurate. The thumbnail can be viewed by
             * the connected user, but maybe not by the guest. Changing the
             * filtering method would be too complicated for now. We will simply
             * avoid to persist the user_representative_picture_id in the database
             * if $params['public']
             */
            if (!empty($row['user_representative_picture_id'])) {
                $image_id = $row['user_representative_picture_id'];
            } elseif (!empty($row['representative_picture_id'])) { // if a representative picture is set, it has priority
                $image_id = $row['representative_picture_id'];
            } elseif ($conf['allow_random_representative']) {
                // searching a random representant among elements in sub-categories
                $image_id = (new CategoryRepository($conn))->getRandomImageInCategory($row);
            } else { // searching a random representant among representant of sub-categories
                if ($row['count_categories'] > 0 and $row['count_images'] > 0) {
                    $result = (new CategoryRepository($conn))->findRandomRepresentantAmongSubCategories($user['id'], $row['uppercats']);
                    if ($conn->db_num_rows($result) > 0) {
                        list($image_id) = $conn->db_fetch_row($result);
                    }
                }
            }

            if (isset($image_id)) {
                if ($conf['representative_cache_on_subcats'] and $row['user_representative_picture_id'] != $image_id) {
                    $user_representative_updates_for[$row['id']] = $image_id;
                }

                $row['representative_picture_id'] = $image_id;
                $image_ids[] = $image_id;
                $categories[] = $row;
            }
            unset($image_id);
            // management of the album thumbnail -- stops here

            $cats[] = $row;
        }
        usort($cats, '\Phyxo\Functions\Utils::global_rank_compare');

        // management of the album thumbnail -- starts here
        if (count($categories) > 0) {
            $thumbnail_src_of = [];
            $new_image_ids = [];

            $result = (new ImageRepository($conn))->findByIds($image_ids);
            while ($row = $conn->db_fetch_assoc($result)) {
                if ($row['level'] <= $user['level']) {
                    $thumbnail_src_of[$row['id']] = \Phyxo\Image\DerivativeImage::thumb_url($row);
                } else {
                    /* problem: we must not display the thumbnail of a photo which has a
                     * higher privacy level than user privacy level
                     *
                     * what is the represented category?
                     * find a random photo matching user permissions
                     * register it at user_representative_picture_id
                     * set it as the representative_picture_id for the category
                     */
                    foreach ($categories as &$category) {
                        if ($row['id'] == $category['representative_picture_id']) {
                            // searching a random representant among elements in sub-categories
                            $image_id = (new CategoryRepository($conn))->getRandomImageInCategory(category);

                            if (isset($image_id) and !in_array($image_id, $image_ids)) {
                                $new_image_ids[] = $image_id;
                            }
                            if ($conf['representative_cache_on_level']) {
                                $user_representative_updates_for[$category['id']] = $image_id;
                            }

                            $category['representative_picture_id'] = $image_id;
                        }
                    }
                    unset($category);
                }
            }

            if (count($new_image_ids) > 0) {
                $result = (new ImageRepository($conn))->findByIds($new_image_ids);
                while ($row = $conn->db_fetch_assoc($result)) {
                    $thumbnail_src_of[$row['id']] = \Phyxo\Image\DerivativeImage::thumb_url($row);
                }
            }
        }

        /* compared to code in include/category_cats, we only persist the new
         * user_representative if we have used $user['id'] and not the guest id,
         * or else the real guest may see thumbnail that he should not
         */
        if (!$params['public'] and count($user_representative_updates_for)) {
            $updates = [];

            foreach ($user_representative_updates_for as $cat_id => $image_id) {
                $updates[] = [
                    'user_id' => $user['id'],
                    'cat_id' => $cat_id,
                    'user_representative_picture_id' => $image_id,
                ];
            }

            (new UserCacheCategoriesRepository($conn))->massUpdatesUserCacheCategories(
                [
                    'primary' => ['user_id', 'cat_id'],
                    'update' => ['user_representative_picture_id']
                ],
                $updates
            );
        }

        foreach ($cats as &$cat) {
            foreach ($categories as $category) {
                if ($category['id'] == $cat['id'] and isset($category['representative_picture_id'])) {
                    $cat['tn_url'] = $thumbnail_src_of[$category['representative_picture_id']];
                }
            }
            // we don't want them in the output
            unset($cat['user_representative_picture_id'], $cat['count_images'], $cat['count_categories']);
        }
        unset($cat);
        // management of the album thumbnail -- stops here

        if ($params['tree_output']) {
            return self::categoriesFlatlistToTree($cats);
        }

        return [
            'categories' => new NamedArray(
                $cats,
                'category',
                \Phyxo\Functions\Ws\Main::stdGetCategoryXmlAttributes()
            )
        ];
    }

    /**
     * API method
     * Returns the list of categories as you can see them in administration
     * @param mixed[] $params
     *
     * Only admin can run this method and permissions are not taken into
     * account.
     */
    public static function getAdminList($params, &$service)
    {
        global $conn;

        $nb_images_of = $conn->result2array((new ImageCategoryRepository($conn))->countByCategory(), 'category_id', 'counter');
        $result = (new CategoryRepository($conn))->findAll();
        $cats = [];
        while ($row = $conn->db_fetch_assoc($result)) {
            $id = $row['id'];
            $row['nb_images'] = isset($nb_images_of[$id]) ? $nb_images_of[$id] : 0;

            $row['name'] = strip_tags(
                \Phyxo\Functions\Plugin::trigger_change(
                    'render_category_name',
                    $row['name'],
                    '\Phyxo\Functions\Ws\Categories::getAdminList'
                )
            );
            $row['fullname'] = strip_tags(
                \Phyxo\Functions\Category::get_cat_display_name_cache(
                    $row['uppercats'],
                    null
                )
            );
            $row['comment'] = strip_tags(
                \Phyxo\Functions\Plugin::trigger_change(
                    'render_category_description',
                    $row['comment'],
                    '\Phyxo\Functions\Ws\Categories::getAdminList'
                )
            );

            $cats[] = $row;
        }

        usort($cats, '\Phyxo\Functions\Utils::global_rank_compare');
        return [
            'categories' => new NamedArray(
                $cats,
                'category',
                ['id', 'nb_images', 'name', 'uppercats', 'global_rank']
            )
        ];
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
    public static function add($params, &$service)
    {
        $options = [];
        if (!empty($params['status']) and in_array($params['status'], ['private', 'public'])) {
            $options['status'] = $params['status'];
        }

        if (!empty($params['comment'])) {
            $options['comment'] = $params['comment'];
        }

        $creation_output = \Phyxo\Functions\Category::create_virtual_category(
            $params['name'],
            $params['parent'],
            $options
        );

        if (isset($creation_output['error'])) {
            return new Error(500, $creation_output['error']);
        }

        \Phyxo\Functions\Utils::invalidate_user_cache();

        return $creation_output;
    }

    /**
     * API method
     * Sets details of a category
     * @param mixed[] $params
     *    @option int cat_id
     *    @option string name (optional)
     *    @option string comment (optional)
     */
    public static function setInfo($params, &$service)
    {
        global $conn;

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
            (new CategoryRepository($conn))->updateCategory($update, $update['id']);
        }
    }

    /**
     * API method
     * Sets representative image of a category
     * @param mixed[] $params
     *    @option int category_id
     *    @option int image_id
     */
    public static function setRepresentative($params, &$service)
    {
        global $conn;

        // does the category really exist?
        $result = (new CategoryRepository($conn))->findById($params['category_id']);
        list($count) = $conn->db_fetch_row($result);
        if ($count == 0) {
            return new Error(404, 'category_id not found');
        }

        // does the image really exist?
        if (!(new ImageRepository($conn))->isImageExists($params['image_id'])) {
            return new Error(404, 'image_id not found');
        }

        // apply change
        (new CategoryRepository($conn))->updateCategory(['representative_picture_id' => $params['image_id']], $params['category_id']);
        (new UserCacheCategoriesRepository($conn))->updateUserCacheCategory(['user_representative_picture_id' => null], ['cat_id' => $params['category_id']]);
    }

    /**
     * API method
     * Deletes a category
     * @param mixed[] $params
     *    @option string|int[] category_id
     *    @option string photo_deletion_mode
     *    @option string pwg_token
     */
    public static function delete($params, &$service)
    {
        global $conn;

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

        $result = (new CategoryRepository($conn))->findByIds($category_ids);
        $category_ids = $conn->result2array($result, null, 'id');

        if (count($category_ids) == 0) {
            return;
        }

        \Phyxo\Functions\Category::delete_categories($category_ids, $params['photo_deletion_mode']);
        \Phyxo\Functions\Utils::update_global_rank();
    }

    /**
     * API method
     * Moves a category
     * @param mixed[] $params
     *    @option string|int[] category_id
     *    @option int parent
     *    @option string pwg_token
     */
    public static function move($params, &$service)
    {
        global $page, $conn;

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

        $result = (new CategoryRepository($conn))->findByIds($category_ids);
        while ($row = $conn->db_fetch_assoc($result)) {
            $categories_in_db[$row['id']] = $row;

            // we break on error at first physical category detected
            if (!empty($row['dir'])) {
                $row['name'] = strip_tags(
                    \Phyxo\Functions\Plugin::trigger_change(
                        'render_category_name',
                        $row['name'],
                        '\Phyxo\Functions\Ws\Categories::move'
                    )
                );

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
         * move_categories function, not here
         * 0 as parent means "move categories at gallery root"
         */
        if (0 != $params['parent']) {
            $subcat_ids = (new CategoryRepository($conn))->getSubcatIds([$params['parent']]);
            if (count($subcat_ids) == 0) {
                return new Error(403, 'Unknown parent category id');
            }
        }

        $page['infos'] = [];
        $page['errors'] = [];

        \Phyxo\Functions\Category::move_categories($category_ids, $params['parent']);
        \Phyxo\Functions\Utils::invalidate_user_cache();

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
                    $categories[$key_of_cat[$node['id_uppercat']]]['sub_categories'] = new NamedArray([], 'category', \Phyxo\Functions\Ws\Main::stdGetCategoryXmlAttributes());
                }

                $categories[$key_of_cat[$node['id_uppercat']]]['sub_categories']->_content[] = &$node;
            }
        }

        return $tree;
    }
}
