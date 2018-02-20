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

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

use Phyxo\TabSheet\TabSheet;

// +-----------------------------------------------------------------------+
// | Basic checks                                                          |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);
check_input_parameter('cat_id', $_GET, false, PATTERN_ID);


$query = 'SELECT * FROM '.CATEGORIES_TABLE.' WHERE id = '.$conn->db_real_escape_string($_GET['cat_id']);
$category = $conn->db_fetch_assoc($conn->db_query($query));
foreach ($category as $k => $v) {
    if ($conn->is_boolean($v)) {
        $category[$k] = $conn->get_boolean($v);
    }
}

define('ALBUM_BASE_URL', get_root_url().'admin/index.php?page=album&amp;cat_id='.$category['id']);

// +-----------------------------------------------------------------------+
// |                                 Tabs                                  |
// +-----------------------------------------------------------------------+
if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'properties';
}

$tabsheet = new TabSheet();
$tabsheet->setId('album');
$tabsheet->select($page['section']);
$tabsheet->assign($template);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'album_'.$page['section'];

include(PHPWG_ROOT_PATH.'admin/album_'.$page['section'].'.php');
