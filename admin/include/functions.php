<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2015 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

/**
 * @package functions\admin\
 */

include_once(PHPWG_ROOT_PATH.'admin/include/functions_metadata.php');
include_once(PHPWG_ROOT_PATH .'include/derivative.inc.php');

/**
 * Deletes a site and call delete_categories for each primary category of the site
 *
 * @param int $id
 */
function delete_site($id) {
    global $conn;

    // destruction of the categories of the site
    $query = 'SELECT id FROM '.CATEGORIES_TABLE.' WHERE site_id = '.$conn->db_real_escape_string($id);
    $category_ids = $conn->query2array($query, null, 'id');
    delete_categories($category_ids);

    // destruction of the site
    $query = 'DELETE FROM '.SITES_TABLE.' WHERE id = '.$conn->db_real_escape_string($id);
    $conn->db_query($query);
}

/**
 * Recursively deletes one or more categories.
 * It also deletes :
 *    - all the elements physically linked to the category (with delete_elements)
 *    - all the links between elements and this category
 *    - all the restrictions linked to the category
 *
 * @param int[] $ids
 * @param string $photo_deletion_mode
 *    - no_delete : delete no photo, may create orphans
 *    - delete_orphans : delete photos that are no longer linked to any category
 *    - force_delete : delete photos even if they are linked to another category
 */
function delete_categories($ids, $photo_deletion_mode='no_delete') {
    global $conn;

    if (count($ids) == 0) {
        return;
    }

    // add sub-category ids to the given ids : if a category is deleted, all
    // sub-categories must be so
    $ids = get_subcat_ids($ids);

    // destruction of all photos physically linked to the category
    $query = 'SELECT id FROM '.IMAGES_TABLE;
    $query .= ' WHERE storage_category_id '.$conn->in($ids);
    $element_ids = $conn->query2array($query, null, 'id');
    delete_elements($element_ids);

    // now, should we delete photos that are virtually linked to the category?
    if ('delete_orphans' == $photo_deletion_mode or 'force_delete' == $photo_deletion_mode) {
        $query = 'SELECT DISTINCT(image_id) FROM '.IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE category_id '.$conn->in($ids);
        $image_ids_linked = $conn->query2array($query, null, 'image_id');

        if (count($image_ids_linked) > 0) {
            if ('delete_orphans' == $photo_deletion_mode) {
                $query = 'SELECT DISTINCT(image_id) FROM '.IMAGE_CATEGORY_TABLE;
                $query .= ' WHERE image_id '.$conn->in($image_ids_linked);
                $query .= ' AND category_id NOT '.$conn->in($ids);
                $image_ids_not_orphans = $conn->query2array($query, null, 'image_id');
                $image_ids_to_delete = array_diff($image_ids_linked, $image_ids_not_orphans);
            }

            if ('force_delete' == $photo_deletion_mode) {
                $image_ids_to_delete = $image_ids_linked;
            }

            delete_elements($image_ids_to_delete, true);
        }
    }

    // destruction of the links between images and this category
    $query = 'DELETE FROM '.IMAGE_CATEGORY_TABLE.' WHERE category_id '.$conn->in($ids);
    $conn->db_query($query);

    // destruction of the access linked to the category
    $query = 'DELETE FROM '.USER_ACCESS_TABLE.' WHERE cat_id '.$conn->in($ids);
    $conn->db_query($query);

    $query = 'DELETE FROM '.GROUP_ACCESS_TABLE.' WHERE cat_id '.$conn->in($ids);
    $conn->db_query($query);

    // destruction of the category
    $query = 'DELETE FROM '.CATEGORIES_TABLE.' WHERE id '.$conn->in($ids);
    $conn->db_query($query);

    $query = 'DELETE FROM '.OLD_PERMALINKS_TABLE.' WHERE cat_id '.$conn->in($ids);
    $conn->db_query($query);

    $query = 'DELETE FROM '.USER_CACHE_CATEGORIES_TABLE.' WHERE cat_id '.$conn->in($ids);
    $conn->db_query($query);

    trigger_notify('delete_categories', $ids);
}

/**
 * Deletes all files (on disk) related to given image ids.
 *
 * @param int[] $ids
 * @return 0|int[] image ids where files were successfully deleted
 */
function delete_element_files($ids) {
    global $conf, $conn;

    if (count($ids) == 0) {
        return 0;
    }

    $new_ids = array();

    $query = 'SELECT id,path,representative_ext FROM '.IMAGES_TABLE;
    $query .= ' WHERE id '.$conn->in($ids);
    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        if (url_is_remote($row['path'])) {
            continue;
        }

        $files = array();
        $files[] = get_element_path($row);

        if (!empty($row['representative_ext'])) {
            $files[] = original_to_representative( $files[0], $row['representative_ext']);
        }

        $ok = true;
        if (!isset($conf['never_delete_originals'])) {
            foreach ($files as $path) {
                if (is_file($path) and !unlink($path)) {
                    $ok = false;
                    trigger_error('"'.$path.'" cannot be removed', E_USER_WARNING);
                    break;
                }
            }
        }

        if ($ok) {
            delete_element_derivatives($row);
            $new_ids[] = $row['id'];
        } else {
            break;
        }
    }

    return $new_ids;
}

/**
 * Deletes elements from database.
 * It also deletes :
 *    - all the comments related to elements
 *    - all the links between categories/tags and elements
 *    - all the favorites/rates associated to elements
 *    - removes elements from caddie
 *
 * @param int[] $ids
 * @param bool $physical_deletion
 * @return int number of deleted elements
 */
function delete_elements($ids, $physical_deletion=false) {
    global $conn;

    if (count($ids) == 0) {
        return 0;
    }
    trigger_notify('begin_delete_elements', $ids);

    if ($physical_deletion) {
        $ids = delete_element_files($ids);
        if (count($ids)==0) {
            return 0;
        }
    }

    // destruction of the comments on the image
    $query = 'DELETE FROM '.COMMENTS_TABLE.' WHERE image_id '.$conn->in($ids);
    $conn->db_query($query);

    // destruction of the links between images and categories
    $query = 'DELETE FROM '.IMAGE_CATEGORY_TABLE.' WHERE image_id '.$conn->in($ids);
    $conn->db_query($query);

    // destruction of the links between images and tags
    $query = 'DELETE FROM '.IMAGE_TAG_TABLE.' WHERE image_id '.$conn->in($ids);
    $conn->db_query($query);

    // destruction of the favorites associated with the picture
    $query = 'DELETE FROM '.FAVORITES_TABLE.' WHERE image_id '.$conn->in($ids);
    $conn->db_query($query);

    // destruction of the rates associated to this element
    $query = 'DELETE FROM '.RATE_TABLE.' WHERE element_id '.$conn->in($ids);
    $conn->db_query($query);

    // destruction of the caddie associated to this element
    $query = 'DELETE FROM '.CADDIE_TABLE.' WHERE element_id '.$conn->in($ids);
    $conn->db_query($query);

    // destruction of the image
    $query = 'DELETE FROM '.IMAGES_TABLE.' WHERE id '.$conn->in($ids);
    $conn->db_query($query);

    // are the photo used as category representant?
    $query = 'SELECT id FROM '.CATEGORIES_TABLE.' WHERE representative_picture_id '.$conn->in($ids);
    $category_ids = $conn->query2array($query, null, 'id');
    if (count($category_ids) > 0) {
        update_category($category_ids);
    }

    trigger_notify('delete_elements', $ids);

    return count($ids);
}

