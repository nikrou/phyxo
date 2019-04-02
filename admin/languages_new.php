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

use Phyxo\Language\Languages;

$languages = new Languages($conn);

// +-----------------------------------------------------------------------+
// |                           setup check                                 |
// +-----------------------------------------------------------------------+

$languages_dir = __DIR__ . '/../language';
if (!is_writable($languages_dir)) {
    $page['errors'][] = \Phyxo\Functions\Language::l10n('Add write access to the "%s" directory', 'language');
}

// +-----------------------------------------------------------------------+
// |                       perform installation                            |
// +-----------------------------------------------------------------------+

if (isset($_GET['revision'])) {
    if (!$services['users']->isWebmaster()) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Webmaster status is required.');
    } else {
        \Phyxo\Functions\Utils::check_token();

        try {
            $languages->extractLanguageFiles('install', $_GET['revision']);
            $install_status = 'ok';
        } catch (\Exception $e) {
            $page['errors'] = \Phyxo\Functions\Language::l10n($e->getMessage());
        }

        \Phyxo\Functions\Utils::redirect(LANGUAGES_BASE_URL . '&section=new&installstatus=' . $install_status);
    }
}

// +-----------------------------------------------------------------------+
// |                        installation result                            |
// +-----------------------------------------------------------------------+
if (isset($_GET['installstatus'])) {
    switch ($_GET['installstatus']) {
        case 'ok':
            $page['infos'][] = \Phyxo\Functions\Language::l10n('Language has been successfully installed');
            break;

        case 'temp_path_error':
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Can\'t create temporary file.');
            break;

        case 'dl_archive_error':
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Can\'t download archive.');
            break;

        case 'archive_error':
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Can\'t read or extract archive.');
            break;

        default:
            $page['errors'][] = \Phyxo\Functions\Language::l10n('An error occured during extraction (%s).', htmlspecialchars($_GET['installstatus']));
    }
}

// +-----------------------------------------------------------------------+
// |                     start template output                             |
// +-----------------------------------------------------------------------+

foreach ($languages->getServerLanguages(true) as $language) {
    list($date, ) = explode(' ', $language['revision_date']);

    $url_auto_install = LANGUAGES_BASE_URL . '&amp;section=new&amp;revision=' . $language['revision_id'] . '&amp;pwg_token=' . \Phyxo\Functions\Utils::get_token();

    $template->append('languages', [
        'EXT_NAME' => $language['extension_name'],
        'EXT_DESC' => $language['extension_description'],
        'EXT_URL' => PEM_URL . '/extension_view.php?eid=' . $language['extension_id'],
        'VERSION' => $language['revision_name'],
        'VER_DESC' => $language['revision_description'],
        'DATE' => $date,
        'AUTHOR' => $language['author_name'],
        'URL_INSTALL' => $url_auto_install,
        'URL_DOWNLOAD' => $language['download_url'] . '&amp;origin=phyxo'
    ]);
}
