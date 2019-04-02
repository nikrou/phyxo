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

use GuzzleHttp\Client;
use App\Repository\TagRepository;
use App\Repository\CommentRepository;
use App\Repository\CategoryRepository;
use App\Repository\RateRepository;
use App\Repository\ImageRepository;
use App\Repository\ImageTagRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Repository\BaseRepository;
use Phyxo\DBLayer\DBLayer;

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
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$php_current_timestamp = date("Y-m-d H:i:s");
$db_version = $conn->db_version();
$db_current_date = (new BaseRepository($conn))->getNow();
$nb_elements = (new ImageRepository($conn))->count();
$nb_categories = (new CategoryRepository($conn))->count();
$nb_virtual = (new CategoryRepository($conn))->count('dir IS NULL');
$nb_physical = (new CategoryRepository($conn))->count('dir IS NOT NULL');
$nb_image_category = (new ImageCategoryRepository($conn))->count();
$nb_tags = (new TagRepository($conn))->count();
$nb_image_tag = (new ImageTagRepository($conn))->count();
$nb_users = (new UserRepository($conn))->count();
$nb_groups = (new GroupRepository($conn))->count();
$nb_rates = (new RateRepository($conn))->count();

$template->assign(
    [
        'PHPWG_URL' => PHPWG_URL,
        'PWG_VERSION' => PHPWG_VERSION,
        'OS' => PHP_OS,
        'PHP_VERSION' => phpversion(),
        'DB_ENGINE' => DBLayer::availableEngines()[$conn->getLayer()],
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
        'PHP_DATATIME' => $php_current_timestamp,
        'DB_DATATIME' => $db_current_date,
    ]
);

if ($conf['activate_comments']) {
    $nb_comments = (new CommentRepository($conn))->count();
    $template->assign('DB_COMMENTS', \Phyxo\Functions\Language::l10n_dec('%d comment', '%d comments', $nb_comments));
}

if ($nb_elements > 0) {
    $min_date_available = (new ImageRepository($conn))->findMinDateAvailable();
    $template->assign(
        'first_added',
        [
            'DB_DATE' =>
            \Phyxo\Functions\Language::l10n('first photo added on %s', \Phyxo\Functions\DateTime::format_date($min_date_available))
        ]
    );
}

// graphics library
switch (\Phyxo\Image\Image::get_library()) {
    case 'Imagick':
        $library = 'ImageMagick';
        $img = new Imagick();
        $version = $img->getVersion();
        if (preg_match('/ImageMagick \d+\.\d+\.\d+-?\d*/', $version['versionString'], $match)) {
            $library = $match[0];
        }
        $template->assign('GRAPHICS_LIBRARY', $library);
        break;

    case 'ExtImagick':
        $library = 'External ImageMagick';
        exec($conf['ext_imagick_dir'] . 'convert -version', $returnarray);
        if (preg_match('/Version: ImageMagick (\d+\.\d+\.\d+-?\d*)/', $returnarray[0], $match)) {
            $library .= ' ' . $match[1];
        }
        $template->assign('GRAPHICS_LIBRARY', $library);
        break;

    case 'GD':
        $gd_info = gd_info();
        $template->assign('GRAPHICS_LIBRARY', 'GD ' . @$gd_info['GD Version']);
        break;
}

$template_filename = 'intro';
