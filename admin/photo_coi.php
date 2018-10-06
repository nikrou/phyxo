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

if (!defined("PHOTO_BASE_URL")) {
    die('Hacking attempt!');
}

use App\Repository\ImageRepository;

\Phyxo\Functions\Utils::check_input_parameter('image_id', $_GET, false, PATTERN_ID);

if (isset($_POST['submit'])) {
    if (strlen($_POST['l']) == 0) {
        (new ImageRepository(($conn)))->updateImage(['coi' => null], $_GET['image_id']);
    } else {
        (new ImageRepository(($conn)))->updateImage(
            [
                'coi' => \Phyxo\Image\DerivativeParams::fraction_to_char($_POST['l'])
                    . \Phyxo\Image\DerivativeParams::fraction_to_char($_POST['t'])
                    . \Phyxo\Image\DerivativeParams::fraction_to_char($_POST['r'])
                    . \Phyxo\Image\DerivativeParams::fraction_to_char($_POST['b'])
            ],
            $_GET['image_id']
        );
    }
}

$result = (new ImageRepository($conn))->findById($_GET['image_id']);
$row = $conn->db_fetch_assoc($result);

if (isset($_POST['submit'])) {
    foreach (\Phyxo\Image\ImageStdParams::get_defined_type_map() as $params) {
        if ($params->sizing->max_crop != 0) {
            \Phyxo\Functions\Utils::delete_element_derivatives($row, $params->type);
        }
    }
    \Phyxo\Functions\Utils::delete_element_derivatives($row, IMG_CUSTOM);
    $uid = '&b=' . time();
    $conf['question_mark_in_urls'] = $conf['php_extension_in_urls'] = true;
    if ($conf['derivative_url_style'] == 1) {
        $conf['derivative_url_style'] = 0; //auto
    }
} else {
    $uid = '';
}

$tpl_var = [
    'TITLE' => \Phyxo\Functions\Utils::render_element_name($row),
    'ALT' => $row['file'],
    'U_IMG' => \Phyxo\Image\DerivativeImage::url(IMG_LARGE, $row),
];

if (!empty($row['coi'])) {
    $tpl_var['coi'] = [
        'l' => \Phyxo\Image\DerivativeParams::char_to_fraction($row['coi'][0]),
        't' => \Phyxo\Image\DerivativeParams::char_to_fraction($row['coi'][1]),
        'r' => \Phyxo\Image\DerivativeParams::char_to_fraction($row['coi'][2]),
        'b' => \Phyxo\Image\DerivativeParams::char_to_fraction($row['coi'][3]),
    ];
}

foreach (\Phyxo\Image\ImageStdParams::get_defined_type_map() as $params) {
    if ($params->sizing->max_crop != 0) {
        $derivative = new \Phyxo\Image\DerivativeImage($params, new \Phyxo\Image\SrcImage($row));
        $template->append('cropped_derivatives', [
            'U_IMG' => $derivative->get_url() . $uid,
            'HTM_SIZE' => $derivative->get_size_htm(),
        ]);
    }
}

$template->assign($tpl_var);
