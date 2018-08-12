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
 * This file is included by the main page to show thumbnails for the default
 * case
 *
 */

$pictures = array();

$selection = array_slice(
    $page['items'],
    $page['start'],
    $page['nb_image_page']
);

$selection = \Phyxo\Functions\Plugin::trigger_change('loc_index_thumbnails_selection', $selection);

if (count($selection) > 0) {
    $rank_of = array_flip($selection);

    $query = 'SELECT * FROM ' . IMAGES_TABLE . ' WHERE id ' . $conn->in($selection);
    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        $row['rank'] = $rank_of[$row['id']];
        $pictures[] = $row;
    }

    usort($pictures, '\Phyxo\Functions\Utils::rank_compare');
    unset($rank_of);
}

if (count($pictures) > 0) {
    // define category slideshow url
    $row = reset($pictures);
    $page['cat_slideshow_url'] = \Phyxo\Functions\URL::add_url_params(
        \Phyxo\Functions\URL::duplicate_picture_url(
            array(
                'image_id' => $row['id'],
                'image_file' => $row['file']
            ),
            array('start')
        ),
        array('slideshow' => (isset($_GET['slideshow']) ? $_GET['slideshow'] : ''))
    );

    if ($conf['activate_comments'] and $user['show_nb_comments']) {
        $query = 'SELECT image_id, COUNT(1) AS nb_comments FROM ' . COMMENTS_TABLE;
        $query .= ' WHERE validated = \'' . $conn->boolean_to_db(true) . '\' AND image_id ' . $conn->in($selection);
        $query .= ' GROUP BY image_id;';
        $nb_comments_of = $conn->query2array($query, 'image_id', 'nb_comments');
    }
}

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_index_thumbnails', $pictures);
$tpl_thumbnails_var = array();

foreach ($pictures as $row) {
    // link on picture.php page
    $url = \Phyxo\Functions\URL::duplicate_picture_url(
        array(
            'image_id' => $row['id'],
            'image_file' => $row['file']
        ),
        array('start')
    );

    if (isset($nb_comments_of)) {
        $row['NB_COMMENTS'] = $row['nb_comments'] = (int)@$nb_comments_of[$row['id']];
    }

    $name = \Phyxo\Functions\Utils::render_element_name($row);
    $desc = \Phyxo\Functions\Utils::render_element_description($row, 'main_page_element_description');

    $tpl_var = array_merge($row, array(
        'TN_ALT' => htmlspecialchars(strip_tags($name)),
        'TN_TITLE' => \Phyxo\Functions\Utils::get_thumbnail_title($row, $name, $desc),
        'URL' => $url,
        'DESCRIPTION' => $desc,
        'src_image' => new \Phyxo\Image\SrcImage($row),
    ));

    if ($conf['index_new_icon']) {
        $tpl_var['icon_ts'] = \Phyxo\Functions\Utils::get_icon($row['date_available']);
    }

    if ($user['show_nb_hits']) {
        $tpl_var['NB_HITS'] = $row['hit'];
    }

    switch ($page['section']) {
        case 'best_rated':
            {
                $name = '(' . $row['rating_score'] . ') ' . $name;
                break;
            }
        case 'most_visited':
            {
                if (!$user['show_nb_hits']) {
                    $name = '(' . $row['hit'] . ') ' . $name;
                }
                break;
            }
    }
    $tpl_var['NAME'] = $name;
    $tpl_thumbnails_var[] = $tpl_var;
}

$derivative_params = \Phyxo\Functions\Plugin::trigger_change(
    'get_index_derivative_params',
    \Phyxo\Image\ImageStdParams::get_by_type(isset($_SESSION['index_deriv']) ? $_SESSION['index_deriv'] : IMG_THUMB)
);
$template->assign(
    array(
        'derivative_params' => $derivative_params,
        'maxRequests' => $conf['max_requests'],
        'SHOW_THUMBNAIL_CAPTION' => $conf['show_thumbnail_caption'],
    )
);
$tpl_thumbnails_var = \Phyxo\Functions\Plugin::trigger_change('loc_end_index_thumbnails', $tpl_thumbnails_var, $pictures);
$template->assign('thumbnails', $tpl_thumbnails_var);
unset($pictures, $selection, $tpl_thumbnails_var);
