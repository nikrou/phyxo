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

add_event_handler('tabsheet_before_select', 'add_core_tabs', 0);

function add_core_tabs($sheets, $tab_id) {
    switch($tab_id)
        {
        case 'tags':
            $sheets['all'] = array(
                'caption' => l10n('All tags'),
                'url' => TAGS_BASE_URL.'&section=all'
            );
            $sheets['perm'] = array(
                'caption' => l10n('Permissions'),
                'url' => TAGS_BASE_URL.'&section=perm'
            );
            $sheets['pending'] = array(
                'caption' => l10n('Pendings'),
                'url' => TAGS_BASE_URL.'&section=pending'
            );
            break;
        case 'album':
            $sheets['properties'] = array(
                'caption' => '<span class="icon-pencil"></span>'.l10n('Properties'),
                'url' => ALBUM_BASE_URL.'&amp;section=properties'
            );
            $sheets['sort_order'] = array(
                'caption' => '<span class="icon-shuffle"></span>'.l10n('Manage photo ranks'),
                'url' => ALBUM_BASE_URL.'&amp;section=sort_order'
            );
            $sheets['permissions'] = array(
                'caption' => '<span class="icon-lock"></span>'.l10n('Permissions'),
                'url' => ALBUM_BASE_URL.'&amp;section=permissions'
            );
            $sheets['notification'] = array(
                'caption' => '<span class="icon-mail-alt"></span>'.l10n('Notification'),
                'url' => ALBUM_BASE_URL.'&amp;section=notification'
            );
      break;
        case 'albums':
            $sheets['list'] = array(
                'caption' => '<span class="icon-menu"></span>'.l10n('List'),
                'url' => ALBUMS_BASE_URL.'&amp;section=list'
            );
            $sheets['move'] = array(
                'caption' => '<span class="icon-move"></span>'.l10n('Move'),
                'url' => ALBUMS_BASE_URL.'&amp;section=move'
            );
            $sheets['permalinks'] = array(
                'caption' => '<span class="icon-link"></span>'.l10n('Permalinks'),
                'url' => ALBUMS_BASE_URL.'&amp;section=permalinks'
            );
            break;
        case 'albums_options':
            global $conf;
            $sheets['status'] = array(
                'caption' => '<span class="icon-lock"></span>'.l10n('Public / Private'),
                'url' => ALBUMS_OPTIONS_BASE_URL.'&amp;section=status'
            );
            $sheets['visible'] = array(
                'caption' => '<span class="icon-block"></span>'.l10n('Lock'),
                'url' => ALBUMS_OPTIONS_BASE_URL.'&amp;section=visible'
            );
            if ($conf['activate_comments']) {
                $sheets['comments'] = array(
                    'caption' => '<span class="icon-chat"></span>'.l10n('Comments'),
                    'url' => ALBUMS_OPTIONS_BASE_URL.'&amp;section=comments'
                );
            }
            if ($conf['allow_random_representative']) {
                $sheets['representative'] = array(
                    'caption' => l10n('Representative'),
                    'url' => ALBUMS_OPTIONS_BASE_URL.'&amp;section=representative'
                );
            }
            break;

        case 'batch_manager':
            $sheets['global'] = array(
                'caption' => l10n('global mode'),
                'url' => BATCH_MANAGER_BASE_URL.'&amp;section=global'
            );
            $sheets['unit'] = array(
                'caption' => l10n('unit mode'),
                'url' => BATCH_MANAGER_BASE_URL.'&amp;section=unit'
            );
            break;

        case 'comments':
            $sheets['user'] = array(
                'caption' => l10n('User comments'),
                'url' => COMMENTS_BASE_URL.'&amp;section=user'
            );
            break;

        case 'users':
            $sheets['list'] = array(
                'caption' => '<span class="icon-users"> </span>'.l10n('User list'),
                'url' => USERS_BASE_URL.'&amp;section=list'
            );
            break;

        case 'groups':
            $sheets['list'] = array(
                'caption' => '<span class="icon-group"> </span>'.l10n('Groups'),
                'url' => GROUPS_BASE_URL.'&amp;section=list'
            );
            $sheets['perm'] = array(
                'caption' => '<span class="icon-lock"> </span>'.l10n('Permissions'),
                'url' => GROUPS_BASE_URL.'&amp;section=perm'
            );
            break;

        case 'configuration':
            $sheets['main'] = array(
                'caption' => l10n('General'),
                'url' => CONFIGURATION_BASE_URL.'&amp;section=main'
            );
            $sheets['sizes'] = array(
                'caption' => l10n('Photo sizes'),
                'url' => CONFIGURATION_BASE_URL.'&amp;section=sizes'
            );
            $sheets['watermark'] = array(
                'caption' => l10n('Watermark'),
                'url' => CONFIGURATION_BASE_URL.'&amp;section=watermark'
            );
            $sheets['display'] = array(
                'caption' => l10n('Display'),
                'url' => CONFIGURATION_BASE_URL.'&amp;section=display'
            );
            $sheets['comments'] = array(
                'caption' => l10n('Comments'),
                'url' => CONFIGURATION_BASE_URL.'&amp;section=comments'
            );
            $sheets['default'] = array(
                'caption' => l10n('Guest Settings'),
                'url' => CONFIGURATION_BASE_URL.'&amp;section=default'
            );
            break;

        case 'help':
            $sheets['add_photos'] = array(
                'caption' => l10n('Add Photos'),
                'url' => HELP_BASE_LINK.'&amp;section=add_photos'
            );
            $sheets['permissions'] = array(
                'caption' => l10n('Permissions'),
                'url' => HELP_BASE_LINK.'&amp;section=permissions'
            );
            $sheets['groups'] = array(
                'caption' => l10n('Groups'),
                'url' => HELP_BASE_LINK.'&amp;section=groups'
            );
            $sheets['virtual_links'] = array(
                'caption' => l10n('Virtual Links'),
                'url' => HELP_BASE_LINK.'&amp;section=virtual_links'
            );
            $sheets['misc'] = array(
                'caption' => l10n('Miscellaneous'),
                'url' => HELP_BASE_LINK.'&amp;section=misc'
            );
            break;

        case 'history':
            $sheets['stats'] = array(
                'caption' => '<span class="icon-signal"></span>'.l10n('Statistics'),
                'url' => HISTORY_BASE_URL.'&amp;section=stats'
            );
            $sheets['search'] = array(
                'caption' => '<span class="icon-search"></span>'.l10n('Search'),
                'url' => HISTORY_BASE_URL.'&amp;section=search'
            );
            break;

        case 'languages':
            $sheets['installed'] = array(
                'caption' => '<span class="icon-language"></span>'.l10n('Installed Languages'),
                'url' => LANGUAGES_BASE_URL.'&amp;section=installed'
            );
            $sheets['update'] = array(
                'caption' => '<span class="icon-arrows-cw"></span>'.l10n('Check for updates'),
                'url' => LANGUAGES_BASE_URL.'&amp;section=update'
            );
            $sheets['new'] = array(
                'caption' => '<span class="icon-plus-circled"></span>'.l10n('Add New Language'),
                'url' => LANGUAGES_BASE_URL.'&amp;section=new'
            );
            break;

        case 'notification_by_mail':
            $sheets['params'] = array(
                'caption' => l10n('Parameters'),
                'url' => NOTIFICATION_BY_MAIL_BASE_URL.'&amp;section=params'
            );
            $sheets['subscribe'] = array(
                'caption' => l10n('Subscribe'),
                'url' => NOTIFICATION_BY_MAIL_BASE_URL.'&amp;section=subscribe'
            );
            $sheets['send'] = array(
                'caption' => l10n('Send'),
                'url' => NOTIFICATION_BY_MAIL_BASE_URL.'&amp;section=send'
            );
            break;

        case 'photo':
            $sheets['properties'] = array(
                'caption' => l10n('Properties'),
                'url' => PHOTO_BASE_URL.'&amp;section=properties'
            );
            $sheets['coi'] = array(
                'caption' => '<span class="icon-crop"></span>'.l10n('Center of interest'),
                'url' => PHOTO_BASE_URL.'&amp;section=coi'
            );
            break;

        case 'photos_add':
            global $conf;
            $sheets['direct'] = array(
                'caption' => '<span class="icon-upload"></span>'.l10n('Web Form'),
                'url' => PHOTOS_ADD_BASE_URL.'&amp;section=direct'
            );
            if ($conf['enable_synchronization'])
                $sheets['ftp'] = array(
                    'caption' => '<span class="icon-exchange"></span>'.l10n('FTP + Synchronization'),
                    'url' => PHOTOS_ADD_BASE_URL.'&amp;section=ftp'
                );
            break;

        case 'plugins':
            $sheets['installed'] = array(
                'caption' => '<span class="icon-equalizer"></span>'.l10n('Plugin list'),
                'url' => PLUGINS_BASE_URL.'&amp;section=installed'
            );
            $sheets['update'] = array(
                'caption' => '<span class="icon-arrows-cw"></span>'.l10n('Check for updates'),
                'url' => PLUGINS_BASE_URL.'&amp;section=update'
            );
            $sheets['new'] = array(
                'caption' => '<span class="icon-plus-circled"></span>'.l10n('Other plugins'),
                'url' => PLUGINS_BASE_URL.'&amp;section=new');
            break;

        case 'rating':
            $sheets['photos'] = array(
                'caption' => l10n('Photos'),
                'url' => RATING_BASE_URL.'&amp;section=photos'
            );
            $sheets['users'] = array(
                'caption' => l10n('Users'),
                'url' => RATING_BASE_URL.'&amp;section=users'
            );
            break;

        case 'themes':
            $sheets['installed'] = array(
                'caption' => '<span class="icon-brush"></span>'.l10n('Installed Themes'),
                'url' => THEMES_BASE_URL.'&amp;section=installed'
            );
            $sheets['update'] = array(
                'caption' => '<span class="icon-arrows-cw"></span>'.l10n('Check for updates'),
                'url' => THEMES_BASE_URL.'&amp;section=update'
            );
            $sheets['new'] = array(
                'caption' => '<span class="icon-plus-circled"></span>'.l10n('Add New Theme'),
                'url' => THEMES_BASE_URL.'&amp;section=new'
            );
            break;

        case 'updates':
            $sheets['core'] = array(
                'caption' => l10n('Phyxo Update'),
                'url' => UPDATES_BASE_URL.'&amp;section=core'
            );

            // $sheets['ext'] = array(
            //     'caption' => l10n('Extensions Update'),
            //     'url' => UPDATES_BASE_URL.'&amp;section=ext'
            // );
            break;
        }

    return $sheets;
}
