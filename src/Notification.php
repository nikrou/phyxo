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
use App\Security\UserProvider;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\SrcImage;
use Phyxo\EntityManager;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Conf;
use Phyxo\Functions\Language;
use Phyxo\Template\AdminTemplate;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class Notification
{
    private $em, $conn, $conf, $userMapper, $categoryMapper, $router;
    private $env, $must_repost, $userProvider, $template, $phyxoVersion, $phyxoWebsite, $mailer, $translator;

    private $infos = [], $errors = [];

    public function __construct(EntityManager $em, Conf $conf, UserMapper $userMapper, CategoryMapper $categoryMapper, RouterInterface $router, UserProvider $userProvider,
                                AdminTemplate $template, string $phyxoVersion, string $phyxoWebsite, \Swift_Mailer $mailer, TranslatorInterface $translator)
    {
        $this->em = $em;
        $this->conn = $em->getConnection();
        $this->conf = $conf;
        $this->userMapper = $userMapper;
        $this->categoryMapper = $categoryMapper;
        $this->router = $router;
        $this->userProvider = $userProvider;
        $this->template = $template;
        $this->phyxoVersion = $phyxoVersion;
        $this->phyxoWebsite = $phyxoWebsite;
        $this->mailer = $mailer;
        $this->translator = $translator;

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
    public function add_news_line(&$news, $count, string $lang_key, string $url = '', bool $add_url = false)
    {
        if ($count > 0) {
            $line = $this->translator->trans($lang_key, ['count' => $count]);
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
            $this->add_news_line(
                $news,
                $this->nb_new_elements($start, $end),
                'number_of_new_photos',
                $this->router->generate('recent_pics', [], UrlGeneratorInterface::ABSOLUTE_URL),
                $add_url
            );
        }

        if (!$exclude_img_cats) {
            $this->add_news_line(
                $news,
                $this->nb_updated_categories($start, $end),
                'number_of_albums_updated',
                $this->router->generate('recent_cats', [], UrlGeneratorInterface::ABSOLUTE_URL),
                $add_url
            );
        }

        $this->add_news_line(
            $news,
            $this->nb_new_comments($start, $end),
            'number_of_new_comments',
            $this->router->generate('comments', [], UrlGeneratorInterface::ABSOLUTE_URL),
            $add_url
        );

        if ($this->userMapper->isAdmin()) {
            $this->add_news_line(
                $news,
                $this->nb_unvalidated_comments($start, $end),
                'number_of_new_comments_to_validate',
                $this->router->generate('admin_comments', [], UrlGeneratorInterface::ABSOLUTE_URL), $add_url
            );

            $this->add_news_line(
                $news,
                $this->nb_new_users($start, $end),
                'number_of_new_users',
                $this->router->generate('admin_users', [], UrlGeneratorInterface::ABSOLUTE_URL),
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
     */
    public function get_recent_post_dates(int $max_dates, int $max_elements, int $max_cats): array
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
            . $this->translator->trans('number_of_new_photos', ['count' => $date_detail['nb_elements']])
            . ' ('
            . '<a href="' . $this->router->generate('recent_pics', [], UrlGeneratorInterface::ABSOLUTE_URL) . '">'
            . $this->translator->trans('Recent photos') . '</a>'
            . ')'
            . '</li><br>';

        $image_std_params = new ImageStandardParams($this->conf);
        $params = $image_std_params->getByType(ImageStandardParams::IMG_THUMB);

        foreach ($date_detail['elements'] as $element) {
            $tn_src = (new DerivativeImage(new SrcImage($element, $picture_ext), $params, $image_std_params))->getUrl();
            $description .= '<a href="';
            $description .= $this->router->generate(
                'picture',
                ['image_id' => $element['id'], 'type' => 'file', 'element_id' => $element['file']],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $description .= '"><img src="' . $tn_src . '"></a>';
        }
        $description .= '...<br>';

        $description .=
            '<li>'
            . $this->translator->trans('number_of_albums_updated', ['count' => $date_detail['nb_cats']])
            . '</li>';

        $description .= '<ul>';
        foreach ($date_detail['categories'] as $cat) {
            $description .=
                '<li>'
                . $this->categoryMapper->getCatDisplayNameCache($cat['uppercats'])
                . ' (' . $this->translator->trans('number_of_new_photos', ['count' => $cat['img_count']]) . ')'
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

        $title = $this->translator->trans('number_of_new_photos', ['count' => $date_detail['nb_elements']]);
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
                ($action === 'send'),
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

        // Save $user, $lang_info and $lang arrays
        $this->env['save_user'] = $user;
        // Save current language to stack, necessary because $user change during NBM
        //\Phyxo\Functions\Mail::switch_lang_to($user['language']);

        $this->env['is_to_send_mail'] = $is_to_send_mail;

        if ($is_to_send_mail) {
            // Init mail configuration
            if (!empty($this->conf['nbm_send_mail_as'])) {
                $this->env['send_as_name'] = $this->conf['nbm_send_mail_as'];
            } else {
                if (!empty($this->conf['mail_sender_name'])) {
                    $this->env['send_as_name'] = $this->conf['mail_sender_name'];
                } else {
                    $this->env['send_as_name'] = $this->conf['gallery_title'];
                }
            }

            $this->env['send_as_mail_address'] = $this->userMapper->getWebmasterEmail();
            // Init mail counter
            $this->env['error_on_mail_count'] = 0;
            $this->env['sent_mail_count'] = 0;
            // Save sendmail message info and error in the original language
            $this->env['msg_info'] = $this->translator->trans('Mail sent to %s [%s].');
            $this->env['msg_error'] = $this->translator->trans('Error when sending email to %s [%s].');
        }
    }

    /*
     * Inc Counter success
     *
     * Return none
     */
    public function inc_mail_sent_success($nbm_user)
    {
        $this->env['sent_mail_count'] += 1;
        $this->infos[] = sprintf($this->env['msg_info'], stripslashes($nbm_user['username']), $nbm_user['mail_address']);
    }

    /*
     * Inc Counter failed
     *
     * Return none
     */
    public function inc_mail_sent_failed($nbm_user)
    {
        $this->env['error_on_mail_count'] += 1;
        $this->errors[] = sprintf($this->env['msg_error'], stripslashes($nbm_user['username']), $nbm_user['mail_address']);
    }

    /*
     * Display Counter Info
     *
     * Return none
     */
    public function display_counter_info()
    {
        if ($this->env['error_on_mail_count'] != 0) {
            $this->errors[] = $this->translator->trans('number_of_mails_not_sent', ['count' => $this->env['error_on_mail_count']]);

            if ($this->env['sent_mail_count'] != 0) {
                $this->infos[] = $this->translator->trans('number_of_mails_sent', ['count' => $this->env['sent_mail_count']]);
            }
        } else {
            if ($this->env['sent_mail_count'] == 0) {
                $this->infos[] = $this->translator->trans('No mail to send.');
            } else {
                $this->infos[] = $this->translator->trans('number_of_mails_sent', ['count' => $this->env['sent_mail_count']]);
            }
        }
    }

    public function assign_vars_nbm_mail_content($nbm_user): array
    {
        return [
            'USERNAME' => $nbm_user['username'],
            'SEND_AS_NAME' => $this->env['send_as_name'],
            'UNSUBSCRIBE_LINK' => $this->router->generate('notification_unsubscribe', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'SUBSCRIBE_LINK' => $this->router->generate('notification_subscribe', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'CONTACT_EMAIL' => $this->env['send_as_mail_address']
        ];
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
        $check_key_treated = [];
        $updated_data_count = 0;
        $error_on_updated_data_count = 0;

        if ($is_subscribe) {
            $msg_info = $this->translator->trans('User %s [%s] was added to the subscription list.');
            $msg_error = $this->translator->trans('User %s [%s] was not added to the subscription list.');
        } else {
            $msg_info = $this->translator->trans('User %s [%s] was removed from the subscription list.');
            $msg_error = $this->translator->trans('User %s [%s] was not removed from the subscription list.');
        }

        if (count($check_key_list) != 0) {
            $updates = [];
            $enabled_value = $this->conn->boolean_to_db($is_subscribe);
            $data_users = $this->get_user_notifications('subscribe', $check_key_list, !$is_subscribe);

            // Prepare message after change language
            $msg_break_timeout = $this->translator->trans('Time to send mail is limited. Others mails are skipped.');

            // Begin nbm users environment
            $this->begin_users_env_nbm(true);

            foreach ($data_users as $nbm_user) {
                if ($this->check_sendmail_timeout()) {
                    // Stop fill list on 'send', if the quota is override
                    $this->errors[] = $msg_break_timeout;
                    break;
                }

                // Fill return list
                $check_key_treated[] = $nbm_user['check_key'];

                $do_update = true;
                if ($nbm_user['mail_address'] != '') {
                    $subject = '[' . $this->conf['gallery_title'] . '] ' . ($is_subscribe ? $this->translator->trans('Subscribe to notification by mail') : $this->translator->trans('Unsubscribe from notification by mail'));

                    $mail_params = [];
                    $mail_params = $this->assign_vars_nbm_mail_content($nbm_user);

                    $section_action_by = ($is_subscribe ? 'subscribe_by_' : 'unsubscribe_by_');
                    $section_action_by .= ($is_admin_request ? 'admin' : 'himself');

                    $mail_params[$section_action_by] = true;

                    $ret = $this->sendMail(
                        [
                            'name' => $nbm_user['username'],
                            'email' => $nbm_user['mail_address'],
                        ],
                        [
                            'name' => $this->env['send_as_name'],
                            'email' => $this->env['send_as_mail_address']
                        ],
                        $subject,
                        $mail_params
                    );

                    if ($ret) {
                        $this->inc_mail_sent_success($nbm_user);
                    } else {
                        $this->inc_mail_sent_failed($nbm_user);
                        $do_update = false;
                    }
                }

                if ($do_update) {
                    $updates[] = [
                        'check_key' => $nbm_user['check_key'],
                        'enabled' => $enabled_value
                    ];
                    $updated_data_count += 1;
                    $this->infos[] = sprintf($msg_info, stripslashes($nbm_user['username']), $nbm_user['mail_address']);
                } else {
                    $error_on_updated_data_count += 1;
                    $this->errors[] = sprintf($msg_error, stripslashes($nbm_user['username']), $nbm_user['mail_address']);
                }
            }

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
            $this->infos[] = $this->translator->trans('number_of_users_updated', ['count' => $updated_data_count]);
        }

        if ($error_on_updated_data_count != 0) {
            $this->errors[] = $this->translator->trans('number_of_users_not_updated', ['count' => $error_on_updated_data_count]);
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
                $this->errors[] = $this->translator->trans('execution_timeout_in_seconds', ['count' => $time_refresh]);
            }
        }
    }

    // Inserting News users
    public function insert_new_data_user_mail_notification()
    {
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

                $this->infos[] = $this->translator->trans('User {username} [{mail_address] added.', ['username' => $nbm_user['username'], 'mail_address' => $nbm_user['mail_address']]);
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
                        $msg_break_timeout = $this->translator->trans('Time to send mail is limited. Others mails are skipped.');
                    } else {
                        $msg_break_timeout = $this->translator->trans('Prepared time for list of users to send mail is limited. Others users are not listed.');
                    }

                    // Begin nbm users environment
                    $this->begin_users_env_nbm($is_action_send);

                    foreach ($data_users as $nbm_user) {
                        if ((!$is_action_send) && $this->check_sendmail_timeout()) {
                            // Stop fill list on 'list_to_send', if the quota is override
                            $this->infos[] = $msg_break_timeout;
                            break;
                        }
                        if (($is_action_send) && $this->check_sendmail_timeout()) {
                            // Stop fill list on 'send', if the quota is override
                            $this->errors[] = $msg_break_timeout;
                            break;
                        }

                        if ($is_action_send) {
                            $tpl_params = [];

                            // Fill return list of "treated" check_key for 'send'
                            $return_list[] = $nbm_user['check_key'];

                            if ($this->conf['nbm_send_detailed_content']) {
                                $news = $this->news($nbm_user['last_send'], $dbnow, false, $this->conf['nbm_send_html_mail']);
                                $exist_data = count($news) > 0;
                            } else {
                                $exist_data = $this->news_exists($nbm_user['last_send'], $dbnow);
                            }

                            if ($exist_data) {
                                $subject = '[' . $this->conf['gallery_title'] . '] ' . $this->translator->trans('New photos added');

                                // Assign current var for nbm mail
                                $tpl_params = $this->assign_vars_nbm_mail_content($nbm_user);

                                if (!is_null($nbm_user['last_send'])) {
                                    $tpl_params['content_new_elements_between'] = [
                                        'DATE_BETWEEN_1' => $nbm_user['last_send'],
                                        'DATE_BETWEEN_2' => $dbnow,
                                    ];
                                } else {
                                    $tpl_params['content_new_elements_single'] = ['DATE_SINGLE' => $dbnow];
                                }

                                if ($this->conf['nbm_send_detailed_content']) {
                                    $tpl_params['global_new_lines'] = $news;
                                }

                                $nbm_user_customize_mail_content = \Phyxo\Functions\Plugin::trigger_change(
                                    'nbm_render_user_customize_mail_content',
                                    $customize_mail_content,
                                    $nbm_user
                                );
                                if (!empty($nbm_user_customize_mail_content)) {
                                    $tpl_params['custom_mail_content'] = $nbm_user_customize_mail_content;
                                }

                                if ($this->conf['nbm_send_html_mail'] && $this->conf['nbm_send_recent_post_dates']) {
                                    $recent_post_dates = $this->get_recent_post_dates_array(
                                        $this->conf['recent_post_dates']['NBM']
                                    );
                                    foreach ($recent_post_dates as $date_detail) {
                                        $tpl_params['recent_posts'][] = [
                                            'TITLE' => $this->get_title_recent_post_date($date_detail),
                                            'HTML_DATA' => $this->get_html_description_recent_post_date($date_detail, $this->conf['picture_ext'])
                                        ];
                                    }
                                }

                                $tpl_params['SEND_AS_NAME'] = $this->env['send_as_name'];

                                $ret = $this->sendMail(
                                    [
                                        'name' => $nbm_user['username'],
                                        'email' => $nbm_user['mail_address'],
                                    ],
                                    [
                                        'name' => $this->env['send_as_name'],
                                        'email' => $this->env['send_as_mail_address']
                                    ],
                                    $subject,
                                    $tpl_params
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
                    }

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
                        $this->errors[] = $this->translator->trans('No user to send notifications by mail.');
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

    protected function sendMail(array $to, array $from, string $subject, array $params)
    {
        $tpl_params = [
            'MAIL_TITLE' => $subject,
            'MAIL_THEME' => $this->conf['mail_theme'],
            'GALLERY_TITLE' => $this->conf['gallery_title'],
            'GALLERY_URL' => $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'LEVEL_SEPARATOR' => $this->conf['level_separator'],
            'CONTENT_ENCODING' => 'utf-8',
            'PHYXO_VERSION' => $this->conf['show_version'] ? $this->phyxoVersion : '',
            'PHYXO_URL' => $this->phyxoWebsite,
        ];

        $tpl_params = array_merge($tpl_params, $params);

        $message = (new \Swift_Message('[' . $this->conf['gallery_title'] . '] ' . $subject))
            ->addTo($to['email'], $to['name'])
            ->setBody($this->template->render('mail/text/notification.text.tpl', $tpl_params), 'text/plain')
            ->addPart($this->template->render('mail/html/notification.html.tpl', $tpl_params), 'text/html');

        $message->setFrom($from['email'], $from['name']);
        $message->setReplyTo($from['email'], $from['name']);

        return $this->mailer->send($message);
    }
}
