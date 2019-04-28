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

define('NOTIFICATION_BY_MAIL_BASE_URL', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=notification_by_mail');

use Phyxo\TabSheet\TabSheet;
use Phyxo\Functions\Notification;

// nbm_global_var
$env_nbm = [
    'start_time' => microtime(true),
    'sendmail_timeout' => (intval(ini_get('max_execution_time')) * $conf['nbm_max_treatment_timeout_percent']),
    'is_sendmail_timeout' => false
];

if ((!isset($env_nbm['sendmail_timeout'])) or (!is_numeric($env_nbm['sendmail_timeout'])) or ($env_nbm['sendmail_timeout'] <= 0)) {
    $env_nbm['sendmail_timeout'] = $conf['nbm_treatment_timeout_default'];
}

$must_repost = false;

// +-----------------------------------------------------------------------+
// |                                 Tabs                                  |
// +-----------------------------------------------------------------------+
if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'params';
}

$tabsheet = new TabSheet();
$tabsheet->add('params', \Phyxo\Functions\Language::l10n('Parameters'), NOTIFICATION_BY_MAIL_BASE_URL . '&amp;section=params');
$tabsheet->add('subscribe', \Phyxo\Functions\Language::l10n('Subscribe'), NOTIFICATION_BY_MAIL_BASE_URL . '&amp;section=subscribe');
$tabsheet->add('send', \Phyxo\Functions\Language::l10n('Send'), NOTIFICATION_BY_MAIL_BASE_URL . '&amp;section=send');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => NOTIFICATION_BY_MAIL_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// | Add event handler                                                     |
// +-----------------------------------------------------------------------+
$notification = new Notification($conn, $userMapper, $categoryMapper);

\Phyxo\Functions\Plugin::add_event_handler('nbm_render_global_customize_mail_content', [$notification, 'render_global_customize_mail_content']);
\Phyxo\Functions\Plugin::trigger_notify('nbm_event_handler_added');

if (!isset($_POST) or (count($_POST) == 0)) {
    // No insert data in post mode
    $notification->insert_new_data_user_mail_notification();
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+


$template->set_filenames(['double_select' => 'double_select.tpl']);

//$template->assign(array('U_HELP' => \Phyxo\Functions\URL::get_root_url().'admin/popuphelp.php?page=notification_by_mail'));

if ($must_repost) {
    // Get name of submit button
    $repost_submit_name = '';
    if (isset($_POST['falsify'])) {
        $repost_submit_name = 'falsify';
    } elseif (isset($_POST['trueify'])) {
        $repost_submit_name = 'trueify';
    } elseif (isset($_POST['send_submit'])) {
        $repost_submit_name = 'send_submit';
    }

    $template->assign('REPOST_SUBMIT_NAME', $repost_submit_name);
}

// +-----------------------------------------------------------------------+
// |                             Load the tab                              |
// +-----------------------------------------------------------------------+

$template_filename = 'notification_by_mail_' . $page['section'];

include(__DIR__ . '/notification_by_mail_' . $page['section'] . '.php');
