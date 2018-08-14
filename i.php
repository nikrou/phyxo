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

define('PHPWG_ROOT_PATH', './');

require_once(PHPWG_ROOT_PATH . '/vendor/autoload.php');

use Phyxo\DBLayer\DBLayer;

// fast bootstrap - no db connection
include(PHPWG_ROOT_PATH . 'include/config_default.inc.php');
if (is_readable(PHPWG_ROOT_PATH . 'local/config/config.inc.php')) {
    include(PHPWG_ROOT_PATH . 'local/config/config.inc.php');
}

defined('PWG_LOCAL_DIR') or define('PWG_LOCAL_DIR', 'local/');
defined('PWG_DERIVATIVE_DIR') or define('PWG_DERIVATIVE_DIR', $conf['data_location'] . 'i/');

include(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'config/database.inc.php');

include(PHPWG_ROOT_PATH . 'include/constants.php');

// end fast bootstrap

function ilog()
{
    global $conf;

    if (!$conf['enable_i_log']) return;

    $line = date("c");
    foreach (func_get_args() as $arg) {
        $line .= ' ';
        if (is_array($arg)) {
            $line .= implode(' ', $arg);
        } else {
            $line .= $arg;
        }
    }
    $file = PHPWG_ROOT_PATH . $conf['data_location'] . 'tmp/i.log';
    if (false == file_put_contents($file, $line . "\n", FILE_APPEND)) {
        \Phyxo\Functions\Utils::mkgetdir(dirname($file));
    }
}

function ierror($msg, $code)
{
    if ($code == 301 || $code == 302) {
        if (ob_get_length() !== false) {
            ob_clean();
        }

        // default url is on html format
        $url = html_entity_decode($msg);
        header('Request-URI: ' . $url);
        header('Content-Location: ' . $url);
        header('Location: ' . $url);
        ilog('WARN', $code, $url, $_SERVER['REQUEST_URI']);
        exit;
    }
    if ($code >= 400) {
        $protocol = $_SERVER["SERVER_PROTOCOL"];
        if (('HTTP/1.1' != $protocol) && ('HTTP/1.0' != $protocol)) {
            $protocol = 'HTTP/1.0';
        }

        header("$protocol $code $msg", true, $code);
    }

    // TODO: improve
    echo $msg;
    ilog('ERROR', $code, $msg, $_SERVER['REQUEST_URI']);
    exit;
}

function time_step(&$step)
{
    $tmp = $step;
    $step = microtime(true);
    return intval(1000 * ($step - $tmp));
}

function url_to_size($s)
{
    $pos = strpos($s, 'x');
    if ($pos === false) {
        return array((int)$s, (int)$s);
    }

    return array((int)substr($s, 0, $pos), (int)substr($s, $pos + 1));
}

function parse_custom_params($tokens)
{
    if (count($tokens) < 1) {
        ierror('Empty array while parsing Sizing', 400);
    }

    $crop = 0;
    $min_size = null;

    $token = array_shift($tokens);
    if ($token[0] == 's') {
        $size = url_to_size(substr($token, 1));
    } elseif ($token[0] == 'e') {
        $crop = 1;
        $size = $min_size = url_to_size(substr($token, 1));
    } else {
        $size = url_to_size($token);
        if (count($tokens) < 2) {
            ierror('Sizing arr', 400);
        }

        $token = array_shift($tokens);
        $crop = \Phyxo\Image\DerivativeParams::char_to_fraction($token);

        $token = array_shift($tokens);
        $min_size = url_to_size($token);
    }

    return new \Phyxo\Image\DerivativeParams(new \Phyxo\Image\SizingParams($size, $crop, $min_size));
}

