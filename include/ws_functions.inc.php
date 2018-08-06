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
 * Event handler for method invocation security check. Should return a Phyxo\Ws\Error
 * if the preconditions are not satifsied for method invocation.
 */
function ws_isInvokeAllowed($res, $methodName, $params)
{
    global $conf, $services;

    if (strpos($methodName, 'reflection.') === 0) { // OK for reflection
        return $res;
    }

    if (!$services['users']->isAuthorizeStatus(ACCESS_GUEST) && strpos($methodName, 'pwg.session.') !== 0) {
        return new Phyxo\Ws\Error(401, 'Access denied');
    }

    return $res;
}

/**
 * returns a "standard" (for our web service) array of sql where clauses that
 * filters the images (images table only)
 */
function ws_std_image_sql_filter($params, $tbl_name = '')
{
    $clauses = array();
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
function ws_std_image_sql_order($params, $tbl_name = '')
{
    global $conn;

    $ret = '';
    if (empty($params['order'])) {
        return $ret;
    }
    $matches = array();
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
                $matches[1][$i] = $conn::RANDOM_FUNCTION . '()';
                break;
        }
        $sortable_fields = array(
            'id', 'file', 'name', 'hit', 'rating_score',
            'date_creation', 'date_available', $conn::RANDOM_FUNCTION . '()'
        );
        if (in_array($matches[1][$i], $sortable_fields)) {
            if (!empty($ret)) {
                $ret .= ', ';
            }
            if ($matches[1][$i] != $conn::RANDOM_FUNCTION . '()') {
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
function ws_std_get_urls($image_row)
{
    global $user;

    $ret = array();
    $ret['page_url'] = \Phyxo\Functions\URL::make_picture_url(
        array(
            'image_id' => $image_row['id'],
            'image_file' => $image_row['file'],
        )
    );

    $src_image = new SrcImage($image_row);

    if ($src_image->is_original()) { // we have a photo
        if ($user['enabled_high']) {
            $ret['element_url'] = $src_image->get_url();
        }
    } else {
        $ret['element_url'] = \Phyxo\Functions\URL::get_element_url($image_row);
    }

    $derivatives = DerivativeImage::get_all($src_image);
    $derivatives_arr = array();
    foreach ($derivatives as $type => $derivative) {
        $size = $derivative->get_size();
        $size != null or $size = array(null, null);
        $derivatives_arr[$type] = array('url' => $derivative->get_url(), 'width' => $size[0], 'height' => $size[1]);
    }
    $ret['derivatives'] = $derivatives_arr;

    return $ret;
}

/**
 * returns an array of image attributes that are to be encoded as xml attributes
 * instead of xml elements
 */
function ws_std_get_image_xml_attributes()
{
    return array('id', 'element_url', 'page_url', 'file', 'width', 'height', 'hit', 'date_available', 'date_creation');
}

function ws_std_get_category_xml_attributes()
{
    return array('id', 'url', 'nb_images', 'total_nb_images', 'nb_categories', 'date_last', 'max_date_last');
}

function ws_std_get_tag_xml_attributes()
{
    return array('id', 'name', 'url_name', 'counter', 'url', 'page_url');
}

/**
 * Writes info to the log file
 */
function ws_logfile($string)
{
    global $conf;

    if (!$conf['ws_enable_log']) {
        return true;
    }

    file_put_contents(
        $conf['ws_log_filepath'],
        '[' . date('c') . '] ' . $string . "\n",
        FILE_APPEND
    );
}

/**
 * create a tree from a flat list of categories, no recursivity for high speed
 */
function categories_flatlist_to_tree($categories)
{
    $tree = array();
    $key_of_cat = array();

    foreach ($categories as $key => &$node) {
        $key_of_cat[$node['id']] = $key;

        if (!isset($node['id_uppercat'])) {
            $tree[] = &$node;
        } else {
            if (!isset($categories[$key_of_cat[$node['id_uppercat']]]['sub_categories'])) {
                $categories[$key_of_cat[$node['id_uppercat']]]['sub_categories'] = new Phyxo\Ws\NamedArray(array(), 'category', ws_std_get_category_xml_attributes());
            }

            $categories[$key_of_cat[$node['id_uppercat']]]['sub_categories']->_content[] = &$node;
        }
    }

    return $tree;
}
