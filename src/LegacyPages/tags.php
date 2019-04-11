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
// |                           initialization                              |
// +-----------------------------------------------------------------------+

include_once(__DIR__ . '/../../include/common.inc.php');

$services['users']->checkStatus(ACCESS_GUEST);

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_tags');

// +-----------------------------------------------------------------------+
// |                             functions                                 |
// +-----------------------------------------------------------------------+

function counter_compare($a, $b)
{
    if ($a['counter'] == $b['counter']) {
        return id_compare($a, $b);
    }

    return ($a['counter'] < $b['counter']) ? +1 : -1;
}

function id_compare($a, $b)
{
    return ($a['id'] < $b['id']) ? -1 : 1;
}


// +-----------------------------------------------------------------------+
// |                       page header and options                         |
// +-----------------------------------------------------------------------+

$title = \Phyxo\Functions\Language::l10n('Tags');

$page['display_mode'] = $conf['tags_default_display_mode'];
if (isset($_GET['display_mode'])) {
    if (in_array($_GET['display_mode'], ['cloud', 'letters'])) {
        $page['display_mode'] = $_GET['display_mode'];
    }
}

foreach (['cloud', 'letters'] as $mode) {
    $template->assign(
        'U_' . strtoupper($mode),
        \Phyxo\Functions\URL::get_root_url() . 'tags.php' . ($conf['tags_default_display_mode'] == $mode ? '' : '?display_mode=' . $mode)
    );
}

$template->assign('display_mode', $page['display_mode']);

// find all tags available for the current user
$tags = $tagMapper->getAvailableTags($user);

// +-----------------------------------------------------------------------+
// |                       letter groups construction                      |
// +-----------------------------------------------------------------------+

if ($page['display_mode'] == 'letters') {
    // we want tags diplayed in alphabetic order
    usort($tags, '\Phyxo\Functions\Utils::tag_alpha_compare');

    $current_letter = null;
    $nb_tags = count($tags);
    $current_column = 1;
    $current_tag_idx = 0;

    $letter = ['tags' => []];

    foreach ($tags as $tag) {
        $tag_letter = mb_strtoupper(mb_substr(\Phyxo\Functions\Language::transliterate($tag['name']), 0, 1, PWG_CHARSET), PWG_CHARSET);

        if ($current_tag_idx == 0) {
            $current_letter = $tag_letter;
            $letter['TITLE'] = $tag_letter;
        }

        //lettre precedente differente de la lettre suivante
        if ($tag_letter !== $current_letter) {
            if ($current_column < $conf['tag_letters_column_number']
                and $current_tag_idx > $current_column * $nb_tags / $conf['tag_letters_column_number']) {
                $letter['CHANGE_COLUMN'] = true;
                $current_column++;
            }

            $letter['TITLE'] = $current_letter;

            $template->append(
                'letters',
                $letter
            );

            $current_letter = $tag_letter;
            $letter = [
                'tags' => []
            ];
        }

        $letter['tags'][] = array_merge(
            $tag,
            [
                'URL' => \Phyxo\Functions\URL::make_index_url(['tags' => [$tag]]),
            ]
        );

        $current_tag_idx++;
    }

    // flush last letter
    if (count($letter['tags']) > 0) {
        unset($letter['CHANGE_COLUMN']);
        $letter['TITLE'] = $current_letter;
        $template->append(
            'letters',
            $letter
        );
    }
} else {
    // +-----------------------------------------------------------------------+
    // |                        tag cloud construction                         |
    // +-----------------------------------------------------------------------+

    // we want only the first most represented tags, so we sort them by counter
    // and take the first tags
    usort($tags, 'counter_compare');
    $tags = array_slice($tags, 0, $conf['full_tag_cloud_items_number']);

    // depending on its counter and the other tags counter, each tag has a level
    $tags = $tagMapper->addLevelToTags($tags);

    // we want tags diplayed in alphabetic order
    usort($tags, '\Phyxo\Functions\Utils::tag_alpha_compare');

    // display sorted tags
    foreach ($tags as $tag) {
        $template->append(
            'tags',
            array_merge(
                $tag,
                [
                    'URL' => \Phyxo\Functions\URL::make_index_url(
                        [
                            'tags' => [$tag],
                        ]
                    ),
                ]
            )
        );
    }
}
// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (!isset($themeconf['hide_menu_on']) or !in_array('theTagsPage', $themeconf['hide_menu_on'])) {
    include(__DIR__ . '/menubar.inc.php');
}

\Phyxo\Functions\Plugin::trigger_notify('loc_end_tags');
\Phyxo\Functions\Utils::flush_page_messages();