function parse_request()
{
    global $conf, $page;

    if (!$conf['question_mark_in_urls'] && !empty($_SERVER['PATH_INFO'])) {
        $req = $_SERVER['PATH_INFO'];
        $req = str_replace('//', '/', $req);
        $path_count = count(explode('/', $req));
        $page['root_path'] = PHPWG_ROOT_PATH . str_repeat('../', $path_count - 1);
    } else {
        $req = $_SERVER["QUERY_STRING"];
        if ($pos = strpos($req, '&')) {
            $req = substr($req, 0, $pos);
        }
        $req = rawurldecode($req);
        $page['root_path'] = PHPWG_ROOT_PATH;
    }

    $req = ltrim($req, '/');

    foreach (preg_split('#/+#', $req) as $token) {
        preg_match($conf['sync_chars_regex'], $token) or ierror('Invalid chars in request', 400);
    }

    $page['derivative_path'] = PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . $req;

    $pos = strrpos($req, '.');
    $pos !== false || ierror('Missing .', 400);
    $ext = substr($req, $pos);
    $page['derivative_ext'] = $ext;
    $req = substr($req, 0, $pos);

    $pos = strrpos($req, '-');
    $pos !== false || ierror('Missing -', 400);
    $deriv = substr($req, $pos + 1);
    $req = substr($req, 0, $pos);

    $deriv = explode('_', $deriv);
    foreach (\Phyxo\Image\ImageStdParams::get_defined_type_map() as $type => $params) {
        if (\Phyxo\Image\DerivativeParams::derivative_to_url($type) == $deriv[0]) {
            $page['derivative_type'] = $type;
            $page['derivative_params'] = $params;
            break;
        }
    }

    if (!isset($page['derivative_type'])) {
        if (\Phyxo\Image\DerivativeParams::derivative_to_url(IMG_CUSTOM) == $deriv[0]) {
            $page['derivative_type'] = IMG_CUSTOM;
        } else {
            ierror('Unknown parsing type', 400);
        }
    }
    array_shift($deriv);

    if ($page['derivative_type'] == IMG_CUSTOM) {
        $params = $page['derivative_params'] = parse_custom_params($deriv);
        \Phyxo\Image\ImageStdParams::apply_global($params);

        if ($params->sizing->ideal_size[0] < 20 or $params->sizing->ideal_size[1] < 20) {
            ierror('Invalid size', 400);
        }
        if ($params->sizing->max_crop < 0 or $params->sizing->max_crop > 1) {
            ierror('Invalid crop', 400);
        }
        $greatest = \Phyxo\Image\ImageStdParams::get_by_type(IMG_XXLARGE);

        $key = array();
        $params->add_url_tokens($key);
        $key = implode('_', $key);
        if (!isset(\Phyxo\Image\ImageStdParams::$custom[$key])) {
            ierror('Size not allowed', 403);
        }
    }

    if (is_file(PHPWG_ROOT_PATH . $req . $ext)) {
        $req = './' . $req; // will be used to match #iamges.path
    } elseif (is_file(PHPWG_ROOT_PATH . '../' . $req . $ext)) {
        $req = '../' . $req;
    }

    $page['src_location'] = $req . $ext;
    $page['src_path'] = PHPWG_ROOT_PATH . $page['src_location'];
    $page['src_url'] = $page['root_path'] . $page['src_location'];
}

function try_switch_source(\Phyxo\Image\DerivativeParams $params, $original_mtime)
{
    global $page;

    if (!isset($page['original_size'])) {
        return false;
    }

    $original_size = $page['original_size'];
    if ($page['rotation_angle'] == 90 || $page['rotation_angle'] == 270) {
        $tmp = $original_size[0];
        $original_size[0] = $original_size[1];
        $original_size[1] = $tmp;
    }
    $dsize = $params->compute_final_size($original_size);

    $use_watermark = $params->use_watermark;
    if ($use_watermark) {
        $use_watermark = $params->will_watermark($dsize);
    }

    $candidates = array();
    foreach (\Phyxo\Image\ImageStdParams::get_defined_type_map() as $candidate) {
        if ($candidate->type == $params->type) {
            continue;
        }
        if ($candidate->use_watermark != $use_watermark) {
            continue;
        }
        if ($candidate->max_width() < $params->max_width() || $candidate->max_height() < $params->max_height()) {
            continue;
        }
        $candidate_size = $candidate->compute_final_size($original_size);
        if ($dsize != $params->compute_final_size($candidate_size)) {
            continue;
        }

        if ($params->sizing->max_crop == 0) {
            if ($candidate->sizing->max_crop != 0) {
                continue;
            }
        } else {
            if ($candidate->sizing->max_crop != 0) {
                continue; // this could be optimized
            }
            if ($candidate_size[0] < $params->sizing->min_size[0] || $candidate_size[1] < $params->sizing->min_size[1]) {
                continue;
            }
        }
        $candidates[] = $candidate;
    }

    foreach (array_reverse($candidates) as $candidate) {
        $candidate_path = $page['derivative_path'];
        $candidate_path = str_replace('-' . \Phyxo\Image\DerivativeParams::derivative_to_url($params->type), '-' . \Phyxo\Image\DerivativeParams::derivative_to_url($candidate->type), $candidate_path);
        $candidate_mtime = @filemtime($candidate_path);
        if ($candidate_mtime === false || $candidate_mtime < $original_mtime || $candidate_mtime < $candidate->last_mod_time) {
            continue;
        }
        $params->use_watermark = false;
        $params->sharpen = min(1, $params->sharpen);
        $page['src_path'] = $candidate_path;
        $page['src_url'] = $page['root_path'] . substr($candidate_path, strlen(PHPWG_ROOT_PATH));
        $page['rotation_angle'] = 0;
        return true;
    }

    return false;
}

