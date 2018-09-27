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

// customize appearance of the site for a user
// +-----------------------------------------------------------------------+
// |                           initialization                              |
// +-----------------------------------------------------------------------+

if (!defined('PHPWG_ROOT_PATH')) { //direct script access
    define('PHPWG_ROOT_PATH', '../../');
    include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');
}

use App\Repository\LanguageRepository;
use App\Repository\ThemeRepository;

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_CLASSIC);

if (!empty($_POST)) {
    \Phyxo\Functions\Utils::check_token();
}

$userdata = $user;

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_profile');

// Reset to default (Guest) custom settings
if (isset($_POST['reset_to_default'])) {
    $fields = [
        'nb_image_page', 'expand',
        'show_nb_comments', 'show_nb_hits',
        'recent_period', 'show_nb_hits'
    ];

    // Get the Guest custom settings
    $query = 'SELECT ' . implode(',', $fields) . ' FROM ' . USER_INFOS_TABLE;
    $query .= ' WHERE user_id = ' . $conf['default_user_id'] . ';';
    $result = $conn->db_query($query);
    $default_user = $conn->db_fetch_assoc($result);
    $userdata = array_merge($userdata, $default_user);
}

save_profile_from_post($userdata, $page['errors']);

$title = \Phyxo\Functions\Language::l10n('Your Gallery Customization');
load_profile_in_template(
    \Phyxo\Functions\URL::get_root_url() . 'profile.php', // action
    \Phyxo\Functions\URL::get_root_url(), // for redirect
    $userdata
);

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (!isset($themeconf['hide_menu_on']) or !in_array('theProfilePage', $themeconf['hide_menu_on'])) {
    include(PHPWG_ROOT_PATH . 'include/menubar.inc.php');
}

\Phyxo\Functions\Plugin::trigger_notify('loc_end_profile');
\Phyxo\Functions\Utils::flush_page_messages();

//------------------------------------------------------ update & customization
function save_profile_from_post($userdata, &$errors)
{
    global $conf, $page, $conn, $services, $conn;

    $errors = [];

    if (!isset($_POST['validate'])) {
        return false;
    }

    $languages = $conn->result2array((new LanguageRepository($conn))->findAll(), 'id', 'name');
    $themes = $conn->result2array((new ThemeRepository($conn))->findAll(), 'id', 'name');

    $special_user = in_array($userdata['id'], [$conf['guest_id'], $conf['default_user_id']]);
    if ($special_user) {
        unset($_POST['username'], $_POST['mail_address'],
            $_POST['password'], $_POST['use_new_pwd'],
            $_POST['passwordConf'], $_POST['theme'],
            $_POST['language']);
        $_POST['theme'] = $services['users']->getDefaultTheme();
        $_POST['language'] = $services['users']->getDefaultLanguage();
    }

    if (!defined('IN_ADMIN')) {
        unset($_POST['username']);
    }

    if ($conf['allow_user_customization'] or defined('IN_ADMIN')) {
        $int_pattern = '/^\d+$/';
        if (empty($_POST['nb_image_page']) || (!preg_match($int_pattern, $_POST['nb_image_page']))) {
            $errors[] = \Phyxo\Functions\Language::l10n('The number of photos per page must be a not null scalar');
        }

        // periods must be integer values, they represents number of days
        if (!preg_match($int_pattern, $_POST['recent_period']) or $_POST['recent_period'] < 0) {
            $errors[] = \Phyxo\Functions\Language::l10n('Recent period must be a positive integer value');
        }

        if (isset($_POST['language']) && !isset($languages[$_POST['language']])) {
            die('Hacking attempt, incorrect language value');
        }

        if (isset($_POST['theme']) && !isset($themes[$_POST['theme']])) {
            die('Hacking attempt, incorrect theme value');
        }
    }

    if (isset($_POST['mail_address'])) {
        // if $_POST and $userdata have are same email
        // validateMailAddress allows, however, to check email
        $mail_error = $services['users']->validateMailAddress($userdata['id'], $_POST['mail_address']);
        if (!empty($mail_error)) {
            $errors[] = $mail_error;
        }
    }

    if (!empty($_POST['use_new_pwd'])) {
        // password must be the same as its confirmation
        if ($_POST['use_new_pwd'] != $_POST['passwordConf']) {
            $errors[] = \Phyxo\Functions\Language::l10n('The passwords do not match');
        }

        if (!defined('IN_ADMIN')) { // changing password requires old password
            $query = 'SELECT ' . $conf['user_fields']['password'] . ' AS password FROM ' . USERS_TABLE;
            $query .= ' WHERE ' . $conf['user_fields']['id'] . ' = \'' . $conn->db_real_escape_string($userdata['id']) . '\'';
            list($current_password) = $conn->db_fetch_row($conn->db_query($query));

            if (!$services['users']->passwordVerify($_POST['password'], $current_password)) {
                $errors[] = \Phyxo\Functions\Language::l10n('Current password is wrong');
            }
        }
    }

    if (count($errors) == 0) {
        // mass_updates function
        if (isset($_POST['mail_address'])) {
            // update common user informations
            $fields = [$conf['user_fields']['email']];

            $data = [];
            $data[$conf['user_fields']['id']] = $userdata['id'];
            $data[$conf['user_fields']['email']] = $_POST['mail_address'];

            // password is updated only if filled
            if (!empty($_POST['use_new_pwd'])) {
                $fields[] = $conf['user_fields']['password'];
                $data[$conf['user_fields']['password']] = $services['users']->passwordHash($_POST['use_new_pwd']);
            }

            // username is updated only if allowed
            if (!empty($_POST['username'])) {
                if ($_POST['username'] != $userdata['username'] and $services['users']->getUserId($_POST['username'])) {
                    $page['errors'][] = \Phyxo\Functions\Language::l10n('this login is already used');
                    unset($_POST['redirect']);
                } else {
                    $fields[] = $conf['user_fields']['username'];
                    $data[$conf['user_fields']['username']] = $_POST['username'];

                    // send email to the user
                    if ($_POST['username'] != $userdata['username']) {
                        \Phyxo\Functions\Mail::switch_lang_to($userdata['language']);

                        $keyargs_content = [
                            \Phyxo\Functions\Language::get_l10n_args('Hello', ''),
                            \Phyxo\Functions\Language::get_l10n_args('Your username has been successfully changed to : %s', $_POST['username']),
                        ];

                        \Phyxo\Functions\Mail::mail(
                            $_POST['mail_address'],
                            [
                                'subject' => '[' . $conf['gallery_title'] . '] ' . \Phyxo\Functions\Language::l10n('Username modification'),
                                'content' => \Phyxo\Functions\Language::l10n_args($keyargs_content),
                                'content_format' => 'text/plain',
                            ]
                        );

                        \Phyxo\Functions\Mail::switch_lang_back();
                    }
                }
            }

            $conn->mass_updates(
                USERS_TABLE,
                [
                    'primary' => [$conf['user_fields']['id']],
                    'update' => $fields
                ],
                [$data]
            );
        }

        if ($conf['allow_user_customization'] or defined('IN_ADMIN')) {
            // update user "additional" informations
            $fields = [
                'nb_image_page', 'language',
                'expand', 'show_nb_hits',
                'recent_period', 'theme'
            ];

            if ($conf['activate_comments']) {
                $fields[] = 'show_nb_comments';
            }

            $data = [];
            $data['user_id'] = $userdata['id'];

            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    $data[$field] = $_POST[$field];
                }
            }
            $conn->mass_updates(
                USER_INFOS_TABLE,
                ['primary' => ['user_id'], 'update' => $fields],
                [$data]
            );
        }
        \Phyxo\Functions\Plugin::trigger_notify('save_profile_from_post', $userdata['id']);

        if (!empty($_POST['redirect'])) {
            \Phyxo\Functions\Utils::redirect($_POST['redirect']);
        }
    }

    return true;
}

