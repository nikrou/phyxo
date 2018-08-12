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

define('PHPWG_ROOT_PATH', '../../');
session_cache_limiter('public');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');

// Check Access and exit when user status is not ok
$services['users']->checkStatus(ACCESS_GUEST);

function guess_mime_type($ext)
{
    switch (strtolower($ext)) {
        case "jpe":
        case "jpeg":
        case "jpg":
            $ctype = "image/jpeg";
            break;
        case "png":
            $ctype = "image/png";
            break;
        case "gif":
            $ctype = "image/gif";
            break;
        case "tiff":
        case "tif":
            $ctype = "image/tiff";
            break;
        case "txt":
            $ctype = "text/plain";
            break;
        case "html":
        case "htm":
            $ctype = "text/html";
            break;
        case "xml":
            $ctype = "text/xml";
            break;
        case "pdf":
            $ctype = "application/pdf";
            break;
        case "zip":
            $ctype = "application/zip";
            break;
        case "ogg":
            $ctype = "application/ogg";
            break;
        default:
            $ctype = "application/octet-stream";
    }

    return $ctype;
}

function do_error($code, $str)
{
    \Phyxo\Functions\HTTP::set_status_header($code);
    echo $str;
    exit();
}


if (!isset($_GET['id']) or !is_numeric($_GET['id']) or !isset($_GET['part']) or !in_array($_GET['part'], array('e', 'r'))) {
    do_error(400, 'Invalid request - id/part');
}

$query = 'SELECT * FROM ' . IMAGES_TABLE;
$query .= ' WHERE id=' . $conn->db_real_escape_string($_GET['id']);

$element_info = $conn->db_fetch_assoc($conn->db_query($query));
if (empty($element_info)) {
    do_error(404, 'Requested id not found');
}

// $filter['visible_categories'] and $filter['visible_images']
// are not used because it's not necessary (filter <> restriction)
$query = 'SELECT id FROM ' . CATEGORIES_TABLE;
$query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON category_id = id';
$query .= ' WHERE image_id = ' . $conn->db_real_escape_string($_GET['id']);
$query .= \Phyxo\Functions\SQL::get_sql_condition_FandF(array('forbidden_categories' => 'category_id', 'forbidden_images' => 'image_id'), ' AND ');
$query .= ' LIMIT 1';

if ($conn->db_num_rows($conn->db_query($query)) == 0) {
    do_error(401, 'Access denied');
}

include_once(PHPWG_ROOT_PATH . 'include/functions_picture.inc.php');
$file = '';
switch ($_GET['part']) {
    case 'e':
        if (!$user['enabled_high']) {
            $deriv = new \Phyxo\Image\DerivativeImage(IMG_XXLARGE, new \Phyxo\Image\SrcImage($element_info));
            if (!$deriv->same_as_source()) {
                do_error(401, 'Access denied e');
            }
        }
        $file = \Phyxo\Functions\Utils::get_element_path($element_info);
        break;
    case 'r':
        $file = \Phyxo\Functions\Utils::original_to_representative(\Phyxo\Functions\Utils::get_element_path($element_info), $element_info['representative_ext']);
        break;
}

if (empty($file)) {
    do_error(404, 'Requested file not found');
}

if ($_GET['part'] == 'e') {
    \Phyxo\Functions\Utils::log($_GET['id'], 'high');
} elseif ($_GET['part'] == 'e') {
    \Phyxo\Functions\Utils::log($_GET['id'], 'other');
}

$http_headers = array();

$ctype = null;
if (!\Phyxo\Functions\URL::url_is_remote($file)) {
    if (!is_readable($file)) {
        do_error(404, "Requested file not found - $file");
    }
    $http_headers[] = 'Content-Length: ' . @filesize($file);
    if (function_exists('mime_content_type')) {
        $ctype = mime_content_type($file);
    }

    $gmt_mtime = gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT';
    $http_headers[] = 'Last-Modified: ' . $gmt_mtime;

    // following lines would indicate how the client should handle the cache
    /* $max_age=300;
       $http_headers[] = 'Expires: '.gmdate('D, d M Y H:i:s', time()+$max_age).' GMT';
       // HTTP/1.1 only
       $http_headers[] = 'Cache-Control: private, must-revalidate, max-age='.$max_age;*/

    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        \Phyxo\Functions\HTTP::set_status_header(304);
        foreach ($http_headers as $header) {
            header($header);
        }
        exit();
    }
}

if (!isset($ctype)) { // give it a guess
    $ctype = guess_mime_type(\Phyxo\Functions\Utils::get_extension($file));
}

$http_headers[] = 'Content-Type: ' . $ctype;

if (isset($_GET['download'])) {
    $http_headers[] = 'Content-Disposition: attachment; filename="' . $element_info['file'] . '";';
    $http_headers[] = 'Content-Transfer-Encoding: binary';
} else {
    $http_headers[] = 'Content-Disposition: inline; filename="' . basename($file) . '";';
}

foreach ($http_headers as $header) {
    header($header);
}
// Without clean and flush there may be some image download problems, or image can be corrupted after download
ob_clean();
flush();
@readfile($file);
