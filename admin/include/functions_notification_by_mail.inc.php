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

/* nbm_global_var */
$env_nbm = array(
    'start_time' => microtime(true),
    'sendmail_timeout' => (intval(ini_get('max_execution_time')) * $conf['nbm_max_treatment_timeout_percent']),
    'is_sendmail_timeout' => false
);

if ((!isset($env_nbm['sendmail_timeout'])) or (!is_numeric($env_nbm['sendmail_timeout'])) or ($env_nbm['sendmail_timeout'] <= 0)) {
    $env_nbm['sendmail_timeout'] = $conf['nbm_treatment_timeout_default'];
}

/*
 * Search an available check_key
 *
 * It's a copy of function find_available_feed_id
 *
 * @return string nbm identifier
 */
function find_available_check_key()
{
    global $conn;

    while (true) {
        $key = generate_key(16);
        $query = 'SELECT count(1) FROM ' . USER_MAIL_NOTIFICATION_TABLE;
        $query .= ' WHERE check_key = \'' . $key . '\';';

        list($count) = $conn->db_fetch_row($conn->db_query($query));
        if ($count == 0) {
            return $key;
        }
    }
}

/*
 * Check sendmail timeout state
 *
 * @return true, if it's timeout
 */
function check_sendmail_timeout()
{
    global $env_nbm;

    $env_nbm['is_sendmail_timeout'] = ((microtime(true) - $env_nbm['start_time']) > $env_nbm['sendmail_timeout']);

    return $env_nbm['is_sendmail_timeout'];
}


/*
 * Add quote to all elements of check_key_list
 *
 * @return quoted check key list
 */
function quote_check_key_list($check_key_list = array())
{
    return array_map(function ($s) {
        return '\'' . $s . '\'';
    }, $check_key_list);
}

/*
 * Execute all main queries to get list of user
 *
 * Type are the type of list 'subscribe', 'send'
 *
 * return array of users
 */
function get_user_notifications($action, $check_key_list = array(), $enabled_filter_value = false)
{
    global $conf, $conn;

    $data_users = array();

    if (in_array($action, array('subscribe', 'send'))) {
        $quoted_check_key_list = quote_check_key_list($check_key_list);
        if (count($check_key_list) > 0) {
            $query_and_check_key = ' AND check_key ' . $conn->in($check_key_list);
        } else {
            $query_and_check_key = '';
        }

        $query = 'SELECT N.user_id,N.check_key,U.' . $conf['user_fields']['username'] . ' as username,';
        $query .= 'U.' . $conf['user_fields']['email'] . ' AS mail_address,N.enabled,N.last_send';
        $query .= ' FROM ' . USER_MAIL_NOTIFICATION_TABLE . ' AS N,' . USERS_TABLE . ' AS U';
        $query .= ' WHERE  N.user_id =  U.' . $conf['user_fields']['id'];


        if ($action == 'send') {
            // No mail empty and all users enabled
            $query .= ' AND N.enabled = \'' . $conn->boolean_to_db(true) . '\' AND U.' . $conf['user_fields']['email'] . ' is not null';
        }

        $query .= $query_and_check_key;

        if (!empty($enabled_filter_value)) {
            $query .= ' AND N.enabled = \'' . $conn->boolean_to_db($enabled_filter_value) . '\'';
        }

        $query .= ' ORDER BY';

        if ($action == 'send') {
            $query .= ' last_send, username;';
        } else {
            $query .= ' username;';
        }

        $result = $conn->db_query($query);
        if (!empty($result)) {
            while ($nbm_user = $conn->db_fetch_assoc($result)) {
                $data_users[] = $nbm_user;
            }
        }
    }

    return $data_users;
}

/*
 * Begin of use nbm environment
 * Prepare and save current environment and initialize data in order to send mail
 *
 * Return none
 */
