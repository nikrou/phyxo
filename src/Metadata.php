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

namespace App;

use DateTime;
use Exception;
use Phyxo\Functions\Language;
use Phyxo\Functions\Utils;
use Phyxo\Conf;

class Metadata
{
    public function __construct(private Conf $conf, private readonly string $rootProjectDir)
    {
    }

    /**
     * returns informations from IPTC metadata, mapping is done in this function.
     *
     * @param array<string, mixed> $map
     *
     * @return array<string, mixed>
     */
    public function getIptcData(string $filename, array $map, string $array_sep = ','): array
    {
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
                            $value = implode($array_sep, array_map($this->cleanIptcValue(...), $iptc[$iptc_key]));
                        } else {
                            $value = $this->cleanIptcValue($iptc[$iptc_key][0]);
                        }

                        foreach (array_keys($map, $iptc_key) as $_key) {
                            $result[$_key] = $value;

                            if (!$this->conf['allow_html_in_metadata']) {
                                // in case the origin of the photo is unsecure (user upload), we
                                // remove HTML tags to avoid XSS (malicious execution of
                                // javascript)
                                $result[$_key] = htmlentities($result[$_key], ENT_QUOTES, 'utf-8');
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
     * @return array<string, mixed>
     */
    public function getSyncIptcData(string $file): array
    {
        $map = $this->conf['use_iptc_mapping'];

        $iptc = $this->getIptcData($file, $map);

        foreach ($iptc as $iptc_key => $value) {
            if (in_array($iptc_key, ['date_creation', 'date_available']) && preg_match('/(\d{4})(\d{2})(\d{2})/', (string) $value, $matches)) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
                $day = (int) $matches[3];
                if (!checkdate($month, $day, $year)) {
                    // we suppose the year is correct
                    $month = 1;
                    $day = 1;
                }

                $iptc[$iptc_key] = DateTime::createFromFormat('Y-m-d', sprintf('%d-%d-%d', $year, $month, $day));
            }
        }

        if (isset($iptc['keywords'])) {
            // official keywords separator is the comma
            $iptc['keywords'] = preg_replace('/[.;]/', ',', (string) $iptc['keywords']);
            $iptc['keywords'] = preg_replace('/,+/', ',', (string) $iptc['keywords']);
            $iptc['keywords'] = preg_replace('/^,+|,+$/', '', (string) $iptc['keywords']);

            $iptc['keywords'] = implode(',', array_unique(explode(',', (string) $iptc['keywords'])));
        }

        return $iptc;
    }

    /**
     * returns informations from EXIF metadata, mapping is done in this function.
     *
     * @param array<string, mixed> $map
     *
     * @return array<string, mixed>
     */
    public function getExifData(string $filename, array $map): array
    {
        $result = [];

        if (!function_exists('exif_read_data')) {
            throw new Exception('Exif extension not available, admin should disable exif use');
        }

        // Read EXIF data
        if (is_readable($filename) && $exif = @exif_read_data($filename)) {
            // configured fields
            foreach ($map as $key => $field) {
                if (!str_contains((string) $field, ';')) {
                    if (isset($exif[$field])) {
                        $result[$key] = $exif[$field];
                    }
                } else {
                    $tokens = explode(';', (string) $field);
                    if (isset($exif[$tokens[0]][$tokens[1]])) {
                        $result[$key] = $exif[$tokens[0]][$tokens[1]];
                    }
                }
            }

            // GPS data
            $gps_exif = array_intersect_key($exif, array_flip(['GPSLatitudeRef', 'GPSLatitude', 'GPSLongitudeRef', 'GPSLongitude']));
            if (count($gps_exif) === 4 && (is_array($gps_exif['GPSLatitude']) && in_array($gps_exif['GPSLatitudeRef'], ['S', 'N']) && is_array($gps_exif['GPSLongitude']) && in_array($gps_exif['GPSLongitudeRef'], ['W', 'E']))) {
                $result['latitude'] = $this->parseExifGpsData($gps_exif['GPSLatitude'], $gps_exif['GPSLatitudeRef']);
                $result['longitude'] = $this->parseExifGpsData($gps_exif['GPSLongitude'], $gps_exif['GPSLongitudeRef']);
            }
        }

        if (!$this->conf['allow_html_in_metadata']) {
            foreach ($result as $key => $value) {
                // in case the origin of the photo is unsecure (user upload), we remove
                // HTML tags to avoid XSS (malicious execution of javascript)
                $result[$key] = htmlentities((string) $value, ENT_QUOTES, 'utf-8');
            }
        }

        return $result;
    }

    /**
     * Returns EXIF metadata to sync from a file, depending on EXIF mapping.
     *
     * @return array<string, mixed>
     */
    public function getSyncExifData(string $file): array
    {
        $exif = $this->getExifData($file, $this->conf['use_exif_mapping']);

        foreach ($exif as $exif_key => $value) {
            if (in_array($exif_key, ['date_creation', 'date_available'])) {
                if (preg_match('/^(\d{4}).(\d{2}).(\d{2}) (\d{2}).(\d{2}).(\d{2})/', (string) $value, $matches)) {
                    if ($matches[1] !== '0000' && $matches[2] !== '00' && $matches[3] !== '00'
                        && $matches[4] !== '00' && $matches[5] !== '00' && $matches[6] !== '00') {
                        $exif[$exif_key] = DateTime::createFromFormat(
                            'Y-m-d H:i:s',
                            $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' . $matches[4] . ':' . $matches[5] . ':' . $matches[6]
                        );
                    } else {
                        unset($exif[$exif_key]);
                    }
                } elseif (preg_match('/^(\d{4}).(\d{2}).(\d{2})/', (string) $value, $matches)) {
                    $exif[$exif_key] = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
                } else {
                    unset($exif[$exif_key]);
                    continue;
                }
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
    public function parseExifGpsData(array $raw, string $ref): float
    {
        foreach ($raw as &$i) {
            $i = explode('/', $i);
            $i = $i[1] == 0 ? 0 : (int) $i[0] / (int) $i[1];
        }

        unset($i);

        $v = (int) $raw[0] + (int) $raw[1] / 60 + (int) $raw[2] / 3600;

        $ref = strtoupper($ref);
        if ($ref === 'S' || $ref === 'W') {
            $v = -$v;
        }

        return $v;
    }

    /**
     * return a cleaned IPTC value.
     */
    public function cleanIptcValue(string $value): string
    {
        // strip leading zeros (weird Kodak Scanner software)
        while (isset($value[0]) && $value[0] === chr(0)) {
            $value = substr($value, 1);
        }

        // remove binary nulls
        $value = str_replace(chr(0x00), ' ', $value);

        // apparently mac uses some MacRoman crap encoding. I don't know
        // how to detect it so a plugin should do the trick.
        if (preg_match('/[\x80-\xff]/', $value) && ($qual = Language::qualify_utf8($value)) != 0) {
            // has non ascii chars
            if ($qual > 0) {
                $input_encoding = 'utf-8';
            } else {
                $input_encoding = 'iso-8859-1';
                if (function_exists('iconv') || function_exists('mb_convert_encoding')) {
                    // using windows-1252 because it supports additional characters
                    // such as "oe" in a single character (ligature). About the
                    // difference between Windows-1252 and ISO-8859-1: the characters
                    // 0x80-0x9F will not convert correctly. But these are control
                    // characters which are almost never used.
                    $input_encoding = 'windows-1252';
                }
            }

            $value = Utils::convertCharset($value, $input_encoding, 'utf-8');
        }

        return $value;
    }

    /**
     * Get all potential file metadata fields, including IPTC and EXIF.
     *
     * @return array<int, mixed>
     */
    public function getSyncMetadataAttributes(): array
    {
        $update_fields = ['filesize', 'width', 'height'];

        if ($this->conf['use_exif']) {
            $update_fields =
                array_merge(
                    $update_fields,
                    array_keys($this->conf['use_exif_mapping']),
                    ['latitude', 'longitude']
                );
        }

        if ($this->conf['use_iptc']) {
            $update_fields =
                [...$update_fields, ...array_keys($this->conf['use_iptc_mapping'])];
        }

        return array_unique($update_fields);
    }

    /**
     * Get all metadata of a file.
     *
     * @param array<string, mixed> $infos - (path[, representative_ext])
     *
     * @return array<string, mixed> - includes data provided in $infos
     */
    public function getSyncMetadata(array $infos): array
    {
        $file = $this->rootProjectDir . '/' . $infos['path'];
        $fs = @filesize($file);

        if ($fs === false) {
            return [];
        }

        $infos['filesize'] = floor($fs / 1024);

        if ($image_size = @getimagesize($file)) {
            $infos['width'] = $image_size[0];
            $infos['height'] = $image_size[1];
        }

        if ($this->conf['use_exif']) {
            $exif = $this->getSyncExifData($file);
            $infos = array_merge($infos, $exif);
        }

        if ($this->conf['use_iptc']) {
            $iptc = $this->getSyncIptcData($file);
            $infos = array_merge($infos, $iptc);
        }

        return $infos;
    }
}
