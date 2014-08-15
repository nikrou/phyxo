<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire           http://phyxo.nikrou.net/ |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

require_once(PHPWG_ROOT_PATH . '/vendor/autoload.php');

use Phyxo\Theme\Themes;

$themes = new Themes();

if (isset($_POST['edit'])) {
    $_POST['theme'] = $_POST['theme_select'];
}

if (isset($_POST['theme']) and '~common~' == $_POST['theme']) {
    $page['theme'] = $_POST['theme'];
    $edited_file = PHPWG_ROOT_PATH.PWG_LOCAL_DIR.'css/rules.css';
} else {
    if (isset($_GET['theme'])) {
        $page['theme'] = $_GET['theme'];
    } elseif (isset($_POST['theme'])) {
        $page['theme'] = $_POST['theme'];
    }

    if (!isset($page['theme']) or !in_array($page['theme'], array_keys($themes->fs_themes))) {
        $page['theme'] = get_default_theme();
    }

    $edited_file = PHPWG_ROOT_PATH.PWG_LOCAL_DIR . 'css/'.$page['theme'].'-rules.css';
}

$template->assign('theme', $page['theme']);

if (file_exists($edited_file)) {
    $content_file = file_get_contents($edited_file);
} else {
    $content_file = "/* " . l10n('locfiledit_newfile') . " */\n\n";
}

$selected = 0;
$value = '~common~';
$file = PHPWG_ROOT_PATH.PWG_LOCAL_DIR . 'css/rules.css';

$options[$value] = (file_exists($file) ? '&#x2714;' : '&#x2718;').' local / css / rules.css';
if ($page['theme'] == $value) {
    $selected = $value;
}

// themes are displayed in the same order as on screen
// [Administration > Configuration > Themes]

$themes->sort_fs_themes();
$default_theme = get_default_theme();
$db_themes = $themes->get_db_themes();

$db_theme_ids = array();
foreach ($db_themes as $db_theme) {
    $db_theme_ids[] = $db_theme['id'];
}

$active_themes = array();
$inactive_themes = array();

foreach ($themes->fs_themes as $theme_id => $fs_theme) {
    if ($theme_id == 'default') {
        continue;
    }

    if (in_array($theme_id, $db_theme_ids)) {
        if ($theme_id == $default_theme) {
            array_unshift($active_themes, $fs_theme);
        } else {
            $active_themes[] = $fs_theme;
        }
    } else {
        $inactive_themes[] = $fs_theme;
    }
}

$active_theme_options = array();
foreach ($active_themes as $theme) {
    $file = PHPWG_ROOT_PATH.PWG_LOCAL_DIR . 'css/'.$theme['id'].'-rules.css';

    $label = (file_exists($file) ? '&#x2714;' : '&#x2718;').' '.$theme['name'];

    if ($default_theme == $theme['id']) {
        $label.= ' ('.l10n('default').')';
    }

    $active_theme_options[$theme['id']] = $label;

    if ($theme['id'] == $page['theme']) {
        $selected = $theme['id'];
    }
}

if (count($active_theme_options) > 0) {
    $options[l10n('Active Themes')] = $active_theme_options;
}

$inactive_theme_options = array();
foreach ($inactive_themes as $theme) {
    $file = PHPWG_ROOT_PATH.PWG_LOCAL_DIR . 'css/'.$theme['id'].'-rules.css';

    $inactive_theme_options[$theme['id']] = (file_exists($file) ? '&#x2714;' : '&#x2718;').' '.$theme['name'];

    if ($theme['id'] == $page['theme']) {
        $selected = $theme['id'];
    }
}

if (count($inactive_theme_options) > 0) {
    $options[l10n('Inactive Themes')] = $inactive_theme_options;
}

$template->assign(
    'css_lang_tpl',
    array(
        'SELECT_NAME' => 'theme_select',
        'OPTIONS' => $options,
        'SELECTED' => $selected
    )
);

$codemirror_mode = 'text/css';
