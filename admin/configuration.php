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
    die('Hacking attempt!');
}

define('CONFIGURATION_BASE_URL', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=configuration');

use Phyxo\TabSheet\TabSheet;
use App\Repository\ConfigRepository;

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

//-------------------------------------------------------- sections definitions
$Sections = ['main', 'sizes', 'watermark', 'display', 'comments', 'default'];

if (!empty($_GET['section']) && in_array($_GET['section'], $Sections)) {
    $section = $_GET['section'];
} else {
    $section = 'main';
}

$action = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=configuration';
$action .= '&amp;section=' . $section;

// TabSheet
$tabsheet = new TabSheet();
$tabsheet->add('main', \Phyxo\Functions\Language::l10n('General'), CONFIGURATION_BASE_URL . '&amp;section=main');
$tabsheet->add('sizes', \Phyxo\Functions\Language::l10n('Photo sizes'), CONFIGURATION_BASE_URL . '&amp;section=sizes');
$tabsheet->add('watermark', \Phyxo\Functions\Language::l10n('Watermark'), CONFIGURATION_BASE_URL . '&amp;section=watermark');
$tabsheet->add('display', \Phyxo\Functions\Language::l10n('Display'), CONFIGURATION_BASE_URL . '&amp;section=display');
$tabsheet->add('comments', \Phyxo\Functions\Language::l10n('Comments'), CONFIGURATION_BASE_URL . '&amp;section=comments');
$tabsheet->add('default', \Phyxo\Functions\Language::l10n('Guest Settings'), CONFIGURATION_BASE_URL . '&amp;section=default');
$tabsheet->select($section);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => CONFIGURATION_BASE_URL,
]);

$main_checkboxes = [
    'allow_user_registration',
    'obligatory_user_mail_address',
    'rate',
    'rate_anonymous',
    'email_admin_on_new_user',
    'allow_user_customization',
    'log',
    'history_admin',
    'history_guest',
];

$sizes_checkboxes = ['original_resize'];

$comments_checkboxes = [
    'activate_comments',
    'comments_forall',
    'comments_validation',
    'email_admin_on_comment',
    'email_admin_on_comment_validation',
    'user_can_delete_comment',
    'user_can_edit_comment',
    'email_admin_on_comment_edition',
    'email_admin_on_comment_deletion',
    'comments_author_mandatory',
    'comments_email_mandatory',
    'comments_enable_website',
];

$display_checkboxes = [
    'menubar_filter_icon',
    'index_sort_order_input',
    'index_flat_icon',
    'index_posted_date_icon',
    'index_created_date_icon',
    'index_slideshow_icon',
    'index_new_icon',
    'picture_metadata_icon',
    'picture_slideshow_icon',
    'picture_favorite_icon',
    'picture_download_icon',
    'picture_navigation_icons',
    'picture_navigation_thumb',
    'picture_menu',
];

$display_info_checkboxes = [
    'author',
    'created_on',
    'posted_on',
    'dimensions',
    'file',
    'filesize',
    'tags',
    'categories',
    'visits',
    'rating_score',
    'privacy_level',
];

// image order management
$sort_fields = [
    '' => '',
    'file ASC' => \Phyxo\Functions\Language::l10n('File name, A &rarr; Z'),
    'file DESC' => \Phyxo\Functions\Language::l10n('File name, Z &rarr; A'),
    'name ASC' => \Phyxo\Functions\Language::l10n('Photo title, A &rarr; Z'),
    'name DESC' => \Phyxo\Functions\Language::l10n('Photo title, Z &rarr; A'),
    'date_creation DESC' => \Phyxo\Functions\Language::l10n('Date created, new &rarr; old'),
    'date_creation ASC' => \Phyxo\Functions\Language::l10n('Date created, old &rarr; new'),
    'date_available DESC' => \Phyxo\Functions\Language::l10n('Date posted, new &rarr; old'),
    'rating_score DESC' => \Phyxo\Functions\Language::l10n('Rating score, high &rarr; low'),
    'date_available ASC' => \Phyxo\Functions\Language::l10n('Date posted, old &rarr; new'),
    'rating_score ASC' => \Phyxo\Functions\Language::l10n('Rating score, low &rarr; high'),
    'hit DESC' => \Phyxo\Functions\Language::l10n('Visits, high &rarr; low'),
    'hit ASC' => \Phyxo\Functions\Language::l10n('Visits, low &rarr; high'),
    'id ASC' => \Phyxo\Functions\Language::l10n('Numeric identifier, 1 &rarr; 9'),
    'id DESC' => \Phyxo\Functions\Language::l10n('Numeric identifier, 9 &rarr; 1'),
    'rank ASC' => \Phyxo\Functions\Language::l10n('Manual sort order'),
];

