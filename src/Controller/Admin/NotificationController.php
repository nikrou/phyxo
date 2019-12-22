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

namespace App\Controller\Admin;

use App\Notification;
use App\Repository\ConfigRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\TabSheet\TabSheet;
use Phyxo\Template\Template;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class NotificationController extends AdminCommonController
{
    private $translator;

    protected function setTabsheet(string $section = 'params'): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('params', $this->translator->trans('Parameters', [], 'admin'), $this->generateUrl('admin_notification'));
        $tabsheet->add('subscribe', $this->translator->trans('Subscribe', [], 'admin'), $this->generateUrl('admin_notification_subscribe'));
        $tabsheet->add('send', $this->translator->trans('Send', [], 'admin'), $this->generateUrl('admin_notification_send'));
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function params(Request $request, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params, Notification $notification, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST')) {
            $updated_param_count = 0;
            $result = $em->getRepository(ConfigRepository::class)->findAll('param like \'nbm\\_%\'');
            while ($nbm_user = $em->getConnection()->db_fetch_assoc($result)) {
                if ($request->request->get($nbm_user['param'])) {
                    $new_value = $request->request->get($nbm_user['param']);
                    if ($conf[$nbm_user['param']] !== $new_value) {
                        $conf[$nbm_user['param']] = $new_value;
                    }
                }
            }

            $this->addFlash('info', $translator->trans('Your configuration settings have been saved', [], 'admin'));

            return $this->redirectToRoute('admin_notification');
        } else {
            $notification->insert_new_data_user_mail_notification();
        }

        $tpl_params['SEND_HTML_MAIL'] = $conf['nbm_send_html_mail'];
        $tpl_params['SEND_MAIL_AS'] = $conf['nbm_send_mail_as'];
        $tpl_params['SEND_DETAILED_CONTENT'] = $conf['nbm_send_detailed_content'];
        $tpl_params['COMPLEMENTARY_MAIL_CONTENT'] = $conf['nbm_complementary_mail_content'];
        $tpl_params['SEND_RECENT_POST_DATES'] = $conf['nbm_send_recent_post_dates'];
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_notification');

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_notification');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Notification', [], 'admin');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('params'), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_notification');

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('notification_by_mail_params.tpl', $tpl_params);
    }

    public function subscribe(Request $request, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params, Notification $notification, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->request->get('falsify') && $request->request->get('cat_true')) {
            $check_key_treated = $notification->unsubscribe_notification_by_mail(true, $request->request->get('cat_true'));
            $notification->do_timeout_treatment('cat_true', $check_key_treated);
        } elseif ($request->request->get('trueify') && $request->request->get('cat_false')) {
            $check_key_treated = $notification->subscribe_notification_by_mail(true, $request->request->get('cat_false'));
            $notification->do_timeout_treatment('cat_false', $check_key_treated);
        }

        $data_users = $notification->get_user_notifications('subscribe');

        $opt_true = [];
        $opt_true_selected = [];
        $opt_false = [];
        $opt_false_selected = [];
        foreach ($data_users as $nbm_user) {
            if ($em->getConnection()->get_boolean($nbm_user['enabled'])) {
                $opt_true[$nbm_user['check_key']] = stripslashes($nbm_user['username']) . '[' . $nbm_user['mail_address'] . ']';
                if ($request->request->get('falsify') && $request->request->get('cat_true') && in_array($nbm_user['check_key'], $request->request->get('cat_true'))) {
                    $opt_true_selected[] = $nbm_user['check_key'];
                }
            } else {
                $opt_false[$nbm_user['check_key']] = stripslashes($nbm_user['username']) . '[' . $nbm_user['mail_address'] . ']';
                if ($request->request->get('trueify') && $request->request->get('cat_false') && in_array($nbm_user['check_key'], $request->request->get('cat_false'))) {
                    $opt_false_selected[] = $nbm_user['check_key'];
                }
            }
        }

        $tpl_params['L_CAT_OPTIONS_TRUE'] = $translator->trans('Subscribed', [], 'admin');
        $tpl_params['L_CAT_OPTIONS_FALSE'] = $translator->trans('Unsubscribed', [], 'admin');

        $tpl_params['category_option_true'] = $opt_true;
        $tpl_params['category_option_true_selected'] = $opt_true_selected;
        $tpl_params['category_option_false'] = $opt_false;
        $tpl_params['category_option_false_selected'] = $opt_false_selected;
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_notification_subscribe');

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_notification');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Notification', [], 'admin');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('subscribe'), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_notification');

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('notification_by_mail_subscribe.tpl', $tpl_params);
    }

    public function send(Request $request, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params, Notification $notification, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;
        $must_repost = false;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $conf_derivatives = unserialize(base64_decode($conf['derivatives']));

        if ($request->isMethod('POST')) {
            $check_key_treated = $notification->do_action_send_mail_notification(
                'send',
                $request->request->get('send_selection'),
                htmlentities($request->request->get('send_customize_mail_content'), ENT_QUOTES, 'utf-8'),
                $conf_derivatives
            );
            $notification->do_timeout_treatment('send_selection', $check_key_treated);

            if ($request->request->get('send_customize_mail_content')) {
                $tpl_var['CUSTOMIZE_MAIL_CONTENT'] = htmlentities($request->request->get('send_customize_mail_content'), ENT_QUOTES, 'utf-8');
            } else {
                $tpl_var['CUSTOMIZE_MAIL_CONTENT'] = $conf['nbm_complementary_mail_content'];
            }
        }

        $tpl_var = ['users' => []];
        $data_users = $notification->do_action_send_mail_notification('list_to_send', [], '', $conf_derivatives);

        if (count($data_users) > 0) {
            foreach ($data_users as $nbm_user) {
                if ((!$must_repost) || (($must_repost) && in_array($nbm_user['check_key'], $request->request->get('send_selection')))) {
                    $tpl_var['users'][] = [
                        'ID' => $nbm_user['check_key'],
                        'CHECKED' => ( // not check if not selected,  on init select<all
                        isset($_POST['send_selection']) && // not init
                        !in_array($nbm_user['check_key'], $request->request->get('send_selection')) // not selected
                        ) ? '' : 'checked="checked"',
                        'USERNAME' => stripslashes($nbm_user['username']),
                        'EMAIL' => $nbm_user['mail_address'],
                        'LAST_SEND' => $nbm_user['last_send']
                    ];
                }
            }
        }

        $tpl_params['send'] = $tpl_var;

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_notification');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Notification', [], 'admin');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('send'), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_notification');

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('notification_by_mail_send.tpl', $tpl_params);
    }
}
