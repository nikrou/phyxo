<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2016 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if (!defined("THEMES_BASE_URL")) {
    die ("Hacking attempt!");
}

use Phyxo\Theme\Themes;

$themes = new Themes($conn);

// +-----------------------------------------------------------------------+
// |                           setup check                                 |
// +-----------------------------------------------------------------------+

$themes_dir = PHPWG_ROOT_PATH.'themes';
if (!is_writable($themes_dir)) {
    $page['errors'][] = l10n('Add write access to the "%s" directory', 'themes');
}

// +-----------------------------------------------------------------------+
// |                       perform installation                            |
// +-----------------------------------------------------------------------+

if (isset($_GET['revision']) and isset($_GET['extension'])) {
    if (!$services['users']->isWebmaster()) {
        $page['errors'][] = l10n('Webmaster status is required.');
    } else {
        check_pwg_token();

        $install_status = $themes->extractThemeFiles(
            'install',
            $_GET['revision'],
            $_GET['extension']
        );

        redirect(THEMES_BASE_URL.'&section=new&installstatus='.$install_status);
    }
}

// +-----------------------------------------------------------------------+
// |                        installation result                            |
// +-----------------------------------------------------------------------+

if (isset($_GET['installstatus'])) {
    switch ($_GET['installstatus'])
        {
        case 'ok':
            $page['infos'][] = l10n('Theme has been successfully installed');
            break;

        case 'temp_path_error':
            $page['errors'][] = l10n('Can\'t create temporary file.');
            break;

        case 'dl_archive_error':
            $page['errors'][] = l10n('Can\'t download archive.');
            break;

        case 'archive_error':
            $page['errors'][] = l10n('Can\'t read or extract archive.');
            break;

        default:
            $page['errors'][] = l10n(
                'An error occured during extraction (%s).',
                htmlspecialchars($_GET['installstatus'])
            );
  }
}

// +-----------------------------------------------------------------------+
// |                          template output                              |
// +-----------------------------------------------------------------------+

foreach($themes->getServerThemes(true) as $theme) {
    $url_auto_install = htmlentities(THEMES_BASE_URL)
        . '&amp;section=new'
        . '&amp;revision=' . $theme['revision_id']
        . '&amp;extension=' . $theme['extension_id']
        . '&amp;pwg_token='.get_pwg_token()
        ;

    $template->append(
        'new_themes',
        array(
            'name' => $theme['extension_name'],
            'thumbnail' => PEM_URL.'/upload/extension-'.$theme['extension_id'].'/thumbnail.jpg',
            'screenshot' => PEM_URL.'/upload/extension-'.$theme['extension_id'].'/screenshot.jpg',
            'install_url' => $url_auto_install,
        )
    );
}

$template->assign(
    'default_screenshot',
    get_root_url().'admin/themes/'.$conf['admin_theme'].'/images/missing_screenshot.png'
);

$template->assign_var_from_handle('ADMIN_CONTENT', 'themes');
