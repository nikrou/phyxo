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
use App\Security\UserProvider;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\TabSheet\TabSheet;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class NotificationController extends AdminCommonController
{
    private $translator, $authorizationChecker;
    private $conf_types = [
        'nbm_send_html_mail' => 'boolean',
        'nbm_send_mail_as' => 'string',
        'nbm_send_detailed_content' => 'boolean',
        'nbm_complementary_mail_content' => 'string',
        'nbm_send_recent_post_dates' => 'boolean',
    ];

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, UserProvider $userProvider)
    {
        parent::__construct($userProvider);
        $this->authorizationChecker = $authorizationChecker;
    }

    protected function setTabsheet(string $section = 'params'): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('params', $this->translator->trans('Parameters', [], 'admin'), $this->generateUrl('admin_notification'));
        $tabsheet->add('subscribe', $this->translator->trans('Subscribe', [], 'admin'), $this->generateUrl('admin_notification_subscribe'));
        if ($this->authorizationChecker->isGranted('ROLE_WEBMASTER')) {
            $tabsheet->add('send', $this->translator->trans('Send', [], 'admin'), $this->generateUrl('admin_notification_send'));
        }
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function params(Request $request, EntityManager $em, Conf $conf, ParameterBagInterface $params, Notification $notification, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST')) {
            // @TODO: find a way to make only one query
            foreach ($this->conf_types as $conf_key => $conf_type) {
                if ($conf_type === 'boolean') {
                    $conf->addOrUpdateParam($conf_key, $request->request->get($conf_key) === 'true', 'boolean');
                } else { // string
                    $conf->addOrUpdateParam($conf_key, $request->request->get($conf_key), 'string');
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
        $tpl_params = array_merge($this->setTabsheet('params'), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_notification');

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);

        return $this->render('notification_by_mail_params.html.twig', $tpl_params);
    }

    public function subscribe(Request $request, EntityManager $em, Conf $conf, ParameterBagInterface $params, Notification $notification, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST')) {
            if ($request->request->get('falsify') && $request->request->get('cat_true')) {
                $check_key_treated = $notification->unsubscribe_notification_by_mail(true, $request->request->get('cat_true'));
                $notification->do_timeout_treatment('cat_true', $check_key_treated);
            } elseif ($request->request->get('trueify') && $request->request->get('cat_false')) {
                $check_key_treated = $notification->subscribe_notification_by_mail(true, $request->request->get('cat_false'));
                $notification->do_timeout_treatment('cat_false', $check_key_treated);
            }
        } else {
            $notification->insert_new_data_user_mail_notification();
        }
        $data_users = $notification->get_user_notifications('subscribe');

        $opt_true = [];
        $opt_true_selected = [];
        $opt_false = [];
        $opt_false_selected = [];
        foreach ($data_users as $nbm_user) {
            if ($nbm_user->getEnabled()) {
                $opt_true[$nbm_user->getCheckKey()] = $nbm_user->getUser()->getUsername() . '[' . $nbm_user->getUser()->getMailAddress() . ']';
                if ($request->request->get('falsify') && $request->request->get('cat_true') && in_array($nbm_user->getCheckKey(), $request->request->get('cat_true'))) {
                    $opt_true_selected[] = $nbm_user->getCheckKey();
                }
            } else {
                $opt_false[$nbm_user->getCheckKey()] = $nbm_user->getUser()->getUsername() . '[' . $nbm_user->getUser()->getMailAddress() . ']';
                if ($request->request->get('trueify') && $request->request->get('cat_false') && in_array($nbm_user->getCheckKey(), $request->request->get('cat_false'))) {
                    $opt_false_selected[] = $nbm_user->getCheckKey();
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
        $tpl_params = array_merge($this->setTabsheet('subscribe'), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_notification');

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);

        return $this->render('notification_by_mail_subscribe.html.twig', $tpl_params);
    }

    public function send(Request $request, EntityManager $em, Conf $conf, ParameterBagInterface $params, Notification $notification, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;
        $must_repost = false;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $conf_derivatives = $conf['derivatives'];

        if ($request->isMethod('POST')) {
            $check_key_treated = $notification->do_action_send_mail_notification(
                'send',
                $request->request->get('send_selection'),
                $request->request->get('send_customize_mail_content'),
                $conf_derivatives
            );
            $notification->do_timeout_treatment('send_selection', $check_key_treated);

            if ($request->request->get('send_customize_mail_content')) {
                $tpl_var['CUSTOMIZE_MAIL_CONTENT'] = $request->request->get('send_customize_mail_content');
            } else {
                $tpl_var['CUSTOMIZE_MAIL_CONTENT'] = $conf['nbm_complementary_mail_content'];
            }
        }

        $tpl_var = ['users' => []];
        $data_users = $notification->do_action_send_mail_notification('list_to_send', [], '', $conf_derivatives);

        foreach ($data_users as $nbm_user) {
            if ((!$must_repost) || (($must_repost) && in_array($nbm_user->getCheckKey(), $request->request->get('send_selection')))) {
                $tpl_var['users'][] = [
                    'ID' => $nbm_user->getCheckKey(),
                    'CHECKED' => ($request->request->get('send_selection') && !in_array($nbm_user->getCheckKey(), $request->request->get('send_selection'))) ? '' : 'checked="checked"',
                    'USERNAME' => $nbm_user->getUser()->getUsername(),
                    'EMAIL' => $nbm_user->getUser()->getMailAddress(),
                    'LAST_SEND' => $nbm_user->getLastSend() ? $nbm_user->getLastSend()->format('Y-m-d H:m:i') : ''
                ];
            }
        }

        $tpl_params['send'] = $tpl_var;

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_notification');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Notification', [], 'admin');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('send'), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_notification');

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('notification_by_mail_send.html.twig', $tpl_params);
    }
}
