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

if (!defined("NOTIFICATION_BY_MAIL_BASE_URL")) {
    die("Hacking attempt!");
}

if (isset($_POST['send_submit']) and isset($_POST['send_selection']) and isset($_POST['send_customize_mail_content'])) {
    $check_key_treated = \Phyxo\Functions\Notification::do_action_send_mail_notification(
        'send',
        $_POST['send_selection'],
        stripslashes($_POST['send_customize_mail_content'])
    );
    \Phyxo\Functions\Notification::do_timeout_treatment('send_selection', $check_key_treated);
}

$tpl_var = array('users' => array());

$data_users = \Phyxo\Functions\Notification::do_action_send_mail_notification('list_to_send');

$tpl_var['CUSTOMIZE_MAIL_CONTENT'] = isset($_POST['send_customize_mail_content']) ? stripslashes($_POST['send_customize_mail_content']) : $conf['nbm_complementary_mail_content'];

if (count($data_users) > 0) {
    foreach ($data_users as $nbm_user) {
        if ((!$must_repost) or // Not timeout, normal treatment
        (($must_repost) and in_array($nbm_user['check_key'], $_POST['send_selection']))  // Must be repost, show only user to send
        ) {
            $tpl_var['users'][] = array(
                'ID' => $nbm_user['check_key'],
                'CHECKED' => ( // not check if not selected,  on init select<all
                isset($_POST['send_selection']) and // not init
                !in_array($nbm_user['check_key'], $_POST['send_selection']) // not selected
                ) ? '' : 'checked="checked"',
                'USERNAME' => stripslashes($nbm_user['username']),
                'EMAIL' => $nbm_user['mail_address'],
                'LAST_SEND' => $nbm_user['last_send']
            );
        }
    }
}

$tpl_var['F_ACTION'] = NOTIFICATION_BY_MAIL_BASE_URL . '&amp;section=send';

$template->assign($page['section'], $tpl_var);