function begin_users_env_nbm($is_to_send_mail = false)
{
    global $user, $lang, $lang_info, $conf, $env_nbm;

    // Save $user, $lang_info and $lang arrays (include/user.inc.php has been executed)
    $env_nbm['save_user'] = $user;
    // Save current language to stack, necessary because $user change during NBM
    \Phyxo\Functions\Mail::switch_lang_to($user['language']);

    $env_nbm['is_to_send_mail'] = $is_to_send_mail;

    if ($is_to_send_mail) {
        // Init mail configuration
        $env_nbm['email_format'] = \Phyxo\Functions\Mail::get_str_email_format($conf['nbm_send_html_mail']);
        $env_nbm['send_as_name'] = !empty($conf['nbm_send_mail_as']) ? $conf['nbm_send_mail_as'] : \Phyxo\Functions\Mail::get_mail_sender_name();
        $env_nbm['send_as_mail_address'] = \Phyxo\Functions\Utils::get_webmaster_mail_address();
        $env_nbm['send_as_mail_formated'] = \Phyxo\Functions\Mail::format_email($env_nbm['send_as_name'], $env_nbm['send_as_mail_address']);
        // Init mail counter
        $env_nbm['error_on_mail_count'] = 0;
        $env_nbm['sent_mail_count'] = 0;
        // Save sendmail message info and error in the original language
        $env_nbm['msg_info'] = \Phyxo\Functions\Language::l10n('Mail sent to %s [%s].');
        $env_nbm['msg_error'] = \Phyxo\Functions\Language::l10n('Error when sending email to %s [%s].');
    }
}

/*
 * End of use nbm environment
 * Restore environment
 *
 * Return none
 */
function end_users_env_nbm()
{
    global $user, $lang, $lang_info, $env_nbm;

    // Restore $user, $lang_info and $lang arrays (include/user.inc.php has been executed)
    $user = $env_nbm['save_user'];
    // Restore current language to stack, necessary because $user change during NBM
    \Phyxo\Functions\Mail::switch_lang_back();

    if ($env_nbm['is_to_send_mail']) {
        unset($env_nbm['email_format']);
        unset($env_nbm['send_as_name']);
        unset($env_nbm['send_as_mail_address']);
        unset($env_nbm['send_as_mail_formated']);
        // Don t unset counter
        //unset($env_nbm['error_on_mail_count']);
        //unset($env_nbm['sent_mail_count']);
        unset($env_nbm['msg_info']);
        unset($env_nbm['msg_error']);
    }

    unset($env_nbm['save_user']);
    unset($env_nbm['is_to_send_mail']);
}

/*
 * Set user on nbm enviromnent
 *
 * Return none
 */
function set_user_on_env_nbm(&$nbm_user, $is_action_send)
{
    global $user, $lang, $lang_info, $env_nbm, $services;

    $user = $services['users']->buildUser($nbm_user['user_id'], true);

    \Phyxo\Functions\Mail::switch_lang_to($user['language']);

    if ($is_action_send) {
        $env_nbm['mail_template'] = \Phyxo\Functions\Mail::get_mail_template($env_nbm['email_format']);
        $env_nbm['mail_template']->set_filename('notification_by_mail', 'notification_by_mail.tpl');
    }
}

/*
 * Unset user on nbm enviromnent
 *
 * Return none
 */
function unset_user_on_env_nbm()
{
    global $env_nbm;

    \Phyxo\Functions\Mail::switch_lang_back();
    unset($env_nbm['mail_template']);
}

/*
 * Inc Counter success
 *
 * Return none
 */
function inc_mail_sent_success($nbm_user)
{
    global $page, $env_nbm;

    $env_nbm['sent_mail_count'] += 1;
    $page['infos'][] = sprintf($env_nbm['msg_info'], stripslashes($nbm_user['username']), $nbm_user['mail_address']);
}

/*
 * Inc Counter failed
 *
 * Return none
 */
function inc_mail_sent_failed($nbm_user)
{
    global $page, $env_nbm;

    $env_nbm['error_on_mail_count'] += 1;
    $page['errors'][] = sprintf($env_nbm['msg_error'], stripslashes($nbm_user['username']), $nbm_user['mail_address']);
}