$comments_order = [
    'ASC' => \Phyxo\Functions\Language::l10n('Show oldest comments first'),
    'DESC' => \Phyxo\Functions\Language::l10n('Show latest comments first'),
];

$mail_themes = [
    'clear' => 'Clear',
    'dark' => 'Dark'
];

$conf_updated = false;
if (isset($_POST['submit'])) {
    $int_pattern = '/^\d+$/';

    \Phyxo\Functions\Utils::check_token();

    if ($section === 'main') {
        if (isset($_POST['gallery_title']) && $conf['gallery_title'] !== $_POST['gallery_title']) {
            $conf_updated = true;
            if (!$conf['allow_html_descriptions']) {
                $conf['gallery_title'] = strip_tags($_POST['gallery_title']);
            } else {
                $conf['gallery_title'] = $_POST['gallery_title'];
            }
        }

        if (isset($_POST['page_banner']) && $conf['page_banner'] !== $_POST['page_banner']) {
            $conf_updated = true;
            $conf['page_banner'] = $_POST['page_banner'];
        }

        if (isset($_POST['week_starts_on']) && $conf['week_starts_on'] !== $_POST['week_starts_on']) {
            $conf_updated = true;
            $conf['week_starts_on'] = $_POST['week_starts_on'];
        }

        if (empty($conf['order_by_custom']) && empty($conf['order_by_inside_category_custom'])) {
            if (!empty($_POST['order_by'])) {
                $order_by = $_POST['order_by'];
                $used = [];
                foreach ($order_by as $i => $val) {
                    if (empty($val) or isset($used[$val])) {
                        unset($order_by[$i]);
                    } else {
                        $used[$val] = true;
                    }
                }
                if (count($order_by) === 0) {
                    $page['errors'][] = l10n('No order field selected');
                } else {
                    // limit to the number of available parameters
                    $order_by = $order_by_inside_category = array_slice($order_by, 0, ceil(count($sort_fields) / 2));

                    // there is no rank outside categories
                    if (($i = array_search('rank ASC', $order_by)) !== false) {
                        unset($order_by[$i]);
                    }

                    // must define a default order_by if user want to order by rank only
                    if (count($order_by) === 0) {
                        $order_by = ['id ASC'];
                    }

                    $new_order_by_value = 'ORDER BY ' . implode(', ', $order_by);
                    if ($conf['order_by'] !== $new_order_by_value) {
                        $conf_updated = true;
                        $conf['order_by'] = $new_order_by_value;
                    }
                    $new_order_by_value = 'ORDER BY ' . implode(', ', $order_by_inside_category);
                    if ($conf['order_by_inside_category'] !== $new_order_by_value) {
                        $conf_updated = true;
                        $conf['order_by_inside_category'] = $new_order_by_value;
                    }
                }
            } else {
                $page['errors'][] = l10n('No order field selected');
            }
        }

        foreach ($main_checkboxes as $name_checkbox) {
            $new_value = !empty($_POST[$name_checkbox]);

            if ($conf[$name_checkbox] !== $new_value) {
                $conf_updated = true;
                $conf[$name_checkbox] = $new_value;
            }
        }

        if (!empty($_POST['mail_theme']) && isset($mail_themes[$_POST['mail_theme']])) {
            if ($conf['mail_theme'] !== $_POST['mail_theme']) {
                $conf_updated = true;
                $conf['mail_theme'] = $_POST['mail_theme'];
            }
        }
    } elseif ($section === 'sizes') {
        include(__DIR__ . '/include/configuration_sizes_process.inc.php');
    } elseif ($section === 'watermark') {
        include(__DIR__ . '/include/configuration_watermark_process.inc.php');
    } elseif ($section === 'comments') {
        if (empty($_POST['nb_comment_page']) || !preg_match($int_pattern, $_POST['nb_comment_page']) || $_POST['nb_comment_page'] < 5 or $_POST['nb_comment_page'] > 50) {
            $page['errors'][] = \Phyxo\Functions\Language::l10n('The number of comments a page must be between 5 and 50 included.');
        } elseif ($conf['nb_comment_page'] !== $_POST['nb_comment_page']) {
            $conf_updated = true;
            $conf['nb_comment_page'] = $_POST['nb_comment_page'];
        }

        if (!empty($_POST['comments_order']) && isset($comments_order[$_POST['comments_order']])) {
            if ($conf['comments_order'] !== $_POST['comments_order']) {
                $conf_updated = true;
                $conf['comments_order'] = $_POST['comments_order'];
            }
        }

        foreach ($comments_checkboxes as $name_checkbox) {
            $new_value = !empty($_POST[$name_checkbox]);

            if ($conf[$name_checkbox] !== $new_value) {
                $conf_updated = true;
                $conf[$name_checkbox] = $new_value;
            }
        }
    } elseif ($section === 'display') {
        if (empty($_POST['nb_categories_page']) || !preg_match($int_pattern, $_POST['nb_categories_page']) || $_POST['nb_categories_page'] < 4) {
            $page['errors'][] = \Phyxo\Functions\Language::l10n('The number of albums a page must be above 4.');
        } else {
            $conf_updated = true;
            $conf['nb_categories_page'] = $_POST['nb_categories_page'];
        }

        foreach ($display_checkboxes as $name_checkbox) {
            $new_value = !empty($_POST[$name_checkbox]);

            if ($conf[$name_checkbox] !== $new_value) {
                $conf_updated = true;
                $conf[$name_checkbox] = $new_value;
            }
        }

        foreach ($display_info_checkboxes as $name_checkbox) {
            $new_value = !empty($_POST['picture_informations'][$name_checkbox]);

            if ($conf[$name_checkbox] !== $new_value) {
                $conf_updated = true;
                $conf[$name_checkbox] = $new_value;
            }
        }
    }
}
if ($conf_updated) {
    $page['infos'][] = \Phyxo\Functions\Language::l10n('Your configuration settings have been saved');
}

