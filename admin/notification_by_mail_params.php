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

if (isset($_POST['param_submit'])) {
    $updated_param_count = 0;
    // Update param
    $result = $conn->db_query('select param, value from '.CONFIG_TABLE.' where param like \'nbm\\_%\'');
    while ($nbm_user = $conn->db_fetch_assoc($result)) {
        if (isset($_POST[$nbm_user['param']])) {
            $value = $_POST[$nbm_user['param']];

            conf_update_param($nbm_user['param'], $value);
            $updated_param_count += 1;
        }
    }

    $page['infos'][] = l10n_dec(
        '%d parameter was updated.', '%d parameters were updated.',
        $updated_param_count
    );

    // Reload conf with new values
    load_conf_from_db('param like \'nbm\\_%\'');
}

$template->assign(array(
    'SEND_HTML_MAIL' => $conf['nbm_send_html_mail'],
    'SEND_MAIL_AS' => $conf['nbm_send_mail_as'],
    'SEND_DETAILED_CONTENT' => $conf['nbm_send_detailed_content'],
    'COMPLEMENTARY_MAIL_CONTENT' => $conf['nbm_complementary_mail_content'],
    'SEND_RECENT_POST_DATES' => $conf['nbm_send_recent_post_dates'],
    'F_ACTION'=> NOTIFICATION_BY_MAIL_BASE_URL.'&amp;section=params'
));

// +-----------------------------------------------------------------------+
// | Sending html code                                                     |
// +-----------------------------------------------------------------------+
$template->assign_var_from_handle('ADMIN_CONTENT', 'notification_by_mail');