/*
 * Display Counter Info
 *
 * Return none
 */
function display_counter_info()
{
    global $page, $env_nbm;

    if ($env_nbm['error_on_mail_count'] != 0) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n_dec(
            '%d mail was not sent.',
            '%d mails were not sent.',
            $env_nbm['error_on_mail_count']
        );

        if ($env_nbm['sent_mail_count'] != 0) {
            $page['infos'][] = \Phyxo\Functions\Language::l10n_dec(
                '%d mail was sent.',
                '%d mails were sent.',
                $env_nbm['sent_mail_count']
            );
        }
    } else {
        if ($env_nbm['sent_mail_count'] == 0) {
            $page['infos'][] = \Phyxo\Functions\Language::l10n('No mail to send.');
        } else {
            $page['infos'][] = \Phyxo\Functions\Language::l10n_dec(
                '%d mail was sent.',
                '%d mails were sent.',
                $env_nbm['sent_mail_count']
            );
        }
    }
}

function assign_vars_nbm_mail_content($nbm_user)
{
    global $env_nbm;

    \Phyxo\Functions\URL::set_make_full_url();

    $env_nbm['mail_template']->assign(
        array(
            'USERNAME' => stripslashes($nbm_user['username']),
            'SEND_AS_NAME' => $env_nbm['send_as_name'],
            'UNSUBSCRIBE_LINK' => \Phyxo\Functions\URL::add_url_params(\Phyxo\Functions\URL::get_gallery_home_url() . '/nbm.php', array('unsubscribe' => $nbm_user['check_key'])),
            'SUBSCRIBE_LINK' => \Phyxo\Functions\URL::add_url_params(\Phyxo\Functions\URL::get_gallery_home_url() . '/nbm.php', array('subscribe' => $nbm_user['check_key'])),
            'CONTACT_EMAIL' => $env_nbm['send_as_mail_address']
        )
    );

    \Phyxo\Functions\URL::unset_make_full_url();
}

/*
 * Subscribe or unsubscribe notification by mail
 *
 * is_subscribe define if action=subscribe or unsubscribe
 * check_key list where action will be done
 *
 * @return check_key list treated
 */
