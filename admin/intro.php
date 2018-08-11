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

if (!defined('PHPWG_ROOT_PATH')) {
    die("Hacking attempt!");
}

use GuzzleHttp\Client;


include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH . 'admin/include/image.class.php');
include_once PHPWG_ROOT_PATH . 'include/dblayers.inc.php';

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
        $response = $client->request('GET', PHPWG_URL . '/download/');
        if ($response->getStatusCode() == 200 && $response->getBody()->isReadable()) {
            $versions = json_decode($response->getBody(), true);
            $latest_version = $versions[0]['version'];
        } else {
            throw new \Exception('Unable to check for upgrade.');
        }

        if (preg_match('/.*-dev$/', PHPWG_VERSION, $matches)) {
            $page['infos'][] = \Phyxo\Functions\Language::l10n('You are running on development sources, no check possible.');
        } elseif (version_compare(PHPWG_VERSION, $latest_version) < 0) {
            $page['infos'][] = \Phyxo\Functions\Language::l10n('A new version of Phyxo is available.');
        } else {
            $page['infos'][] = \Phyxo\Functions\Language::l10n('You are running the latest version of Phyxo.');
        }
    } catch (\Exception $e) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Unable to check for upgrade.');
    }
} elseif (isset($_GET['action']) and 'phpinfo' == $_GET['action']) {
    // Show phpinfo() output
    phpinfo();
    exit();
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$php_current_timestamp = date("Y-m-d H:i:s");
$db_version = $conn->db_version();
list($db_current_date) = $conn->db_fetch_row($conn->db_query('SELECT now();'));

$query = 'SELECT COUNT(1) FROM ' . IMAGES_TABLE;
list($nb_elements) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM ' . CATEGORIES_TABLE;
list($nb_categories) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM ' . CATEGORIES_TABLE . ' WHERE dir IS NULL';
list($nb_virtual) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM ' . CATEGORIES_TABLE . ' WHERE dir IS NOT NULL';
list($nb_physical) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM ' . IMAGE_CATEGORY_TABLE;
list($nb_image_category) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM ' . TAGS_TABLE;
list($nb_tags) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM ' . IMAGE_TAG_TABLE;
list($nb_image_tag) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM ' . USERS_TABLE;
list($nb_users) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM ' . GROUPS_TABLE;
list($nb_groups) = $conn->db_fetch_row($conn->db_query($query));

$query = 'SELECT COUNT(1) FROM ' . RATE_TABLE;
list($nb_rates) = $conn->db_fetch_row($conn->db_query($query));

$template->assign(
    array(
        'PHPWG_URL' => PHPWG_URL,
        'PWG_VERSION' => PHPWG_VERSION,
        'OS' => PHP_OS,
        'PHP_VERSION' => phpversion(),
        'DB_ENGINE' => $dblayers[$conf['dblayer']]['engine'],
        'DB_VERSION' => $db_version,
        'DB_ELEMENTS' => \Phyxo\Functions\Language::l10n_dec('%d photo', '%d photos', $nb_elements),
        'DB_CATEGORIES' =>
            \Phyxo\Functions\Language::l10n_dec('%d album including', '%d albums including', $nb_categories) .
            \Phyxo\Functions\Language::l10n_dec('%d physical', '%d physicals', $nb_physical) .
            \Phyxo\Functions\Language::l10n_dec(' and %d virtual', ' and %d virtuals', $nb_virtual),
        'DB_IMAGE_CATEGORY' => \Phyxo\Functions\Language::l10n_dec('%d association', '%d associations', $nb_image_category),
        'DB_TAGS' => \Phyxo\Functions\Language::l10n_dec('%d tag', '%d tags', $nb_tags),
        'DB_IMAGE_TAG' => \Phyxo\Functions\Language::l10n_dec('%d association', '%d associations', $nb_image_tag),
        'DB_USERS' => \Phyxo\Functions\Language::l10n_dec('%d user', '%d users', $nb_users),
        'DB_GROUPS' => \Phyxo\Functions\Language::l10n_dec('%d group', '%d groups', $nb_groups),
        'DB_RATES' => ($nb_rates == 0) ? \Phyxo\Functions\Language::l10n('no rate') : \Phyxo\Functions\Language::l10n('%d rates', $nb_rates),
        'U_CHECK_UPGRADE' => \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?action=check_upgrade',
        'U_PHPINFO' => \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?action=phpinfo',
        'PHP_DATATIME' => $php_current_timestamp,
        'DB_DATATIME' => $db_current_date,
    )
);

if ($conf['activate_comments']) {
    $query = 'SELECT COUNT(1) FROM ' . COMMENTS_TABLE . ';';
    list($nb_comments) = $conn->db_fetch_row($conn->db_query($query));
    $template->assign('DB_COMMENTS', \Phyxo\Functions\Language::l10n_dec('%d comment', '%d comments', $nb_comments));
}

if ($nb_elements > 0) {
    $query = 'SELECT MIN(date_available) FROM ' . IMAGES_TABLE . ';';
    list($first_date) = $conn->db_fetch_row($conn->db_query($query));

    $template->assign(
        'first_added',
        array(
            'DB_DATE' =>
                \Phyxo\Functions\Language::l10n('first photo added on %s', \Phyxo\Functions\DateTime::format_date($first_date))
        )
    );
}

// graphics library
switch (pwg_image::get_library()) {
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
        exec($conf['ext_imagick_dir'] . 'convert -version', $returnarray);
        if (preg_match('/Version: ImageMagick (\d+\.\d+\.\d+-?\d*)/', $returnarray[0], $match)) {
            $library .= ' ' . $match[1];
        }
        $template->assign('GRAPHICS_LIBRARY', $library);
        break;

    case 'gd':
        $gd_info = gd_info();
        $template->assign('GRAPHICS_LIBRARY', 'GD ' . @$gd_info['GD Version']);
        break;
}

$template_filename = 'intro';
