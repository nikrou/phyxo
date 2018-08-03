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


// The "No Photo Yet" feature: if you have no photo yet in your gallery, the
// gallery displays only a big box to show you the way for adding your first
// photos
// no message inside administration 
// keep the ability to login
// keep the ability to discuss with web API
if (!(defined('IN_ADMIN') and IN_ADMIN)
    and script_basename() != 'identification'
    and script_basename() != 'ws'
    and !isset($_SESSION['no_photo_yet'])) {  // temporary hide

    $query = 'SELECT COUNT(1) FROM ' . IMAGES_TABLE . ';';
    list($nb_photos) = $conn->db_fetch_row($conn->db_query($query));
    if ($nb_photos === 0) {
        // make sure we don't use the mobile theme, which is not compatible with
        // the "no photo yet" feature
        $template = new Phyxo\Template\Template(PHPWG_ROOT_PATH . 'themes', $user['theme']);

        if (isset($_GET['no_photo_yet'])) {
            if ('browse' == $_GET['no_photo_yet']) {
                $_SESSION['no_photo_yet'] = 'browse';
                redirect(make_index_url());
                exit();
            }

            if ('deactivate' == $_GET['no_photo_yet']) {
                conf_update_param('no_photo_yet', 'false');
                redirect(make_index_url());
                exit();
            }
        }

        header('Content-Type: text/html; charset=' . get_pwg_charset());
        $template->set_filenames(array('no_photo_yet' => 'no_photo_yet.tpl'));

        if ($services['users']->isAdmin()) {
            $url = $conf['no_photo_yet_url'];
            if (substr($url, 0, 4) != 'http') {
                $url = get_root_url() . $url;
            }

            $template->assign(
                array(
                    'step' => 2,
                    'intro' => l10n(
                        'Hello %s, your Phyxo photo gallery is empty!',
                        $user['username']
                    ),
                    'next_step_url' => $url,
                    'deactivate_url' => get_root_url() . '?no_photo_yet=deactivate',
                )
            );
        } else {

            $template->assign(
                array(
                    'step' => 1,
                    'U_LOGIN' => 'identification.php',
                    'deactivate_url' => get_root_url() . '?no_photo_yet=browse',
                )
            );
        }

        trigger_notify('loc_end_no_photo_yet');

        $template->pparse('no_photo_yet');
        exit();
    } else {
        conf_update_param('no_photo_yet', false);
    }
}
