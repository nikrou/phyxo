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

define('PHPWG_ROOT_PATH', '../../');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');
$services['users']->checkStatus(ACCESS_FREE);

/**
 * returns language value 'included' or 'excluded' depending on boolean
 * value. This function is useful only to make duplicate code shorter
 *
 * @param bool is_included
 * @return string
 */
function inc_exc_str($is_included)
{
    return $is_included ? \Phyxo\Functions\Language::l10n('included') : \Phyxo\Functions\Language::l10n('excluded');
}


$title = \Phyxo\Functions\Language::l10n('Search rules');
include(PHPWG_ROOT_PATH . 'include/menubar.inc.php');

// +-----------------------------------------------------------------------+
// |                        Textual rules creation                         |
// +-----------------------------------------------------------------------+

// Rules are stored in database, serialized in an array. This array must be
// transformed into a list of textual rules.

$search = \Phyxo\Functions\Search::get_search_array($_GET['search_id']);

if (isset($search['q'])) {
    $template->append('search_words', $search['q']);
} else {
    $template->assign(
        ['INTRODUCTION' => 'OR' == $search['mode'] ? \Phyxo\Functions\Language::l10n('At least one listed rule must be satisfied.') : \Phyxo\Functions\Language::l10n('Each listed rule must be satisfied.')]
    );
}

if (isset($search['fields']['allwords'])) {
    $template->append(
        'search_words',
        \Phyxo\Functions\Language::l10n(
            'searched words : %s',
            join(', ', $search['fields']['allwords']['words'])
        )
    );
}

if (isset($search['fields']['tags'])) {
    $template->assign('SEARCH_TAGS_MODE', $search['fields']['tags']['mode']);

    $query = 'SELECT name FROM ' . TAGS_TABLE;
    $query .= ' WHERE id ' . $conn->in($search['fields']['tags']['words']);
    $template->assign(
        'search_tags',
        $conn->query2array($query, 'name')
    );
}

if (isset($search['fields']['author'])) {
    $template->append(
        'search_words',
        \Phyxo\Functions\Language::l10n(
            'author(s) : %s',
            join(', ', array_map('strip_tags', $search['fields']['author']['words']))
        )
    );
}

if (isset($search['fields']['cat'])) {
    if ($search['fields']['cat']['sub_inc']) {
        // searching all the categories id of sub-categories
        $cat_ids = \Phyxo\Functions\Category::get_subcat_ids($search['fields']['cat']['words']);
    } else {
        $cat_ids = $search['fields']['cat']['words'];
    }

    $query = 'SELECT id, uppercats, global_rank FROM ' . CATEGORIES_TABLE;
    $query .= ' WHERE id ' . $conn->in($cat_ids);
    $result = $conn->db_query($query);

    $categories = [];
    if (!empty($result)) {
        while ($row = $conn->db_fetch_assoc($result)) {
            $categories[] = $row;
        }
    }
    usort($categories, '\Phyxo\Functions\Utils::global_rank_compare');

    foreach ($categories as $category) {
        $template->append(
            'search_categories',
            \Phyxo\Functions\Category::get_cat_display_name_cache(
                $category['uppercats'],
                null // no url on category names
            )
        );
    }
}

foreach (['date_available', 'date_creation'] as $datefield) {
    if ('date_available' == $datefield) {
        $lang_items = [
            'date' => \Phyxo\Functions\Language::l10n('posted on %s'),
            'period' => \Phyxo\Functions\Language::l10n('posted between %s (%s) and %s (%s)'),
            'after' => \Phyxo\Functions\Language::l10n('posted after %s (%s)'),
            'before' => \Phyxo\Functions\Language::l10n('posted before %s (%s)'),
        ];
    } elseif ('date_creation' == $datefield) {
        $lang_items = [
            'date' => \Phyxo\Functions\Language::l10n('created on %s'),
            'period' => \Phyxo\Functions\Language::l10n('created between %s (%s) and %s (%s)'),
            'after' => \Phyxo\Functions\Language::l10n('created after %s (%s)'),
            'before' => \Phyxo\Functions\Language::l10n('created before %s (%s)'),
        ];
    }

    $keys = [
        'date' => $datefield,
        'after' => $datefield . '-after',
        'before' => $datefield . '-before',
    ];

    if (isset($search['fields'][$keys['date']])) {
        $template->assign(
            strtoupper($datefield),
            sprintf(
                $lang_items['date'],
                \Phyxo\Functions\DateTime::format_date($search['fields'][$keys['date']])
            )
        );
    } elseif (isset($search['fields'][$keys['before']]) and isset($search['fields'][$keys['after']])) {
        $template->assign(
            strtoupper($datefield),
            sprintf(
                $lang_items['period'],
                \Phyxo\Functions\DateTime::format_date($search['fields'][$keys['after']]['date']),
                inc_exc_str($search['fields'][$keys['after']]['inc']),
                \Phyxo\Functions\DateTime::format_date($search['fields'][$keys['before']]['date']),
                inc_exc_str($search['fields'][$keys['before']]['inc'])
            )
        );
    } elseif (isset($search['fields'][$keys['before']])) {
        $template->assign(
            strtoupper($datefield),
            sprintf(
                $lang_items['before'],
                \Phyxo\Functions\DateTime::format_date($search['fields'][$keys['before']]['date']),
                inc_exc_str($search['fields'][$keys['before']]['inc'])
            )
        );
    } elseif (isset($search['fields'][$keys['after']])) {
        $template->assign(
            strtoupper($datefield),
            sprintf(
                $lang_items['after'],
                \Phyxo\Functions\DateTime::format_date($search['fields'][$keys['after']]['date']),
                inc_exc_str($search['fields'][$keys['after']]['inc'])
            )
        );
    }
}

// +-----------------------------------------------------------------------+
// |                           html code display                           |
// +-----------------------------------------------------------------------+

\Phyxo\Functions\Utils::flush_page_messages();
