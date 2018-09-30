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

use App\Repository\CaddieRepository;

// +-----------------------------------------------------------------------+
// |                        batch management request                       |
// +-----------------------------------------------------------------------+

if (isset($_GET['batch'])) {
    \Phyxo\Functions\Utils::check_input_parameter('batch', $_GET, false, '/^\d+(,\d+)*$/');

    (new CaddieRepository($conn))->emptyCaddie($user['id']);

    $inserts = [];
    foreach (explode(',', $_GET['batch']) as $image_id) {
        $inserts[] = [
            'user_id' => $user['id'],
            'element_id' => $image_id,
        ];
    }
    (new CaddieRepository($conn))->addElements(
        array_keys($inserts[0]),
        $inserts
    );

    \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=batch_manager&filter=prefilter-caddie');
}

// add default event handler for image and thumbnail resize
\Phyxo\Functions\Plugin::add_event_handler('upload_image_resize', 'pwg_image_resize');
\Phyxo\Functions\Plugin::add_event_handler('upload_thumbnail_resize', 'pwg_image_resize');

// +-----------------------------------------------------------------------+
// |                             prepare form                              |
// +-----------------------------------------------------------------------+

include_once(PHPWG_ROOT_PATH . 'admin/include/photos_add_direct_prepare.inc.php');

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+
\Phyxo\Functions\Plugin::trigger_notify('loc_end_photo_add_direct');
