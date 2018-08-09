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

if (!defined('PHOTOS_ADD_BASE_URL')) {
    die("Hacking attempt!");
}

// +-----------------------------------------------------------------------+
// |                        batch management request                       |
// +-----------------------------------------------------------------------+

if (isset($_GET['batch'])) {
    check_input_parameter('batch', $_GET, false, '/^\d+(,\d+)*$/');

    $query = 'DELETE FROM ' . CADDIE_TABLE . ' WHERE user_id = ' . $conn->db_real_escape_string($user['id']);
    $conn->db_query($query);

    $inserts = array();
    foreach (explode(',', $_GET['batch']) as $image_id) {
        $inserts[] = array(
            'user_id' => $user['id'],
            'element_id' => $image_id,
        );
    }
    $conn->mass_inserts(
        CADDIE_TABLE,
        array_keys($inserts[0]),
        $inserts
    );

    redirect(\Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=batch_manager&filter=prefilter-caddie');
}

// +-----------------------------------------------------------------------+
// |                             prepare form                              |
// +-----------------------------------------------------------------------+

include_once(PHPWG_ROOT_PATH . 'admin/include/photos_add_direct_prepare.inc.php');

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+
\Phyxo\Functions\Plugin::trigger_notify('loc_end_photo_add_direct');