function send_derivative($expires)
{
    global $page;

    if (isset($_GET['ajaxload']) and $_GET['ajaxload'] == 'true') {
        echo json_encode(array('url' => \Phyxo\Functions\URL::embellish_url(\Phyxo\Functions\URL::get_absolute_root_url() . $page['derivative_path'])));
        return;
    }

    $fp = fopen($page['derivative_path'], 'rb');

    $fstat = fstat($fp);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $fstat['mtime']) . ' GMT');
    if ($expires !== false) {
        header('Expires: ' . gmdate('D, d M Y H:i:s', $expires) . ' GMT');
    }
    header('Content-length: ' . $fstat['size']);
    header('Connection: close');

    $ctype = "application/octet-stream";
    switch (strtolower($page['derivative_ext'])) {
        case ".jpe":
        case ".jpeg":
        case ".jpg":
            $ctype = "image/jpeg";
            break;
        case ".png":
            $ctype = "image/png";
            break;
        case ".gif":
            $ctype = "image/gif";
            break;
    }
    header("Content-Type: $ctype");

    fpassthru($fp);
    fclose($fp);
}

$page = array();
$begin = $step = microtime(true);
$timing = array();
foreach (explode(',', 'load,rotate,crop,scale,sharpen,watermark,save,send') as $k) {
    $timing[$k] = '';
}

try {
    $conn = DBLayer::init($conf['dblayer'], $conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_base']);
} catch (Exception $e) {
    ilog("db error", $e->getMessage());
}

$query = 'SELECT value FROM ' . $prefixeTable . 'config WHERE param=\'derivatives\'';
list($conf['derivatives']) = $conn->db_fetch_row($conn->db_query($query));
\Phyxo\Image\ImageStdParams::load_from_db();

parse_request();
$params = $page['derivative_params'];

$src_mtime = @filemtime($page['src_path']);
if ($src_mtime === false) {
    ierror('Source not found', 404);
}

$need_generate = false;
$derivative_mtime = @filemtime($page['derivative_path']);
if ($derivative_mtime === false or $derivative_mtime < $src_mtime or $derivative_mtime < $params->last_mod_time) {
    $need_generate = true;
}

$expires = false;
$now = time();
if (isset($_GET['b'])) {
    $expires = $now + 100;
    header("Cache-control: no-store, max-age=100");
} elseif ($now > (max($src_mtime, $params->last_mod_time) + 24 * 3600)) {
    // somehow arbitrary - if derivative params or src didn't change for the last 24 hours, we send an expire header for several days
    $expires = $now + 10 * 24 * 3600;
}

if (!$need_generate) {
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) and strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $derivative_mtime) {
        // send the last mod time of the file back
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $derivative_mtime) . ' GMT', true, 304);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 10 * 24 * 3600) . ' GMT', true, 304);
        exit;
    }
    send_derivative($expires);
    exit;
}

$page['coi'] = null;
if (strpos($page['src_location'], '/pwg_representative/') === false
    && strpos($page['src_location'], 'themes/') === false && strpos($page['src_location'], 'plugins/') === false) {
    try {
        $query = 'SELECT * FROM ' . $prefixeTable . 'images';
        $query .= ' WHERE path=\'' . $conn->db_real_escape_string($page['src_location']) . '\'';

        if (($row = $conn->db_fetch_assoc($conn->db_query($query)))) {
            if (isset($row['width'])) {
                $page['original_size'] = array($row['width'], $row['height']);
            }
            $page['coi'] = $row['coi'];

            if (!isset($row['rotation'])) {
                $page['rotation_angle'] = \Phyxo\Image\Image::get_rotation_angle($page['src_path']);

                $conn->single_update(
                    $prefixeTable . 'images',
                    array('rotation' => \Phyxo\Image\Image::get_rotation_code_from_angle($page['rotation_angle'])),
                    array('id' => $row['id'])
                );
            } else {
                $page['rotation_angle'] = \Phyxo\Image\Image::get_rotation_angle_from_code($row['rotation']);
            }
        }
        if (!$row) {
            ierror('Db file path not found', 404);
        }
    } catch (Exception $e) {
        ilog("db error", $e->getMessage());
    }
} else {
    $page['rotation_angle'] = 0;
}
$conn->db_close();

