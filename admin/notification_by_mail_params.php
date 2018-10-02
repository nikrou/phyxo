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

use App\Repository\ConfigRepository;

if (isset($_POST['param_submit'])) {
    $updated_param_count = 0;
    // Update param
    $result = (new ConfigRepository($conn))->findAll('param like \'nbm\\_%\'');
    while ($nbm_user = $conn->db_fetch_assoc($result)) {
        if (isset($_POST[$nbm_user['param']])) {
            $value = $_POST[$nbm_user['param']];

            $conf[$nbm_user['param']] = $value;
            $updated_param_count += 1;
        }
    }

    $page['infos'][] = \Phyxo\Functions\Language::l10n_dec(
        '%d parameter was updated.',
        '%d parameters were updated.',
        $updated_param_count
    );

    // Reload conf with new values
    $conf->loadFromDB('param like \'nbm\\_%\'');
}

$template->assign([
    'SEND_HTML_MAIL' => $conf['nbm_send_html_mail'],
    'SEND_MAIL_AS' => $conf['nbm_send_mail_as'],
    'SEND_DETAILED_CONTENT' => $conf['nbm_send_detailed_content'],
    'COMPLEMENTARY_MAIL_CONTENT' => $conf['nbm_complementary_mail_content'],
    'SEND_RECENT_POST_DATES' => $conf['nbm_send_recent_post_dates'],
    'F_ACTION' => NOTIFICATION_BY_MAIL_BASE_URL . '&amp;section=params'
]);
