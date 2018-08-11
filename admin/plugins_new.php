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

if (!defined("PLUGINS_BASE_URL")) {
    die("Hacking attempt!");
}

use Phyxo\Plugin\Plugins;

$plugins = new Plugins($conn);

//------------------------------------------------------automatic installation
if (isset($_GET['revision']) and isset($_GET['extension'])) {
    if (!$services['users']->isWebmaster()) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Webmaster status is required.');
    } else {
        \Phyxo\Functions\Utils::check_token();

        try {
            $plugins->extractPluginFiles('install', $_GET['revision'], $_GET['extension'], $plugin_id);
            $install_status = 'ok';
        } catch (\Exception $e) {
            $page['errors'] = $e->getMessage();
        }

        \Phyxo\Functions\Utils::redirect(PLUGINS_BASE_URL . '&installstatus=' . $install_status . '&plugin_id=' . $plugin_id);
    }
}

//--------------------------------------------------------------install result
if (isset($_GET['installstatus'])) {
    switch ($_GET['installstatus']) {
        case 'ok':
            $activate_url = PLUGINS_BASE_URL
                . '&amp;plugin=' . $_GET['plugin_id']
                . '&amp;pwg_token=' . \Phyxo\Functions\Utils::get_token()
                . '&amp;action=activate';

            $page['infos'][] = \Phyxo\Functions\Language::l10n('Plugin has been successfully copied');
            $page['infos'][] = '<a href="' . $activate_url . '">' . \Phyxo\Functions\Language::l10n('Activate it now') . '</a>';
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
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Please check "plugins" folder and sub-folders permissions (CHMOD).');
    }
}

//---------------------------------------------------------------Order options
$template->assign('order_options', array(
    'date' => \Phyxo\Functions\Language::l10n('Post date'),
    'revision' => \Phyxo\Functions\Language::l10n('Last revisions'),
    'name' => \Phyxo\Functions\Language::l10n('Name'),
    'author' => \Phyxo\Functions\Language::l10n('Author'),
    'downloads' => \Phyxo\Functions\Language::l10n('Number of downloads')
));

// +-----------------------------------------------------------------------+
// |                     start template output                             |
// +-----------------------------------------------------------------------+

try {
    if (count($plugins->getServerPlugins(true)) > 0) {
        /* order plugins */
        if (!empty($_SESSION['plugins_new_order'])) {
            $order_selected = $_SESSION['plugins_new_order'];
            $plugins->sortServerPlugins($order_selected);
            $template->assign('order_selected', $order_selected);
        } else {
            $plugins->sortServerPlugins('date');
            $template->assign('order_selected', 'date');
        }

        foreach ($plugins->getServerPlugins() as $plugin) {
            $ext_desc = trim($plugin['extension_description'], " \n\r");
            list($small_desc) = explode("\n", wordwrap($ext_desc, 200));

            $url_auto_install = htmlentities(PLUGINS_BASE_URL)
                . '&amp;section=new'
                . '&amp;revision=' . $plugin['revision_id']
                . '&amp;extension=' . $plugin['extension_id']
                . '&amp;pwg_token=' . \Phyxo\Functions\Utils::get_token();

            $template->append('plugins', array(
                'ID' => $plugin['extension_id'],
                'EXT_NAME' => $plugin['extension_name'],
                'EXT_URL' => PEM_URL . '/extension_view.php?eid=' . $plugin['extension_id'],
                'SMALL_DESC' => trim($small_desc, " \r\n"),
                'BIG_DESC' => $ext_desc,
                'VERSION' => $plugin['revision_name'],
                'REVISION_DATE' => preg_replace('/[^0-9]/', '', $plugin['revision_date']),
                'AUTHOR' => $plugin['author_name'],
                'DOWNLOADS' => $plugin['extension_nb_downloads'],
                'URL_INSTALL' => $url_auto_install,
                'URL_DOWNLOAD' => $plugin['download_url'] . '&amp;origin=phyxo_download'
            ));
        }
    }
} catch (\Exception $e) {
    $page['errors'][] = \Phyxo\Functions\Language::l10n('Can\'t connect to server.');
}
