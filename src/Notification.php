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

namespace App;

use App\Repository\ImageRepository;
use App\Repository\CommentRepository;
use App\Repository\UserMailNotificationRepository;
use App\Repository\UserRepository;
use App\Repository\UserInfosRepository;
use App\Repository\BaseRepository;
use App\DataMapper\UserMapper;
use App\DataMapper\CategoryMapper;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\SrcImage;
use Phyxo\EntityManager;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Conf;
use Phyxo\Functions\Language;
use Symfony\Component\Routing\RouterInterface;

class Notification
{
    private $em, $conn, $conf, $userMapper, $categoryMapper, $router;
    private $env, $must_repost;

    public function __construct(EntityManager $em, Conf $conf, UserMapper $userMapper, CategoryMapper $categoryMapper, RouterInterface $router)
    {
        $this->em = $em;
        $this->conn = $em->getConnection();
        $this->conf = $conf;
        $this->userMapper = $userMapper;
        $this->categoryMapper = $categoryMapper;
        $this->router = $router;

        $this->env = [
            'start_time' => microtime(true),
            'sendmail_timeout' => (intval(ini_get('max_execution_time')) * $conf['nbm_max_treatment_timeout_percent']),
            'is_sendmail_timeout' => false
        ];

        if ((!isset($this->env['sendmail_timeout'])) || (!is_numeric($this->env['sendmail_timeout'])) || ($this->env['sendmail_timeout'] <= 0)) {
            $this->env['sendmail_timeout'] = $conf['nbm_treatment_timeout_default'];
        }

        $this->must_repost = false;
    }

    /**
     * Returns number of new comments between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int
     */
    public function nb_new_comments($start = null, $end = null)
    {
        return $this->em->getRepository(CommentRepository::class)->getNewComments($this->userMapper->getUser(), [], $start, $end, $count_only = true);
    }

    /**
     * Returns new comments between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int[] comment ids
     */
    public function new_comments($start = null, $end = null)
    {
        return $this->em->getRepository(CommentRepository::class)->getNewComments($this->userMapper->getUser(), [], $start, $end);
    }

    /**
     * Returns number of unvalidated comments between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int
     */
    public function nb_unvalidated_comments($start = null, $end = null)
    {
        return $this->em->getRepository(CommentRepository::class)->getUnvalidatedComments($start, $end, $count_only = true);
    }

    /**
     * Returns number of new photos between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int
     */
    public function nb_new_elements($start = null, $end = null)
    {
        return $this->em->getRepository(ImageRepository::class)->getNewElements($this->userMapper->getUser(), [], $start, $end, $count_only = true);
    }

    /**
     * Returns new photos between two dates.es
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int[] photos ids
     */
    public function new_elements($start = null, $end = null)
    {
        return $this->em->getRepository(ImageRepository::class)->getNewElements($this->userMapper->getUser(), [], $start, $end);
    }

    /**
     * Returns number of updated categories between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int
     */
    public function nb_updated_categories($start = null, $end = null)
    {
        return $this->em->getRepository(ImageRepository::class)->getUpdatedCategories($this->userMapper->getUser(), [], $start, $end, $count_only = true);
    }

    /**
     * Returns updated categories between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int[] categories ids
     */
    public function updated_categories($start = null, $end = null)
    {
        return $this->em->getRepository(ImageRepository::class)->getUpdatedCategories($this->userMapper->getUser(), [], $start, $end, $count_only = true);
    }

    /**
     * Returns number of new users between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int
     */
    public function nb_new_users($start = null, $end = null)
    {
        return $this->em->getRepository(UserInfosRepository::class)->getNewUsers($start, $end, $count_only = true);
    }

