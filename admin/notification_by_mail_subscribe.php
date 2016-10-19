<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2016 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

if (!defined("NOTIFICATION_BY_MAIL_BASE_URL")) {
    die ("Hacking attempt!");
}

if (isset($_POST['falsify']) and isset($_POST['cat_true'])) {
    $check_key_treated = unsubscribe_notification_by_mail(true, $_POST['cat_true']);
    do_timeout_treatment('cat_true', $check_key_treated);
} elseif (isset($_POST['trueify']) and isset($_POST['cat_false'])) {
    $check_key_treated = subscribe_notification_by_mail(true, $_POST['cat_false']);
    do_timeout_treatment('cat_false', $check_key_treated);
}

$template->assign($page['section'], true);

$template->assign(
    array(
        'L_CAT_OPTIONS_TRUE' => l10n('Subscribed'),
        'L_CAT_OPTIONS_FALSE' => l10n('Unsubscribed')
    )
);

$data_users = get_user_notifications('subscribe');

$opt_true = array();
$opt_true_selected = array();
$opt_false = array();
$opt_false_selected = array();
foreach ($data_users as $nbm_user) {
    if ($conn->get_boolean($nbm_user['enabled'])) {
        $opt_true[ $nbm_user['check_key'] ] = stripslashes($nbm_user['username']).'['.$nbm_user['mail_address'].']';
        if ((isset($_POST['falsify']) and isset($_POST['cat_true']) and in_array($nbm_user['check_key'], $_POST['cat_true']))) {
            $opt_true_selected[] = $nbm_user['check_key'];
        }
    } else {
        $opt_false[ $nbm_user['check_key'] ] = stripslashes($nbm_user['username']).'['.$nbm_user['mail_address'].']';
        if (isset($_POST['trueify']) and isset($_POST['cat_false']) and in_array($nbm_user['check_key'], $_POST['cat_false'])) {
            $opt_false_selected[] = $nbm_user['check_key'];
        }
    }
}

$template->assign(array(
    'category_option_true' => $opt_true,
    'category_option_true_selected' => $opt_true_selected,
    'category_option_false' => $opt_false,
    'category_option_false_selected' => $opt_false_selected,
    'F_ACTION'=> NOTIFICATION_BY_MAIL_BASE_URL.'&amp;section=subscribe'
));

// +-----------------------------------------------------------------------+
// | Sending html code                                                     |
// +-----------------------------------------------------------------------+
$template->assign_var_from_handle('DOUBLE_SELECT', 'double_select');
$template->assign_var_from_handle('ADMIN_CONTENT', 'notification_by_mail');
