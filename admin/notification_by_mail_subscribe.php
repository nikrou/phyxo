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

use Phyxo\Functions\Notification;

if (!defined("NOTIFICATION_BY_MAIL_BASE_URL")) {
    die("Hacking attempt!");
}
$notification = new Notification($conn, $userMapper);

if (isset($_POST['falsify']) and isset($_POST['cat_true'])) {
    $check_key_treated = $notification->unsubscribe_notification_by_mail(true, $_POST['cat_true']);
    $notification->do_timeout_treatment('cat_true', $check_key_treated);
} elseif (isset($_POST['trueify']) and isset($_POST['cat_false'])) {
    $check_key_treated = $notification->subscribe_notification_by_mail(true, $_POST['cat_false']);
    $notification->do_timeout_treatment('cat_false', $check_key_treated);
}

$template->assign($page['section'], true);

$template->assign(
    [
        'L_CAT_OPTIONS_TRUE' => \Phyxo\Functions\Language::l10n('Subscribed'),
        'L_CAT_OPTIONS_FALSE' => \Phyxo\Functions\Language::l10n('Unsubscribed')
    ]
);

$data_users = $notification->get_user_notifications('subscribe');

$opt_true = [];
$opt_true_selected = [];
$opt_false = [];
$opt_false_selected = [];
foreach ($data_users as $nbm_user) {
    if ($conn->get_boolean($nbm_user['enabled'])) {
        $opt_true[$nbm_user['check_key']] = stripslashes($nbm_user['username']) . '[' . $nbm_user['mail_address'] . ']';
        if ((isset($_POST['falsify']) and isset($_POST['cat_true']) and in_array($nbm_user['check_key'], $_POST['cat_true']))) {
            $opt_true_selected[] = $nbm_user['check_key'];
        }
    } else {
        $opt_false[$nbm_user['check_key']] = stripslashes($nbm_user['username']) . '[' . $nbm_user['mail_address'] . ']';
        if (isset($_POST['trueify']) and isset($_POST['cat_false']) and in_array($nbm_user['check_key'], $_POST['cat_false'])) {
            $opt_false_selected[] = $nbm_user['check_key'];
        }
    }
}

$template->assign([
    'category_option_true' => $opt_true,
    'category_option_true_selected' => $opt_true_selected,
    'category_option_false' => $opt_false,
    'category_option_false_selected' => $opt_false_selected,
    'F_ACTION' => NOTIFICATION_BY_MAIL_BASE_URL . '&amp;section=subscribe'
]);

// +-----------------------------------------------------------------------+
// | Sending html code                                                     |
// +-----------------------------------------------------------------------+
$template->assign_var_from_handle('DOUBLE_SELECT', 'double_select');
