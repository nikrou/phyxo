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

use App\DataMapper\AlbumMapper;
use App\DataMapper\ImageMapper;
use App\Repository\CommentRepository;
use App\Repository\UserMailNotificationRepository;
use App\Repository\UserInfosRepository;
use App\DataMapper\UserMapper;
use App\Entity\UserMailNotification;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\SrcImage;
use Phyxo\EntityManager;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class Notification
{
    private $conf, $userMapper, $albumMapper, $router, $commentRepository, $imageMapper;
    private $env, $mailer, $translator, $userMailNotificationRepository, $userInfosRepository;

    private $infos = [], $errors = [];

    public function __construct(Conf $conf, UserMapper $userMapper, AlbumMapper $albumMapper, RouterInterface $router, ImageMapper $imageMapper,
                                MailerInterface $mailer, TranslatorInterface $translator, CommentRepository $commentRepository,
                                UserMailNotificationRepository $userMailNotificationRepository, UserInfosRepository $userInfosRepository)
    {
        $this->conf = $conf;
        $this->userMapper = $userMapper;
        $this->albumMapper = $albumMapper;
        $this->router = $router;
        $this->mailer = $mailer;
        $this->translator = $translator;
        $this->userMailNotificationRepository = $userMailNotificationRepository;
        $this->userInfosRepository = $userInfosRepository;
        $this->commentRepository = $commentRepository;
        $this->imageMapper = $imageMapper;

        $this->env = [
            'start_time' => microtime(true),
            'sendmail_timeout' => (intval(ini_get('max_execution_time')) * $conf['nbm_max_treatment_timeout_percent']),
            'is_sendmail_timeout' => false
        ];

        if ((!isset($this->env['sendmail_timeout'])) || (!is_numeric($this->env['sendmail_timeout'])) || ($this->env['sendmail_timeout'] <= 0)) {
            $this->env['sendmail_timeout'] = $conf['nbm_treatment_timeout_default'];
        }
    }

    /**
     * Returns number of new comments between two dates.
     */
    public function nb_new_comments(\DateTimeInterface $start = null, \DateTimeInterface $end = null): int
    {
        return $this->commentRepository->getNewComments($this->userMapper->getUser()->getForbiddenCategories(), $start, $end, $count_only = true);
    }

    /**
     * Returns new comments between two dates.
     */
    public function new_comments(\DateTimeInterface $start = null, \DateTimeInterface $end = null): array
    {
        return $this->commentRepository->getNewComments($this->userMapper->getUser()->getForbiddenCategories(), $start, $end);
    }

    /**
     * Returns number of unvalidated comments between two dates.
     */
    public function nb_unvalidated_comments(\DateTimeInterface $start = null, \DateTimeInterface $end = null): int
    {
        return $this->commentRepository->getUnvalidatedComments($start, $end, $count_only = true);
    }

    /**
     * Returns number of new photos between two dates.
     */
    public function nb_new_elements(\DateTimeInterface $start = null, \DateTimeInterface $end = null): int
    {
        return $this->imageMapper->getRepository()->getNewElements($this->userMapper->getUser()->getForbiddenCategories(), $start, $end, $count_only = true);
    }

    /**
     * Returns new photos between two dates
     */
    public function new_elements(\DateTimeInterface $start = null, \DateTimeInterface $end = null): array
    {
        return $this->imageMapper->getRepository()->getNewElements($this->userMapper->getUser()->getForbiddenCategories(), $start, $end);
    }

    /**
     * Returns number of updated albums between two dates.
     */
    public function nb_updated_categories(\DateTimeInterface $start = null, \DateTimeInterface $end = null): int
    {
        return $this->imageMapper->getRepository()->getUpdatedAlbums($this->userMapper->getUser()->getForbiddenCategories(), $start, $end, $count_only = true);
    }

    /**
     * Returns updated categories between two dates.
     */
    public function updated_categories(\DateTimeInterface $start = null, \DateTimeInterface $end = null): array
    {
        return $this->imageMapper->getRepository()->getUpdatedAlbums($this->userMapper->getUser()->getForbiddenCategories(), $start, $end);
    }

    /**
     * Returns number of new users between two dates.
     */
    public function nb_new_users(\DateTimeInterface $start = null, \DateTimeInterface $end = null): int
    {
        return $this->userInfosRepository->countNewUsers($start, $end);
    }

    /**
     * Returns new users between two dates.
     */
    public function new_users(\DateTimeInterface $start = null, \DateTimeInterface $end = null)
    {
        return $this->userInfosRepository->getNewUsers($start, $end);
    }

    /**
     * Returns if there was new activity between two dates.
     *
     * Takes in account: number of new comments, number of new elements, number of
     * updated categories. Administrators are also informed about: number of
     * unvalidated comments, number of new users.
     * @todo number of unvalidated elements
     */
    public function news_exists(\DateTimeInterface $start = null, \DateTimeInterface $end = null): bool
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
        $dates = $this->imageMapper->getRepository()->getRecentPostedImages($this->userMapper->getUser()->getForbiddenCategories(), $max_dates);

        for ($i = 0; $i < count($dates); $i++) {
            if ($max_elements > 0) { // get some thumbnails ...

                $dates[$i]['elements'] = $this->imageMapper->getRepository()->findRandomImages($this->userMapper->getUser()->getForbiddenCategories(), $max_elements);
            }

            if ($max_cats > 0) { // get some albums ...
                $dates[$i]['categories'] = $this->imageMapper->getRepository()->getRecentImages($this->userMapper->getUser()->getForbiddenCategories(), $dates[$i]['date_available'], $max_cats);
            }
        }

        return $dates;
    }

    /**
     * Returns information about recently published elements grouped by post date.
     * Same as get_recent_post_dates() but parameters as an indexed array.
     * @see get_recent_post_dates()
     */
    public function get_recent_post_dates_array(array $args): array
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
                . $this->albumMapper->getAlbumsDisplayNameCache($cat['uppercats'])
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
        $english_months = [1 => "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

        $date = $date_detail['date_available'];
        $exploded_date = strptime($date, '%Y-%m-%d %H:%M:%S');

        $title = $this->translator->trans('number_of_new_photos', ['count' => $date_detail['nb_elements']]);
        $title .= ' (' . $this->translator->trans($english_months[1 + $exploded_date['tm_mon']]) . ' ' . $exploded_date['tm_mday'] . ')';

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
     * Execute all main queries to get list of user
     *
     * Type are the type of list 'subscribe', 'send'
     *
     * return array of users
     */
    public function get_user_notifications($action, $check_key_list = [], ?bool $enabled_filter_value = null)
    {
        if (in_array($action, ['subscribe', 'send'])) {
            if ($action == 'send') {
                $orders = ['n.last_send', 'u.username'];
            } else {
                $orders = ['u.username'];
            }

            return $this->userMailNotificationRepository->findInfosForUsers(($action === 'send'), $enabled_filter_value, $check_key_list, $orders);
        } else {
            return [];
        }
    }

    /*
     * Begin of use nbm environment
     * Prepare and save current environment and initialize data in order to send mail
     *
     * Return none
     */
    public function begin_users_env_nbm($is_to_send_mail = false)
    {
        // Save $user, $lang_info and $lang arrays
        $this->env['save_user'] = $this->userMapper->getUser();
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

            $this->env['send_as_mail_address'] = $this->userMapper->getWebmaster()->getMailAddress();
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
        $this->infos[] = sprintf($this->env['msg_info'], $nbm_user->getUser()->getUsername(), $nbm_user->getUser()->getMailAddress());
    }

    /*
     * Inc Counter failed
     *
     * Return none
     */
    public function inc_mail_sent_failed($nbm_user)
    {
        $this->env['error_on_mail_count'] += 1;
        $this->errors[] = sprintf($this->env['msg_error'], $nbm_user->getUser()->getUsername(), $nbm_user->getUser()->getMailAddress());
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
            'USERNAME' => $nbm_user->getUser()->getUsername(),
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
    public function do_subscribe_unsubscribe_notification_by_mail($is_admin_request, bool $is_subscribe = false, $check_key_list = [])
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

        if (count($check_key_list) > 0) {
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
                $check_key_treated[] = $nbm_user->getCheckKey();

                $do_update = true;
                if ($nbm_user->getUser()->getMailAddress()) {
                    $subject = ($is_subscribe ? $this->translator->trans('Subscribe to notification by mail') : $this->translator->trans('Unsubscribe from notification by mail'));

                    $mail_params = [];
                    $mail_params = $this->assign_vars_nbm_mail_content($nbm_user);

                    $section_action_by = ($is_subscribe ? 'subscribe_by_' : 'unsubscribe_by_');
                    $section_action_by .= ($is_admin_request ? 'admin' : 'himself');

                    $mail_params[$section_action_by] = true;

                    try {
                        $this->sendMail(
                            [
                                'name' => $nbm_user->getUser()->getUsername(),
                                'email' => $nbm_user->getUser()->getMailAddress(),
                            ],
                            [
                                'name' => $this->env['send_as_name'],
                                'email' => $this->env['send_as_mail_address']
                            ],
                            $subject,
                            $mail_params
                        );
                        $this->inc_mail_sent_success($nbm_user);
                    } catch (\Exception $e) {
                        $this->inc_mail_sent_failed($nbm_user);
                        $do_update = false;
                    }
                }

                if ($do_update) {
                    $nbm_user->setEnabled($is_subscribe);
                    $this->userMailNotificationRepository->addOrUpdateUserMailNotification($nbm_user);
                    $updated_data_count += 1;
                    $this->infos[] = sprintf($msg_info, $nbm_user->getUser()->getUsername(), $nbm_user->getUser()->getMailAddress());
                } else {
                    $error_on_updated_data_count += 1;
                    $this->errors[] = sprintf($msg_error, $nbm_user->getUser()->getUsername(), $nbm_user->getUser()->getMailAddress());
                }
            }

            $this->display_counter_info();
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

                $this->errors[] = $this->translator->trans('execution_timeout_in_seconds', ['count' => $time_refresh]);
            }
        }
    }

    // Inserting News users
    public function insert_new_data_user_mail_notification()
    {
        $new_users = $this->userMailNotificationRepository->findUsersWithNoMailNotificationInfos();

        // null mail_address are not selected in the list
        if (count($new_users) > 0) {
            $check_key_list = [];

            foreach ($new_users as $new_user) {
                $nbm_user = new UserMailNotification();
                $nbm_user->setCheckKey(Utils::generate_key(16));
                $check_key_list[] = $nbm_user->getCheckKey();
                $nbm_user->setEnabled(false);
                $nbm_user->setUser($new_user);

                $this->userMailNotificationRepository->addOrUpdateUserMailNotification($nbm_user);

                $this->infos[] = $this->translator->trans('User {username} [{mail_address] added.', ['username' => $new_user->getUsername(), 'mail_address' => $new_user->getMailAddress()]);
            }

            // Update field enabled with specific function
            $check_key_treated = $this->do_subscribe_unsubscribe_notification_by_mail(true, $this->conf['nbm_default_value_user_enabled'], $check_key_list);

            // On timeout simulate like tabsheet send
            if ($this->env['is_sendmail_timeout']) {
                $check_key_list = array_diff($check_key_list, $check_key_treated);
                if (count($check_key_list) > 0) {
                    $this->userMailNotificationRepository->deleteByCheckKeys($check_key_list);
                }
            }
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

        if (!in_array($action, ['list_to_send', 'send'])) {
            return [];
        }

        $now = new \DateTime();

        $is_action_send = ($action == 'send');

        // disabled and null mail_address are not selected in the list
        $data_users = $this->get_user_notifications('send', $check_key_list);

        // List all if it's define on options or on timeout
        $is_list_all_without_test = ($this->env['is_sendmail_timeout'] || $this->conf['nbm_list_all_enabled_users_to_send']);

        // Check if exist news to list user or send mails
        if ((!$is_list_all_without_test) || ($is_action_send)) {
            if (count($data_users) > 0) {
                if (!isset($customize_mail_content)) {
                    $customize_mail_content = $this->conf['nbm_complementary_mail_content'];
                }

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
                        $return_list[] = $nbm_user->getCheckKey();

                        if ($this->conf['nbm_send_detailed_content']) {
                            $news = $this->news($nbm_user->getLastSend(), $now, false, $this->conf['nbm_send_html_mail']);
                            $exist_data = count($news) > 0;
                        } else {
                            $exist_data = $this->news_exists($nbm_user->getLastSend(), $now);
                        }

                        if ($exist_data) {
                            $subject = $this->translator->trans('New photos added');

                            // Assign current var for nbm mail
                            $tpl_params = $this->assign_vars_nbm_mail_content($nbm_user);

                            if (!is_null($nbm_user->getLastSend())) {
                                $tpl_params['content_new_elements_between'] = [
                                    'DATE_BETWEEN_1' => $nbm_user->getLastSend(),
                                    'DATE_BETWEEN_2' => $now,
                                ];
                            } else {
                                $tpl_params['content_new_elements_single'] = ['DATE_SINGLE' => $now];
                            }

                            if ($this->conf['nbm_send_detailed_content']) {
                                $tpl_params['global_new_lines'] = $news;
                            }

                            $nbm_user_customize_mail_content = $customize_mail_content;

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

                            try {
                                $this->sendMail(
                                    [
                                        'name' => $nbm_user->getUser()->getUsername(),
                                        'email' => $nbm_user->getUser()->getMailAddress(),
                                    ],
                                    [
                                        'name' => $this->env['send_as_name'],
                                        'email' => $this->env['send_as_mail_address']
                                    ],
                                    $subject,
                                    $tpl_params
                                );
                                $this->inc_mail_sent_success($nbm_user);

                                $nbm_user->setLastSend($now);
                                $this->userMailNotificationRepository->addOrUpdateUserMailNotification($nbm_user);
                            } catch (\Exception $e) {
                                $this->inc_mail_sent_failed($nbm_user);
                            }
                        }
                    } else {
                        if ($this->news_exists($nbm_user->getLastSend(), $now)) {
                            // Fill return list of "selected" users for 'list_to_send'
                            $return_list[] = $nbm_user;
                        }
                    }
                }

                if ($is_action_send) {
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


        // Return list of "selected" users for 'list_to_send'
        // Return list of "treated" check_key for 'send'
        return $return_list;
    }

    protected function sendMail(array $to, array $from, string $subject, array $params): void
    {
        $tpl_params = [
            'MAIL_TITLE' => $subject,
            'MAIL_THEME' => $this->conf['mail_theme'],
            'GALLERY_TITLE' => $this->conf['gallery_title'],
            'GALLERY_URL' => $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'LEVEL_SEPARATOR' => $this->conf['level_separator'],
        ];

        $tpl_params = array_merge($tpl_params, $params);

        $message = (new TemplatedEmail())
                ->subject('[' . $this->conf['gallery_title'] . '] ' . $subject)
                ->to(new Address($to['email'], $to['name']))
                ->textTemplate('mail/text/notification.text.twig')
                ->htmlTemplate('mail/html/notification.html.twig')
                ->context($tpl_params);

        $message->from(new Address($from['email'], $from['name']));
        $message->replyTo(new Address($from['email'], $from['name']));

        $this->mailer->send($message);
    }
}
