<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2015 Nicolas Roudaire         http://www.phyxo.net/ |
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

if (!defined('PHPWG_ROOT_PATH')) {
    die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

if (!$conf['enable_synchronization']) {
    die('synchronization is disabled');
}

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

if (!empty($_POST) or isset($_GET['action'])) {
    check_pwg_token();
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+
$template->set_filenames(array('site_manager' => 'site_manager.tpl'));

// +-----------------------------------------------------------------------+
// |                        new site creation form                         |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit']) and !empty($_POST['galleries_url'])) {
    $is_remote = url_is_remote( $_POST['galleries_url'] );
    if ($is_remote) {
        fatal_error('remote sites not supported');
    }
    $url = preg_replace('/[\/]*$/', '', $_POST['galleries_url']);
    $url.= '/';
    if (!(strpos($url, '.') === 0)) {
        $url = './' . $url;
    }

    // site must not exists
    $query = 'SELECT COUNT(id) AS count FROM '.SITES_TABLE;
    $query .= ' WHERE galleries_url = \''.$url.'\';';
    $row = $conn->db_fetch_assoc($conn->db_query($query));
    if ($row['count'] > 0) {
        $page['errors'][] = l10n('This site already exists').' ['.$url.']';
    }
    if (count($page['errors']) == 0) {
        if (!file_exists($url)) {
            $page['errors'][] = l10n('Directory does not exist').' ['.$url.']';
        }
    }

    if (count($page['errors']) == 0) {
        $query = 'INSERT INTO '.SITES_TABLE.' (galleries_url) VALUES(\''.$url.'\');';
        $conn->db_query($query);
        $page['infos'][] = $url.' '.l10n('created');
    }
}

// +-----------------------------------------------------------------------+
// |                            actions on site                            |
// +-----------------------------------------------------------------------+
if (isset($_GET['site']) and is_numeric($_GET['site'])) {
    $page['site'] = $_GET['site'];
}
if (isset($_GET['action']) and isset($page['site'])) {
    $query = 'SELECT galleries_url FROM '.SITES_TABLE.' WHERE id = '.$conn->db_real_escape_string($page['site']);
    list($galleries_url) = $conn->db_fetch_row($conn->db_query($query));
    if ($_GET['action']=='delete') {
        delete_site($page['site']);
        $page['infos'][] = $galleries_url.' '.l10n('deleted');
    }
}

$template->assign(
    array(
        'F_ACTION'  => get_root_url().'admin.php'.get_query_string_diff(array('action','site','pwg_token')),
        'PWG_TOKEN' => get_pwg_token(),
    )
);

$query = 'SELECT c.site_id, COUNT(DISTINCT c.id) AS nb_categories, COUNT(i.id) AS nb_images';
$query .= ' FROM '.CATEGORIES_TABLE.' AS c LEFT JOIN '.IMAGES_TABLE.' AS i ON c.id=i.storage_category_id';
$query .= ' WHERE c.site_id IS NOT NULL GROUP BY c.site_id;';
$sites_detail = $conn->query2array($query, 'site_id');

$query = 'SELECT * FROM '.SITES_TABLE;
$result = $conn->db_query($query);

while ($row = $conn->db_fetch_assoc($result)) {
    $is_remote = url_is_remote($row['galleries_url']);
    $base_url = PHPWG_ROOT_PATH.'admin.php';
    $base_url.= '?page=site_manager';
    $base_url.= '&amp;site='.$row['id'];
    $base_url.= '&amp;pwg_token='.get_pwg_token();
    $base_url.= '&amp;action=';

    $update_url = PHPWG_ROOT_PATH.'admin.php';
    $update_url.= '?page=site_update';
    $update_url.= '&amp;site='.$row['id'];

    $tpl_var =
        array(
            'NAME' => $row['galleries_url'],
            'TYPE' => l10n( $is_remote ? 'Remote' : 'Local' ),
            'CATEGORIES' => (int)@$sites_detail[$row['id']]['nb_categories'],
            'IMAGES' => (int)@$sites_detail[$row['id']]['nb_images'],
            'U_SYNCHRONIZE' => $update_url
        );

    if ($row['id'] != 1) {
        $tpl_var['U_DELETE'] = $base_url.'delete';
    }

    $plugin_links = array();
    //$plugin_links is array of array composed of U_HREF, U_HINT & U_CAPTION
    $plugin_links = trigger_change('get_admins_site_links',
    $plugin_links, $row['id'], $is_remote);
    $tpl_var['plugin_links'] = $plugin_links;

    $template->append('sites', $tpl_var);
}

$template->assign_var_from_handle('ADMIN_CONTENT', 'site_manager');
