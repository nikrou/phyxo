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

use App\Repository\ImageRepository;

// The "No Photo Yet" feature: if you have no photo yet in your gallery, the
// gallery displays only a big box to show you the way for adding your first
// photos
// no message inside administration
// keep the ability to login
// keep the ability to discuss with web API
if (!(defined('IN_ADMIN') and IN_ADMIN)
    && \Phyxo\Functions\Utils::script_basename() != 'identification'
    && \Phyxo\Functions\Utils::script_basename() != 'ws'
    && !isset($_SESSION['no_photo_yet'])) {  // temporary hide

    if ((new ImageRepository($conn))->count() === 0) {
        if (isset($_GET['no_photo_yet'])) {
            if ('browse' == $_GET['no_photo_yet']) {
                $_SESSION['no_photo_yet'] = 'browse';
                \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::make_index_url());
                exit();
            }

            if ('deactivate' == $_GET['no_photo_yet']) {
                $conf['no_photo_yet'] = false;
                \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::make_index_url());
                exit();
            }
        }

        $template->set_filenames(['no_photo_yet' => 'no_photo_yet.tpl']);

        if ($services['users']->isAdmin()) {
            $url = $conf['no_photo_yet_url'];
            if (substr($url, 0, 4) != 'http') {
                $url = \Phyxo\Functions\URL::get_root_url() . $url;
            }

            $template->assign(
                [
                    'step' => 2,
                    'intro' => \Phyxo\Functions\Language::l10n(
                        'Hello %s, your Phyxo photo gallery is empty!',
                        $user['username']
                    ),
                    'next_step_url' => $url,
                    'deactivate_url' => \Phyxo\Functions\URL::get_root_url() . '?no_photo_yet=deactivate',
                ]
            );
        } else {
            $template->assign(
                [
                    'step' => 1,
                    'U_LOGIN' => 'identification.php',
                    'deactivate_url' => \Phyxo\Functions\URL::get_root_url() . '?no_photo_yet=browse',
                ]
            );
        }

        \Phyxo\Functions\Plugin::trigger_notify('loc_end_no_photo_yet');
    } else {
        $conf['no_photo_yet'] = false;
    }
}