/**
 * Assign template variables, from arguments
 * Used to build profile edition pages
 *
 * @param string $url_action
 * @param string $url_redirect
 * @param array $userdata
 */
function load_profile_in_template($url_action, $url_redirect, $userdata, $template_prefixe = null)
{
    global $template, $conf, $conn;

    $languages = $conn->result2array((new LanguageRepository($conn))->findAll(), 'id', 'name');
    $themes = $conn->result2array((new ThemeRepository($conn))->findAll(), 'id', 'name');

    $template->assign(
        'radio_options',
        [
            'true' => \Phyxo\Functions\Language::l10n('Yes'),
            'false' => \Phyxo\Functions\Language::l10n('No')
        ]
    );

    $template->assign(
        [
            $template_prefixe . 'USERNAME' => stripslashes($userdata['username']),
            $template_prefixe . 'EMAIL' => @$userdata['email'],
            $template_prefixe . 'ALLOW_USER_CUSTOMIZATION' => $conf['allow_user_customization'],
            $template_prefixe . 'ACTIVATE_COMMENTS' => $conf['activate_comments'],
            $template_prefixe . 'NB_IMAGE_PAGE' => $userdata['nb_image_page'],
            $template_prefixe . 'RECENT_PERIOD' => $userdata['recent_period'],
            $template_prefixe . 'EXPAND' => $userdata['expand'] ? 'true' : 'false',
            $template_prefixe . 'NB_COMMENTS' => $userdata['show_nb_comments'] ? 'true' : 'false',
            $template_prefixe . 'NB_HITS' => $userdata['show_nb_hits'] ? 'true' : 'false',
            $template_prefixe . 'REDIRECT' => $url_redirect,
            $template_prefixe . 'F_ACTION' => $url_action,
        ]
    );

    $template->assign('template_selection', $userdata['theme']);
    $template->assign('template_options', $themes);

    if (isset($languages[$userdata['language']])) {
        $template->assign('language_selection', $userdata['language']);
    }

    $template->assign('language_options', $languages);

    $special_user = in_array($userdata['id'], [$conf['guest_id'], $conf['default_user_id']]);
    $template->assign('SPECIAL_USER', $special_user);
    $template->assign('IN_ADMIN', defined('IN_ADMIN'));

    // allow plugins to add their own form data to content
    \Phyxo\Functions\Plugin::trigger_notify('load_profile_in_template', $userdata);

    $template->assign('PWG_TOKEN', \Phyxo\Functions\Utils::get_token());
}
