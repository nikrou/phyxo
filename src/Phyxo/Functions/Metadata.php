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

namespace Phyxo\Functions;

use App\Repository\ImageRepository;

class Metadata
{
    /**
     * returns informations from IPTC metadata, mapping is done in this function.
     *
     * @param string $filename
     * @param array $map
     * @return array
     */
    public static function get_iptc_data($filename, $map, $array_sep = ',')
    {
        global $conf;

        $result = [];

        $imginfo = [];
        if (false == @getimagesize($filename, $imginfo)) {
            return $result;
        }

        if (isset($imginfo['APP13'])) {
            $iptc = iptcparse($imginfo['APP13']);
            if (is_array($iptc)) {
                $rmap = array_flip($map);
                foreach (array_keys($rmap) as $iptc_key) {
                    if (isset($iptc[$iptc_key][0])) {
                        if ($iptc_key == '2#025') {
                            $value = implode($array_sep, array_map('self::clean_iptc_value', $iptc[$iptc_key]));
                        } else {
                            $value = self::clean_iptc_value($iptc[$iptc_key][0]);
                        }

                        foreach (array_keys($map, $iptc_key) as $pwg_key) {
                            $result[$pwg_key] = $value;

                            if (!$conf['allow_html_in_metadata']) {
                            // in case the origin of the photo is unsecure (user upload), we
                            // remove HTML tags to avoid XSS (malicious execution of
                            // javascript)
                                $result[$pwg_key] = strip_tags($result[$pwg_key]);
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Returns IPTC metadata to sync from a file, depending on IPTC mapping.
     * @toto : clean code (factorize foreach)
     *
     * @param string $file
     * @return array
     */
    public static function get_sync_iptc_data($file)
    {
        global $conf;

        $map = $conf['use_iptc_mapping'];

        $iptc = self::get_iptc_data($file, $map);

        foreach ($iptc as $pwg_key => $value) {
            if (in_array($pwg_key, ['date_creation', 'date_available'])) {
                if (preg_match('/(\d{4})(\d{2})(\d{2})/', $value, $matches)) {
                    $year = $matches[1];
                    $month = $matches[2];
                    $day = $matches[3];

                    if (!checkdate($month, $day, $year)) {
                       // we suppose the year is correct
                        $month = 1;
                        $day = 1;
                    }

                    $iptc[$pwg_key] = $year . '-' . $month . '-' . $day;
                }
            }
        }

        if (isset($iptc['keywords'])) {
            // official keywords separator is the comma
            $iptc['keywords'] = preg_replace('/[.;]/', ',', $iptc['keywords']);
            $iptc['keywords'] = preg_replace('/,+/', ',', $iptc['keywords']);
            $iptc['keywords'] = preg_replace('/^,+|,+$/', '', $iptc['keywords']);

            $iptc['keywords'] = implode(
                ',',
                array_unique(
                    explode(
                        ',',
                        $iptc['keywords']
                    )
                )
            );
        }

        foreach ($iptc as $pwg_key => $value) {
            $iptc[$pwg_key] = addslashes($iptc[$pwg_key]);
        }

        return $iptc;
    }

    /**
     * returns informations from EXIF metadata, mapping is done in this function.
     *
     * @param string $filename
     * @param array $map
     * @return array
     */
    public static function get_exif_data($filename, $map)
    {
        global $conf;

        $result = [];

        if (!function_exists('exif_read_data')) {
            die('Exif extension not available, admin should disable exif use');
        }

        // Read EXIF data
        if (is_readable($filename) && $exif = @exif_read_data($filename)) {
            $exif = \Phyxo\Functions\Plugin::trigger_change('format_exif_data', $exif, $filename, $map);

            // configured fields
            foreach ($map as $key => $field) {
                if (strpos($field, ';') === false) {
                    if (isset($exif[$field])) {
                        $result[$key] = $exif[$field];
                    }
                } else {
                    $tokens = explode(';', $field);
                    if (isset($exif[$tokens[0]][$tokens[1]])) {
                        $result[$key] = $exif[$tokens[0]][$tokens[1]];
                    }
                }
            }

            // GPS data
            $gps_exif = array_intersect_key($exif, array_flip(['GPSLatitudeRef', 'GPSLatitude', 'GPSLongitudeRef', 'GPSLongitude']));
            if (count($gps_exif) == 4) {
                if (is_array($gps_exif['GPSLatitude']) and in_array($gps_exif['GPSLatitudeRef'], ['S', 'N'])
                    && is_array($gps_exif['GPSLongitude']) and in_array($gps_exif['GPSLongitudeRef'], ['W', 'E'])) {
                    $result['latitude'] = self::parse_exif_gps_data($gps_exif['GPSLatitude'], $gps_exif['GPSLatitudeRef']);
                    $result['longitude'] = self::parse_exif_gps_data($gps_exif['GPSLongitude'], $gps_exif['GPSLongitudeRef']);
                }
            }
        }

        if (!$conf['allow_html_in_metadata']) {
            foreach ($result as $key => $value) {
                // in case the origin of the photo is unsecure (user upload), we remove
                // HTML tags to avoid XSS (malicious execution of javascript)
                $result[$key] = strip_tags($value);
            }
        }

        return $result;
    }

    /**
     * Returns EXIF metadata to sync from a file, depending on EXIF mapping.
     *
     * @param string $file
     * @return array
     */
    public static function get_sync_exif_data($file)
    {
        global $conf;

        $exif = self::get_exif_data($file, $conf['use_exif_mapping']);

        foreach ($exif as $pwg_key => $value) {
            if (in_array($pwg_key, ['date_creation', 'date_available'])) {
                if (preg_match('/^(\d{4}).(\d{2}).(\d{2}) (\d{2}).(\d{2}).(\d{2})/', $value, $matches)) {
                    if ($matches[1] != '0000' && $matches[2] != '00' && $matches[3] != '00'
                        && $matches[4] != '00' && $matches[5] != '00' && $matches[6] != '00') {
                        $exif[$pwg_key] = $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' . $matches[4] . ':' . $matches[5] . ':' . $matches[6];
                    } else {
                        unset($exif[$pwg_key]);
                    }
                } elseif (preg_match('/^(\d{4}).(\d{2}).(\d{2})/', $value, $matches)) {
                    $exif[$pwg_key] = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
                } else {
                    unset($exif[$pwg_key]);
                    continue;
                }
            }
            if (!empty($exif[$pwg_key])) {
                $exif[$pwg_key] = addslashes($exif[$pwg_key]); // @TODO: why addslashes ???
            }
        }

        return $exif;
    }

    /**
     * Converts EXIF GPS format to a float value.
     *
     * @param string[] $raw eg:
     *    - 41/1
     *    - 54/1
     *    - 9843/500
     * @param string $ref 'S', 'N', 'E', 'W'. eg: 'N'
     * @return float eg: 41.905468
     */
    public static function parse_exif_gps_data($raw, $ref)
    {
        foreach ($raw as &$i) {
            $i = explode('/', $i);
            $i = $i[1] == 0 ? 0 : $i[0] / $i[1];
        }
        unset($i);

        $v = $raw[0] + $raw[1] / 60 + $raw[2] / 3600;

        $ref = strtoupper($ref);
        if ($ref == 'S' or $ref == 'W') {
            $v = -$v;
        }

        return $v;
    }

    /**
     * return a cleaned IPTC value.
     *
     * @param string $value
     * @return string
     */
    public static function clean_iptc_value($value)
    {
        // strip leading zeros (weird Kodak Scanner software)
        while (isset($value[0]) and $value[0] == chr(0)) {
            $value = substr($value, 1);
        }
        // remove binary nulls
        $value = str_replace(chr(0x00), ' ', $value);

        if (preg_match('/[\x80-\xff]/', $value)) {
            // apparently mac uses some MacRoman crap encoding. I don't know
            // how to detect it so a plugin should do the trick.
            $value = \Phyxo\Functions\Plugin::trigger_change('clean_iptc_value', $value);
            if (($qual = \Phyxo\Functions\Language::qualify_utf8($value)) != 0) { // has non ascii chars
                if ($qual > 0) {
                    $input_encoding = 'utf-8';
                } else {
                    $input_encoding = 'iso-8859-1';
                    if (function_exists('iconv') or function_exists('mb_convert_encoding')) {
                        // using windows-1252 because it supports additional characters
                        // such as "oe" in a single character (ligature). About the
                        // difference between Windows-1252 and ISO-8859-1: the characters
                        // 0x80-0x9F will not convert correctly. But these are control
                        // characters which are almost never used.
                        $input_encoding = 'windows-1252';
                    }
                }

                $value = \Phyxo\Functions\Utils::convert_charset($value, $input_encoding, \Phyxo\Functions\Utils::get_charset());
            }
        }
        return $value;
    }

    /**
     * Get all potential file metadata fields, including IPTC and EXIF.
     *
     * @return string[]
     */
    public static function get_sync_metadata_attributes()
    {
        global $conf;

        $update_fields = ['filesize', 'width', 'height'];

        if ($conf['use_exif']) {
            $update_fields =
                array_merge(
                $update_fields,
                array_keys($conf['use_exif_mapping']),
                ['latitude', 'longitude']
            );
        }

        if ($conf['use_iptc']) {
            $update_fields =
                array_merge(
                $update_fields,
                array_keys($conf['use_iptc_mapping'])
            );
        }

        return array_unique($update_fields);
    }

    /**
     * Get all metadata of a file.
     *
     * @param array $infos - (path[, representative_ext])
     * @return array - includes data provided in $infos
     */
    public static function get_sync_metadata($infos)
    {
        global $conf;

        $file = PHPWG_ROOT_PATH . $infos['path'];
        $fs = @filesize($file);

        if ($fs === false) {
            return false;
        }

        $infos['filesize'] = floor($fs / 1024);

        if (isset($infos['representative_ext'])) {
            $file = \Phyxo\Functions\Utils::original_to_representative($file, $infos['representative_ext']);
        }

        if ($image_size = @getimagesize($file)) {
            $infos['width'] = $image_size[0];
            $infos['height'] = $image_size[1];
        }

        if ($conf['use_exif']) {
            $exif = self::get_sync_exif_data($file);
            $infos = array_merge($infos, $exif);
        }

        if ($conf['use_iptc']) {
            $iptc = self::get_sync_iptc_data($file);
            $infos = array_merge($infos, $iptc);
        }

        return $infos;
    }

    /**
     * Sync all metadata of a list of images.
     * Metadata are fetched from original files and saved in database.
     *
     * @param int[] $ids
     */
    public static function sync_metadata($ids)
    {
        global $conf, $conn, $services;

        if (!defined('CURRENT_DATE')) {
            define('CURRENT_DATE', date('Y-m-d'));
        }

        $datas = [];
        $tags_of = [];
        $result = (new ImageRepository($conn))->findByIds($ids);
        while ($data = $conn->db_fetch_assoc($result)) {
            $data = self::get_sync_metadata($data);
            if ($data === false) {
                continue;
            }

            $id = $data['id'];
            foreach (['keywords', 'tags'] as $key) {
                if (isset($data[$key])) {
                    if (!isset($tags_of[$id])) {
                        $tags_of[$id] = [];
                    }

                    foreach (explode(',', $data[$key]) as $tag_name) {
                        $tags_of[$id][] = $services['tags']->tagIdFromTagName($tag_name);
                    }
                }
            }

            $data['date_metadata_update'] = CURRENT_DATE;

            $datas[] = $data;
        }

        if (count($datas) > 0) {
            $update_fields = self::get_sync_metadata_attributes();
            $update_fields[] = 'date_metadata_update';

            $update_fields = array_diff(
                $update_fields,
                ['tags', 'keywords']
            );

            (new ImageRepository($conn))->massUpdates(
                [
                    'primary' => ['id'],
                    'update' => $update_fields
                ],
                $datas,
                MASS_UPDATES_SKIP_EMPTY
            );
        }

        $services['tags']->setTagsOf($tags_of);
    }
}
