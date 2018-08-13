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

use Phyxo\Ws\Server;

/**
 * API method
 * Returns a list of missing derivatives (not generated yet)
 * @param mixed[] $params
 *    @option string types (optional)
 *    @option int[] ids
 *    @option int max_urls
 *    @option int prev_page (optional)
 */
function ws_getMissingDerivatives($params, &$service)
{
    global $conf, $conn;

    if (empty($params['types'])) {
        $types = array_keys(\Phyxo\Image\ImageStdParams::get_defined_type_map());
    } else {
        $types = array_intersect(array_keys(\Phyxo\Image\ImageStdParams::get_defined_type_map()), $params['types']);
        if (count($types) == 0) {
            return new Phyxo\Ws\Error(Server::WS_ERR_INVALID_PARAM, "Invalid types");
        }
    }

    $max_urls = $params['max_urls'];
    $query = 'SELECT MAX(id)+1, COUNT(1) FROM ' . IMAGES_TABLE;
    list($max_id, $image_count) = $conn->db_fetch_row($conn->db_query($query));

    if (0 == $image_count) {
        return array();
    }

    $start_id = $params['prev_page'];
    if ($start_id <= 0) {
        $start_id = $max_id;
    }

    $uid = '&b=' . time();

    $conf['question_mark_in_urls'] = $conf['php_extension_in_urls'] = true;
    $conf['derivative_url_style'] = 2; //script

    $qlimit = min(5000, ceil(max($image_count / 500, $max_urls / count($types))));
    $where_clauses = ws_std_image_sql_filter($params, '');
    $where_clauses[] = 'id<start_id';

    if (!empty($params['ids'])) {
        $where_clauses[] = 'id ' . $conn->in($params['ids']);
    }

    $query_model = 'SELECT id, path, representative_ext, width, height, rotation FROM ' . IMAGES_TABLE;
    $query_model .= ' WHERE ' . implode(' AND ', $where_clauses) . ' ORDER BY id DESC LIMIT ' . $qlimit;

    $urls = array();
    do {
        $result = $conn->db_query(str_replace('start_id', $start_id, $query_model));
        $is_last = $conn->db_num_rows($result) < $qlimit;

        while ($row = $conn->db_fetch_assoc($result)) {
            $start_id = $row['id'];
            $src_image = new \Phyxo\Image\SrcImage($row);
            if ($src_image->is_mimetype()) {
                continue;
            }

            foreach ($types as $type) {
                $derivative = new \Phyxo\Image\DerivativeImage($type, $src_image);
                if ($type != $derivative->get_type()) {
                    continue;
                }
                if (@filemtime($derivative->get_path()) === false) {
                    $urls[] = $derivative->get_url() . $uid;
                }
            }

            if (count($urls) >= $max_urls and !$is_last) {
                break;
            }
        }
        if ($is_last) {
            $start_id = 0;
        }
    } while (count($urls) < $max_urls and $start_id);

    $ret = array();
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
function ws_getVersion($params, &$service)
{
    return PHPWG_VERSION;
}

/**
 * API method
 * Returns general informations about the installation
 * @param mixed[] $params
 */
function ws_getInfos($params, &$service)
{
    global $conn;

    $infos['version'] = PHPWG_VERSION;

    $query = 'SELECT COUNT(1) FROM ' . IMAGES_TABLE . ';';
    list($infos['nb_elements']) = $conn->db_fetch_row($conn->db_query($query));

    $query = 'SELECT COUNT(1) FROM ' . CATEGORIES_TABLE . ';';
    list($infos['nb_categories']) = $conn->db_fetch_row($conn->db_query($query));

    $query = 'SELECT COUNT(1) FROM ' . CATEGORIES_TABLE . ' WHERE dir IS NULL;';
    list($infos['nb_virtual']) = $conn->db_fetch_row($conn->db_query($query));

    $query = 'SELECT COUNT(1) FROM ' . CATEGORIES_TABLE . ' WHERE dir IS NOT NULL;';
    list($infos['nb_physical']) = $conn->db_fetch_row($conn->db_query($query));

    $query = 'SELECT COUNT(1) FROM ' . IMAGE_CATEGORY_TABLE . ';';
    list($infos['nb_image_category']) = $conn->db_fetch_row($conn->db_query($query));

    $query = 'SELECT COUNT(1) FROM ' . TAGS_TABLE . ';';
    list($infos['nb_tags']) = $conn->db_fetch_row($conn->db_query($query));

    $query = 'SELECT COUNT(1) FROM ' . IMAGE_TAG_TABLE . ';';
    list($infos['nb_image_tag']) = $conn->db_fetch_row($conn->db_query($query));

    $query = 'SELECT COUNT(1) FROM ' . USERS_TABLE . ';';
    list($infos['nb_users']) = $conn->db_fetch_row($conn->db_query($query));

    $query = 'SELECT COUNT(1) FROM ' . GROUPS_TABLE . ';';
    list($infos['nb_groups']) = $conn->db_fetch_row($conn->db_query($query));

    $query = 'SELECT COUNT(1) FROM ' . COMMENTS_TABLE . ';';
    list($infos['nb_comments']) = $conn->db_fetch_row($conn->db_query($query));

    // first element
    if ($infos['nb_elements'] > 0) {
        $query = 'SELECT MIN(date_available) FROM ' . IMAGES_TABLE . ';';
        list($infos['first_date']) = $conn->db_fetch_row($conn->db_query($query));
    }

    // unvalidated comments
    if ($infos['nb_comments'] > 0) {
        $query = 'SELECT COUNT(1) FROM ' . COMMENTS_TABLE . ' WHERE validated=\'' . $conn->boolean_to_db(false) . '\'';
        list($infos['nb_unvalidated_comments']) = $conn->db_fetch_row($conn->db_query($query));
    }

    foreach ($infos as $name => $value) {
        $output[] = array(
            'name' => $name,
            'value' => $value,
        );
    }

    return array('infos' => new Phyxo\Ws\NamedArray($output, 'item'));
}

/**
 * API method
 * Adds images to the caddie
 * @param mixed[] $params
 *    @option int[] image_id
 */
function ws_caddie_add($params, &$service)
{
    global $user, $conn;

    $query = 'SELECT id FROM ' . IMAGES_TABLE;
    $query .= ' LEFT JOIN ' . CADDIE_TABLE . ' ON id=element_id AND user_id=' . $user['id'];
    $query .= ' WHERE id ' . $conn->in($params['image_id']) . ' AND element_id IS NULL';
    $result = $conn->query2array($query, null, 'id');

    $datas = array();
    foreach ($result as $id) {
        $datas[] = array(
            'element_id' => $id,
            'user_id' => $user['id'],
        );
    }
    if (count($datas)) {
        $conn->mass_inserts(
            CADDIE_TABLE,
            array('element_id', 'user_id'),
            $datas
        );
    }

    return count($datas);
}

/**
 * API method
 * Deletes rates of an user
 * @param mixed[] $params
 *    @option int user_id
 *    @option string anonymous_id (optional)
 */
function ws_rates_delete($params, &$service)
{
    global $conn;

    $query = 'DELETE FROM ' . RATE_TABLE . ' WHERE user_id=' . $conn->db_real_escape_string($params['user_id']);

    if (!empty($params['anonymous_id'])) {
        $query .= ' AND anonymous_id=\'' . $conn->db_real_escape_string($params['anonymous_id']) . '\'';
    }
    if (!empty($params['image_id'])) {
        $query .= ' AND element_id=' . $conn->db_real_escape_string($params['image_id']);
    }

    $changes = $conn->db_changes($conn->db_query($query));
    if ($changes) {
        \Phyxo\Functions\Rate::update_rating_score();
    }

    return $changes;
}

/**
 * API method
 * Performs a login
 * @param mixed[] $params
 *    @option string username
 *    @option string password
 */
function ws_session_login($params, &$service)
{
    global $conn, $services;

    if ($services['users']->tryLogUser($params['username'], $params['password'], false)) {
        return true;
    }

    return new Phyxo\Ws\Error(999, 'Invalid username/password');
}


/**
 * API method
 * Performs a logout
 * @param mixed[] $params
 */
function ws_session_logout($params, &$service)
{
    global $services;

    if (!$services['users']->isGuest()) {
        $services['users']->logoutUser();
    }

    return true;
}

/**
 * API method
 * Returns info about the current user
 * @param mixed[] $params
 */
function ws_session_getStatus($params, &$service)
{
    global $user, $conf, $conn, $services;

    $res['username'] = $services['users']->isGuest() ? 'guest' : stripslashes($user['username']);
    foreach (array('status', 'theme', 'language') as $k) {
        $res[$k] = $user[$k];
    }
    $res['pwg_token'] = \Phyxo\Functions\Utils::get_token();
    $res['charset'] = \Phyxo\Functions\Utils::get_charset();

    list($dbnow) = $conn->db_fetch_row($conn->db_query('SELECT NOW();'));
    $res['current_datetime'] = $dbnow;
    $res['version'] = PHPWG_VERSION;

    if ($services['users']->isAdmin()) {
        $res['upload_file_types'] = implode(
            ',',
            array_unique(
                array_map(
                    'strtolower',
                    $conf['upload_form_all_types'] ? $conf['file_ext'] : $conf['picture_ext']
                )
            )
        );
    }

    return $res;
}