/**
 * Deletes an user.
 * It also deletes all related data (accesses, favorites, permissions, etc.)
 * @todo : accept array input
 *
 * @param int $user_id
 */
function delete_user($user_id) {
    global $conf, $conn;

    $tables = array(
        // destruction of the access linked to the user
        USER_ACCESS_TABLE,
        // destruction of data notification by mail for this user
        USER_MAIL_NOTIFICATION_TABLE,
        // destruction of data RSS notification for this user
        USER_FEED_TABLE,
        // deletion of calculated permissions linked to the user
        USER_CACHE_TABLE,
        // deletion of computed cache data linked to the user
        USER_CACHE_CATEGORIES_TABLE,
        // destruction of the group links for this user
        USER_GROUP_TABLE,
        // destruction of the favorites associated with the user
        FAVORITES_TABLE,
        // destruction of the caddie associated with the user
        CADDIE_TABLE,
        // deletion of piwigo specific informations
        USER_INFOS_TABLE,
    );

    foreach ($tables as $table) {
        $query = 'DELETE FROM '.$table.' WHERE user_id = '.$user_id.';';
        $conn->db_query($query);
    }

    // purge of sessions
    $query = 'DELETE FROM '.SESSIONS_TABLE.' WHERE data LIKE \'pwg_uid|i:'.(int) $user_id.';%\';';
    $conn->db_query($query);

    // destruction of the user
    $query = 'DELETE FROM '.USERS_TABLE.' WHERE '.$conf['user_fields']['id'].' = '.(int) $user_id.';';
    $conn->db_query($query);

    trigger_notify('delete_user', $user_id);
}

/**
 * Verifies that the representative picture really exists in the db and
 * picks up a random representative if possible and based on config.
 *
 * @param 'all'|int|int[] $ids
 */
function update_category($ids='all') {
    global $conf, $conn;

    if ($ids=='all') {
        $where_cats = '1=1';
    } elseif (!is_array($ids)) {
        $where_cats = '%s='.$ids;
    } else {
        if (count($ids) == 0) {
            return false;
        }
        $where_cats = '%s '.$conn->in($ids);
    }

    // find all categories where the setted representative is not possible :
    // the picture does not exist
    $query = 'SELECT DISTINCT c.id FROM '.CATEGORIES_TABLE.' AS c';
    $query .= ' LEFT JOIN '.IMAGES_TABLE.' AS i ON c.representative_picture_id = i.id';
    $query .= ' WHERE representative_picture_id IS NOT NULL';
    $query .= ' AND '.sprintf($where_cats, 'c.id').' AND i.id IS NULL;';
    $wrong_representant = $conn->query2array($query, null, 'id');

    if (count($wrong_representant) > 0) {
        $query = 'UPDATE '.CATEGORIES_TABLE;
        $query .= ' SET representative_picture_id = NULL';
        $query .= ' WHERE id '.$conn->in($wrong_representant);
        $conn->db_query($query);
    }

    if (!$conf['allow_random_representative']) {
        // If the random representant is not allowed, we need to find
        // categories with elements and with no representant. Those categories
        // must be added to the list of categories to set to a random
        // representant.
        $query = 'SELECT DISTINCT id FROM '.CATEGORIES_TABLE;
        $query .= ' LEFT JOIN '.IMAGE_CATEGORY_TABLE.' ON id = category_id';
        $query .= ' WHERE representative_picture_id IS NULL';
        $query .= ' AND '.sprintf($where_cats, 'category_id');
        $to_rand = $conn->query2array($query, null, 'id');
        if (count($to_rand) > 0) {
            set_random_representant($to_rand);
        }
    }
}

/**
 * Checks and repairs IMAGE_CATEGORY_TABLE integrity.
 * Removes all entries from the table which correspond to a deleted image.
 */
function images_integrity() {
    global $conn;

    $query = 'SELECT image_id FROM '.IMAGES_TABLE;
    $query .= ' LEFT JOIN '.IMAGE_CATEGORY_TABLE.' ON id = image_id WHERE id IS NULL;';
    $result = $conn->db_query($query);
    $orphan_image_ids = $conn->query2array($query, null, 'image_id');

    if (count($orphan_image_ids) > 0) {
        $query = 'DELETE FROM '.IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE image_id '.$conn->in($orphan_image_ids);
        $conn->db_query($query);
    }
}

/**
 * Returns an array containing sub-directories which are potentially
 * a category.
 * Directories named ".svn", "thumbnail", "pwg_high" or "pwg_representative"
 * are omitted.
 *
 * @param string $basedir (eg: ./galleries)
 * @return string[]
 */
function get_fs_directories($path, $recursive=true) {
    global $conf;

    $dirs = array();
    $path = rtrim($path, '/');

    $exclude_folders = array_merge(
        $conf['sync_exclude_folders'],
        array(
            '.', '..', '.svn',
            'thumbnail', 'pwg_high',
            'pwg_representative',
        )
    );
    $exclude_folders = array_flip($exclude_folders);


    // @TODO: use glob !!!
    if (is_dir($path)) {
        if ($contents = opendir($path)) {
            while (($node = readdir($contents)) !== false) {
                if (is_dir($path.'/'.$node) and !isset($exclude_folders[$node])) {
                    $dirs[] = $path.'/'.$node;
                    if ($recursive) {
                        $dirs = array_merge($dirs, get_fs_directories($path.'/'.$node));
                    }
                }
            }
            closedir($contents);
        }
    }

    return $dirs;
}

/**
 * Orders categories (update categories.rank and global_rank database fields)
 * so that rank field are consecutive integers starting at 1 for each child.
 */
function update_global_rank() {
    global $cat_map, $conn;

    $query = 'SELECT id, id_uppercat, uppercats, rank, global_rank FROM '.CATEGORIES_TABLE;
    $query .= ' ORDER BY id_uppercat,rank,name';

    $cat_map = array();

    $current_rank = 0;
    $current_uppercat = '';

    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        if ($row['id_uppercat'] != $current_uppercat) {
            $current_rank = 0;
            $current_uppercat = $row['id_uppercat'];
        }
        ++$current_rank;
        $cat = array(
            'rank' => $current_rank,
            'rank_changed' => $current_rank!=$row['rank'],
            'global_rank' => $row['global_rank'],
            'uppercats' => $row['uppercats'],
        );
        $cat_map[$row['id']] = $cat;
    }

    $datas = array();

    // use function()
    $cat_map_callback = function($m) use ($cat_map) {
        return $cat_map[$m[1]]['rank'];
    };

    foreach ($cat_map as $id => $cat) {
        $new_global_rank = preg_replace_callback(
            '/(\d+)/',
            $cat_map_callback,
            str_replace(',', '.', $cat['uppercats'] )
        );

        if ($cat['rank_changed'] || $new_global_rank!=$cat['global_rank']) {
            $datas[] = array(
                'id' => $id,
                'rank' => $cat['rank'],
                'global_rank' => $new_global_rank,
            );
        }
    }

    unset($cat_map);

    $conn->mass_updates(
        CATEGORIES_TABLE,
        array(
            'primary' => array('id'),
            'update'  => array('rank', 'global_rank')
        ),
        $datas
    );

    return count($datas);
}

