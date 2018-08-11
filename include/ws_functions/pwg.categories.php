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
function ws_categories_getImages($params, &$service)
{
    global $user, $conf, $conn;

    $images = array();

    //------------------------------------------------- get the related categories
    $where_clauses = array();
    foreach ($params['cat_id'] as $cat_id) {
        if ($params['recursive']) {
            $where_clauses[] = 'uppercats ' . $conn::REGEX_OPERATOR . ' \'(^|,)' . $conn->db_real_escape_string($cat_id) . '(,|$)\'';
        } else {
            $where_clauses[] = 'id=' . $conn->db_real_escape_string($cat_id);
        }
    }
    if (!empty($where_clauses)) {
        $where_clauses = array('(' . implode(' OR ', $where_clauses) . ')');
    }
    $where_clauses[] = \Phyxo\Functions\SQL::get_sql_condition_FandF(
        array('forbidden_categories' => 'id'),
        null,
        true
    );

    $query = 'SELECT id, name, permalink, image_order FROM ' . CATEGORIES_TABLE;
    $query .= ' WHERE ' . implode(' AND ', $where_clauses);
    $result = $conn->db_query($query);

    $cats = array();
    while ($row = $conn->db_fetch_assoc($result)) {
        $row['id'] = (int)$row['id'];
        $cats[$row['id']] = $row;
    }

    //-------------------------------------------------------- get the images
    if (!empty($cats)) {
        $where_clauses = ws_std_image_sql_filter($params, 'i.');
        $where_clauses[] = 'category_id ' . $conn->in(array_keys($cats));
        $where_clauses[] = \Phyxo\Functions\SQL::get_sql_condition_FandF(
            array('visible_images' => 'i.id'),
            null,
            true
        );

        $order_by = ws_std_image_sql_order($params, 'i.');
        if (empty($order_by) and count($params['cat_id']) == 1 and isset($cats[$params['cat_id'][0]]['image_order'])) {
            $order_by = $cats[$params['cat_id'][0]]['image_order'];
        }
        $order_by = empty($order_by) ? $conf['order_by'] : 'ORDER BY ' . $order_by;

        $query = 'SELECT i.*, ' . $conn->db_group_concat('category_id') . ' AS cat_ids FROM ' . IMAGES_TABLE . ' i';
        $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON i.id=image_id';
        $query .= ' WHERE ' . implode(' AND ', $where_clauses);
        $query .= ' GROUP BY i.id';
        $query .= ' ' . $order_by;
        $query .= ' LIMIT ' . (int)$params['per_page'];
        $query .= ' OFFSET ' . (int)($params['per_page'] * $params['page']) . ';';
        $result = $conn->db_query($query);

        while ($row = $conn->db_fetch_assoc($result)) {
            $image = array();
            foreach (array('id', 'width', 'height', 'hit') as $k) {
                if (isset($row[$k])) {
                    $image[$k] = (int)$row[$k];
                }
            }
            foreach (array('file', 'name', 'comment', 'date_creation', 'date_available') as $k) {
                $image[$k] = $row[$k];
            }
            $image = array_merge($image, ws_std_get_urls($row));

            $image_cats = array();
            foreach (explode(',', $row['cat_ids']) as $cat_id) {
                $url = \Phyxo\Functions\URL::make_index_url(array('category' => $cats[$cat_id]));
                $page_url = \Phyxo\Functions\URL::make_picture_url(
                    array(
                        'category' => $cats[$cat_id],
                        'image_id' => $row['id'],
                        'image_file' => $row['file'],
                    )
                );
                $image_cats[] = array(
                    'id' => (int)$cat_id,
                    'url' => $url,
                    'page_url' => $page_url,
                );
            }

            $image['categories'] = new Phyxo\Ws\NamedArray(
                $image_cats,
                'category',
                array('id', 'url', 'page_url')
            );
            $images[] = $image;
        }
    }

    return array(
        'paging' => new Phyxo\Ws\NamedStruct(
            array(
                'page' => $params['page'],
                'per_page' => $params['per_page'],
                'count' => count($images)
            )
        ),
        'images' => new Phyxo\Ws\NamedArray(
            $images,
            'image',
            ws_std_get_image_xml_attributes()
        )
    );
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
function ws_categories_getList($params, &$service)
{
    global $user, $conf, $conn, $services;

    $where = array('1=1');
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
        $where[] = 'visible = \'true\'';

        $join_user = $conf['guest_id'];
    } elseif ($services['users']->isAdmin()) {
        // in this very specific case, we don't want to hide empty
        // categories. Method calculatePermissions will only return
        // categories that are either locked or private and not permitted
        //
        // calculatePermissions does not consider empty categories as forbidden
         // @TODO : modify calculatePermissions. It must return an array to apply DBLayer::in
        $forbidden_categories = $services['users']->calculatePermissions($user['id'], $user['status']);
        $where[] = 'id NOT IN (' . $forbidden_categories . ')';
        $join_type = 'LEFT';
    }

    $query = 'SELECT id, name, comment, permalink, uppercats, global_rank, id_uppercat,';
    $query .= 'nb_images, count_images AS total_nb_images, representative_picture_id,';
    $query .= 'user_representative_picture_id, count_images, count_categories,';
    $query .= 'date_last, max_date_last, count_categories AS nb_categories FROM ' . CATEGORIES_TABLE;
    $query .= ' ' . $join_type . ' JOIN ' . USER_CACHE_CATEGORIES_TABLE . ' ON id=cat_id AND user_id=' . $join_user;
    $query .= ' WHERE ' . implode(' AND ', $where);
    $result = $conn->db_query($query);

    // management of the album thumbnail -- starts here
    $image_ids = array();
    $categories = array();
    $user_representative_updates_for = array();
    // management of the album thumbnail -- stops here

    $cats = array();
    while ($row = $conn->db_fetch_assoc($result)) {
        $row['url'] = \Phyxo\Functions\URL::make_index_url(
            array(
                'category' => $row
            )
        );
        foreach (array('id', 'nb_images', 'total_nb_images', 'nb_categories') as $key) {
            $row[$key] = (int)$row[$key];
        }

        if ($params['fullname']) {
            $row['name'] = strip_tags(\Phyxo\Functions\Category::get_cat_display_name_cache($row['uppercats'], null));
        } else {
            $row['name'] = strip_tags(
                \Phyxo\Functions\Plugin::trigger_change(
                    'render_category_name',
                    $row['name'],
                    'ws_categories_getList'
                )
            );
        }

        $row['comment'] = strip_tags(
            \Phyxo\Functions\Plugin::trigger_change(
                'render_category_description',
                $row['comment'],
                'ws_categories_getList'
            )
        );

        // management of the album thumbnail -- starts here
        //
        // on branch 2.3, the algorithm is duplicated from
        // include/category_cats, but we should use a common code for Piwigo 2.4
        //
        // warning : if the API method is called with $params['public'], the
        // album thumbnail may be not accurate. The thumbnail can be viewed by
        // the connected user, but maybe not by the guest. Changing the
        // filtering method would be too complicated for now. We will simply
        // avoid to persist the user_representative_picture_id in the database
        // if $params['public']
        if (!empty($row['user_representative_picture_id'])) {
            $image_id = $row['user_representative_picture_id'];
        } elseif (!empty($row['representative_picture_id'])) { // if a representative picture is set, it has priority
            $image_id = $row['representative_picture_id'];
        } elseif ($conf['allow_random_representative']) {
            // searching a random representant among elements in sub-categories
            $image_id = \Phyxo\Functions\Category::get_random_image_in_category($row);
        } else { // searching a random representant among representant of sub-categories
            if ($row['count_categories'] > 0 and $row['count_images'] > 0) {
                $query = 'SELECT representative_picture_id FROM ' . CATEGORIES_TABLE;
                $query .= ' LEFT JOIN ' . USER_CACHE_CATEGORIES_TABLE . ' ON id=cat_id AND user_id=' . $user['id'];
                $query .= ' WHERE uppercats LIKE \'' . $row['uppercats'] . ',%\' AND representative_picture_id IS NOT NULL';
                $query .= ' ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(array('visible_categories' => 'id'), "\n  AND");
                $query .= ' ORDER BY ' . $conn::RANDOM_FUNCTION . '() LIMIT 1;';
                $subresult = $conn->db_query($query);

                if ($conn->db_num_rows($subresult) > 0) {
                    list($image_id) = $conn->db_fetch_row($subresult);
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
        $thumbnail_src_of = array();
        $new_image_ids = array();

        $query = 'SELECT id, path, representative_ext, level FROM ' . IMAGES_TABLE;
        $query .= ' WHERE id ' . $conn->in($image_ids);
        $result = $conn->db_query($query);

        while ($row = $conn->db_fetch_assoc($result)) {
            if ($row['level'] <= $user['level']) {
                $thumbnail_src_of[$row['id']] = \Phyxo\Image\DerivativeImage::thumb_url($row);
            } else {
                // problem: we must not display the thumbnail of a photo which has a
                // higher privacy level than user privacy level
                //
                // * what is the represented category?
                // * find a random photo matching user permissions
                // * register it at user_representative_picture_id
                // * set it as the representative_picture_id for the category
                foreach ($categories as &$category) {
                    if ($row['id'] == $category['representative_picture_id']) {
                        // searching a random representant among elements in sub-categories
                        $image_id = \Phyxo\Functions\Category::get_random_image_in_category($category);

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
            $query = 'SELECT id, path, representative_ext FROM ' . IMAGES_TABLE;
            $query .= ' WHERE id ' . $conn->in($new_image_ids);
            $result = $conn->db_query($query);

            while ($row = $conn->db_fetch_assoc($result)) {
                $thumbnail_src_of[$row['id']] = \Phyxo\Image\DerivativeImage::thumb_url($row);
            }
        }
    }

    // compared to code in include/category_cats, we only persist the new
    // user_representative if we have used $user['id'] and not the guest id,
    // or else the real guest may see thumbnail that he should not
    if (!$params['public'] and count($user_representative_updates_for)) {
        $updates = array();

        foreach ($user_representative_updates_for as $cat_id => $image_id) {
            $updates[] = array(
                'user_id' => $user['id'],
                'cat_id' => $cat_id,
                'user_representative_picture_id' => $image_id,
            );
        }

        $conn->mass_updates(
            USER_CACHE_CATEGORIES_TABLE,
            array(
                'primary' => array('user_id', 'cat_id'),
                'update' => array('user_representative_picture_id')
            ),
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
        return categories_flatlist_to_tree($cats);
    }

    return array(
        'categories' => new Phyxo\Ws\NamedArray(
            $cats,
            'category',
            ws_std_get_category_xml_attributes()
        )
    );
}

/**
 * API method
 * Returns the list of categories as you can see them in administration
 * @param mixed[] $params
 *
 * Only admin can run this method and permissions are not taken into
 * account.
 */
function ws_categories_getAdminList($params, &$service)
{
    global $conn;

    $query = 'SELECT category_id, COUNT(1) AS counter FROM ' . IMAGE_CATEGORY_TABLE;
    $query .= ' GROUP BY category_id;';
    $nb_images_of = $conn->query2array($query, 'category_id', 'counter');

    $query = 'SELECT id, name, comment, uppercats, global_rank, dir FROM ' . CATEGORIES_TABLE;
    $result = $conn->db_query($query);

    $cats = array();
    while ($row = $conn->db_fetch_assoc($result)) {
        $id = $row['id'];
        $row['nb_images'] = isset($nb_images_of[$id]) ? $nb_images_of[$id] : 0;

        $row['name'] = strip_tags(
            \Phyxo\Functions\Plugin::trigger_change(
                'render_category_name',
                $row['name'],
                'ws_categories_getAdminList'
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
                'ws_categories_getAdminList'
            )
        );

        $cats[] = $row;
    }

    usort($cats, '\Phyxo\Functions\Utils::global_rank_compare');
    return array(
        'categories' => new Phyxo\Ws\NamedArray(
            $cats,
            'category',
            array('id', 'nb_images', 'name', 'uppercats', 'global_rank')
        )
    );
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
function ws_categories_add($params, &$service)
{
    include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

    $options = array();
    if (!empty($params['status']) and in_array($params['status'], array('private', 'public'))) {
        $options['status'] = $params['status'];
    }

    if (!empty($params['comment'])) {
        $options['comment'] = $params['comment'];
    }

    $creation_output = create_virtual_category(
        $params['name'],
        $params['parent'],
        $options
    );

    if (isset($creation_output['error'])) {
        return new Phyxo\Ws\Error(500, $creation_output['error']);
    }

    invalidate_user_cache();

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
function ws_categories_setInfo($params, &$service)
{
    global $conn;

    $update = array(
        'id' => $params['category_id'],
    );

    $info_columns = array('name', 'comment', );

    $perform_update = false;
    foreach ($info_columns as $key) {
        if (isset($params[$key])) {
            $perform_update = true;
            $update[$key] = $params[$key];
        }
    }

    if ($perform_update) {
        $conn->single_update(
            CATEGORIES_TABLE,
            $update,
            array('id' => $update['id'])
        );
    }
}

/**
 * API method
 * Sets representative image of a category
 * @param mixed[] $params
 *    @option int category_id
 *    @option int image_id
 */
function ws_categories_setRepresentative($params, &$service)
{
    global $conn;

    // does the category really exist?
    $query = 'SELECT COUNT(1) FROM ' . CATEGORIES_TABLE;
    $query .= ' WHERE id = ' . $conn->db_real_escape_string($params['category_id']);
    list($count) = $conn->db_fetch_row($conn->db_query($query));
    if ($count == 0) {
        return new Phyxo\Ws\Error(404, 'category_id not found');
    }

    // does the image really exist?
    $query = 'SELECT COUNT(1) FROM ' . IMAGES_TABLE;
    $query .= ' WHERE id = ' . $conn->db_real_escape_string($params['image_id']);
    list($count) = $conn->db_fetch_row($conn->db_query($query));
    if ($count == 0) {
        return new Phyxo\Ws\Error(404, 'image_id not found');
    }

    // apply change
    $query = 'UPDATE ' . CATEGORIES_TABLE;
    $query .= ' SET representative_picture_id = ' . $conn->db_real_escape_string($params['image_id']);
    $query .= ' WHERE id = ' . $conn->db_real_escape_string($params['category_id']);
    $conn->db_query($query);

    $query = 'UPDATE ' . USER_CACHE_CATEGORIES_TABLE . ' SET user_representative_picture_id = NULL';
    $query .= ' WHERE cat_id = ' . $conn->db_real_escape_string($params['category_id']);
    $conn->db_query($query);
}

/**
 * API method
 * Deletes a category
 * @param mixed[] $params
 *    @option string|int[] category_id
 *    @option string photo_deletion_mode
 *    @option string pwg_token
 */
function ws_categories_delete($params, &$service)
{
    global $conn;

    if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
        return new Phyxo\Ws\Error(403, 'Invalid security token');
    }

    $modes = array('no_delete', 'delete_orphans', 'force_delete');
    if (!in_array($params['photo_deletion_mode'], $modes)) {
        return new Phyxo\Ws\Error(
            500,
            '[ws_categories_delete]'
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

    $category_ids = array();
    foreach ($params['category_id'] as $category_id) {
        if ($category_id > 0) {
            $category_ids[] = $category_id;
        }
    }

    if (count($category_ids) == 0) {
        return;
    }

    $query = 'SELECT id FROM ' . CATEGORIES_TABLE;
    $query .= ' WHERE id ' . $conn->in($category_ids);
    $category_ids = $conn->query2array($query, null, 'id');

    if (count($category_ids) == 0) {
        return;
    }

    include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
    \Phyxo\Functions\Category::delete_categories($category_ids, $params['photo_deletion_mode']);
    update_global_rank();
}

/**
 * API method
 * Moves a category
 * @param mixed[] $params
 *    @option string|int[] category_id
 *    @option int parent
 *    @option string pwg_token
 */
function ws_categories_move($params, &$service)
{
    global $page, $conn;

    if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
        return new Phyxo\Ws\Error(403, 'Invalid security token');
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

    $category_ids = array();
    foreach ($params['category_id'] as $category_id) {
        if ($category_id > 0) {
            $category_ids[] = $category_id;
        }
    }

    if (count($category_ids) == 0) {
        return new Phyxo\Ws\Error(403, 'Invalid category_id input parameter, no category to move');
    }

    // we can't move physical categories
    $categories_in_db = array();

    $query = 'SELECT id, name, dir FROM ' . CATEGORIES_TABLE;
    $query .= ' WHERE id ' . $conn->in($category_ids);
    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        $categories_in_db[$row['id']] = $row;

        // we break on error at first physical category detected
        if (!empty($row['dir'])) {
            $row['name'] = strip_tags(
                \Phyxo\Functions\Plugin::trigger_change(
                    'render_category_name',
                    $row['name'],
                    'ws_categories_move'
                )
            );

            return new Phyxo\Ws\Error(
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

        return new Phyxo\Ws\Error(403, sprintf('Category %u does not exist', $unknown_category_ids[0]));
    }

    // does this parent exists? This check should be made in the
    // move_categories function, not here
    // 0 as parent means "move categories at gallery root"
    if (0 != $params['parent']) {
        $subcat_ids = \Phyxo\Functions\Category::get_subcat_ids(array($params['parent']));
        if (count($subcat_ids) == 0) {
            return new Phyxo\Ws\Error(403, 'Unknown parent category id');
        }
    }

    $page['infos'] = array();
    $page['errors'] = array();

    include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
    move_categories($category_ids, $params['parent']);
    invalidate_user_cache();

    if (count($page['errors']) != 0) {
        return new Phyxo\Ws\Error(403, implode('; ', $page['errors']));
    }
}