function do_subscribe_unsubscribe_notification_by_mail($is_admin_request, $is_subscribe = false, $check_key_list = array())
{
    global $conf, $page, $env_nbm, $conf, $conn;

    \Phyxo\Functions\URL::set_make_full_url();

    $check_key_treated = array();
    $updated_data_count = 0;
    $error_on_updated_data_count = 0;

    if ($is_subscribe) {
        $msg_info = \Phyxo\Functions\Language::l10n('User %s [%s] was added to the subscription list.');
        $msg_error = \Phyxo\Functions\Language::l10n('User %s [%s] was not added to the subscription list.');
    } else {
        $msg_info = \Phyxo\Functions\Language::l10n('User %s [%s] was removed from the subscription list.');
        $msg_error = \Phyxo\Functions\Language::l10n('User %s [%s] was not removed from the subscription list.');
    }

    if (count($check_key_list) != 0) {
        $updates = array();
        $enabled_value = $conn->boolean_to_db($is_subscribe);
        $data_users = get_user_notifications('subscribe', $check_key_list, !$is_subscribe);

        // Prepare message after change language
        $msg_break_timeout = \Phyxo\Functions\Language::l10n('Time to send mail is limited. Others mails are skipped.');

        // Begin nbm users environment
        begin_users_env_nbm(true);

        foreach ($data_users as $nbm_user) {
            if (check_sendmail_timeout()) {
                // Stop fill list on 'send', if the quota is override
                $page['errors'][] = $msg_break_timeout;
                break;
            }

            // Fill return list
            $check_key_treated[] = $nbm_user['check_key'];

            $do_update = true;
            if ($nbm_user['mail_address'] != '') {
                // set env nbm user
                set_user_on_env_nbm($nbm_user, true);

                $subject = '[' . $conf['gallery_title'] . '] ' . ($is_subscribe ? \Phyxo\Functions\Language::l10n('Subscribe to notification by mail') : \Phyxo\Functions\Language::l10n('Unsubscribe from notification by mail'));

                // Assign current var for nbm mail
                assign_vars_nbm_mail_content($nbm_user);

                $section_action_by = ($is_subscribe ? 'subscribe_by_' : 'unsubscribe_by_');
                $section_action_by .= ($is_admin_request ? 'admin' : 'himself');
                $env_nbm['mail_template']->assign(
                    array(
                        $section_action_by => true,
                        'GOTO_GALLERY_TITLE' => $conf['gallery_title'],
                        'GOTO_GALLERY_URL' => \Phyxo\Functions\URL::get_gallery_home_url(),
                    )
                );

                $ret = \Phyxo\Functions\Mail::mail(
                    array(
                        'name' => stripslashes($nbm_user['username']),
                        'email' => $nbm_user['mail_address'],
                    ),
                    array(
                        'from' => $env_nbm['send_as_mail_formated'],
                        'subject' => $subject,
                        'email_format' => $env_nbm['email_format'],
                        'content' => $env_nbm['mail_template']->parse('notification_by_mail', true),
                        'content_format' => $env_nbm['email_format'],
                    )
                );

                if ($ret) {
                    inc_mail_sent_success($nbm_user);
                } else {
                    inc_mail_sent_failed($nbm_user);
                    $do_update = false;
                }

                // unset env nbm user
                unset_user_on_env_nbm();
            }

            if ($do_update) {
                $updates[] = array(
                    'check_key' => $nbm_user['check_key'],
                    'enabled' => $enabled_value
                );
                $updated_data_count += 1;
                $page['infos'][] = sprintf($msg_info, stripslashes($nbm_user['username']), $nbm_user['mail_address']);
            } else {
                $error_on_updated_data_count += 1;
                $page['errors'][] = sprintf($msg_error, stripslashes($nbm_user['username']), $nbm_user['mail_address']);
            }
        }

        // Restore nbm environment
        end_users_env_nbm();

        display_counter_info();

        $conn->mass_updates(
            USER_MAIL_NOTIFICATION_TABLE,
            array(
                'primary' => array('check_key'),
                'update' => array('enabled')
            ),
            $updates
        );
    }

    if ($updated_data_count > 0) {
        $page['infos'][] = \Phyxo\Functions\Language::l10n_dec(
            '%d user was updated.',
            '%d users were updated.',
            $updated_data_count
        );
    }

    if ($error_on_updated_data_count != 0) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n_dec(
            '%d user was not updated.',
            '%d users were not updated.',
            $error_on_updated_data_count
        );
    }

    \Phyxo\Functions\URL::unset_make_full_url();

    return $check_key_treated;
}

/*
 * Unsubscribe notification by mail
 *
 * check_key list where action will be done
 *
 * @return check_key list treated
 */
function unsubscribe_notification_by_mail($is_admin_request, $check_key_list = array())
{
    return do_subscribe_unsubscribe_notification_by_mail($is_admin_request, false, $check_key_list);
}

/*
 * Subscribe notification by mail
 *
 * check_key list where action will be done
 *
 * @return check_key list treated
 */
function subscribe_notification_by_mail($is_admin_request, $check_key_list = array())
{
    return do_subscribe_unsubscribe_notification_by_mail($is_admin_request, true, $check_key_list);
}


/*
 * Do timeout treatment in order to finish to send mails
 *
 * @param $post_keyname: key of check_key post array
 * @param check_key_treated: array of check_key treated
 * @return none
 */