/**
 * Change the **visible** property on a set of categories.
 *
 * @param int[] $categories
 * @param boolean|string $value
 * @param boolean $unlock_child optional   default false
 */
function set_cat_visible($categories, $value, $unlock_child = false) {
    global $conn;

    if (($value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) === null) {
        trigger_error("set_cat_visible invalid param $value", E_USER_WARNING);
        return false;
    }

    // unlocking a category => all its parent categories become unlocked
    if ($value) {
        $cats = get_uppercat_ids($categories);
        if ($unlock_child) {
            $cats = array_merge($cats, get_subcat_ids($categories));
        }

        $query = 'UPDATE '.CATEGORIES_TABLE;
        $query .= ' SET visible = \''.$conn->boolean_to_db(true).'\'';
        $query .= ' WHERE id '.$conn->in($cats);
        $conn->db_query($query);
    } else { // locking a category   => all its child categories become locked
        $subcats = get_subcat_ids($categories);
        $query = 'UPDATE '.CATEGORIES_TABLE;
        $query .= ' SET visible = \''.$conn->boolean_to_db(false).'\'';
        $query .= ' WHERE id '.$conn->in($subcats);
        $conn->db_query($query);
    }
}

/**
 * Change the **status** property on a set of categories : private or public.
 *
 * @param int[] $categories
 * @param string $value
 */
function set_cat_status($categories, $value) {
    global $conn;

    if (!in_array($value, array('public', 'private'))) {
        trigger_error("set_cat_status invalid param $value", E_USER_WARNING);
        return false;
  }

    // make public a category => all its parent categories become public
    if ($value == 'public') {
        $uppercats = get_uppercat_ids($categories);
        $query = 'UPDATE '.CATEGORIES_TABLE.' SET status = \'public\'';
        $query .= ' WHERE id '.$conn->in($uppercats);
        $conn->db_query($query);
    }

    // make a category private => all its child categories become private
    if ($value == 'private') {
        $subcats = get_subcat_ids($categories);

        $query = 'UPDATE '.CATEGORIES_TABLE;
        $query .= ' SET status = \'private\'';
        $query .= ' WHERE id '.$conn->in($subcats);
        $conn->db_query($query);

        // @TODO: add unit tests for that
        // We have to keep permissions consistant: a sub-album can't be
        // permitted to a user or group if its parent album is not permitted to
        // the same user or group. Let's remove all permissions on sub-albums if
        // it is not consistant. Let's take the following example:
        //
        // A1        permitted to U1,G1
        // A1/A2     permitted to U1,U2,G1,G2
        // A1/A2/A3  permitted to U3,G1
        // A1/A2/A4  permitted to U2
        // A1/A5     permitted to U4
        // A6        permitted to U4
        // A6/A7     permitted to G1
        //
        // (we consider that it can be possible to start with inconsistant
        // permission, given that public albums can have hidden permissions,
        // revealed once the album returns to private status)
        //
        // The admin selects A2,A3,A4,A5,A6,A7 to become private (all but A1,
        // which is private, which can be true if we're moving A2 into A1). The
        // result must be:
        //
        // A2 permission removed to U2,G2
        // A3 permission removed to U3
        // A4 permission removed to U2
        // A5 permission removed to U2
        // A6 permission removed to U4
        // A7 no permission removed
        //
        // 1) we must extract "top albums": A2, A5 and A6
        // 2) for each top album, decide which album is the reference for permissions
        // 3) remove all inconsistant permissions from sub-albums of each top-album

        // step 1, search top albums
        $top_categories = array();
        $parent_ids = array();

        $query = 'SELECT id,name,id_uppercat,uppercats,global_rank FROM '.CATEGORIES_TABLE;
        $query .= ' WHERE id '.$conn->in($categories);
        $all_categories = $conn->query2array($query);
        usort($all_categories, 'global_rank_compare');

        foreach ($all_categories as $cat) {
            $is_top = true;

            if (!empty($cat['id_uppercat'])) {
                foreach (explode(',', $cat['uppercats']) as $id_uppercat) {
                    if (isset($top_categories[$id_uppercat])) {
                        $is_top = false;
                        break;
                    }
                }
            }

            if ($is_top) {
                $top_categories[$cat['id']] = $cat;

                if (!empty($cat['id_uppercat'])) {
                    $parent_ids[] = $cat['id_uppercat'];
                }
            }
        }

        // step 2, search the reference album for permissions
        //
        // to find the reference of each top album, we will need the parent albums
        $parent_cats = array();

        if (count($parent_ids) > 0) {
            $query = 'SELECT id,status FROM '.CATEGORIES_TABLE;
            $query .= ' WHERE id '.$conn->in($parent_ids);
            $parent_cats= $conn->query2array($query, 'id');
        }

        $tables = array(
            USER_ACCESS_TABLE => 'user_id',
            GROUP_ACCESS_TABLE => 'group_id'
        );

        foreach ($top_categories as $top_category) {
            // what is the "reference" for list of permissions? The parent album
            // if it is private, else the album itself
            $ref_cat_id = $top_category['id'];

            if (!empty($top_category['id_uppercat'])
                and isset($parent_cats[ $top_category['id_uppercat'] ])
                and 'private' == $parent_cats[$top_category['id_uppercat']]['status']) {
                $ref_cat_id = $top_category['id_uppercat'];
            }

            $subcats = get_subcat_ids(array($top_category['id']));

            foreach ($tables as $table => $field) {
                // what are the permissions user/group of the reference album
                $query = 'SELECT '.$field.' FROM '.$table;
                $query .= ' WHERE cat_id = '.$conn->db_real_escape_string($ref_cat_id);
                $ref_access = $conn->query2array($query, null, $field);

                if (count($ref_access) == 0) {
                    $ref_access[] = -1;
                }

                // step 3, remove the inconsistant permissions from sub-albums
                $query = 'DELETE FROM '.$table;
                $query .= ' WHERE '.$field.' NOT '.$conn->in($ref_access);
                $query .= ' AND cat_id '.$conn->in($subcats);
                $conn->db_query($query);
            }
        }
    }
}

/**
 * Returns all uppercats category ids of the given category ids.
 *
 * @param int[] $cat_ids
 * @return int[]
 */
function get_uppercat_ids($cat_ids) {
    global $conn;

    if (!is_array($cat_ids) or count($cat_ids) < 1) {
        return array();
    }

    $uppercats = array();

    $query = 'SELECT uppercats FROM '.CATEGORIES_TABLE;
    $query .= ' WHERE id '.$conn->in($cat_ids);
    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        $uppercats = array_merge($uppercats, explode(',', $row['uppercats']));
    }
    $uppercats = array_unique($uppercats);

    return $uppercats;
}

/**
 * Set a new random representant to the categories.
 *
 * @param int[] $categories
 */
