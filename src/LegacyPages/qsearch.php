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

use App\Repository\SearchRepository;

include_once(__DIR__ . '/../../include/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_GUEST);

if (empty($_GET['q'])) {
    \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::make_index_url());
}

$search = [];
$search['q'] = $_GET['q'];

$result = (new SearchRepository($conn))->findByRules(serialize($search));
$search_id = $conn->result2array($result, null, 'id');
if (!empty($search_id)) {
    $search_id = $search_id[0];
    (new SearchRepository($conn))->updateLastSeen($search_id);
} else {
    $search_id = (new SearchRepository($conn))->addSearch(serialize($search_id));
}

\Phyxo\Functions\Utils::redirect(
    \Phyxo\Functions\URL::make_index_url(
        [
            'section' => 'search',
            'search' => $search_id,
        ]
    )
);
