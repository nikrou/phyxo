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
    die("Hacking attempt!");
}

define('COMMENTS_BASE_URL', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=comments');

use Phyxo\TabSheet\TabSheet;
use App\Repository\CommentRepository;

if (isset($_GET['start']) and is_numeric($_GET['start'])) {
    $page['start'] = $_GET['start'];
} else {
    $page['start'] = 0;
}

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+

if (!empty($_POST)) {
    if (empty($_POST['comments'])) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Select at least one comment');
    } else {
        \Phyxo\Functions\Utils::check_input_parameter('comments', $_POST, true, PATTERN_ID);

        if (isset($_POST['validate'])) {
            $services['comments']->validateUserComment($_POST['comments']);

            $page['infos'][] = \Phyxo\Functions\Language::l10n_dec(
                '%d user comment validated',
                '%d user comments validated',
                count($_POST['comments'])
            );
        }

        if (isset($_POST['reject'])) {
            $services['comments']->deleteUserComment($_POST['comments']);

            $page['infos'][] = \Phyxo\Functions\Language::l10n_dec(
                '%d user comment rejected',
                '%d user comments rejected',
                count($_POST['comments'])
            );
        }
    }
}

// +-----------------------------------------------------------------------+
// | Tabs                                                                  |
// +-----------------------------------------------------------------------+
if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'all';
}

$tabsheet = new tabsheet();
$tabsheet->add('all', \Phyxo\Functions\Language::l10n('All'), COMMENTS_BASE_URL . '&amp;section=all');
$tabsheet->add('pending', \Phyxo\Functions\Language::l10n('Pendings'), COMMENTS_BASE_URL . '&amp;section=pending');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => COMMENTS_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// |                           comments display                            |
// +-----------------------------------------------------------------------+

$nb_total = 0;
$nb_pending = 0;

$result = (new CommentRepository($conn))->countGroupByValidated();
while ($row = $conn->db_fetch_assoc($result)) {
    $nb_total += $row['counter'];

    if ($conn->get_boolean($row['validated']) == false) {
        $nb_pending = $row['counter'];
    }
}

if ($page['section'] === 'all') {
    $template->assign([
        'NB_ELEMENTS' => $nb_total,
        'TABSHEET_TITLE' => \Phyxo\Functions\Language::l10n('All'),
    ]);
} else {
    $template->assign([
        'NB_ELEMENTS' => $nb_pending,
        'TABSHEET_TITLE' => \Phyxo\Functions\Language::l10n('Pendings'),
    ]);
}

$result = (new CommentRepository($conn))->getCommentOnImages(
    $conf['comments_page_nb_comments'],
    $page['start'],
    $validated = $page['section'] === 'pending' ? false : null
);
while ($row = $conn->db_fetch_assoc($result)) {
    $thumb = \Phyxo\Image\DerivativeImage::thumb_url(
        [
            'id' => $row['image_id'],
            'path' => $row['path'],
        ]
    );
    if (empty($row['author_id'])) {
        $author_name = $row['author'];
    } else {
        $author_name = stripslashes($row['username']);
    }
    $template->append(
        'comments',
        [
            'U_PICTURE' => \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=photo-' . $row['image_id'],
            'ID' => $row['id'],
            'TN_SRC' => $thumb,
            'AUTHOR' => \Phyxo\Functions\Plugin::trigger_change('render_comment_author', $author_name),
            'DATE' => \Phyxo\Functions\DateTime::format_date($row['date'], ['day_name', 'day', 'month', 'year', 'time']),
            'CONTENT' => \Phyxo\Functions\Plugin::trigger_change('render_comment_content', $row['content']),
            'IS_PENDING' => $conn->get_boolean($row['validated']) === false,
            'IP' => $row['anonymous_id'],
        ]
    );

    $list[] = $row['id'];
}

// +-----------------------------------------------------------------------+
// |                            navigation bar                             |
// +-----------------------------------------------------------------------+

$navbar = \Phyxo\Functions\Utils::create_navigation_bar(
    \Phyxo\Functions\URL::get_root_url() . 'admin/index.php' . \Phyxo\Functions\URL::get_query_string_diff(['start']),
    ('pending' == $page['section'] ? $nb_pending : $nb_total),
    $page['start'],
    $conf['comments_page_nb_comments']
);

$template->assign('navbar', $navbar);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'comments';

$template->assign(['F_ACTION' => \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=comments']);