function set_random_representant($categories) {
    global $conn;

    $datas = array();
    foreach ($categories as $category_id) {
        $query = 'SELECT image_id FROM '.IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE category_id = '.$category_id;
        $query .= ' ORDER BY '.$conn::RANDOM_FUNCTION.'()  LIMIT 1;';
        list($representative) = $conn->db_fetch_row($conn->db_query($query));

        $datas[] = array(
            'id' => $category_id,
            'representative_picture_id' => $representative,
        );
    }

    $conn->mass_updates(
        CATEGORIES_TABLE,
        array(
            'primary' => array('id'),
            'update' => array('representative_picture_id')
        ),
        $datas
    );
}

/**
 * Returns the fulldir for each given category id.
 *
 * @param int[] intcat_ids
 * @return string[]
 */
function get_fulldirs($cat_ids) {
    global $cat_dirs, $conn;

    if (count($cat_ids) == 0) {
        return array();
    }

    // caching directories of existing categories
    $query = 'SELECT id, dir  FROM '.CATEGORIES_TABLE.' WHERE dir IS NOT NULL;';
    $cat_dirs = $conn->query2array($query, 'id', 'dir');

    // caching galleries_url
    $query = 'SELECT id, galleries_url FROM '.SITES_TABLE;
    $galleries_url = $conn->query2array($query, 'id', 'galleries_url');

    // categories : id, site_id, uppercats
    $query = 'SELECT id, uppercats, site_id FROM '.CATEGORIES_TABLE;
    $query .= ' WHERE dir IS NOT NULL';
    $query .= ' AND id '.$conn->in($cat_ids);
    $categories = $conn->query2array($query);

    // filling $cat_fulldirs
    $cat_dirs_callback = function($m) use ($cat_dirs) {
        return $cat_dirs[$m[1]];
    };

    $cat_fulldirs = array();
    foreach ($categories as $category) {
        $uppercats = str_replace(',', '/', $category['uppercats']);
        $cat_fulldirs[$category['id']] = $galleries_url[$category['site_id']];
        $cat_fulldirs[$category['id']].= preg_replace_callback(
            '/(\d+)/',
            $cat_dirs_callback,
            $uppercats
        );
    }

    unset($cat_dirs);

    return $cat_fulldirs;
}

/**
 * Returns an array with all file system files according to $conf['file_ext']
 *
 * @param string $path
 * @param bool $recursive
 * @return array
 */
function get_fs($path, $recursive = true) {
    global $conf;

    // because isset is faster than in_array...
    if (!isset($conf['flip_picture_ext'])) {
        $conf['flip_picture_ext'] = array_flip($conf['picture_ext']);
    }
    if (!isset($conf['flip_file_ext'])) {
        $conf['flip_file_ext'] = array_flip($conf['file_ext']);
    }

    $fs['elements'] = array();
    $fs['thumbnails'] = array();
    $fs['representatives'] = array();
    $subdirs = array();

    // @TODO: use glob
    if (is_dir($path)) {
        if ($contents = opendir($path)) {
            while (($node = readdir($contents)) !== false) {
                if ($node == '.' or $node == '..') {continue;}

                if (is_file($path.'/'.$node)) {
                    $extension = get_extension($node);

                    if (isset($conf['flip_picture_ext'][$extension])) {
                        if (basename($path) == 'thumbnail') {
                            $fs['thumbnails'][] = $path.'/'.$node;
                        } elseif (basename($path) == 'pwg_representative') {
                            $fs['representatives'][] = $path.'/'.$node;
                        } else {
                            $fs['elements'][] = $path.'/'.$node;
                        }
                    } elseif (isset($conf['flip_file_ext'][$extension])) {
                        $fs['elements'][] = $path.'/'.$node;
                    }
                } elseif (is_dir($path.'/'.$node) and $node != 'pwg_high' and $recursive) {
                    $subdirs[] = $node;
                }
            }
        }
        closedir($contents);

        foreach ($subdirs as $subdir) {
            $tmp_fs = get_fs($path.'/'.$subdir);
            $fs['elements'] = array_merge($fs['elements'], $tmp_fs['elements']);
            $fs['thumbnails'] = array_merge($fs['thumbnails'], $tmp_fs['thumbnails']);
            $fs['representatives'] = array_merge($fs['representatives'], $tmp_fs['representatives']);
        }
    }

    return $fs;
}

/**
 * Synchronize base users list and related users list.
 *
 * Compares and synchronizes base users table (USERS_TABLE) with its child
 * tables (USER_INFOS_TABLE, USER_ACCESS, USER_CACHE, USER_GROUP) : each
 * base user must be present in child tables, users in child tables not
 * present in base table must be deleted.
 */
function sync_users() {
    global $conf, $conn, $services;

    $query = 'SELECT '.$conf['user_fields']['id'].' AS id FROM '.USERS_TABLE;
    $base_users = $conn->query2array($query, null, 'id');

    $query = 'SELECT user_id FROM '.USER_INFOS_TABLE;
    $infos_users = $conn->query2array($query, null, 'user_id');

    // users present in $base_users and not in $infos_users must be added
    $to_create = array_diff($base_users, $infos_users);

    if (count($to_create) > 0) {
        $services['users']->createUserInfos($to_create);
    }

    // users present in user related tables must be present in the base user
    // table
    $tables = array(
        USER_MAIL_NOTIFICATION_TABLE,
        USER_FEED_TABLE,
        USER_INFOS_TABLE,
        USER_ACCESS_TABLE,
        USER_CACHE_TABLE,
        USER_CACHE_CATEGORIES_TABLE,
        USER_GROUP_TABLE
    );

    foreach ($tables as $table) {
        $query = 'SELECT DISTINCT user_id FROM '.$table;
        $to_delete = array_diff(
            $conn->query2array($query, null, 'user_id'),
            $base_users
        );

        if (count($to_delete) > 0) {
            $query = 'DELETE FROM '.$table;
            $query .= ' WHERE user_id '.$conn->in($to_delete);
            $conn->db_query($query);
        }
    }
}

/**
 * Updates categories.uppercats field based on categories.id + categories.id_uppercat
 */
function update_uppercats() {
    global $conn;

    $query = 'SELECT id, id_uppercat, uppercats FROM '.CATEGORIES_TABLE;
    $cat_map = $conn->query2array($query, 'id');

    $datas = array();
    foreach ($cat_map as $id => $cat) {
        $upper_list = array();

        $uppercat = $id;
        while ($uppercat) {
            $upper_list[] = $uppercat;
            $uppercat = $cat_map[$uppercat]['id_uppercat'];
        }

        $new_uppercats = implode(',', array_reverse($upper_list));
        if ($new_uppercats != $cat['uppercats']) {
            $datas[] = array(
                'id' => $id,
                'uppercats' => $new_uppercats
            );
        }
    }
    $fields = array('primary' => array('id'), 'update' => array('uppercats'));
    $conn->mass_updates(CATEGORIES_TABLE, $fields, $datas);
}

/**
 * Update images.path field base on images.file and storage categories fulldirs.
 */
function update_path() {
    global $conn;

    $query = 'SELECT DISTINCT(storage_category_id) FROM '.IMAGES_TABLE;
    $query .= ' WHERE storage_category_id IS NOT NULL';
    $cat_ids = $conn->query2array($query, null, 'storage_category_id');
    $fulldirs = get_fulldirs($cat_ids);

    foreach ($cat_ids as $cat_id) { // @TODO : use mass_updates ?
        $query = 'UPDATE '.IMAGES_TABLE;
        $query .= ' SET path = '.$conn->db_concat(array("'".$fulldirs[$cat_id]."/'", 'file'));
        $query .= ' WHERE storage_category_id = '.$conn->db_real_escape_string($cat_id);
        $conn->db_query($query);
    }
}

