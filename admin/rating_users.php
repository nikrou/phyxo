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

if (!defined("RATING_BASE_URL")) {
    die("Hacking attempt!");
}

use App\Repository\RateRepository;
use App\Repository\ImageRepository;
use App\Repository\UserRepository;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\SrcImage;

$filter_min_rates = 2;
if (isset($_GET['f_min_rates'])) {
    $filter_min_rates = (int)$_GET['f_min_rates'];
}

$consensus_top_number = $conf['top_number'];
if (isset($_GET['consensus_top_number'])) {
    $consensus_top_number = (int)$_GET['consensus_top_number'];
}

// build users
$users_by_id = [];
$result = (new UserRepository($conn))->getUserInfosList();
while ($row = $conn->db_fetch_assoc($result)) {
    $users_by_id[(int)$row['id']] = [
        'name' => $row['username'],
        'anon' => $userMapper->isClassicUser()
    ];
}

$by_user_rating_model = ['rates' => []];
foreach ($conf['rate_items'] as $rate) {
    $by_user_rating_model['rates'][$rate] = [];
}

// by user aggregation
$image_ids = [];
$by_user_ratings = [];
$result = (new RateRepository($conn))->findAll();
while ($row = $conn->db_fetch_assoc($result)) {
    if (!isset($users_by_id[$row['user_id']])) {
        $users_by_id[$row['user_id']] = ['name' => '???' . $row['user_id'], 'anon' => false];
    }
    $usr = $users_by_id[$row['user_id']];
    if ($usr['anon']) {
        $user_key = $usr['name'] . '(' . $row['anonymous_id'] . ')';
    } else {
        $user_key = $usr['name'];
    }
    $rating = &$by_user_ratings[$user_key];
    if (is_null($rating)) {
        $rating = $by_user_rating_model;
        $rating['uid'] = (int)$row['user_id'];
        $rating['aid'] = $usr['anon'] ? $row['anonymous_id'] : '';
        $rating['last_date'] = $rating['first_date'] = $row['date'];
        $rating['md5sum'] = md5($rating['uid'] . $rating['aid']);
    } else {
        $rating['first_date'] = $row['date'];
    }

    $rating['rates'][$row['rate']][] = [
        'id' => $row['element_id'],
        'date' => $row['date'],
    ];
    $image_ids[$row['element_id']] = 1;
    unset($rating);
}

// get image tn urls
$image_urls = [];
if (count($image_ids) > 0) {
    $result = (new ImageRepository($conn))->findByIds(array_keys($image_ids));
    $params = $image_std_params->getByType(ImageStandardParams::IMG_SQUARE);
    while ($row = $conn->db_fetch_assoc($result)) {
        $image_urls[$row['id']] = [
            'tn' => (new DerivativeImage(new SrcImage($row, $conf['picture_ext']), $params, $image_std_params))->getUrl(),
            'page' => \Phyxo\Functions\URL::make_picture_url(['image_id' => $row['id'], 'image_file' => $row['file']]),
        ];
    }
}

//all image averages
$all_img_sum = [];
$result = (new RateRepository($conn))->calculateAverageyElement();
while ($row = $conn->db_fetch_assoc($result)) {
    $all_img_sum[(int)$row['element_id']] = ['avg' => (float)$row['avg']];
}

$result = (new ImageRepository($conn))->findBestRated($consensus_top_number);
$best_rated = array_flip($conn->result2array($result, null, 'id'));

// by user stats
foreach ($by_user_ratings as $id => &$rating) {
    $c = 0;
    $s = 0;
    $ss = 0;
    $consensus_dev = 0;
    $consensus_dev_top = 0;
    $consensus_dev_top_count = 0;
    foreach ($rating['rates'] as $rate => $rates) {
        $ct = count($rates);
        $c += $ct;
        $s += $ct * $rate;
        $ss += $ct * $rate * $rate;
        foreach ($rates as $id_date) {
            $dev = abs($rate - $all_img_sum[$id_date['id']]['avg']);
            $consensus_dev += $dev;
            if (isset($best_rated[$id_date['id']])) {
                $consensus_dev_top += $dev;
                $consensus_dev_top_count++;
            }
        }
    }

    $consensus_dev /= $c;
    if ($consensus_dev_top_count) {
        $consensus_dev_top /= $consensus_dev_top_count;
    }

    $var = ($ss - $s * $s / $c) / $c;
    $rating += [
        'id' => $id,
        'count' => $c,
        'avg' => $s / $c,
        'cv' => $s == 0 ? -1 : sqrt($var) / ($s / $c), // http://en.wikipedia.org/wiki/Coefficient_of_variation
        'cd' => $consensus_dev,
        'cdtop' => $consensus_dev_top_count ? $consensus_dev_top : '',
    ];
}
unset($rating);

// filter
foreach ($by_user_ratings as $id => $rating) {
    if ($rating['count'] <= $filter_min_rates) {
        unset($by_user_ratings[$id]);
    }
}

function avg_compare($a, $b)
{
    $d = $a['avg'] - $b['avg'];
    return ($d == 0) ? 0 : ($d < 0 ? -1 : 1);
}

function count_compare($a, $b)
{
    $d = $a['count'] - $b['count'];
    return ($d == 0) ? 0 : ($d < 0 ? -1 : 1);
}

function cv_compare($a, $b)
{
    $d = $b['cv'] - $a['cv']; //desc
    return ($d == 0) ? 0 : ($d < 0 ? -1 : 1);
}

function consensus_dev_compare($a, $b)
{
    $d = $b['cd'] - $a['cd']; //desc
    return ($d == 0) ? 0 : ($d < 0 ? -1 : 1);
}

function last_rate_compare($a, $b)
{
    return -strcmp($a['last_date'], $b['last_date']);
}

$order_by_index = 4;
if (isset($_GET['order_by']) and is_numeric($_GET['order_by'])) {
    $order_by_index = $_GET['order_by'];
}

$available_order_by = [
    [\Phyxo\Functions\Language::l10n('Average rate'), 'avg_compare'],
    [\Phyxo\Functions\Language::l10n('Number of rates'), 'count_compare'],
    [\Phyxo\Functions\Language::l10n('Variation'), 'cv_compare'],
    [\Phyxo\Functions\Language::l10n('Consensus deviation'), 'consensus_dev_compare'],
    [\Phyxo\Functions\Language::l10n('Last'), 'last_rate_compare'],
];

for ($i = 0; $i < count($available_order_by); $i++) {
    $template->append('order_by_options', $available_order_by[$i][0]);
}

$template->assign('order_by_options_selected', [$order_by_index]);
$x = uasort($by_user_ratings, $available_order_by[$order_by_index][1]);

$template->assign([
    'F_ACTION' => \Phyxo\Functions\URL::get_root_url() . 'admin/index.php',
    'F_MIN_RATES' => $filter_min_rates,
    'CONSENSUS_TOP_NUMBER' => $consensus_top_number,
    'available_rates' => $conf['rate_items'],
    'ratings' => $by_user_ratings,
    'image_urls' => $image_urls,
    'TN_WIDTH' => $image_std_params->getByType(ImageStandardParams::IMG_SQUARE)->sizing->ideal_size[0],
]);