function do_timeout_treatment($post_keyname, $check_key_treated = array())
{
    global $env_nbm, $base_url, $page, $must_repost;

    if ($env_nbm['is_sendmail_timeout']) {
        if (isset($_POST[$post_keyname])) {
            $post_count = count($_POST[$post_keyname]);
            $treated_count = count($check_key_treated);
            if ($treated_count != 0) {
                $time_refresh = ceil((microtime(true) - $env_nbm['start_time']) * $post_count / $treated_count);
            } else {
                $time_refresh = 0;
            }
            $_POST[$post_keyname] = array_diff($_POST[$post_keyname], $check_key_treated);

            $must_repost = true;
            $page['errors'][] = \Phyxo\Functions\Language::l10n_dec(
                'Execution time is out, treatment must be continue [Estimated time: %d second].',
                'Execution time is out, treatment must be continue [Estimated time: %d seconds].',
                $time_refresh
            );
        }
    }
}

/*
 * Get the authorized_status for each tab
 * return corresponding status
 */
function get_tab_status($mode)
{
    $result = ACCESS_WEBMASTER;
    switch ($mode) {
        case 'param':
        case 'subscribe':
            $result = ACCESS_WEBMASTER;
            break;
        case 'send':
            $result = ACCESS_ADMINISTRATOR;
            break;
        default:
            $result = ACCESS_WEBMASTER;
            break;
    }
    return $result;
}

/*
 * Inserting News users
 */
function insert_new_data_user_mail_notification()
{
    global $conf, $page, $env_nbm, $conn;

    // Set null mail_address empty
    $query = 'UPDATE ' . USERS_TABLE;
    $query .= ' SET ' . $conf['user_fields']['email'] . ' = null';
    $query .= ' WHERE trim(' . $conf['user_fields']['email'] . ') = \'\';'; // @TODO: simplify
    $conn->db_query($query);

    // null mail_address are not selected in the list
    $query = 'SELECT u.' . $conf['user_fields']['id'] . ' AS user_id,';
    $query .= ' u.' . $conf['user_fields']['username'] . ' AS username,';
    $query .= ' u.' . $conf['user_fields']['email'] . ' AS mail_address FROM ' . USERS_TABLE . ' AS u';
    $query .= ' LEFT JOIN ' . USER_MAIL_NOTIFICATION_TABLE . ' AS m ON u.' . $conf['user_fields']['id'] . ' = m.user_id';
    $query .= ' WHERE u.' . $conf['user_fields']['email'] . ' is not null';
    $query .= ' AND m.user_id is null';
    $query .= ' ORDER BY user_id;';

    $result = $conn->db_query($query);
    if ($conn->db_num_rows($result) > 0) {
        $inserts = array();
        $check_key_list = array();

        while ($nbm_user = $conn->db_fetch_assoc($result)) {
            // Calculate key
            $nbm_user['check_key'] = find_available_check_key();

            // Save key
            $check_key_list[] = $nbm_user['check_key'];

            // Insert new nbm_users
            $inserts[] = array(
                'user_id' => $nbm_user['user_id'],
                'check_key' => $nbm_user['check_key'],
                'enabled' => 'false' // By default if false, set to true with specific functions
            );

            $page['infos'][] = \Phyxo\Functions\Language::l10n(
                'User %s [%s] added.',
                stripslashes($nbm_user['username']),
                $nbm_user['mail_address']
            );
        }

        // Insert new nbm_users
        $conn->mass_inserts(USER_MAIL_NOTIFICATION_TABLE, array('user_id', 'check_key', 'enabled'), $inserts);
        // Update field enabled with specific function
        $check_key_treated = do_subscribe_unsubscribe_notification_by_mail(
            true,
            $conf['nbm_default_value_user_enabled'],
            $check_key_list
        );

        // On timeout simulate like tabsheet send
        if ($env_nbm['is_sendmail_timeout']) {
            $quoted_check_key_list = quote_check_key_list(array_diff($check_key_list, $check_key_treated));
            if (count($check_key_list) > 0) {
                $query = 'DELETE FROM ' . USER_MAIL_NOTIFICATION_TABLE;
                $query .= ' WHERE check_key ' . $conn->in($check_key_list);
                $result = $conn->db_query($query);

                \Phyxo\Functions\Utils::redirect($base_url . \Phyxo\Functions\URL::get_query_string_diff(array(), false), \Phyxo\Functions\Language::l10n('Operation in progress') . "\n" . \Phyxo\Functions\Language::l10n('Please wait...'));
            }
        }
    }
}

