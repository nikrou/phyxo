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

/**
 * This file is included by the picture page to manage picture metadata
 *
 */

if (($conf['show_exif']) and (function_exists('exif_read_data'))) {
    $exif_mapping = [];
    foreach ($conf['show_exif_fields'] as $field) {
        $exif_mapping[$field] = $field;
    }

    $exif = \Phyxo\Functions\Metadata::get_exif_data($picture['current']['src_image']->get_path(), $exif_mapping);

    if (count($exif) > 0) {
        $tpl_meta = [
            'TITLE' => \Phyxo\Functions\Language::l10n('EXIF Metadata'),
            'lines' => [],
        ];

        foreach ($conf['show_exif_fields'] as $field) {
            if (strpos($field, ';') === false) {
                if (isset($exif[$field])) {
                    $key = $field;
                    if (isset($lang['exif_field_' . $field])) {
                        $key = $lang['exif_field_' . $field];
                    }
                    $tpl_meta['lines'][$key] = $exif[$field];
                }
            } else {
                $tokens = explode(';', $field);
                if (isset($exif[$field])) {
                    $key = $tokens[1];
                    if (isset($lang['exif_field_' . $key])) {
                        $key = $lang['exif_field_' . $key];
                    }
                    $tpl_meta['lines'][$key] = $exif[$field];
                }
            }
        }
        $template->append('metadata', $tpl_meta);
    }
}

if ($conf['show_iptc']) {
    $iptc = \Phyxo\Functions\Metadata::get_iptc_data($picture['current']['src_image']->get_path(), $conf['show_iptc_mapping'], ', ');

    if (count($iptc) > 0) {
        $tpl_meta = [
            'TITLE' => \Phyxo\Functions\Language::l10n('IPTC Metadata'),
            'lines' => [],
        ];

        foreach ($iptc as $field => $value) {
            $key = $field;
            if (isset($lang[$field])) {
                $key = $lang[$field];
            }
            $tpl_meta['lines'][$key] = $value;
        }
        $template->append('metadata', $tpl_meta);
    }
}
