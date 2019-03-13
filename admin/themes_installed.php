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

if (!defined("THEMES_BASE_URL")) {
    die("Hacking attempt!");
}

use Phyxo\Theme\Themes;

$themes = new Themes($conn);

// +-----------------------------------------------------------------------+
// |                          perform actions                              |
// +-----------------------------------------------------------------------+

if (isset($_GET['action']) and isset($_GET['theme'])) {
    $page['errors'] = $themes->performAction($_GET['action'], $_GET['theme']);

    if (empty($page['errors'])) {
        if ($_GET['action'] == 'activate' or $_GET['action'] == 'deactivate') {
            $template->delete_compiled_templates();
        }
        \Phyxo\Functions\Utils::redirect(THEMES_BASE_URL . '&section=installed');
    }
}

// +-----------------------------------------------------------------------+
// |                     start template output                             |
// +-----------------------------------------------------------------------+

$themes->sortFsThemes();

$default_theme = $services['users']->getDefaultTheme();

$db_themes = $themes->getDbThemes();
$db_theme_ids = [];
foreach ($db_themes as $db_theme) {
    $db_theme_ids[] = $db_theme['id'];
}

$tpl_themes = [];

foreach ($themes->getFsThemes() as $theme_id => $fs_theme) {
    $tpl_theme = [
        'ID' => $theme_id,
        'NAME' => $fs_theme['name'],
        'VISIT_URL' => $fs_theme['uri'],
        'VERSION' => $fs_theme['version'],
        'DESC' => $fs_theme['description'],
        'AUTHOR' => $fs_theme['author'],
        'AUTHOR_URL' => @$fs_theme['author uri'],
        'PARENT' => @$fs_theme['parent'],
        'SCREENSHOT' => $fs_theme['screenshot'],
        'IS_MOBILE' => $fs_theme['mobile'],
        'ADMIN_URI' => @$fs_theme['admin_uri'],
    ];

    if (in_array($theme_id, $db_theme_ids)) {
        $tpl_theme['STATE'] = 'active';
        $tpl_theme['IS_DEFAULT'] = ($theme_id == $default_theme);
        $tpl_theme['DEACTIVABLE'] = true;

        if (count($db_theme_ids) <= 1) {
            $tpl_theme['DEACTIVABLE'] = false;
            $tpl_theme['DEACTIVATE_TOOLTIP'] = \Phyxo\Functions\Language::l10n('Impossible to deactivate this theme, you need at least one theme.');
        }
        if ($tpl_theme['IS_DEFAULT']) {
            $tpl_theme['DEACTIVABLE'] = false;
            $tpl_theme['DEACTIVATE_TOOLTIP'] = \Phyxo\Functions\Language::l10n('Impossible to deactivate the default theme.');
        }
    } else {
        $tpl_theme['STATE'] = 'inactive';

        // is the theme "activable" ?
        if (isset($fs_theme['activable']) and !$fs_theme['activable']) {
            $tpl_theme['ACTIVABLE'] = false;
            $tpl_theme['ACTIVABLE_TOOLTIP'] = \Phyxo\Functions\Language::l10n('This theme was not designed to be directly activated');
        } else {
            $tpl_theme['ACTIVABLE'] = true;
        }

        $missing_parent = $themes->missingParentTheme($theme_id);
        if (isset($missing_parent)) {
            $tpl_theme['ACTIVABLE'] = false;

            $tpl_theme['ACTIVABLE_TOOLTIP'] = \Phyxo\Functions\Language::l10n(
                'Impossible to activate this theme, the parent theme is missing: %s',
                $missing_parent
            );
        }

        // is the theme "deletable" ?
        $children = $themes->getChildrenThemes($theme_id);

        $tpl_theme['DELETABLE'] = true;

        if (count($children) > 0) {
            $tpl_theme['DELETABLE'] = false;

            $tpl_theme['DELETE_TOOLTIP'] = \Phyxo\Functions\Language::l10n(
                'Impossible to delete this theme. Other themes depends on it: %s',
                implode(', ', $children)
            );
        }
    }

    $tpl_themes[] = $tpl_theme;
}

// sort themes by state then by name
function cmp($a, $b)
{
    $s = ['active' => 0, 'inactive' => 1];

    if (@$a['IS_DEFAULT']) return -1;
    if (@$b['IS_DEFAULT']) return 1;

    if ($a['STATE'] == $b['STATE']) {
        return strcasecmp($a['NAME'], $b['NAME']);
    } else {
        return $s[$a['STATE']] >= $s[$b['STATE']];
    }
}
usort($tpl_themes, 'cmp');

$template->assign(
    [
        'activate_baseurl' => THEMES_BASE_URL . '&amp;section=installed&amp;action=activate&amp;theme=',
        'deactivate_baseurl' => THEMES_BASE_URL . '&amp;section=installed&amp;action=deactivate&amp;theme=',
        'set_default_baseurl' => THEMES_BASE_URL . '&amp;section=installed&amp;action=set_default&amp;theme=',
        'delete_baseurl' => THEMES_BASE_URL . '&amp;section=installed&amp;action=delete&amp;theme=',
        'tpl_themes' => $tpl_themes,
    ]
);

\Phyxo\Functions\Plugin::trigger_notify('loc_end_themes_installed');
