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

if (!defined("PHPWG_ROOT_PATH")) {
    die("Hacking attempt!");
}

$errors = array();
$pwatermark = $_POST['w'];

// step 0 - manage upload if any
if (isset($_FILES['watermarkImage']) and !empty($_FILES['watermarkImage']['tmp_name'])) {
    list($width, $height, $type) = getimagesize($_FILES['watermarkImage']['tmp_name']);
    if (IMAGETYPE_PNG != $type) {
        $errors['watermarkImage'] = sprintf(
            \Phyxo\Functions\Language::l10n('Allowed file types: %s.'),
            'PNG'
        );
    } else {
        $upload_dir = PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'watermarks';
        if (\Phyxo\Functions\Utils::mkgetdir($upload_dir, \Phyxo\Functions\Utils::MKGETDIR_DEFAULT & ~\Phyxo\Functions\Utils::MKGETDIR_DIE_ON_ERROR)) {
            $new_name = \Phyxo\Functions\Utils::get_filename_wo_extension($_FILES['watermarkImage']['name']) . '.png';
            $file_path = $upload_dir . '/' . $new_name;

            if (move_uploaded_file($_FILES['watermarkImage']['tmp_name'], $file_path)) {
                $pwatermark['file'] = substr($file_path, strlen(PHPWG_ROOT_PATH));
            } else {
                $page['errors'][] = $errors['watermarkImage'] = "$file_path " . \Phyxo\Functions\Language::l10n('no write access');
            }
        } else {
            $page['errors'][] = $errors['watermarkImage'] = sprintf(\Phyxo\Functions\Language::l10n('Add write access to the "%s" directory'), $upload_dir);
        }
    }
}

// step 1 - sanitize HTML input
switch ($pwatermark['position']) {
    case 'topleft':
        {
            $pwatermark['xpos'] = 0;
            $pwatermark['ypos'] = 0;
            break;
        }
    case 'topright':
        {
            $pwatermark['xpos'] = 100;
            $pwatermark['ypos'] = 0;
            break;
        }
    case 'middle':
        {
            $pwatermark['xpos'] = 50;
            $pwatermark['ypos'] = 50;
            break;
        }
    case 'bottomleft':
        {
            $pwatermark['xpos'] = 0;
            $pwatermark['ypos'] = 100;
            break;
        }
    case 'bottomright':
        {
            $pwatermark['xpos'] = 100;
            $pwatermark['ypos'] = 100;
            break;
        }
}

// step 2 - check validity
$v = intval($pwatermark['xpos']);
if ($v < 0 or $v > 100) {
    $errors['watermark']['xpos'] = '[0..100]';
}

$v = intval($pwatermark['ypos']);
if ($v < 0 or $v > 100) {
    $errors['watermark']['ypos'] = '[0..100]';
}

$v = intval($pwatermark['opacity']);
if ($v <= 0 or $v > 100) {
    $errors['watermark']['opacity'] = '(0..100]';
}

// step 3 - save data
if (count($errors) == 0) {
    $watermark = new \Phyxo\Image\WatermarkParams();
    $watermark->file = $pwatermark['file'];
    $watermark->xpos = intval($pwatermark['xpos']);
    $watermark->ypos = intval($pwatermark['ypos']);
    $watermark->xrepeat = intval($pwatermark['xrepeat']);
    $watermark->opacity = intval($pwatermark['opacity']);
    $watermark->min_size = array(intval($pwatermark['minw']), intval($pwatermark['minh']));

    $old_watermark = \Phyxo\Image\ImageStdParams::get_watermark();
    $watermark_changed =
        $watermark->file != $old_watermark->file
        || $watermark->xpos != $old_watermark->xpos
        || $watermark->ypos != $old_watermark->ypos
        || $watermark->xrepeat != $old_watermark->xrepeat
        || $watermark->opacity != $old_watermark->opacity;

    // save the new watermark configuration
    \Phyxo\Image\ImageStdParams::set_watermark($watermark);

    // do we have to regenerate the derivatives (and which types)?
    $changed_types = array();

    foreach (\Phyxo\Image\ImageStdParams::get_defined_type_map() as $type => $params) {
        $old_use_watermark = $params->use_watermark;
        \Phyxo\Image\ImageStdParams::apply_global($params);

        $changed = $params->use_watermark != $old_use_watermark;
        if (!$changed and $params->use_watermark) {
            $changed = $watermark_changed;
        }
        if (!$changed and $params->use_watermark) {
            // if thresholds change and before/after the threshold is lower than the corresponding derivative side -> some derivatives might switch the watermark
            $changed |= $watermark->min_size[0] != $old_watermark->min_size[0] and ($watermark->min_size[0] < $params->max_width() or $old_watermark->min_size[0] < $params->max_width());
            $changed |= $watermark->min_size[1] != $old_watermark->min_size[1] and ($watermark->min_size[1] < $params->max_height() or $old_watermark->min_size[1] < $params->max_height());
        }

        if ($changed) {
            $params->last_mod_time = time();
            $changed_types[] = $type;
        }
    }

    \Phyxo\Image\ImageStdParams::save();

    if (count($changed_types)) {
        clear_derivative_cache($changed_types);
    }

    $page['infos'][] = \Phyxo\Functions\Language::l10n('Your configuration settings are saved');
} else {
    $template->assign('watermark', $pwatermark);
    $template->assign('ferrors', $errors);
}