/*
 * Apply global functions to mail content
 * return customize mail content rendered
 */
function render_global_customize_mail_content($customize_mail_content)
{
    global $conf;

    // @TODO : find a better way to detect html or remove test
    if ($conf['nbm_send_html_mail'] and !(strpos($customize_mail_content, '<') === 0)) {
        // On HTML mail, detects if the content are HTML format.
        // If it's plain text format, convert content to readable HTML
        return nl2br(htmlspecialchars($customize_mail_content));
    } else {
        return $customize_mail_content;
    }
}

/*
 * Send mail for notification to all users
 * Return list of "selected" users for 'list_to_send'
 * Return list of "treated" check_key for 'send'
 */
function do_action_send_mail_notification($action = 'list_to_send', $check_key_list = array(), $customize_mail_content = '')
{
    global $conf, $page, $user, $lang_info, $lang, $env_nbm, $conn;

    $return_list = array();

    if (in_array($action, array('list_to_send', 'send'))) {
        list($dbnow) = $conn->db_fetch_row($conn->db_query('SELECT NOW();'));

        $is_action_send = ($action == 'send');

        // disabled and null mail_address are not selected in the list
        $data_users = get_user_notifications('send', $check_key_list);

        // List all if it's define on options or on timeout
        $is_list_all_without_test = ($env_nbm['is_sendmail_timeout'] or $conf['nbm_list_all_enabled_users_to_send']);

        // Check if exist news to list user or send mails
        if ((!$is_list_all_without_test) or ($is_action_send)) {
            if (count($data_users) > 0) {
                $datas = array();

                if (!isset($customize_mail_content)) {
                    $customize_mail_content = $conf['nbm_complementary_mail_content'];
                }

                $customize_mail_content = \Phyxo\Functions\Plugin::trigger_change('nbm_render_global_customize_mail_content', $customize_mail_content);

                // Prepare message after change language
                if ($is_action_send) {
                    $msg_break_timeout = \Phyxo\Functions\Language::l10n('Time to send mail is limited. Others mails are skipped.');
                } else {
                    $msg_break_timeout = \Phyxo\Functions\Language::l10n('Prepared time for list of users to send mail is limited. Others users are not listed.');
                }

                // Begin nbm users environment
                begin_users_env_nbm($is_action_send);

                foreach ($data_users as $nbm_user) {
                    if ((!$is_action_send) and check_sendmail_timeout()) {
                        // Stop fill list on 'list_to_send', if the quota is override
                        $page['infos'][] = $msg_break_timeout;
                        break;
                    }
                    if (($is_action_send) and check_sendmail_timeout()) {
                        // Stop fill list on 'send', if the quota is override
                        $page['errors'][] = $msg_break_timeout;
                        break;
                    }

                    // set env nbm user
                    set_user_on_env_nbm($nbm_user, $is_action_send);

                    if ($is_action_send) {
                        \Phyxo\Functions\URL::set_make_full_url();
                        // Fill return list of "treated" check_key for 'send'
                        $return_list[] = $nbm_user['check_key'];

                        if ($conf['nbm_send_detailed_content']) {
                            $news = news($nbm_user['last_send'], $dbnow, false, $conf['nbm_send_html_mail']);
                            $exist_data = count($news) > 0;
                        } else {
                            $exist_data = news_exists($nbm_user['last_send'], $dbnow);
                        }

                        if ($exist_data) {
                            $subject = '[' . $conf['gallery_title'] . '] ' . \Phyxo\Functions\Language::l10n('New photos added');

                            // Assign current var for nbm mail
                            assign_vars_nbm_mail_content($nbm_user);

                            if (!is_null($nbm_user['last_send'])) {
                                $env_nbm['mail_template']->assign(
                                    'content_new_elements_between',
                                    array(
                                        'DATE_BETWEEN_1' => $nbm_user['last_send'],
                                        'DATE_BETWEEN_2' => $dbnow,
                                    )
                                );
                            } else {
                                $env_nbm['mail_template']->assign(
                                    'content_new_elements_single',
                                    array('DATE_SINGLE' => $dbnow)
                                );
                            }

                            if ($conf['nbm_send_detailed_content']) {
                                $env_nbm['mail_template']->assign('global_new_lines', $news);
                            }

                            $nbm_user_customize_mail_content = \Phyxo\Functions\Plugin::trigger_change(
                                'nbm_render_user_customize_mail_content',
                                $customize_mail_content,
                                $nbm_user
                            );
                            if (!empty($nbm_user_customize_mail_content)) {
                                $env_nbm['mail_template']->assign('custom_mail_content', $nbm_user_customize_mail_content);
                            }

                            if ($conf['nbm_send_html_mail'] and $conf['nbm_send_recent_post_dates']) {
                                $recent_post_dates = get_recent_post_dates_array(
                                    $conf['recent_post_dates']['NBM']
                                );
                                foreach ($recent_post_dates as $date_detail) {
                                    $env_nbm['mail_template']->append(
                                        'recent_posts',
                                        array(
                                            'TITLE' => get_title_recent_post_date($date_detail),
                                            'HTML_DATA' => get_html_description_recent_post_date($date_detail)
                                        )
                                    );
                                }
                            }

                            $env_nbm['mail_template']->assign(
                                array(
                                    'GOTO_GALLERY_TITLE' => $conf['gallery_title'],
                                    'GOTO_GALLERY_URL' => \Phyxo\Functions\URL::get_gallery_home_url(),
                                    'SEND_AS_NAME' => $env_nbm['send_as_name'],
                                )
                            );

                            $ret = \Phyxo\Functions\Mail::mail(
                                array(
                                    'name' => stripslashes($nbm_user['username']),
                                    'email' => $nbm_user['mail_address'],
                                ),
                                array(
                                    'from' => $env_nbm['send_as_mail_formated'],
                                    'subject' => $subject,
                                    'email_format' => $env_nbm['email_format'],
                                    'content' => $env_nbm['mail_template']->parse('notification_by_mail', true),
                                    'content_format' => $env_nbm['email_format'],
                                )
                            );

                            if ($ret) {
                                inc_mail_sent_success($nbm_user);

                                $datas[] = array(
                                    'user_id' => $nbm_user['user_id'],
                                    'last_send' => $dbnow
                                );
                            } else {
                                inc_mail_sent_failed($nbm_user);
                            }

                            \Phyxo\Functions\URL::unset_make_full_url();
                        }
                    } else {
                        if (news_exists($nbm_user['last_send'], $dbnow)) {
                            // Fill return list of "selected" users for 'list_to_send'
                            $return_list[] = $nbm_user;
                        }
                    }

                    // unset env nbm user
                    unset_user_on_env_nbm();
                }

                // Restore nbm environment
                end_users_env_nbm();

                if ($is_action_send) {
                    $conn->mass_updates(
                        USER_MAIL_NOTIFICATION_TABLE,
                        array(
                            'primary' => array('user_id'),
                            'update' => array('last_send')
                        ),
                        $datas
                    );

                    display_counter_info();
                }
            } else {
                if ($is_action_send) {
                    $page['errors'][] = \Phyxo\Functions\Language::l10n('No user to send notifications by mail.');
                }
            }
        } else {
            // Quick List, don't check news
            // Fill return list of "selected" users for 'list_to_send'
            $return_list = $data_users;
        }
    }

    // Return list of "selected" users for 'list_to_send'
    // Return list of "treated" check_key for 'send'
    return $return_list;
}
