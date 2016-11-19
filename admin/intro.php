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

if (!defined('PHPWG_ROOT_PATH')) {
    die ("Hacking attempt!");
}

use GuzzleHttp\Client;


include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH.'admin/include/image.class.php');
include_once PHPWG_ROOT_PATH. 'include/dblayers.inc.php';

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+

// Check for upgrade : code inspired from punbb
if (isset($_GET['action']) and 'check_upgrade' == $_GET['action']) {
    try {
        $client = new Client();
        $request = $client->createRequest('GET', PHPWG_URL.'/download/');
        $response = $client->send($request);
        if ($response->getStatusCode()==200 && $response->getBody()->isReadable()) {
            $versions = json_decode($response->getBody(), true);
            $latest_version = $versions[0]['version'];
        } else {
            throw new \Exception('Unable to check for upgrade.');
        }

        if (preg_match('/.*-dev$/', PHPWG_VERSION, $matches)) {
            $page['infos'][] = l10n('You are running on development sources, no check possible.');
        } elseif (version_compare(PHPWG_VERSION, $latest_version) < 0) {
            $page['infos'][] = l10n('A new version of Phyxo is available.');
        } else {
            $page['infos'][] = l10n('You are running the latest version of Phyxo.');
        }
    } catch (\Exception $e) {
        $page['errors'][] = l10n('Unable to check for upgrade.');
    }
} elseif (isset($_GET['action']) and 'phpinfo' == $_GET['action']) {
    // Show phpinfo() output
    phpinfo();
    exit();
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(array('intro' => 'intro.tpl'));

$php_current_timestamp = date("Y-m-d H:i:s");
$db_version = $conn->db_version();
list($db_current_date) = $conn->db_fetch_row($conn->db_query('SELECT now();'));

$query = 'SELECT COUNT(1) FROM '.IMAGES_TABLE;
list($nb_elements) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM '.CATEGORIES_TABLE;
list($nb_categories) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM '.CATEGORIES_TABLE.' WHERE dir IS NULL';
list($nb_virtual) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM '.CATEGORIES_TABLE.' WHERE dir IS NOT NULL';
list($nb_physical) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM '.IMAGE_CATEGORY_TABLE;
list($nb_image_category) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM '.TAGS_TABLE;
list($nb_tags) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM '.IMAGE_TAG_TABLE;
list($nb_image_tag) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM '.USERS_TABLE;
list($nb_users) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM '.GROUPS_TABLE;
list($nb_groups) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM '.RATE_TABLE;
list($nb_rates) = $conn->db_fetch_row($conn->db_query($query));

$template->assign(
    array(
        'PHPWG_URL' => PHPWG_URL,
        'PWG_VERSION' => PHPWG_VERSION,
        'OS' => PHP_OS,
        'PHP_VERSION' => phpversion(),
        'DB_ENGINE' => $dblayers[$conf['dblayer']]['engine'],
        'DB_VERSION' => $db_version,
        'DB_ELEMENTS' => l10n_dec('%d photo', '%d photos', $nb_elements),
        'DB_CATEGORIES' =>
        l10n_dec('%d album including', '%d albums including', $nb_categories).
        l10n_dec('%d physical', '%d physicals', $nb_physical).
        l10n_dec(' and %d virtual', ' and %d virtuals', $nb_virtual),
        'DB_IMAGE_CATEGORY' => l10n_dec('%d association', '%d associations', $nb_image_category),
        'DB_TAGS' => l10n_dec('%d tag', '%d tags', $nb_tags),
        'DB_IMAGE_TAG' => l10n_dec('%d association', '%d associations', $nb_image_tag),
        'DB_USERS' => l10n_dec('%d user', '%d users', $nb_users),
        'DB_GROUPS' => l10n_dec('%d group', '%d groups', $nb_groups),
        'DB_RATES' => ($nb_rates == 0) ? l10n('no rate') : l10n('%d rates', $nb_rates),
        'U_CHECK_UPGRADE' => get_root_url().'admin/index.php?action=check_upgrade',
        'U_PHPINFO' => get_root_url().'admin/index.php?action=phpinfo',
        'PHP_DATATIME' => $php_current_timestamp,
        'DB_DATATIME' => $db_current_date,
    )
);

if ($conf['activate_comments']) {
    $query = 'SELECT COUNT(1) FROM '.COMMENTS_TABLE.';';
    list($nb_comments) = $conn->db_fetch_row($conn->db_query($query));
    $template->assign('DB_COMMENTS', l10n_dec('%d comment', '%d comments', $nb_comments));
}

if ($nb_elements > 0) {
    $query = 'SELECT MIN(date_available) FROM '.IMAGES_TABLE.';';
    list($first_date) = $conn->db_fetch_row($conn->db_query($query));

    $template->assign(
        'first_added',
        array(
            'DB_DATE' =>
            l10n('first photo added on %s', format_date($first_date))
        )
    );
}

// graphics library
switch (pwg_image::get_library())
    {
    case 'imagick':
        $library = 'ImageMagick';
        $img = new Imagick();
        $version = $img->getVersion();
        if (preg_match('/ImageMagick \d+\.\d+\.\d+-?\d*/', $version['versionString'], $match)) {
            $library = $match[0];
        }
        $template->assign('GRAPHICS_LIBRARY', $library);
        break;

    case 'ext_imagick':
        $library = 'External ImageMagick';
        exec($conf['ext_imagick_dir'].'convert -version', $returnarray);
        if (preg_match('/Version: ImageMagick (\d+\.\d+\.\d+-?\d*)/', $returnarray[0], $match)) {
            $library .= ' ' . $match[1];
        }
        $template->assign('GRAPHICS_LIBRARY', $library);
        break;

    case 'gd':
        $gd_info = gd_info();
        $template->assign('GRAPHICS_LIBRARY', 'GD '.@$gd_info['GD Version']);
        break;
    }

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'intro');
