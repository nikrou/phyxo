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

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_page_header');

$template->assign(
    [
        'GALLERY_TITLE' =>
            isset($page['gallery_title']) ?
            $page['gallery_title'] : $conf['gallery_title'],

        'PAGE_BANNER' =>
            \Phyxo\Functions\Plugin::trigger_change(
            'render_page_banner',
            str_replace(
                '%gallery_title%',
                $conf['gallery_title'],
                isset($page['page_banner']) ? $page['page_banner'] : $conf['page_banner']
            )
        ),

        'BODY_ID' => isset($page['body_id']) ? $page['body_id'] : '',
        'CONTENT_ENCODING' => \Phyxo\Functions\Utils::get_charset(),
        'PAGE_TITLE' => strip_tags($title),
        'U_HOME' => \Phyxo\Functions\URL::get_root_url(),
        'LEVEL_SEPARATOR' => $conf['level_separator'],
    ]
);


// Header notes
if (!empty($header_notes)) {
    $template->assign('header_notes', $header_notes);
}

\Phyxo\Functions\Plugin::trigger_notify('loc_end_page_header');
// header('Content-Type: text/html; charset='.\Phyxo\Functions\Utils::get_charset()); // To restore ?
\Phyxo\Functions\Plugin::trigger_notify('loc_after_page_header');