if (!try_switch_source($params, $src_mtime) && $params->type == IMG_CUSTOM) {
    $sharpen = 0;
    foreach (\Phyxo\Image\ImageStdParams::get_defined_type_map() as $std_params) {
        $sharpen += $std_params->sharpen;
    }
    $params->sharpen = round($sharpen / count(\Phyxo\Image\ImageStdParams::get_defined_type_map()));
}

if (!\Phyxo\Functions\Utils::mkgetdir(dirname($page['derivative_path']))) {
    ierror("dir create error", 500);
}

ignore_user_abort(true);
@set_time_limit(0);

$image = new \Phyxo\Image\Image($page['src_path']);
$timing['load'] = time_step($step);

$changes = 0;

// rotate
if (0 != $page['rotation_angle']) {
    $image->rotate($page['rotation_angle']);
    $changes++;
    $timing['rotate'] = time_step($step);
}

// Crop & scale
$o_size = $d_size = array($image->get_width(), $image->get_height());
$params->sizing->compute($o_size, $page['coi'], $crop_rect, $scaled_size);
if ($crop_rect) {
    $changes++;
    $image->crop($crop_rect->width(), $crop_rect->height(), $crop_rect->l, $crop_rect->t);
    $timing['crop'] = time_step($step);
}

if ($scaled_size) {
    $changes++;
    $image->resize($scaled_size[0], $scaled_size[1]);
    $d_size = $scaled_size;
    $timing['scale'] = time_step($step);
}

if ($params->sharpen) {
    $changes += $image->sharpen($params->sharpen);
    $timing['sharpen'] = time_step($step);
}

if ($params->will_watermark($d_size)) {
    $wm = \Phyxo\Image\ImageStdParams::get_watermark();
    $wm_image = new \Phyxo\Image\Image(PHPWG_ROOT_PATH . $wm->file);
    $wm_size = array($wm_image->get_width(), $wm_image->get_height());
    if ($d_size[0] < $wm_size[0] or $d_size[1] < $wm_size[1]) {
        $wm_scaling_params = \Phyxo\Image\SizingParams::classic($d_size[0], $d_size[1]);
        $wm_scaling_params->compute($wm_size, null, $tmp, $wm_scaled_size);
        $wm_size = $wm_scaled_size;
        $wm_image->resize($wm_scaled_size[0], $wm_scaled_size[1]);
    }
    $x = round(($wm->xpos / 100) * ($d_size[0] - $wm_size[0]));
    $y = round(($wm->ypos / 100) * ($d_size[1] - $wm_size[1]));
    if ($image->compose($wm_image, $x, $y, $wm->opacity)) {
        $changes++;
        if ($wm->xrepeat) {
            // todo
            $pad = $wm_size[0] + max(30, round($wm_size[0] / 4));
            for ($i = -$wm->xrepeat; $i <= $wm->xrepeat; $i++) {
                if (!$i) {
                    continue;
                }
                $x2 = $x + $i * $pad;
                if ($x2 >= 0 && $x2 + $wm_size[0] < $d_size[0]) {
                    if (!$image->compose($wm_image, $x2, $y, $wm->opacity)) {
                        break;
                    }
                }
            }
        }
    }
    $wm_image->destroy();
    $timing['watermark'] = time_step($step);
}

// no change required - redirect to source
if (!$changes) {
    header("X-i: No change");
    ierror($page['src_url'], 301);
}

if ($d_size[0] * $d_size[1] < $conf['derivatives_strip_metadata_threshold']) {// strip metadata for small images
    $image->strip();
}

$image->set_compression_quality(\Phyxo\Image\ImageStdParams::$quality);
$image->write($page['derivative_path']);
$image->destroy();
@chmod($page['derivative_path'], 0644);
$timing['save'] = time_step($step);

send_derivative($expires);
$timing['send'] = time_step($step);

ilog(
    'perf',
    basename($page['src_path']),
    $o_size,
    $o_size[0] * $o_size[1],
    basename($page['derivative_path']),
    $d_size,
    $d_size[0] * $d_size[1],
    function_exists('memory_get_peak_usage') ? round(memory_get_peak_usage() / (1024 * 1024), 1) : '',
    time_step($begin),
    '|',
    $timing
);