/**
 * Change the parent category of the given categories. The categories are
 * supposed virtual.
 *
 * @param int[] $category_ids
 * @param int $new_parent (-1 for root)
 */
function move_categories($category_ids, $new_parent = -1) {
    global $page, $conn;

    if (count($category_ids) == 0) {
        return;
    }

    $new_parent = $new_parent < 1 ? 'NULL' : $new_parent;
    $categories = array();

    $query = 'SELECT id, id_uppercat, status, uppercats FROM '.CATEGORIES_TABLE;
    $query .= ' WHERE id '.$conn->in($category_ids);
    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        $categories[$row['id']] = array(
            'parent' => empty($row['id_uppercat']) ? 'NULL' : $row['id_uppercat'],
            'status' => $row['status'],
            'uppercats' => $row['uppercats']
        );
    }

    // is the movement possible? The movement is impossible if you try to move
    // a category in a sub-category or itself
    if ('NULL' != $new_parent) {
        $query = 'SELECT uppercats FROM '.CATEGORIES_TABLE.' WHERE id = '.$new_parent.';';
        list($new_parent_uppercats) = $conn->db_fetch_row($conn->db_query($query));

        foreach ($categories as $category) {
            // technically, you can't move a category with uppercats 12,125,13,14
            // into a new parent category with uppercats 12,125,13,14,24
            if (preg_match('/^'.$category['uppercats'].'(,|$)/', $new_parent_uppercats)) {
                $page['errors'][] = l10n('You cannot move an album in its own sub album');
                return;
            }
        }
    }

    $tables = array(
        USER_ACCESS_TABLE => 'user_id',
        GROUP_ACCESS_TABLE => 'group_id'
    );

    $query = 'UPDATE '.CATEGORIES_TABLE;
    $query .= ' SET id_uppercat = '.$new_parent;
    $query .= ' WHERE id '.$conn->in($category_ids);
    $conn->db_query($query);

    update_uppercats();
    update_global_rank();

    // status and related permissions management
    if ('NULL' == $new_parent) {
        $parent_status = 'public';
    } else {
        $query = 'SELECT status FROM '.CATEGORIES_TABLE.' WHERE id = '.$new_parent.';';
        list($parent_status) = $conn->db_fetch_row($conn->db_query($query));
    }

    if ('private' == $parent_status) {
        set_cat_status(array_keys($categories), 'private');
    }

    $page['infos'][] = l10n_dec(
        '%d album moved', '%d albums moved',
        count($categories)
    );
}

/**
 * Create a virtual category.
 *
 * @param string $category_name
 * @param int $parent_id
 * @param array $options
 *    - boolean commentable
 *    - boolean visible
 *    - string status
 *    - string comment
 *    - boolean inherit
 * @return array ('info', 'id') or ('error')
 */
function create_virtual_category($category_name, $parent_id=null, $options=array()) {
    global $conf, $user, $conn;

    // is the given category name only containing blank spaces ?
    if (preg_match('/^\s*$/', $category_name)) {
        return array('error' => l10n('The name of an album must not be empty'));
    }

    $insert = array(
        'name' => $category_name,
        'rank' => 0,
        'global_rank' => 0,
    );

    // is the album commentable?
    if (isset($options['commentable']) and is_bool($options['commentable'])) {
        $insert['commentable'] = $options['commentable'];
    } else {
        $insert['commentable'] = $conf['newcat_default_commentable'];
    }
    $insert['commentable'] = $conn->boolean_to_string($insert['commentable']);

    // is the album temporarily locked? (only visible by administrators,
    // whatever permissions) (may be overwritten if parent album is not
    // visible)
    if (isset($options['visible']) and is_bool($options['visible'])) {
        $insert['visible'] = $options['visible'];
    } else {
        $insert['visible'] = $conf['newcat_default_visible'];
    }
    $insert['visible'] = $conn->boolean_to_string($insert['visible']);

    // is the album private? (may be overwritten if parent album is private)
    if (isset($options['status']) and 'private' == $options['status']) {
        $insert['status'] = 'private';
    } else {
        $insert['status'] = $conf['newcat_default_status'];
    }

    // any description for this album?
    if (isset($options['comment'])) {
        $insert['comment'] = $conf['allow_html_descriptions'] ? $options['comment'] : strip_tags($options['comment']);
    }

    if (!empty($parent_id) and is_numeric($parent_id)) {
        $query = 'SELECT id, uppercats, global_rank, visible, status FROM '.CATEGORIES_TABLE;
        $query .= ' WHERE id = '.$parent_id.';';
        $parent = $conn->db_fetch_assoc($conn->db_query($query));

        $insert['id_uppercat'] = $parent['id'];
        $insert['global_rank'] = $parent['global_rank'].'.'.$insert['rank'];

        // at creation, must a category be visible or not ? Warning : if the
        // parent category is invisible, the category is automatically create
        // invisible. (invisible = locked)
        if ($conn->get_boolean($parent['visible'])===false) {
            $insert['visible'] = 'false';
        }

        // at creation, must a category be public or private ? Warning : if the
        // parent category is private, the category is automatically create
        // private.
        if ('private' == $parent['status']) {
            $insert['status'] = 'private';
        }

        $uppercats_prefix = $parent['uppercats'].',';
    } else {
        $uppercats_prefix = '';
    }

    // we have then to add the virtual category
    $conn->single_insert(CATEGORIES_TABLE, $insert);
    $inserted_id = $conn->db_insert_id(CATEGORIES_TABLE);

    $conn->single_update(
        CATEGORIES_TABLE,
        array('uppercats' => $uppercats_prefix.$inserted_id),
        array('id' => $inserted_id)
    );

    update_global_rank();

    if ('private'==$insert['status'] and !empty($insert['id_uppercat'])
        and ((isset($options['inherit']) and $options['inherit']) or $conf['inheritance_by_default'])) {
        $query = 'SELECT group_id FROM '.GROUP_ACCESS_TABLE;
        $query .= ' WHERE cat_id = '.$insert['id_uppercat'];
        $granted_grps =  $conn->query2array($query, null, 'group_id');
        $inserts = array();
        foreach ($granted_grps as $granted_grp) {
            $inserts[] = array('group_id' => $granted_grp, 'cat_id' => $inserted_id);
        }
        $conn->mass_inserts(GROUP_ACCESS_TABLE, array('group_id','cat_id'), $inserts);

        $query = 'SELECT user_id FROM '.USER_ACCESS_TABLE.' WHERE cat_id = '.$insert['id_uppercat'];
        $granted_users =  $conn->query2array($query, null, 'user_id');
        add_permission_on_category($inserted_id, array_unique(array_merge(get_admins(), array($user['id']), $granted_users)));
    } elseif ('private' == $insert['status']) {
        add_permission_on_category($inserted_id, array_unique(array_merge(get_admins(), array($user['id']))));
    }

    return array(
        'info' => l10n('Virtual album added'),
        'id'   => $inserted_id,
    );
}

