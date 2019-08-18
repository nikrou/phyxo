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

if (!defined("LANGUAGES_BASE_URL")) {
    die("Hacking attempt!");
}

use Phyxo\Update\Updates;

$autoupdate = new Updates($conn, $userMapper, 'languages');

$show_reset = false;
$conf['updates_ignored'] = json_decode($conf['updates_ignored'], true);

try {
    $autoupdate->getServerExtensions();
    $server_languages = $autoupdate->getType('languages')->getServerLanguages();

    if (count($server_languages) > 0) {
        foreach ($autoupdate->getType('languages')->getFsLanguages() as $extension_id => $fs_extension) {
            if (!isset($fs_extension['extension']) or !isset($server_languages[$fs_extension['extension']])) {
                continue;
            }

            $extension_info = $server_languages[$fs_extension['extension']];

            if (!version_compare($fs_extension['version'], $extension_info['revision_name'], '>=')) {
                $template->append(
                    'update_languages',
                    [
                        'ID' => $extension_info['extension_id'],
                        'REVISION_ID' => $extension_info['revision_id'],
                        'EXT_ID' => $extension_id,
                        'EXT_NAME' => $fs_extension['name'],
                        'EXT_URL' => PEM_URL . '/extension_view.php?eid=' . $extension_info['extension_id'],
                        'EXT_DESC' => trim($extension_info['extension_description'], " \n\r"),
                        'REV_DESC' => trim($extension_info['revision_description'], " \n\r"),
                        'CURRENT_VERSION' => $fs_extension['version'],
                        'NEW_VERSION' => $extension_info['revision_name'],
                        'AUTHOR' => $extension_info['author_name'],
                        'DOWNLOADS' => $extension_info['extension_nb_downloads'],
                        'URL_DOWNLOAD' => $extension_info['download_url'] . '&amp;origin=phyxo',
                        'IGNORED' => !empty($conf['updates_ignored']['languages']) && in_array($extension_id, $conf['updates_ignored']['languages']),
                    ]
                );
            }
        }

        if (!empty($conf['updates_ignored']['languages'])) {
            $show_reset = true;
        }
    }

    $template->assign('SHOW_RESET', $show_reset);
    $template->assign('PWG_TOKEN', \Phyxo\Functions\Utils::get_token());
    $template->assign('EXT_TYPE', $page['page'] == 'updates' ? 'extensions' : $page['page']);
} catch (\Exception $e) {
    $page['errors'][] = \Phyxo\Functions\Language::l10n('Can\'t connect to server.');
    $template->append(
        ['error' => $page['error']]
    );
}