$template->assign(
    [
        'F_ACTION' => $action,
        'PWG_TOKEN' => \Phyxo\Functions\Utils::get_token(),
    ]
);

if ($section === 'main') {
    if (!empty($conf['order_by_custom']) || !empty($conf['order_by_inside_category_custom'])) {
        $order_by = [''];
        $template->assign('ORDER_BY_IS_CUSTOM', true);
    } else {
        $out = [];
        $order_by = trim($conf['order_by_inside_category']);
        $order_by = str_replace('ORDER BY ', null, $order_by);
        $order_by = explode(', ', $order_by);
    }

    $template->assign(
        'main',
        [
            'CONF_GALLERY_TITLE' => htmlspecialchars($conf['gallery_title']),
            'CONF_PAGE_BANNER' => htmlspecialchars($conf['page_banner']),
            'week_starts_on_options' => [
                'sunday' => $lang['day'][0],
                'monday' => $lang['day'][1],
            ],
            'week_starts_on_options_selected' => $conf['week_starts_on'],
            'mail_theme' => $conf['mail_theme'],
            'mail_theme_options' => $mail_themes,
            'order_by' => $order_by,
            'order_by_options' => $sort_fields,
        ]
    );
    foreach ($main_checkboxes as $name_checkbox) {
        $template->append('main', [$name_checkbox => $conf[$name_checkbox]], true);
    }
} elseif ($section === 'sizes') {
    // we only load the derivatives if it was not already loaded: it occurs
    // when submitting the form and an error remains
    if (!isset($page['sizes_loaded_in_tpl'])) {
        $is_gd = (\Phyxo\Image\Image::get_library() === 'GD') ? true : false;
        $template->assign('is_gd', $is_gd);
        $template->assign(
            'sizes',
            [
                'original_resize_maxwidth' => $conf['original_resize_maxwidth'],
                'original_resize_maxheight' => $conf['original_resize_maxheight'],
                'original_resize_quality' => $conf['original_resize_quality'],
            ]
        );

        foreach ($sizes_checkboxes as $checkbox) {
            $template->append(
                'sizes',
                [
                    $checkbox => $conf[$checkbox]
                ],
                true
            );
        }

        // derivatives = multiple size
        $enabled = \Phyxo\Image\ImageStdParams::get_defined_type_map();
        if (!empty($conf['disabled_derivatives'])) {
            $disabled = unserialize($conf['disabled_derivatives']);
        } else {
            $disabled = [];
        }

        $tpl_vars = [];
        foreach (\Phyxo\Image\ImageStdParams::get_all_types() as $type) {
            $tpl_var = [];

            $tpl_var['must_square'] = ($type == IMG_SQUARE ? true : false);
            $tpl_var['must_enable'] = ($type == IMG_SQUARE || $type == IMG_THUMB || $type == $conf['derivative_default_size']) ? true : false;

            if (!empty($enabled[$type]) && ($params = $enabled[$type])) {
                $tpl_var['enabled'] = true;
            } else {
                $tpl_var['enabled'] = false;
                $params = $disabled[$type];
            }

            if ($params) {
                list($tpl_var['w'], $tpl_var['h']) = $params->sizing->ideal_size;
                if (($tpl_var['crop'] = round(100 * $params->sizing->max_crop)) > 0) {
                    list($tpl_var['minw'], $tpl_var['minh']) = $params->sizing->min_size;
                } else {
                    $tpl_var['minw'] = $tpl_var['minh'] = "";
                }
                $tpl_var['sharpen'] = $params->sharpen;
            }
            $tpl_vars[$type] = $tpl_var;
        }
        $template->assign('derivatives', $tpl_vars);
        $template->assign('resize_quality', \Phyxo\Image\ImageStdParams::$quality);

        $tpl_vars = [];
        $now = time();
        foreach (\Phyxo\Image\ImageStdParams::$custom as $custom => $time) {
            $tpl_vars[$custom] = ($now - $time <= 24 * 3600) ? \Phyxo\Functions\Language::l10n('today') : \Phyxo\Functions\DateTime::time_since($time, 'day');
        }
        $template->assign('custom_derivatives', $tpl_vars);
    }
} elseif ($section === 'watermark') {
    $watermark_files = [];
    foreach (glob(PHPWG_ROOT_PATH . 'themes/default/watermarks/*.png') as $file) {
        $watermark_files[] = substr($file, strlen(PHPWG_ROOT_PATH));
    }
    if (($glob = glob(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'watermarks/*.png')) !== false) {
        foreach ($glob as $file) {
            $watermark_files[] = substr($file, strlen(PHPWG_ROOT_PATH));
        }
    }
    $watermark_filemap = ['' => '---'];
    foreach ($watermark_files as $file) {
        $display = basename($file);
        $watermark_filemap[$file] = $display;
    }
    $template->assign('watermark_files', $watermark_filemap);

    if ($template->get_template_vars('watermark') === null) {
        $wm = \Phyxo\Image\ImageStdParams::get_watermark();

        $position = 'custom';
        if ($wm->xpos == 0 and $wm->ypos == 0) {
            $position = 'topleft';
        }
        if ($wm->xpos == 100 and $wm->ypos == 0) {
            $position = 'topright';
        }
        if ($wm->xpos == 50 and $wm->ypos == 50) {
            $position = 'middle';
        }
        if ($wm->xpos == 0 and $wm->ypos == 100) {
            $position = 'bottomleft';
        }
        if ($wm->xpos == 100 and $wm->ypos == 100) {
            $position = 'bottomright';
        }

        if ($wm->xrepeat != 0) {
            $position = 'custom';
        }

        $template->assign(
            'watermark',
            [
                'file' => $wm->file,
                'minw' => $wm->min_size[0],
                'minh' => $wm->min_size[1],
                'xpos' => $wm->xpos,
                'ypos' => $wm->ypos,
                'xrepeat' => $wm->xrepeat,
                'opacity' => $wm->opacity,
                'position' => $position,
            ]
        );
    }
} elseif ($section === 'comments') {
    $template->assign(
        'comments',
        [
            'NB_COMMENTS_PAGE' => $conf['nb_comment_page'],
            'comments_order' => $conf['comments_order'],
            'comments_order_options' => $comments_order
        ]
    );

    foreach ($comments_checkboxes as $checkbox) {
        $template->append('comments', [$checkbox => $conf[$checkbox]], true);
    }
} elseif ($section === 'display') {
    foreach ($display_checkboxes as $checkbox) {
        $template->append('display', [$checkbox => $conf[$checkbox]], true);
    }
    $template->append(
        'display',
        [
            'picture_informations' => json_decode($conf['picture_informations'], true),
            'NB_CATEGORIES_PAGE' => $conf['nb_categories_page'],
        ],
        true
    );
} elseif ($section === 'default') {
    $edit_user = $services['users']->buildUser($conf['guest_id'], false);

    $errors = [];
    if (\Phyxo\Functions\Utils::save_profile_from_post($edit_user, $errors)) {
        // Reload user
        $edit_user = $services['users']->buildUser($conf['guest_id'], false);
        $page['infos'][] = \Phyxo\Functions\Language::l10n('Your configuration settings have been saved');
    }
    $page['errors'] = array_merge($page['errors'], $errors);

    \Phyxo\Functions\Utils::load_profile_in_template($action, '', $edit_user, 'GUEST_');
    $template->assign('default', []);
}

$template_filename = 'configuration_' . $section;