/**
 * Associate a list of images to a list of categories.
 * The function will not duplicate links and will preserve ranks.
 *
 * @param int[] $images
 * @param int[] $categories
 */
function associate_images_to_categories($images, $categories) {
    global $conn;

    if (count($images) == 0 || count($categories) == 0) {
        return false;
    }

    // get existing associations
    $query = 'SELECT image_id,category_id FROM '.IMAGE_CATEGORY_TABLE;
    $query .= ' WHERE image_id '.$conn->in($images);
    $query .= ' AND category_id '.$conn->in($categories);
    $result = $conn->db_query($query);

    $existing = array();
    while ($row = $conn->db_fetch_assoc($result)) {
        $existing[ $row['category_id'] ][] = $row['image_id'];
    }

    // get max rank of each categories
    $query = 'SELECT category_id,MAX(rank) AS max_rank FROM '.IMAGE_CATEGORY_TABLE;
    $query .= ' WHERE rank IS NOT NULL';
    $query .= ' AND category_id '.$conn->in($categories);
    $query .= ' GROUP BY category_id;';

    $current_rank_of = $conn->query2array(
        $query,
        'category_id',
        'max_rank'
    );

    // associate only not already associated images
    $inserts = array();
    foreach ($categories as $category_id) {
        if (!isset($current_rank_of[$category_id])) {
            $current_rank_of[$category_id] = 0;
        }
        if (!isset($existing[$category_id])) {
            $existing[$category_id] = array();
        }

        foreach ($images as $image_id) {
            if (!in_array($image_id, $existing[$category_id])) {
                $rank = ++$current_rank_of[$category_id];

                $inserts[] = array(
                    'image_id' => $image_id,
                    'category_id' => $category_id,
                    'rank' => $rank,
                );
            }
        }
    }

    if (count($inserts)) {
        $conn->mass_inserts(
            IMAGE_CATEGORY_TABLE,
            array_keys($inserts[0]),
            $inserts
        );

        update_category($categories);
    }
}

/**
 * Dissociate images from all old categories except their storage category and
 * associate to new categories.
 * This function will preserve ranks.
 *
 * @param int[] $images
 * @param int[] $categories
 */
function move_images_to_categories($images, $categories) {
    global $conn;

    if (count($images) == 0) {
        return false;
    }

    // let's first break links with all old albums but their "storage album"
    $query = 'DELETE FROM '.IMAGE_CATEGORY_TABLE;
    $query .= ' WHERE category_id in (';
    $query .= ' SELECT id FROM '.IMAGES_TABLE;
    $query .= ' WHERE (storage_category_id IS NULL OR storage_category_id NOT '.$conn->in($categories).')';
    $query .= ')';
    $query .= ' AND image_id '.$conn->in($images);

    $conn->db_query($query);

    if (is_array($categories) and count($categories) > 0) {
        associate_images_to_categories($images, $categories);
    }
}

/**
 * Associate images associated to a list of source categories to a list of
 * destination categories.
 *
 * @param int[] $sources
 * @param int[] $destinations
 */
function associate_categories_to_categories($sources, $destinations) {
    global $conn;

    if (count($sources) == 0) {
        return false;
    }

    $query = 'SELECT image_id FROM '.IMAGE_CATEGORY_TABLE;
    $query .= ' WHERE category_id '.$conn->in($sources);
    $images = $conn->query2array($query, null, 'image_id');

    associate_images_to_categories($images, $destinations);
}

/**
 * Refer main Phyxo URLs (currently PHPWG_DOMAIN domain)
 *
 * @return string[]
 */
function pwg_URL() {
    $urls = array(
        'HOME'       => PHPWG_URL,
        'WIKI'       => PHPWG_URL.'/doc',
        'DEMO'       => PHPWG_URL.'/demo',
        'FORUM'      => PHPWG_URL.'/forum',
        'BUGS'       => PHPWG_URL.'/bugs',
        'EXTENSIONS' => PHPWG_URL.'/ext',
    );

    return $urls;
}

/**
 * Invalidates cached data (permissions and category counts) for all users.
 */
function invalidate_user_cache($full=true) {
    global $conn;

    if ($full) {
        $query = 'TRUNCATE TABLE '.USER_CACHE_CATEGORIES_TABLE.';';
        $conn->db_query($query);
        $query = 'TRUNCATE TABLE '.USER_CACHE_TABLE.';';
        $conn->db_query($query);
    } else {
        $query = 'UPDATE '.USER_CACHE_TABLE.' SET need_update = \''.$conn->boolean_to_db(true).'\'';
        $conn->db_query($query);
    }
    trigger_notify('invalidate_user_cache', $full);
}

/**
 * Invalidates cached tags counter for all users.
 */
function invalidate_user_cache_nb_tags() {
    global $user, $conn;

    unset($user['nb_available_tags']);

    $query = 'UPDATE '.USER_CACHE_TABLE;
    $query .= ' SET nb_available_tags = NULL';
    $conn->db_query($query);
}

/**
 * Returns access levels as array used on template with html_options functions.
 *
 * @param int $MinLevelAccess
 * @param int $MaxLevelAccess
 * @return array
 */
function get_user_access_level_html_options($MinLevelAccess=ACCESS_FREE, $MaxLevelAccess=ACCESS_CLOSED) {
    $tpl_options = array();
    for ($level = $MinLevelAccess; $level <= $MaxLevelAccess; $level++) {
        $tpl_options[$level] = l10n(sprintf('ACCESS_%d', $level));
    }

    return $tpl_options;
}

/**
 * Is the category accessible to the (Admin) user ?
 * Note : if the user is not authorized to see this category, category jump
 * will be replaced by admin cat_modify page
 *
 * @param int $category_id
 * @return bool
 */
function cat_admin_access($category_id) {
    global $user;

    // $filter['visible_categories'] and $filter['visible_images']
    // are not used because it's not necessary (filter <> restriction)
    if (in_array($category_id, explode(',', $user['forbidden_categories']))) {
        return false;
    }

    return true;
}

/**
 * Retrieve data from external URL.
 *
 * @param string $src
 * @param string|Ressource $dest - can be a file ressource or string
 * @param array $get_data - data added to request url
 * @param array $post_data - data transmitted with POST
 * @param string $user_agent
 * @param int $step (internal use)
 * @return bool
 */
