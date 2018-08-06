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

// +-----------------------------------------------------------------------+
// |                          define and include                           |
// +-----------------------------------------------------------------------+

define('PHPWG_ROOT_PATH', '../../');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_GUEST);

// +-----------------------------------------------------------------------+
// |                     generate random element list                      |
// +-----------------------------------------------------------------------+

$query = 'SELECT id FROM ' . IMAGES_TABLE;
$query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON id = ic.image_id';
$query .= ' ' . get_sql_condition_FandF(
    array(
        'forbidden_categories' => 'category_id',
        'visible_categories' => 'category_id',
        'visible_images' => 'id'
    ),
    'WHERE'
);
$query .= ' ORDER BY ' . $conn::RANDOM_FUNCTION . '()';
$query .= ' LIMIT ' . min(50, $conf['top_number'], $user['nb_image_page']) . ';';

// +-----------------------------------------------------------------------+
// |                                redirect                               |
// +-----------------------------------------------------------------------+

redirect(\Phyxo\Functions\URL::make_index_url(array('list' => $conn->query2array($query, null, 'id'))));