    /**
     * Returns new users between two dates.
     *
     * @param string $start (mysql datetime format)
     * @param string $end (mysql datetime format)
     * @return int[] user ids
     */
    public function new_users($start = null, $end = null)
    {
        return $this->em->getRepository(UserInfosRepository::class)->getNewUsers($start, $end);
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
    public function news_exists($start = null, $end = null)
    {
        return (($this->nb_new_comments($start, $end) > 0) || ($this->nb_new_elements($start, $end) > 0)
            || ($this->nb_updated_categories($start, $end) > 0) || (($this->userMapper->isAdmin())
            && ($this->nb_unvalidated_comments($start, $end) > 0)) || (($this->userMapper->isAdmin()) && ($this->nb_new_users($start, $end) > 0)));
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
    public function add_news_line(&$news, $count, $singular_key, $plural_key, $url = '', $add_url = false)
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
    public function news($start = null, $end = null, $exclude_img_cats = false, $add_url = false)
    {
        $news = [];

        if (!$exclude_img_cats) {
            $this->add_news_line($news, $this->nb_new_elements($start, $end), '%d new photo', '%d new photos', $this->router->generate('recent_pics'), $add_url);
        }

        if (!$exclude_img_cats) {
            $this->add_news_line($news, $this->nb_updated_categories($start, $end), '%d album updated', '%d albums updated', $this->router->generate('recent_cats'), $add_url);
        }

        $this->add_news_line($news, $this->nb_new_comments($start, $end), '%d new comment', '%d new comments', $this->router->generate('comments'), $add_url);

        if ($this->userMapper->isAdmin()) {
            $this->add_news_line($news, $this->nb_unvalidated_comments($start, $end), '%d comment to validate', '%d comments to validate', $this->router->generate('admin_comments'), $add_url);

            $this->add_news_line($news, $this->nb_new_users($start, $end), '%d new user', '%d new users', $this->router->generate('admin_users'), $add_url);
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
    public function get_recent_post_dates($max_dates, $max_elements, $max_cats)
    {
        $where_sql = (new BaseRepository($this->conn))->getStandardSQLWhereRestrictFilter($this->userMapper->getUser(), [], 'WHERE', 'i.id', true);

        $result = (new ImageRepository($this->conn))->getRecentPostedImages($where_sql, $max_dates);
        $dates = $this->conn->result2array($result);

        for ($i = 0; $i < count($dates); $i++) {
            if ($max_elements > 0) { // get some thumbnails ...
                $result = (new ImageRepository($this->conn))->findRandomImages($where_sql, '', $max_elements);
                $dates[$i]['elements'] = $this->conn->result2array($result);
            }

            if ($max_cats > 0) { // get some categories ...
                $result = (new ImageRepository($this->conn))->getRecentImages($where_sql, $dates[$i]['date_available'], $max_cats);
                $dates[$i]['categories'] = $this->conn->result2array($result);
            }
        }

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
    public function get_recent_post_dates_array($args)
    {
        return $this->get_recent_post_dates(
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
    public function get_html_description_recent_post_date(array $date_detail, array $picture_ext): string
    {
        $description = '<ul>';

        $description .=
            '<li>'
            . \Phyxo\Functions\Language::l10n_dec('%d new photo', '%d new photos', $date_detail['nb_elements'])
            . ' ('
            . '<a href="' . $this->router->generate('recent_pics') . '">'
            . \Phyxo\Functions\Language::l10n('Recent photos') . '</a>'
            . ')'
            . '</li><br>';

        $image_std_params = new ImageStandardParams($this->conf);
        $params = $image_std_params->getByType(ImageStandardParams::IMG_THUMB);

        foreach ($date_detail['elements'] as $element) {
            $tn_src = (new DerivativeImage(new SrcImage($element, $picture_ext), $params, $image_std_params))->getUrl();
            $description .= '<a href="' . $this->router->generate('picture', ['image_id' => $element['id'], 'type' => 'file', 'element_id' => $element['file']]) . '"><img src="' . $tn_src . '"></a>';
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
                . $this->categoryMapper->getCatDisplayNameCache($cat['uppercats'])
                . ' (' . \Phyxo\Functions\Language::l10n_dec('%d new photo', '%d new photos', $cat['img_count']) . ')'
                . '</li>';
        }
        $description .= '</ul>';

        $description .= '</ul>'; // @TODO: fix html output. Cannot have two </ul>

        return $description;
    }

    /**
     * Returns title about recently published elements grouped by post date.
     *
     * @param array $date_detail returned value of get_recent_post_dates()
     * @return string
     */
    public function get_title_recent_post_date($date_detail)
    {
        global $lang;

        $date = $date_detail['date_available'];
        $exploded_date = strptime($date, '%Y-%m-%d %H:%M:%S');

        $title = Language::l10n_dec('%d new photo', '%d new photos', $date_detail['nb_elements']);
        $title .= ' (' . $lang['month'][1 + $exploded_date['tm_mon']] . ' ' . $exploded_date['tm_mday'] . ')';

        return $title;
    }

    /*
     * Check sendmail timeout state
     *
     * @return true, if it's timeout
     */
    public function check_sendmail_timeout()
    {
        $this->env['is_sendmail_timeout'] = ((microtime(true) - $this->env['start_time']) > $this->env['sendmail_timeout']);

        return $this->env['is_sendmail_timeout'];
    }

    /*
     * Add quote to all elements of check_key_list
     *
     * @return quoted check key list
     */
    public function quote_check_key_list($check_key_list = [])
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
    public function get_user_notifications($action, $check_key_list = [], $enabled_filter_value = false)
    {
        $data_users = [];

        if (in_array($action, ['subscribe', 'send'])) {
            if ($action == 'send') {
                $order_by = ' ORDER BY last_send, username';
            } else {
                $order_by = ' ORDER BY username';
            }

            $result = (new UserMailNotificationRepository($this->conn))->findInfosForUsers(
                $no_mail_empty = ($action === 'send'),
                $enabled_filter_value,
                $check_key_list,
                $order_by
            );
            while ($nbm_user = $this->conn->db_fetch_assoc($result)) {
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
    public function begin_users_env_nbm($is_to_send_mail = false)
    {
        global $user;

        // Save $user, $lang_info and $lang arrays (include/user.inc.php has been executed)
        $this->env['save_user'] = $user;
        // Save current language to stack, necessary because $user change during NBM
        \Phyxo\Functions\Mail::switch_lang_to($user['language']);

        $this->env['is_to_send_mail'] = $is_to_send_mail;

        if ($is_to_send_mail) {
            // Init mail configuration
            $this->env['email_format'] = \Phyxo\Functions\Mail::get_str_email_format($this->conf['nbm_send_html_mail']);
            $this->env['send_as_name'] = !empty($this->conf['nbm_send_mail_as']) ? $this->conf['nbm_send_mail_as'] : \Phyxo\Functions\Mail::get_mail_sender_name();
            $this->env['send_as_mail_address'] = $this->userMapper->getWebmasterEmail();
            $this->env['send_as_mail_formated'] = \Phyxo\Functions\Mail::format_email($this->env['send_as_name'], $this->env['send_as_mail_address']);
            // Init mail counter
            $this->env['error_on_mail_count'] = 0;
            $this->env['sent_mail_count'] = 0;
            // Save sendmail message info and error in the original language
            $this->env['msg_info'] = \Phyxo\Functions\Language::l10n('Mail sent to %s [%s].');
            $this->env['msg_error'] = \Phyxo\Functions\Language::l10n('Error when sending email to %s [%s].');
        }
    }

    /*
     * End of use nbm environment
     * Restore environment
     *
     * Return none
     */
    public function end_users_env_nbm()
    {
        global $user;

        // Restore $user, $lang_info and $lang arrays (include/user.inc.php has been executed)
        $user = $this->env['save_user'];
        // Restore current language to stack, necessary because $user change during NBM
        \Phyxo\Functions\Mail::switch_lang_back();

        if ($this->env['is_to_send_mail']) {
            unset($this->env['email_format'], $this->env['send_as_name'], $this->env['send_as_mail_address'], $this->env['send_as_mail_formated'], $this->env['msg_info'], $this->env['msg_error']);

            // Don t unset counter
            //unset($this->env['error_on_mail_count']);
            //unset($this->env['sent_mail_count']);
        }

        unset($this->env['save_user'], $this->env['is_to_send_mail']);
    }

    /*
     * Set user on nbm enviromnent
     *
     * Return none
     */
    public function set_user_on_env_nbm(&$nbm_user, $is_action_send)
    {
        global $user;

        $user = $this->userMapper->buildUser($nbm_user['user_id'], true);

        \Phyxo\Functions\Mail::switch_lang_to($user['language']);

        if ($is_action_send) {
            $this->env['mail_template'] = \Phyxo\Functions\Mail::get_mail_template($this->env['email_format']);
            $this->env['mail_template']->set_filename('notification_by_mail', 'notification_by_mail.tpl');
        }
    }

    /*
     * Unset user on nbm enviromnent
     *
     * Return none
     */
    public function unset_user_on_env_nbm()
    {
        \Phyxo\Functions\Mail::switch_lang_back();
        unset($this->env['mail_template']);
    }

    /*
     * Inc Counter success
     *
     * Return none
     */
    public function inc_mail_sent_success($nbm_user)
    {
        global $page;

        $this->env['sent_mail_count'] += 1;
        $page['infos'][] = sprintf($this->env['msg_info'], stripslashes($nbm_user['username']), $nbm_user['mail_address']);
    }

    /*
     * Inc Counter failed
     *
     * Return none
     */
    public function inc_mail_sent_failed($nbm_user)
    {
        global $page;

        $this->env['error_on_mail_count'] += 1;
        $page['errors'][] = sprintf($this->env['msg_error'], stripslashes($nbm_user['username']), $nbm_user['mail_address']);
    }

    /*
     * Display Counter Info
     *
     * Return none
     */
    public function display_counter_info()
    {
        global $page;

        if ($this->env['error_on_mail_count'] != 0) {
            $page['errors'][] = \Phyxo\Functions\Language::l10n_dec(
                '%d mail was not sent.',
                '%d mails were not sent.',
                $this->env['error_on_mail_count']
            );

            if ($this->env['sent_mail_count'] != 0) {
                $page['infos'][] = \Phyxo\Functions\Language::l10n_dec(
                    '%d mail was sent.',
                    '%d mails were sent.',
                    $this->env['sent_mail_count']
                );
            }
        } else {
            if ($this->env['sent_mail_count'] == 0) {
                $page['infos'][] = \Phyxo\Functions\Language::l10n('No mail to send.');
            } else {
                $page['infos'][] = \Phyxo\Functions\Language::l10n_dec(
                    '%d mail was sent.',
                    '%d mails were sent.',
                    $this->env['sent_mail_count']
                );
            }
        }
    }

    public function assign_vars_nbm_mail_content($nbm_user)
    {
        $this->env['mail_template']->assign(
            [
                'USERNAME' => stripslashes($nbm_user['username']),
                'SEND_AS_NAME' => $this->env['send_as_name'],
                'UNSUBSCRIBE_LINK' => $this->router->generate('notification_unsubscribe'),
                'SUBSCRIBE_LINK' => $this->router->generate('notification_subscribe'),
                'CONTACT_EMAIL' => $this->env['send_as_mail_address']
            ]
        );
    }

    /*
     * Subscribe or unsubscribe notification by mail
     *
     * is_subscribe define if action=subscribe or unsubscribe
     * check_key list where action will be done
     *
     * @return check_key list treated
     */
    public function do_subscribe_unsubscribe_notification_by_mail($is_admin_request, $is_subscribe = false, $check_key_list = [])
    {
        global $page;

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
            $enabled_value = $this->conn->boolean_to_db($is_subscribe);
            $data_users = $this->get_user_notifications('subscribe', $check_key_list, !$is_subscribe);

            // Prepare message after change language
            $msg_break_timeout = \Phyxo\Functions\Language::l10n('Time to send mail is limited. Others mails are skipped.');

            // Begin nbm users environment
            $this->begin_users_env_nbm(true);

            foreach ($data_users as $nbm_user) {
                if ($this->check_sendmail_timeout()) {
                    // Stop fill list on 'send', if the quota is override
                    $page['errors'][] = $msg_break_timeout;
                    break;
                }

                // Fill return list
                $check_key_treated[] = $nbm_user['check_key'];

                $do_update = true;
                if ($nbm_user['mail_address'] != '') {
                    // set env nbm user
                    $this->set_user_on_env_nbm($nbm_user, true);

                    $subject = '[' . $this->conf['gallery_title'] . '] ' . ($is_subscribe ? \Phyxo\Functions\Language::l10n('Subscribe to notification by mail') : \Phyxo\Functions\Language::l10n('Unsubscribe from notification by mail'));

                    // Assign current var for nbm mail
                    $this->assign_vars_nbm_mail_content($nbm_user);

                    $section_action_by = ($is_subscribe ? 'subscribe_by_' : 'unsubscribe_by_');
                    $section_action_by .= ($is_admin_request ? 'admin' : 'himself');
                    $this->env['mail_template']->assign(
                        [
                            $section_action_by => true,
                            'GOTO_GALLERY_TITLE' => $this->conf['gallery_title'],
                            'GOTO_GALLERY_URL' => $this->router->generate('homepage'),
                        ]
                    );

                    $ret = \Phyxo\Functions\Mail::mail(
                        [
                            'name' => stripslashes($nbm_user['username']),
                            'email' => $nbm_user['mail_address'],
                        ],
                        [
                            'from' => $this->env['send_as_mail_formated'],
                            'subject' => $subject,
                            'email_format' => $this->env['email_format'],
                            'content' => $this->env['mail_template']->parse('notification_by_mail', true),
                            'content_format' => $this->env['email_format'],
                            'email_webmaster' => $this->userMapper->getWebmasterEmail(),
                            'name_webmaster' => $this->userMapper->getWebmasterUsername(),
                        ]
                    );

                    if ($ret) {
                        $this->inc_mail_sent_success($nbm_user);
                    } else {
                        $this->inc_mail_sent_failed($nbm_user);
                        $do_update = false;
                    }

                    // unset env nbm user
                    $this->unset_user_on_env_nbm();
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
            $this->end_users_env_nbm();

            $this->display_counter_info();

            (new UserMailNotificationRepository($this->conn))->massUpdates(
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

        return $check_key_treated;
    }

    /*
     * Unsubscribe notification by mail
     *
     * check_key list where action will be done
     *
     * @return check_key list treated
     */
    public function unsubscribe_notification_by_mail($is_admin_request, $check_key_list = [])
    {
        return $this->do_subscribe_unsubscribe_notification_by_mail($is_admin_request, false, $check_key_list);
    }

    /*
     * Subscribe notification by mail
     *
     * check_key list where action will be done
     *
     * @return check_key list treated
     */
    public function subscribe_notification_by_mail($is_admin_request, $check_key_list = [])
    {
        return $this->do_subscribe_unsubscribe_notification_by_mail($is_admin_request, true, $check_key_list);
    }

    /*
     * Do timeout treatment in order to finish to send mails
     *
     * @param $post_keyname: key of check_key post array
     * @param check_key_treated: array of check_key treated
     * @return none
     */
    public function do_timeout_treatment($post_keyname, $check_key_treated = [])
    {
        global $page;

        if ($this->env['is_sendmail_timeout']) {
            if (isset($_POST[$post_keyname])) {
                $post_count = count($_POST[$post_keyname]);
                $treated_count = count($check_key_treated);
                if ($treated_count != 0) {
                    $time_refresh = ceil((microtime(true) - $this->env['start_time']) * $post_count / $treated_count);
                } else {
                    $time_refresh = 0;
                }
                $_POST[$post_keyname] = array_diff($_POST[$post_keyname], $check_key_treated);

                $this->must_repost = true;
                $page['errors'][] = \Phyxo\Functions\Language::l10n_dec(
                    'Execution time is out, treatment must be continue [Estimated time: %d second].',
                    'Execution time is out, treatment must be continue [Estimated time: %d seconds].',
                    $time_refresh
                );
            }
        }
    }

    // Inserting News users
    public function insert_new_data_user_mail_notification()
    {
        global $page, $base_url;

        // null mail_address are not selected in the list
        $result = (new UserRepository($this->conn))->findUsersWithNoMailNotificationInfos();
        if ($this->conn->db_num_rows($result) > 0) {
            $inserts = [];
            $check_key_list = [];

            while ($nbm_user = $this->conn->db_fetch_assoc($result)) {
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
            (new UserMailNotificationRepository($this->conn))->massInserts(['user_id', 'check_key', 'enabled'], $inserts);
            // Update field enabled with specific function
            $check_key_treated = $this->do_subscribe_unsubscribe_notification_by_mail(
                true,
                $this->conf['nbm_default_value_user_enabled'],
                $check_key_list
            );

            // On timeout simulate like tabsheet send
            if ($this->env['is_sendmail_timeout']) {
                $check_key_list = array_diff($check_key_list, $check_key_treated);
                if (count($check_key_list) > 0) {
                    (new UserMailNotificationRepository($this->conn))->deleteByCheckKeys($check_key_list);

                    // Redirect
                }
            }
        }
    }

    /*
     * Apply global functions to mail content
     * return customize mail content rendered
     */
    public function render_global_customize_mail_content($customize_mail_content)
    {
        // @TODO : find a better way to detect html or remove test
        if ($this->conf['nbm_send_html_mail'] and !(strpos($customize_mail_content, '<') === 0)) {
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
    public function do_action_send_mail_notification($action = 'list_to_send', $check_key_list = [], $customize_mail_content = '')
    {
        global $page;

        $return_list = [];

        if (in_array($action, ['list_to_send', 'send'])) {
            $dbnow = (new BaseRepository($this->conn))->getNow();

            $is_action_send = ($action == 'send');

            // disabled and null mail_address are not selected in the list
            $data_users = $this->get_user_notifications('send', $check_key_list);

            // List all if it's define on options or on timeout
            $is_list_all_without_test = ($this->env['is_sendmail_timeout'] || $this->conf['nbm_list_all_enabled_users_to_send']);

            // Check if exist news to list user or send mails
            if ((!$is_list_all_without_test) or ($is_action_send)) {
                if (count($data_users) > 0) {
                    $datas = [];

                    if (!isset($customize_mail_content)) {
                        $customize_mail_content = $this->conf['nbm_complementary_mail_content'];
                    }

                    $customize_mail_content = \Phyxo\Functions\Plugin::trigger_change('nbm_render_global_customize_mail_content', $customize_mail_content);

                    // Prepare message after change language
                    if ($is_action_send) {
                        $msg_break_timeout = \Phyxo\Functions\Language::l10n('Time to send mail is limited. Others mails are skipped.');
                    } else {
                        $msg_break_timeout = \Phyxo\Functions\Language::l10n('Prepared time for list of users to send mail is limited. Others users are not listed.');
                    }

                    // Begin nbm users environment
                    $this->begin_users_env_nbm($is_action_send);

                    foreach ($data_users as $nbm_user) {
                        if ((!$is_action_send) && $this->check_sendmail_timeout()) {
                            // Stop fill list on 'list_to_send', if the quota is override
                            $page['infos'][] = $msg_break_timeout;
                            break;
                        }
                        if (($is_action_send) && $this->check_sendmail_timeout()) {
                            // Stop fill list on 'send', if the quota is override
                            $page['errors'][] = $msg_break_timeout;
                            break;
                        }

                        // set env nbm user
                        $this->set_user_on_env_nbm($nbm_user, $is_action_send);

                        if ($is_action_send) {
                            // Fill return list of "treated" check_key for 'send'
                            $return_list[] = $nbm_user['check_key'];

                            if ($this->conf['nbm_send_detailed_content']) {
                                $news = $this->news($nbm_user['last_send'], $dbnow, false, $this->conf['nbm_send_html_mail']);
                                $exist_data = count($news) > 0;
                            } else {
                                $exist_data = $this->news_exists($nbm_user['last_send'], $dbnow);
                            }

                            if ($exist_data) {
                                $subject = '[' . $this->conf['gallery_title'] . '] ' . \Phyxo\Functions\Language::l10n('New photos added');

                                // Assign current var for nbm mail
                                $this->assign_vars_nbm_mail_content($nbm_user);

                                if (!is_null($nbm_user['last_send'])) {
                                    $this->env['mail_template']->assign(
                                        'content_new_elements_between',
                                        [
                                            'DATE_BETWEEN_1' => $nbm_user['last_send'],
                                            'DATE_BETWEEN_2' => $dbnow,
                                        ]
                                    );
                                } else {
                                    $this->env['mail_template']->assign(
                                        'content_new_elements_single',
                                        ['DATE_SINGLE' => $dbnow]
                                    );
                                }

                                if ($this->conf['nbm_send_detailed_content']) {
                                    $this->env['mail_template']->assign('global_new_lines', $news);
                                }

                                $nbm_user_customize_mail_content = \Phyxo\Functions\Plugin::trigger_change(
                                    'nbm_render_user_customize_mail_content',
                                    $customize_mail_content,
                                    $nbm_user
                                );
                                if (!empty($nbm_user_customize_mail_content)) {
                                    $this->env['mail_template']->assign('custom_mail_content', $nbm_user_customize_mail_content);
                                }

                                if ($this->conf['nbm_send_html_mail'] && $this->conf['nbm_send_recent_post_dates']) {
                                    $recent_post_dates = $this->get_recent_post_dates_array(
                                        $this->conf['recent_post_dates']['NBM']
                                    );
                                    foreach ($recent_post_dates as $date_detail) {
                                        $this->env['mail_template']->append(
                                            'recent_posts',
                                            [
                                                'TITLE' => $this->get_title_recent_post_date($date_detail),
                                                'HTML_DATA' => $this->get_html_description_recent_post_date($date_detail, $this->conf['picture_ext'])
                                            ]
                                        );
                                    }
                                }

                                $this->env['mail_template']->assign(
                                    [
                                        'GOTO_GALLERY_TITLE' => $this->conf['gallery_title'],
                                        'GOTO_GALLERY_URL' => $this->router->generate('homepage'),
                                        'SEND_AS_NAME' => $this->env['send_as_name'],
                                    ]
                                );

                                $ret = \Phyxo\Functions\Mail::mail(
                                    [
                                        'name' => stripslashes($nbm_user['username']),
                                        'email' => $nbm_user['mail_address'],
                                    ],
                                    [
                                        'from' => $this->env['send_as_mail_formated'],
                                        'subject' => $subject,
                                        'email_format' => $this->env['email_format'],
                                        'content' => $this->env['mail_template']->parse('notification_by_mail', true),
                                        'content_format' => $this->env['email_format'],
                                    ]
                                );

                                if ($ret) {
                                    $this->inc_mail_sent_success($nbm_user);

                                    $datas[] = [
                                        'user_id' => $nbm_user['user_id'],
                                        'last_send' => $dbnow
                                    ];
                                } else {
                                    $this->inc_mail_sent_failed($nbm_user);
                                }
                            }
                        } else {
                            if ($this->news_exists($nbm_user['last_send'], $dbnow)) {
                                // Fill return list of "selected" users for 'list_to_send'
                                $return_list[] = $nbm_user;
                            }
                        }

                        // unset env nbm user
                        $this->unset_user_on_env_nbm();
                    }

                    // Restore nbm environment
                    $this->end_users_env_nbm();

                    if ($is_action_send) {
                        (new UserMailNotificationRepository($this->conn))->massUpdates(
                            [
                                'primary' => ['user_id'],
                                'update' => ['last_send']
                            ],
                            $datas
                        );

                        $this->display_counter_info();
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
