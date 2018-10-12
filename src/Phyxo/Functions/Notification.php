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

namespace Phyxo\Functions;

use App\Repository\ImageRepository;
use App\Repository\CommentRepository;
use Phyxo\DBLayer\DBLayer;
use App\Repository\UserMailNotificationRepository;
use App\Repository\UserRepository;
use App\Repository\UserInfosRepository;

class Notification
{
    public function __construct(DBLayer $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Returns number of new comments between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int
     */
    public static function nb_new_comments($start = null, $end = null)
    {
        global $conn;

        return (new CommentRepository($conn))->getNewComments($start, $end, $count_only = true);
    }

    /**
     * Returns new comments between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int[] comment ids
     */
    public static function new_comments($start = null, $end = null)
    {
        global $conn;

        return (new CommentRepository($conn))->getNewComments($start, $end);
    }

    /**
     * Returns number of unvalidated comments between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int
     */
    public static function nb_unvalidated_comments($start = null, $end = null)
    {
        global $conn;

        (new CommentRepository($conn))->getUnvalidatedComments($start, $end, $count_only = true);
    }

    /**
     * Returns number of new photos between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int
     */
    public static function nb_new_elements($start = null, $end = null)
    {
        global $conn;

        return (new ImageRepository($conn))->getNewElements($start, $end, $count_only = true);
    }

    /**
     * Returns new photos between two dates.es
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int[] photos ids
     */
    public static function new_elements($start = null, $end = null)
    {
        global $conn;

        return (new ImageRepository($conn))->getNewElements($start, $end);
    }

    /**
     * Returns number of updated categories between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int
     */
    public static function nb_updated_categories($start = null, $end = null)
    {
        global $conn;

        return (new ImageRepository($conn))->getUpdatedCategories($start, $end, $count_only = true);
    }

    /**
     * Returns updated categories between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int[] categories ids
     */
    public static function updated_categories($start = null, $end = null)
    {
        global $conn;

        return (new ImageRepository($conn))->getUpdatedCategories($start, $end, $count_only = true);
    }

    /**
     * Returns number of new users between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int
     */
    public static function nb_new_users($start = null, $end = null)
    {
        global $conn;

        return (new UserInfosRepository($conn))->getNewUsers($start, $end, $count_only = true);
    }

    /**
     * Returns new users between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int[] user ids
     */
    public static function new_users($start = null, $end = null)
    {
        global $conn;

        return (new UserInfosRepository($conn))->getNewUsers($start, $end);
    }

    /**
     * Returns if there was new activity between two dates.
     *
     * Takes in account: number of new comments, number of new elements, number of
     * updated categories. Administrators are also informed about: number of
     * unvalidated comments, number of new users.
     * @todo number of unvalidated elements
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return boolean
     */
    public static function news_exists($start = null, $end = null)
    {
        global $services;

        return ((self::nb_new_comments($start, $end) > 0) || (self::nb_new_elements($start, $end) > 0)
            || (self::nb_updated_categories($start, $end) > 0) || (($services['users']->isAdmin())
            && (self::nb_unvalidated_comments($start, $end) > 0)) || (($services['users']->isAdmin()) && (self::nb_new_users($start, $end) > 0)));
    }

    /**
     * Formats a news line and adds it to the array (e.g. '5 new elements')
     *
     * @param array &$news
     * @param int $count
     * @param string $singular_key
     * @param string $plural_key
     * @param string $url
     * @param bool $add_url
     */
    public static function add_news_line(&$news, $count, $singular_key, $plural_key, $url = '', $add_url = false)
    {
        if ($count > 0) {
            $line = \Phyxo\Functions\Language::l10n_dec($singular_key, $plural_key, $count);
            if ($add_url and !empty($url)) {
                $line = '<a href="' . $url . '">' . $line . '</a>';
            }
            $news[] = $line;
        }
    }

    /**
     * Returns new activity between two dates.
     *
     * Takes in account: number of new comments, number of new elements, number of
     * updated categories. Administrators are also informed about: number of
     * unvalidated comments, number of new users.
     * @todo number of unvalidated elements
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @param bool $exclude_img_cats if true, no info about new images/categories
     * @param bool $add_url add html link around news
     * @return array
     */
    public static function news($start = null, $end = null, $exclude_img_cats = false, $add_url = false)
    {
        global $services;

        $news = [];

        if (!$exclude_img_cats) {
            self::add_news_line(
                $news,
                self::nb_new_elements($start, $end),
                '%d new photo',
                '%d new photos',
                \Phyxo\Functions\URL::make_index_url(['section' => 'recent_pics']),
                $add_url
            );
        }

        if (!$exclude_img_cats) {
            self::add_news_line(
                $news,
                self::nb_updated_categories($start, $end),
                '%d album updated',
                '%d albums updated',
                \Phyxo\Functions\URL::make_index_url(['section' => 'recent_cats']),
                $add_url
            );
        }

        self::add_news_line(
            $news,
            self::nb_new_comments($start, $end),
            '%d new comment',
            '%d new comments',
            \Phyxo\Functions\URL::get_root_url() . 'comments.php',
            $add_url
        );

        if ($services['users']->isAdmin()) {
            self::add_news_line(
                $news,
                self::nb_unvalidated_comments($start, $end),
                '%d comment to validate',
                '%d comments to validate',
                \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=comments',
                $add_url
            );

            self::add_news_line(
                $news,
                self::nb_new_users($start, $end),
                '%d new user',
                '%d new users',
                \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=user_list',
                $add_url
            );
        }

        return $news;
    }

    /**
     * Returns information about recently published elements grouped by post date.
     *
     * @param int $max_dates maximum number of recent dates
     * @param int $max_elements maximum number of elements per date
     * @param int $max_cats maximum number of categories per date
     * @return array
     */
    public static function get_recent_post_dates($max_dates, $max_elements, $max_cats)
    {
        global $conf, $user, $persistent_cache, $conn;

        $cache_key = $persistent_cache->make_key('recent_posts' . $user['id'] . $user['cache_update_time'] . $max_dates . $max_elements . $max_cats);
        if ($persistent_cache->get($cache_key, $cached)) {
            return $cached;
        }
        $where_sql = \Phyxo\Functions\SQL::get_std_sql_where_restrict_filter('WHERE', 'i.id', true);

        $result = (new ImageRepository($conn))->getRecentPostedImages($where_sql, $max_dates);
        $dates = $conn->result2array($result);

        for ($i = 0; $i < count($dates); $i++) {
            if ($max_elements > 0) { // get some thumbnails ...
                $result = (new ImageRepository($conn))->findRandomImages($where_sql, $max_elements);
                $dates[$i]['elements'] = $conn->result2array($result);
            }

            if ($max_cats > 0) { // get some categories ...
                $result = (new ImageRepository($conn))->getRecentImages($where_sql, $dates[$i]['date_available'], $max_cats);
                $dates[$i]['categories'] = $conn->result2array($result);
            }
        }

        $persistent_cache->set($cache_key, $dates);
        return $dates;
    }

    /**
     * Returns information about recently published elements grouped by post date.
     * Same as get_recent_post_dates() but parameters as an indexed array.
     * @see get_recent_post_dates()
     *
     * @param array $args
     * @return array
     */
    public static function get_recent_post_dates_array($args)
    {
        return self::get_recent_post_dates(
            (empty($args['max_dates']) ? 3 : $args['max_dates']),
            (empty($args['max_elements']) ? 3 : $args['max_elements']),
            (empty($args['max_cats']) ? 3 : $args['max_cats'])
        );
    }

    /**
     * Returns html description about recently published elements grouped by post date.
     * @todo clean up HTML output, currently messy and invalid !
     *
     * @param array $date_detail returned value of get_recent_post_dates()
     * @return string
     */
    public static function get_html_description_recent_post_date($date_detail)
    {
        global $conf;

        $description = '<ul>';

        $description .=
            '<li>'
            . \Phyxo\Functions\Language::l10n_dec('%d new photo', '%d new photos', $date_detail['nb_elements'])
            . ' ('
            . '<a href="' . \Phyxo\Functions\URL::make_index_url(['section' => 'recent_pics']) . '">'
            . \Phyxo\Functions\Language::l10n('Recent photos') . '</a>'
            . ')'
            . '</li><br>';

        foreach ($date_detail['elements'] as $element) {
            $tn_src = \Phyxo\Image\DerivativeImage::thumb_url($element);
            $description .= '<a href="' .
                \Phyxo\Functions\URL::make_picture_url([
                'image_id' => $element['id'],
                'image_file' => $element['file'],
            ]) . '"><img src="' . $tn_src . '"></a>';
        }
        $description .= '...<br>';

        $description .=
            '<li>'
            . \Phyxo\Functions\Language::l10n_dec('%d album updated', '%d albums updated', $date_detail['nb_cats'])
            . '</li>';

        $description .= '<ul>';
        foreach ($date_detail['categories'] as $cat) {
            $description .=
                '<li>'
                . \Phyxo\Functions\Category::get_cat_display_name_cache($cat['uppercats'])
                . ' (' .
                \Phyxo\Functions\Language::l10n_dec('%d new photo', '%d new photos', $cat['img_count']) . ')'
                . '</li>';
        }
        $description .= '</ul>';

        $description .= '</ul>'; // @TODO: fix html output. Cannot have to </ul>

        return $description;
    }

    /**
     * Returns title about recently published elements grouped by post date.
     *
     * @param array $date_detail returned value of get_recent_post_dates()
     * @return string
     */
    public static function get_title_recent_post_date($date_detail)
    {
        global $lang;

        $date = $date_detail['date_available'];
        $exploded_date = strptime($date, '%Y-%m-%d %H:%M:%S');

        $title = \Phyxo\Functions\Language::l10n_dec('%d new photo', '%d new photos', $date_detail['nb_elements']);
        $title .= ' (' . $lang['month'][1 + $exploded_date['tm_mon']] . ' ' . $exploded_date['tm_mday'] . ')';

        return $title;
    }

    /*
     * Check sendmail timeout state
     *
     * @return true, if it's timeout
     */
    public static function check_sendmail_timeout()
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
    public static function quote_check_key_list($check_key_list = [])
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
    public static function get_user_notifications($action, $check_key_list = [], $enabled_filter_value = false)
    {
        global $conf, $conn;

        $data_users = [];

        if (in_array($action, ['subscribe', 'send'])) {
            if ($action == 'send') {
                $order_by = ' ORDER BY last_send, username';
            } else {
                $order_by = ' ORDER BY username';
            }

            $result = (new UserMailNotificationRepository($conn))->findInfosForUsers(
                $no_mail_empty = ($action === 'send'),
                $enabled_filter_value,
                $check_key_list,
                $order_by
            );
            while ($nbm_user = $conn->db_fetch_assoc($result)) {
                $data_users[] = $nbm_user;
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
    public static function begin_users_env_nbm($is_to_send_mail = false)
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
    public static function end_users_env_nbm()
    {
        global $user, $lang, $lang_info, $env_nbm;

        // Restore $user, $lang_info and $lang arrays (include/user.inc.php has been executed)
        $user = $env_nbm['save_user'];
        // Restore current language to stack, necessary because $user change during NBM
        \Phyxo\Functions\Mail::switch_lang_back();

        if ($env_nbm['is_to_send_mail']) {
            unset($env_nbm['email_format'], $env_nbm['send_as_name'], $env_nbm['send_as_mail_address'], $env_nbm['send_as_mail_formated'], $env_nbm['msg_info'], $env_nbm['msg_error']);



            // Don t unset counter
            //unset($env_nbm['error_on_mail_count']);
            //unset($env_nbm['sent_mail_count']);


        }

        unset($env_nbm['save_user'], $env_nbm['is_to_send_mail']);

    }

/*
 * Set user on nbm enviromnent
 *
 * Return none
 */
    public static function set_user_on_env_nbm(&$nbm_user, $is_action_send)
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
    public static function unset_user_on_env_nbm()
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
    public static function inc_mail_sent_success($nbm_user)
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
    public static function inc_mail_sent_failed($nbm_user)
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
    public static function display_counter_info()
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

    public static function assign_vars_nbm_mail_content($nbm_user)
    {
        global $env_nbm;

        \Phyxo\Functions\URL::set_make_full_url();

        $env_nbm['mail_template']->assign(
            [
                'USERNAME' => stripslashes($nbm_user['username']),
                'SEND_AS_NAME' => $env_nbm['send_as_name'],
                'UNSUBSCRIBE_LINK' => \Phyxo\Functions\URL::add_url_params(\Phyxo\Functions\URL::get_gallery_home_url() . '/nbm.php', ['unsubscribe' => $nbm_user['check_key']]),
                'SUBSCRIBE_LINK' => \Phyxo\Functions\URL::add_url_params(\Phyxo\Functions\URL::get_gallery_home_url() . '/nbm.php', ['subscribe' => $nbm_user['check_key']]),
                'CONTACT_EMAIL' => $env_nbm['send_as_mail_address']
            ]
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
    public static function do_subscribe_unsubscribe_notification_by_mail($is_admin_request, $is_subscribe = false, $check_key_list = [])
    {
        global $conf, $page, $env_nbm, $conf, $conn;

        \Phyxo\Functions\URL::set_make_full_url();

        $check_key_treated = [];
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
            $updates = [];
            $enabled_value = $conn->boolean_to_db($is_subscribe);
            $data_users = self::get_user_notifications('subscribe', $check_key_list, !$is_subscribe);

            // Prepare message after change language
            $msg_break_timeout = \Phyxo\Functions\Language::l10n('Time to send mail is limited. Others mails are skipped.');

            // Begin nbm users environment
            self::begin_users_env_nbm(true);

            foreach ($data_users as $nbm_user) {
                if (self::check_sendmail_timeout()) {
                    // Stop fill list on 'send', if the quota is override
                    $page['errors'][] = $msg_break_timeout;
                    break;
                }

                // Fill return list
                $check_key_treated[] = $nbm_user['check_key'];

                $do_update = true;
                if ($nbm_user['mail_address'] != '') {
                    // set env nbm user
                    self::set_user_on_env_nbm($nbm_user, true);

                    $subject = '[' . $conf['gallery_title'] . '] ' . ($is_subscribe ? \Phyxo\Functions\Language::l10n('Subscribe to notification by mail') : \Phyxo\Functions\Language::l10n('Unsubscribe from notification by mail'));

                    // Assign current var for nbm mail
                    self::assign_vars_nbm_mail_content($nbm_user);

                    $section_action_by = ($is_subscribe ? 'subscribe_by_' : 'unsubscribe_by_');
                    $section_action_by .= ($is_admin_request ? 'admin' : 'himself');
                    $env_nbm['mail_template']->assign(
                        [
                            $section_action_by => true,
                            'GOTO_GALLERY_TITLE' => $conf['gallery_title'],
                            'GOTO_GALLERY_URL' => \Phyxo\Functions\URL::get_gallery_home_url(),
                        ]
                    );

                    $ret = \Phyxo\Functions\Mail::mail(
                        [
                            'name' => stripslashes($nbm_user['username']),
                            'email' => $nbm_user['mail_address'],
                        ],
                        [
                            'from' => $env_nbm['send_as_mail_formated'],
                            'subject' => $subject,
                            'email_format' => $env_nbm['email_format'],
                            'content' => $env_nbm['mail_template']->parse('notification_by_mail', true),
                            'content_format' => $env_nbm['email_format'],
                        ]
                    );

                    if ($ret) {
                        self::inc_mail_sent_success($nbm_user);
                    } else {
                        self::inc_mail_sent_failed($nbm_user);
                        $do_update = false;
                    }

                    // unset env nbm user
                    self::unset_user_on_env_nbm();
                }

                if ($do_update) {
                    $updates[] = [
                        'check_key' => $nbm_user['check_key'],
                        'enabled' => $enabled_value
                    ];
                    $updated_data_count += 1;
                    $page['infos'][] = sprintf($msg_info, stripslashes($nbm_user['username']), $nbm_user['mail_address']);
                } else {
                    $error_on_updated_data_count += 1;
                    $page['errors'][] = sprintf($msg_error, stripslashes($nbm_user['username']), $nbm_user['mail_address']);
                }
            }

            // Restore nbm environment
            self::end_users_env_nbm();

            self::display_counter_info();

            (new UserMailNotificationRepository($conn))->massUpdates(
                [
                    'primary' => ['check_key'],
                    'update' => ['enabled']
                ],
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
    public static function unsubscribe_notification_by_mail($is_admin_request, $check_key_list = [])
    {
        return self::do_subscribe_unsubscribe_notification_by_mail($is_admin_request, false, $check_key_list);
    }

    /*
     * Subscribe notification by mail
     *
     * check_key list where action will be done
     *
     * @return check_key list treated
     */
    public static function subscribe_notification_by_mail($is_admin_request, $check_key_list = [])
    {
        return self::do_subscribe_unsubscribe_notification_by_mail($is_admin_request, true, $check_key_list);
    }

    /*
     * Do timeout treatment in order to finish to send mails
     *
     * @param $post_keyname: key of check_key post array
     * @param check_key_treated: array of check_key treated
     * @return none
     */
    public static function do_timeout_treatment($post_keyname, $check_key_treated = [])
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

    // Inserting News users
    public static function insert_new_data_user_mail_notification()
    {
        global $conf, $page, $env_nbm, $conn;

        // null mail_address are not selected in the list
        $result = (new UserRepository($conn))->findUsersWithNoMailNotificationInfos();
        if ($conn->db_num_rows($result) > 0) {
            $inserts = [];
            $check_key_list = [];

            while ($nbm_user = $conn->db_fetch_assoc($result)) {
                // Calculate key
                $nbm_user['check_key'] = \Phyxo\Functions\Utils::generate_key(16);

                // Save key
                $check_key_list[] = $nbm_user['check_key'];

                // Insert new nbm_users
                $inserts[] = [
                    'user_id' => $nbm_user['user_id'],
                    'check_key' => $nbm_user['check_key'],
                    'enabled' => 'false' // By default if false, set to true with specific functions
                ];

                $page['infos'][] = \Phyxo\Functions\Language::l10n(
                    'User %s [%s] added.',
                    stripslashes($nbm_user['username']),
                    $nbm_user['mail_address']
                );
            }

            // Insert new nbm_users
            (new UserMailNotificationRepository($conn))->massInserts(['user_id', 'check_key', 'enabled'], $inserts);
            // Update field enabled with specific function
            $check_key_treated = self::do_subscribe_unsubscribe_notification_by_mail(
                true,
                $conf['nbm_default_value_user_enabled'],
                $check_key_list
            );

            // On timeout simulate like tabsheet send
            if ($env_nbm['is_sendmail_timeout']) {
                $check_key_list = array_diff($check_key_list, $check_key_treated);
                if (count($check_key_list) > 0) {
                    (new UserMailNotificationRepository($conn))->deleteByCheckKeys($check_key_list);
                    \Phyxo\Functions\Utils::redirect($base_url . \Phyxo\Functions\URL::get_query_string_diff([], false), \Phyxo\Functions\Language::l10n('Operation in progress') . "\n" . \Phyxo\Functions\Language::l10n('Please wait...'));
                }
            }
        }
    }

    /*
     * Apply global functions to mail content
     * return customize mail content rendered
     */
    public static function render_global_customize_mail_content($customize_mail_content)
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
    public static function do_action_send_mail_notification($action = 'list_to_send', $check_key_list = [], $customize_mail_content = '')
    {
        global $conf, $page, $user, $lang_info, $lang, $env_nbm, $conn;

        $return_list = [];

        if (in_array($action, ['list_to_send', 'send'])) {
            list($dbnow) = $conn->db_fetch_row($conn->db_query('SELECT NOW();'));

            $is_action_send = ($action == 'send');

            // disabled and null mail_address are not selected in the list
            $data_users = self::get_user_notifications('send', $check_key_list);

            // List all if it's define on options or on timeout
            $is_list_all_without_test = ($env_nbm['is_sendmail_timeout'] or $conf['nbm_list_all_enabled_users_to_send']);

            // Check if exist news to list user or send mails
            if ((!$is_list_all_without_test) or ($is_action_send)) {
                if (count($data_users) > 0) {
                    $datas = [];

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
                    self::begin_users_env_nbm($is_action_send);

                    foreach ($data_users as $nbm_user) {
                        if ((!$is_action_send) && self::check_sendmail_timeout()) {
                           // Stop fill list on 'list_to_send', if the quota is override
                            $page['infos'][] = $msg_break_timeout;
                            break;
                        }
                        if (($is_action_send) && self::check_sendmail_timeout()) {
                            // Stop fill list on 'send', if the quota is override
                            $page['errors'][] = $msg_break_timeout;
                            break;
                        }

                        // set env nbm user
                        self::set_user_on_env_nbm($nbm_user, $is_action_send);

                        if ($is_action_send) {
                            \Phyxo\Functions\URL::set_make_full_url();
                            // Fill return list of "treated" check_key for 'send'
                            $return_list[] = $nbm_user['check_key'];

                            if ($conf['nbm_send_detailed_content']) {
                                $news = \Phyxo\Functions\Notification::news($nbm_user['last_send'], $dbnow, false, $conf['nbm_send_html_mail']);
                                $exist_data = count($news) > 0;
                            } else {
                                $exist_data = \Phyxo\Functions\Notification::news_exists($nbm_user['last_send'], $dbnow);
                            }

                            if ($exist_data) {
                                $subject = '[' . $conf['gallery_title'] . '] ' . \Phyxo\Functions\Language::l10n('New photos added');

                                // Assign current var for nbm mail
                                self::assign_vars_nbm_mail_content($nbm_user);

                                if (!is_null($nbm_user['last_send'])) {
                                    $env_nbm['mail_template']->assign(
                                        'content_new_elements_between',
                                        [
                                            'DATE_BETWEEN_1' => $nbm_user['last_send'],
                                            'DATE_BETWEEN_2' => $dbnow,
                                        ]
                                    );
                                } else {
                                    $env_nbm['mail_template']->assign(
                                        'content_new_elements_single',
                                        ['DATE_SINGLE' => $dbnow]
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

                                if ($conf['nbm_send_html_mail'] && $conf['nbm_send_recent_post_dates']) {
                                    $recent_post_dates = \Phyxo\Functions\Notification::get_recent_post_dates_array(
                                        $conf['recent_post_dates']['NBM']
                                    );
                                    foreach ($recent_post_dates as $date_detail) {
                                        $env_nbm['mail_template']->append(
                                            'recent_posts',
                                            [
                                                'TITLE' => \Phyxo\Functions\Notification::get_title_recent_post_date($date_detail),
                                                'HTML_DATA' => \Phyxo\Functions\Notification::get_html_description_recent_post_date($date_detail)
                                            ]
                                        );
                                    }
                                }

                                $env_nbm['mail_template']->assign(
                                    [
                                        'GOTO_GALLERY_TITLE' => $conf['gallery_title'],
                                        'GOTO_GALLERY_URL' => \Phyxo\Functions\URL::get_gallery_home_url(),
                                        'SEND_AS_NAME' => $env_nbm['send_as_name'],
                                    ]
                                );

                                $ret = \Phyxo\Functions\Mail::mail(
                                    [
                                        'name' => stripslashes($nbm_user['username']),
                                        'email' => $nbm_user['mail_address'],
                                    ],
                                    [
                                        'from' => $env_nbm['send_as_mail_formated'],
                                        'subject' => $subject,
                                        'email_format' => $env_nbm['email_format'],
                                        'content' => $env_nbm['mail_template']->parse('notification_by_mail', true),
                                        'content_format' => $env_nbm['email_format'],
                                    ]
                                );

                                if ($ret) {
                                    self::inc_mail_sent_success($nbm_user);

                                    $datas[] = [
                                        'user_id' => $nbm_user['user_id'],
                                        'last_send' => $dbnow
                                    ];
                                } else {
                                    self::inc_mail_sent_failed($nbm_user);
                                }

                                \Phyxo\Functions\URL::unset_make_full_url();
                            }
                        } else {
                            if (\Phyxo\Functions\Notification::news_exists($nbm_user['last_send'], $dbnow)) {
                                // Fill return list of "selected" users for 'list_to_send'
                                $return_list[] = $nbm_user;
                            }
                        }

                        // unset env nbm user
                        self::unset_user_on_env_nbm();
                    }

                    // Restore nbm environment
                    self::end_users_env_nbm();

                    if ($is_action_send) {
                        (new UserMailNotificationRepository($conn))->massUpdates(
                            [
                                'primary' => ['user_id'],
                                'update' => ['last_send']
                            ],
                            $datas
                        );

                        self::display_counter_info();
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
}