function fetchRemote($src, &$dest, $get_data=array(), $post_data=array(), $user_agent='Phyxo', $step=0) {
    // Try to retrieve data from local file?
    if (!url_is_remote($src)) {
        $content = @file_get_contents($src);
        if ($content !== false) {
            is_resource($dest) ? @fwrite($dest, $content) : $dest = $content;
            return true;
        } else {
            return false;
        }
    }

    // After 3 redirections, return false
    if ($step > 3) {
        return false;
    }

    // Initialization
    $method  = empty($post_data) ? 'GET' : 'POST';
    $request = empty($post_data) ? '' : http_build_query($post_data, '', '&');
    if (!empty($get_data)) {
        $src .= strpos($src, '?') === false ? '?' : '&';
        $src .= http_build_query($get_data, '', '&');
    }

    // Initialize $dest
    is_resource($dest) or $dest = '';

    // Try curl to read remote file
    // TODO : remove all these @
    if (function_exists('curl_init') && function_exists('curl_exec')) {
        $ch = @curl_init();
        @curl_setopt($ch, CURLOPT_URL, $src);
        @curl_setopt($ch, CURLOPT_HEADER, 1);
        @curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($method == 'POST') {
            @curl_setopt($ch, CURLOPT_POST, 1);
            @curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        }
        $content = @curl_exec($ch);
        $header_length = @curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);
        if ($content !== false and $status >= 200 and $status < 400) {
            if (preg_match('/Location:\s+?(.+)/', substr($content, 0, $header_length), $m)) {
                return fetchRemote($m[1], $dest, array(), array(), $user_agent, $step+1);
            }
            $content = substr($content, $header_length);
            is_resource($dest) ? @fwrite($dest, $content) : $dest = $content;
            return true;
        }
    }

    // Try file_get_contents to read remote file
    if (ini_get('allow_url_fopen')) {
        $opts = array(
            'http' => array(
                'method' => $method,
                'user_agent' => $user_agent,
            )
        );
        if ($method == 'POST') {
            $opts['http']['content'] = $request;
        }
        $context = @stream_context_create($opts);
        $content = @file_get_contents($src, false, $context);
        if ($content !== false) {
            is_resource($dest) ? @fwrite($dest, $content) : $dest = $content;
            return true;
        }
    }

    // Try fsockopen to read remote file
    $src = parse_url($src);
    $host = $src['host'];
    $path = isset($src['path']) ? $src['path'] : '/';
    $path .= isset($src['query']) ? '?'.$src['query'] : '';

    if (($s = @fsockopen($host,80,$errno,$errstr,5)) === false) {
        return false;
    }

    $http_request  = $method." ".$path." HTTP/1.0\r\n";
    $http_request .= "Host: ".$host."\r\n";
    if ($method == 'POST') {
        $http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
        $http_request .= "Content-Length: ".strlen($request)."\r\n";
    }
    $http_request .= "User-Agent: ".$user_agent."\r\n";
    $http_request .= "Accept: */*\r\n";
    $http_request .= "\r\n";
    $http_request .= $request;

    fwrite($s, $http_request);

    $i = 0;
    $in_content = false;
    while (!feof($s)) {
        $line = fgets($s);

        if (rtrim($line,"\r\n") == '' && !$in_content) {
            $in_content = true;
            $i++;
            continue;
        }
        if ($i == 0) {
            if (!preg_match('/HTTP\/(\\d\\.\\d)\\s*(\\d+)\\s*(.*)/',rtrim($line,"\r\n"), $m)) {
                fclose($s);
                return false;
            }
            $status = (integer) $m[2];
            if ($status < 200 || $status >= 400) {
                fclose($s);
                return false;
            }
        }
        if (!$in_content) {
            if (preg_match('/Location:\s+?(.+)$/',rtrim($line,"\r\n"),$m)) {
                fclose($s);
                return fetchRemote(trim($m[1]),$dest,array(),array(),$user_agent,$step+1);
            }
            $i++;
            continue;
        }
        is_resource($dest) ? @fwrite($dest, $line) : $dest .= $line;
        $i++;
    }
    fclose($s);
    return true;
}

/**
 * Returns the groupname corresponding to the given group identifier if exists.
 *
 * @param int $group_id
 * @return string|false
 */
function get_groupname($group_id) {
    global $conn;

    $query = 'SELECT name FROM '.GROUPS_TABLE.' WHERE id = '.intval($group_id).';';
    $result = $conn->db_query($query);
    if ($conn->db_num_rows($result) > 0) {
        list($groupname) = $conn->db_fetch_row($result);
    } else {
        return false;
    }

    return $groupname;
}

/**
 * Returns the username corresponding to the given user identifier if exists.
 *
 * @param int $user_id
 * @return string|false
 */
function get_username($user_id) {
    global $conf, $conn;

    $query = 'SELECT '.$conf['user_fields']['username'].' FROM '.USERS_TABLE;
    $query .= ' WHERE '.$conf['user_fields']['id'].' = '.intval($user_id).';';
    $result = $conn->db_query($query);
    if ($conn->db_num_rows($result) > 0) {
        list($username) = $conn->db_fetch_row($result);
    } else {
        return false;
    }

    // @TODO: why stripslashes ?
    return stripslashes($username);
}

/**
 * Return admin menu id for accordion.
 *
 * @param string $menu_page
 * @return int
 */
function get_active_menu($menu_page) {
    global $page;

    if (isset($page['active_menu'])) {
        return $page['active_menu'];
    }

    switch ($menu_page)
    {
    case 'photo':
    case 'photos_add':
    case 'rating':
    case 'tags':
    case 'batch_manager':
        return 0;

    case 'album':
    case 'cat_list':
    case 'cat_move':
    case 'cat_options':
    case 'permalinks':
        return 1;

    case 'user_list':
    case 'user_perm':
    case 'group_list':
    case 'group_perm':
    case 'notification_by_mail':
        return 2;

    case 'plugins':
    case 'plugin':
        return 3;

    case 'site_manager':
    case 'site_update':
    case 'stats':
    case 'history':
    case 'maintenance':
    case 'comments':
    case 'updates':
        return 4;

    case 'configuration':
    case 'derivatives':
    case 'menubar':
    case 'themes':
    case 'theme':
    case 'languages':
        return 5;

    default:
        return 0;
    }
}

/**
 * Returns the argument_ids array with new sequenced keys based on related
 * names. Sequence is not case sensitive.
 * Warning: By definition, this function breaks original keys.
 *
 * @param int[] $elements_ids
 * @param string[] $name - names of elements, indexed by ids
 * @return int[]
 */
function order_by_name($element_ids, $name) {
    $ordered_element_ids = array();
    foreach ($element_ids as $k_id => $element_id) {
        $key = strtolower($name[$element_id]) .'-'. $name[$element_id] .'-'. $k_id;
        $ordered_element_ids[$key] = $element_id;
    }
    ksort($ordered_element_ids);
    return $ordered_element_ids;
}

/**
 * Grant access to a list of categories for a list of users.
 *
 * @param int[] $category_ids
 * @param int[] $user_ids
 */
function add_permission_on_category($category_ids, $user_ids) {
    global $conn;

    if (!is_array($category_ids)) {
        $category_ids = array($category_ids);
    }
    if (!is_array($user_ids)) {
        $user_ids = array($user_ids);
    }

    // check for emptiness
    if (count($category_ids) == 0 or count($user_ids) == 0) {
        return;
    }

    // make sure categories are private and select uppercats or subcats
    $cat_ids = get_uppercat_ids($category_ids);
    if (isset($_POST['apply_on_sub'])) {
        $cat_ids = array_merge($cat_ids, get_subcat_ids($category_ids));
    }

    $query = 'SELECT id  FROM '.CATEGORIES_TABLE;
    $query .= ' WHERE id '.$conn->in($cat_ids);
    $query .= ' AND status = \'private\';';
    $private_cats = $conn->query2array($query, null, 'id');

    if (count($private_cats) == 0) {
        return;
    }

    $inserts = array();
    foreach ($private_cats as $cat_id) {
        foreach ($user_ids as $user_id) {
            $inserts[] = array(
                'user_id' => $user_id,
                'cat_id' => $cat_id
            );
        }
    }

    $conn->mass_inserts(
        USER_ACCESS_TABLE,
        array('user_id','cat_id'),
        $inserts,
        array('ignore' => true)
    );
}

