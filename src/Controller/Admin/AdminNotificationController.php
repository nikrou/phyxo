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

use App\Form\NotificationType;
use App\Notification;
use Phyxo\Conf;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminNotificationController extends AbstractController
{
    private TranslatorInterface $translator;
    public function __construct(private readonly AuthorizationCheckerInterface $authorizationChecker)
    {
    }
    protected function setTabsheet(string $section = 'params'): TabSheet
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('params', $this->translator->trans('Parameters', [], 'admin'), $this->generateUrl('admin_notification'));
        $tabsheet->add('subscribe', $this->translator->trans('Subscribe', [], 'admin'), $this->generateUrl('admin_notification_subscribe'));
        if ($this->authorizationChecker->isGranted('ROLE_WEBMASTER')) {
            $tabsheet->add('send', $this->translator->trans('Send', [], 'admin'), $this->generateUrl('admin_notification_send'));
        }

        $tabsheet->select($section);

        return $tabsheet;
    }
    #[Route('/admin/notification', name: 'admin_notification')]
    public function params(Request $request, Conf $conf, Notification $notification, TranslatorInterface $translator): Response
    {
        $tpl_params = [];
        $this->translator = $translator;

        $form = $this->createForm(NotificationType::class, $conf);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($form->getData() as $confKey => $confParam) {
                $conf->addOrUpdateParam($confKey, $confParam['value'], $confParam['type']);
            }

            $this->addFlash('success', $translator->trans('Your configuration settings have been updated', [], 'admin'));

            $this->redirectToRoute('admin_notification');
        } else {
            $notification->insert_new_data_user_mail_notification();
        }

        $tpl_params['form'] = $form->createView();
        $tpl_params['PAGE_TITLE'] = $translator->trans('Notification', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('params');

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_notification');

        return $this->render('notification_by_mail_params.html.twig', $tpl_params);
    }
    #[Route('/admin/notification/subscribe', name: 'admin_notification_subscribe')]
    public function subscribe(Request $request, Notification $notification, TranslatorInterface $translator): Response
    {
        $tpl_params = [];
        $this->translator = $translator;

        if ($request->isMethod('POST')) {
            if ($request->request->get('falsify') && $request->request->has('cat_true')) {
                $check_key_treated = $notification->unsubscribe_notification_by_mail(true, $request->request->all('cat_true'));
                $notification->do_timeout_treatment('cat_true', $check_key_treated);
            } elseif ($request->request->get('trueify') && $request->request->has('cat_false')) {
                $check_key_treated = $notification->subscribe_notification_by_mail(true, $request->request->all('cat_false'));
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
                if ($request->request->get('falsify') && $request->request->has('cat_true') && in_array($nbm_user->getCheckKey(), $request->request->all('cat_true'))) {
                    $opt_true_selected[] = $nbm_user->getCheckKey();
                }
            } else {
                $opt_false[$nbm_user->getCheckKey()] = $nbm_user->getUser()->getUsername() . '[' . $nbm_user->getUser()->getMailAddress() . ']';
                if ($request->request->get('trueify') && $request->request->has('cat_false') && in_array($nbm_user->getCheckKey(), $request->request->all('cat_false'))) {
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
        $tpl_params['tabsheet'] = $this->setTabsheet('subscribe');

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_notification');

        return $this->render('notification_by_mail_subscribe.html.twig', $tpl_params);
    }
    #[Route('/admin/notification/send', name: 'admin_notification_send')]
    public function send(Request $request, Conf $conf, Notification $notification, TranslatorInterface $translator): Response
    {
        $tpl_params = [];
        $this->translator = $translator;

        $tpl_var = [];
        $tpl_var['CUSTOMIZE_MAIL_CONTENT'] = $conf['nbm_complementary_mail_content'];

        if ($request->isMethod('POST')) {
            $check_key_treated = $notification->do_action_send_mail_notification(
                'send',
                $request->request->all('send_selection'),
                $request->request->get('send_customize_mail_content')
            );
            $notification->do_timeout_treatment('send_selection', $check_key_treated);

            if ($request->request->get('send_customize_mail_content')) {
                $tpl_var['CUSTOMIZE_MAIL_CONTENT'] = $request->request->get('send_customize_mail_content');
            }
        }

        $tpl_var['users'] = [];
        $data_users = $notification->do_action_send_mail_notification('list_to_send', [], '');

        foreach ($data_users as $nbm_user) {
            if (in_array($nbm_user->getCheckKey(), $request->request->all('send_selection'))) {
                $tpl_var['users'][] = [
                    'ID' => $nbm_user->getCheckKey(),
                    'CHECKED' => ($request->request->get('send_selection') && !in_array($nbm_user->getCheckKey(), $request->request->all('send_selection'))) ? '' : 'checked="checked"',
                    'USERNAME' => $nbm_user->getUser()->getUsername(),
                    'EMAIL' => $nbm_user->getUser()->getMailAddress(),
                    'LAST_SEND' => $nbm_user->getLastSend() ? $nbm_user->getLastSend()->format('Y-m-d H:m:i') : ''
                ];
            }
        }

        $tpl_params['send'] = $tpl_var;

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_notification');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Notification', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('send');

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_notification');

        return $this->render('notification_by_mail_send.html.twig', $tpl_params);
    }
}
