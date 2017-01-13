<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2017 Nicolas Roudaire         http://www.phyxo.net/ |
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

if (isset($_POST['send_submit']) and isset($_POST['send_selection']) and isset($_POST['send_customize_mail_content'])) {
    $check_key_treated = do_action_send_mail_notification('send', $_POST['send_selection'], stripslashes($_POST['send_customize_mail_content']));
    do_timeout_treatment('send_selection', $check_key_treated);
}

$tpl_var = array('users' => array());

$data_users = do_action_send_mail_notification('list_to_send');

$tpl_var['CUSTOMIZE_MAIL_CONTENT'] = isset($_POST['send_customize_mail_content']) ? stripslashes($_POST['send_customize_mail_content']) : $conf['nbm_complementary_mail_content'];

if (count($data_users)>0) {
    foreach ($data_users as $nbm_user) {
        if (
            (!$must_repost) or // Not timeout, normal treatment
            (($must_repost) and in_array($nbm_user['check_key'], $_POST['send_selection']))  // Must be repost, show only user to send
        ) {
            $tpl_var['users'][] = array(
                'ID' => $nbm_user['check_key'],
                'CHECKED' =>  ( // not check if not selected,  on init select<all
                    isset($_POST['send_selection']) and // not init
                    !in_array($nbm_user['check_key'], $_POST['send_selection']) // not selected
                )   ? '' : 'checked="checked"',
                'USERNAME'=> stripslashes($nbm_user['username']),
                'EMAIL' => $nbm_user['mail_address'],
                'LAST_SEND'=> $nbm_user['last_send']
            );
        }
    }
}

$tpl_var['F_ACTION'] = NOTIFICATION_BY_MAIL_BASE_URL.'&amp;section=send';

$template->assign($page['section'], $tpl_var);

$template->assign_var_from_handle('ADMIN_CONTENT', 'notification_by_mail');