/**
 * Returns the list of admin users.
 *
 * @param boolean $include_webmaster
 * @return int[]
 */
function get_admins($include_webmaster=true) {
    global $conn;

    $status_list = array('admin');

    if ($include_webmaster) {
        $status_list[] = 'webmaster';
    }

    $query = 'SELECT user_id  FROM '.USER_INFOS_TABLE;
    $query .= ' WHERE status '.$conn->in($status_list);

    return $conn->query2array($query, null, 'user_id');
}

/**
 * Delete all derivative files for one or several types
 *
 * @param 'all'|int[] $types
 */
function clear_derivative_cache($types='all') {
    if ($types === 'all') {
        $types = ImageStdParams::get_all_types();
        $types[] = IMG_CUSTOM;
    } elseif (!is_array($types)) {
        $types = array($types);
    }

    for ($i=0; $i<count($types); $i++) {
        $type = $types[$i];
        if ($type == IMG_CUSTOM) {
            $type = derivative_to_url($type).'[a-zA-Z0-9]+';
        } elseif (in_array($type, ImageStdParams::get_all_types())) {
            $type = derivative_to_url($type);
        } else { //assume a custom type
            $type = derivative_to_url(IMG_CUSTOM).'_'.$type;
        }
        $types[$i] = $type;
    }

    $pattern='#.*-';
    if (count($types)>1) {
        $pattern .= '(' . implode('|',$types) . ')';
    } else {
        $pattern .= $types[0];
    }
    $pattern.='\.[a-zA-Z0-9]{3,4}$#';

    // @TODO: use glob
    if ($contents = @opendir(PHPWG_ROOT_PATH.PWG_DERIVATIVE_DIR)) {
        while (($node = readdir($contents)) !== false) {
            if ($node != '.' and $node != '..' and is_dir(PHPWG_ROOT_PATH.PWG_DERIVATIVE_DIR.$node)) {
                clear_derivative_cache_rec(PHPWG_ROOT_PATH.PWG_DERIVATIVE_DIR.$node, $pattern);
            }
        }
        closedir($contents);
    }
}

/**
 * Used by clear_derivative_cache()
 * @ignore
 */
function clear_derivative_cache_rec($path, $pattern) {
    $rmdir = true;
    $rm_index = false;

    // @TODO: use glob
    if ($contents = opendir($path)) {
        while (($node = readdir($contents)) !== false) {
            if ($node == '.' or $node == '..') {
                continue;
            }
            if (is_dir($path.'/'.$node)) {
                $rmdir &= clear_derivative_cache_rec($path.'/'.$node, $pattern);
            } else {
                if (preg_match($pattern, $node)) {
                    unlink($path.'/'.$node);
                } elseif ($node=='index.htm') {
                    $rm_index = true;
                } else {
                    $rmdir = false;
                }
            }
        }
        closedir($contents);

        if ($rmdir) {
            if ($rm_index) {
                unlink($path.'/index.htm');
            }
            clearstatcache();
            @rmdir($path);
        }
        return $rmdir;
  }
}

/**
 * Deletes derivatives of a particular element
 *
 * @param array $infos ('path'[, 'representative_ext'])
 * @param 'all'|int $type
 */
function delete_element_derivatives($infos, $type='all') {
    $path = $infos['path'];
    if (!empty($infos['representative_ext'])) {
        $path = original_to_representative( $path, $infos['representative_ext']);
    }
    if (substr_compare($path, '../', 0, 3)==0) {
        $path = substr($path, 3);
    }
    $dot = strrpos($path, '.');
    if ($type=='all') {
        $pattern = '-*';
    } else {
        $pattern = '-'.derivative_to_url($type).'*';
    }
    $path = substr_replace($path, $pattern, $dot, 0);
    if (($glob = glob(PHPWG_ROOT_PATH.PWG_DERIVATIVE_DIR.$path)) !== false) {
        foreach($glob as $file) {
            unlink($file);
        }
    }
}

/**
 * Returns an array containing sub-directories, excluding ".svn"
 *
 * @param string $directory
 * @return string[]
 */
function get_dirs($directory) {
    $sub_dirs = array();
    if ($opendir = opendir($directory)) {
        while ($file = readdir($opendir)) {
            if ($file != '.' and $file != '..' and is_dir($directory.'/'.$file) and $file != '.svn') {
                $sub_dirs[] = $file;
            }
        }
        closedir($opendir);
    }

    return $sub_dirs;
}

/**
 * Recursively delete a directory.
 *
 * @param string $path
 * @param string $trash_path, try to move the directory to this path if it cannot be delete
 */
function deltree($path, $trash_path=null) {
    if (is_dir($path)) {
        $fh = opendir($path);
        while ($file = readdir($fh)) {
            if ($file != '.' and $file != '..') {
                $pathfile = $path . '/' . $file;
                if (is_dir($pathfile)) {
                    deltree($pathfile, $trash_path);
                } else {
                    @unlink($pathfile);
                }
            }
        }
        closedir($fh);

        if (@rmdir($path)) {
            return true;
        } elseif (!empty($trash_path)) {
            if (!is_dir($trash_path)) {
                @mkgetdir($trash_path, MKGETDIR_RECURSIVE|MKGETDIR_DIE_ON_ERROR|MKGETDIR_PROTECT_HTACCESS);
            }
            while ($r = $trash_path . '/' . md5(uniqid(rand(), true))) {
                if (!is_dir($r)) {
                    @rename($path, $r);
                    break;
                }
            }
        } else {
            return false;
        }
    }
}

/**
 * Returns keys to identify the state of main tables. A key consists of the
 * last modification timestamp and the total of items (separated by a _).
 * Additionally returns the hash of root path.
 * Used to invalidate LocalStorage cache on admin pages.
 *
 * @param string|string[] list of keys to retrieve (categories,groups,images,tags,users)
 * @return string[]
 */
function get_admin_client_cache_keys($requested=array()) {
    global $conn;

    $tables = array(
        'categories' => CATEGORIES_TABLE,
        'groups' => GROUPS_TABLE,
        'images' => IMAGES_TABLE,
        'tags' => TAGS_TABLE,
        'users' => USER_INFOS_TABLE
    );

    if (!is_array($requested)) {
        $requested = array($requested);
    }
    if (empty($requested)) {
        $requested = array_keys($tables);
    } else {
        $requested = array_intersect($requested, array_keys($tables));
    }

    $keys = array(
        '_hash' => md5(get_absolute_root_url()),
    );

    foreach ($requested as $item) {
        // @TODO : add _ between timestamp and count -> pwg_concat ??
        $query = 'SELECT '.$conn->db_date_to_ts('MAX(lastmodified)').', COUNT(1)';
        $query .= ' FROM '. $tables[$item] .';';
        $result = $conn->db_query($query);
        $row = $conn->db_fetch_row($result);

        $keys[$item] = sprintf('%s_%s', $row[0], $row[1]);
    }

    return $keys;
}
