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

class Language
{
    /**
     *  return language file content or emmty string if file does not exist
     */
    public static function loadLanguageFile(string $filename, string $dirname = ''): string
    {
        $content = '';

        if (!empty($dirname)) {
            $filename = $dirname . '/' . $filename;
        }

        if (is_readable($filename)) {
            $content = file_get_contents($filename);
        }

        return $content;
    }

    // charset methods

    /**
     * finds out if a string is in ASCII, UTF-8 or other encoding
     * @return int *0* if _$str_ is ASCII, *1* if UTF-8, *-1* otherwise
     */
    public static function qualify_utf8(string $Str): int
    {
        $ret = 0;
        for ($i = 0; $i < strlen($Str); $i++) {
            if (ord($Str[$i]) < 0x80) {
                continue;
            } // 0bbbbbbb
            $ret = 1;
            if ((ord($Str[$i]) & 0xE0) == 0xC0) {
                $n = 1;
            } // 110bbbbb
            elseif ((ord($Str[$i]) & 0xF0) == 0xE0) {
                $n = 2;
            } // 1110bbbb
            elseif ((ord($Str[$i]) & 0xF8) == 0xF0) {
                $n = 3;
            } // 11110bbb
            elseif ((ord($Str[$i]) & 0xFC) == 0xF8) {
                $n = 4;
            } // 111110bb
            elseif ((ord($Str[$i]) & 0xFE) == 0xFC) {
                $n = 5;
            } // 1111110b
            else {
                return -1;
            } // Does not match any model
            for ($j = 0; $j < $n; $j++) { // n bytes matching 10bbbbbb follow ?
                if ((++$i == strlen($Str)) || ((ord($Str[$i]) & 0xC0) != 0x80)) {
                    return -1;
                }
            }
        }
        return $ret;
    }

    /**
     * Remove accents from a UTF-8 or ISO-8859-1 string (from wordpress)
     *
     * @param string $string
     * @return string
     */
    public static function remove_accents($string)
    {
        $chars = [];
        $double_chars = [];
        $utf = self::qualify_utf8($string);
        if ($utf == 0) {
            return $string; // ascii
        }

        if ($utf > 0) {
            $chars = [
                // Decompositions for Latin-1 Supplement
                "\xc3\x80" => 'A', "\xc3\x81" => 'A',
                "\xc3\x82" => 'A', "\xc3\x83" => 'A',
                "\xc3\x84" => 'A', "\xc3\x85" => 'A',
                "\xc3\x87" => 'C', "\xc3\x88" => 'E',
                "\xc3\x89" => 'E', "\xc3\x8a" => 'E',
                "\xc3\x8b" => 'E', "\xc3\x8c" => 'I',
                "\xc3\x8d" => 'I', "\xc3\x8e" => 'I',
                "\xc3\x8f" => 'I', "\xc3\x91" => 'N',
                "\xc3\x92" => 'O', "\xc3\x93" => 'O',
                "\xc3\x94" => 'O', "\xc3\x95" => 'O',
                "\xc3\x96" => 'O', "\xc3\x99" => 'U',
                "\xc3\x9a" => 'U', "\xc3\x9b" => 'U',
                "\xc3\x9c" => 'U', "\xc3\x9d" => 'Y',
                "\xc3\x9f" => 's', "\xc3\xa0" => 'a',
                "\xc3\xa1" => 'a', "\xc3\xa2" => 'a',
                "\xc3\xa3" => 'a', "\xc3\xa4" => 'a',
                "\xc3\xa5" => 'a', "\xc3\xa7" => 'c',
                "\xc3\xa8" => 'e', "\xc3\xa9" => 'e',
                "\xc3\xaa" => 'e', "\xc3\xab" => 'e',
                "\xc3\xac" => 'i', "\xc3\xad" => 'i',
                "\xc3\xae" => 'i', "\xc3\xaf" => 'i',
                "\xc3\xb1" => 'n', "\xc3\xb2" => 'o',
                "\xc3\xb3" => 'o', "\xc3\xb4" => 'o',
                "\xc3\xb5" => 'o', "\xc3\xb6" => 'o',
                "\xc3\xb9" => 'u', "\xc3\xba" => 'u',
                "\xc3\xbb" => 'u', "\xc3\xbc" => 'u',
                "\xc3\xbd" => 'y', "\xc3\xbf" => 'y',
                // Decompositions for Latin Extended-A
                "\xc4\x80" => 'A', "\xc4\x81" => 'a',
                "\xc4\x82" => 'A', "\xc4\x83" => 'a',
                "\xc4\x84" => 'A', "\xc4\x85" => 'a',
                "\xc4\x86" => 'C', "\xc4\x87" => 'c',
                "\xc4\x88" => 'C', "\xc4\x89" => 'c',
                "\xc4\x8a" => 'C', "\xc4\x8b" => 'c',
                "\xc4\x8c" => 'C', "\xc4\x8d" => 'c',
                "\xc4\x8e" => 'D', "\xc4\x8f" => 'd',
                "\xc4\x90" => 'D', "\xc4\x91" => 'd',
                "\xc4\x92" => 'E', "\xc4\x93" => 'e',
                "\xc4\x94" => 'E', "\xc4\x95" => 'e',
                "\xc4\x96" => 'E', "\xc4\x97" => 'e',
                "\xc4\x98" => 'E', "\xc4\x99" => 'e',
                "\xc4\x9a" => 'E', "\xc4\x9b" => 'e',
                "\xc4\x9c" => 'G', "\xc4\x9d" => 'g',
                "\xc4\x9e" => 'G', "\xc4\x9f" => 'g',
                "\xc4\xa0" => 'G', "\xc4\xa1" => 'g',
                "\xc4\xa2" => 'G', "\xc4\xa3" => 'g',
                "\xc4\xa4" => 'H', "\xc4\xa5" => 'h',
                "\xc4\xa6" => 'H', "\xc4\xa7" => 'h',
                "\xc4\xa8" => 'I', "\xc4\xa9" => 'i',
                "\xc4\xaa" => 'I', "\xc4\xab" => 'i',
                "\xc4\xac" => 'I', "\xc4\xad" => 'i',
                "\xc4\xae" => 'I', "\xc4\xaf" => 'i',
                "\xc4\xb0" => 'I', "\xc4\xb1" => 'i',
                "\xc4\xb2" => 'IJ', "\xc4\xb3" => 'ij',
                "\xc4\xb4" => 'J', "\xc4\xb5" => 'j',
                "\xc4\xb6" => 'K', "\xc4\xb7" => 'k',
                "\xc4\xb8" => 'k', "\xc4\xb9" => 'L',
                "\xc4\xba" => 'l', "\xc4\xbb" => 'L',
                "\xc4\xbc" => 'l', "\xc4\xbd" => 'L',
                "\xc4\xbe" => 'l', "\xc4\xbf" => 'L',
                "\xc5\x80" => 'l', "\xc5\x81" => 'L',
                "\xc5\x82" => 'l', "\xc5\x83" => 'N',
                "\xc5\x84" => 'n', "\xc5\x85" => 'N',
                "\xc5\x86" => 'n', "\xc5\x87" => 'N',
                "\xc5\x88" => 'n', "\xc5\x89" => 'N',
                "\xc5\x8a" => 'n', "\xc5\x8b" => 'N',
                "\xc5\x8c" => 'O', "\xc5\x8d" => 'o',
                "\xc5\x8e" => 'O', "\xc5\x8f" => 'o',
                "\xc5\x90" => 'O', "\xc5\x91" => 'o',
                "\xc5\x92" => 'OE', "\xc5\x93" => 'oe',
                "\xc5\x94" => 'R', "\xc5\x95" => 'r',
                "\xc5\x96" => 'R', "\xc5\x97" => 'r',
                "\xc5\x98" => 'R', "\xc5\x99" => 'r',
                "\xc5\x9a" => 'S', "\xc5\x9b" => 's',
                "\xc5\x9c" => 'S', "\xc5\x9d" => 's',
                "\xc5\x9e" => 'S', "\xc5\x9f" => 's',
                "\xc5\xa0" => 'S', "\xc5\xa1" => 's',
                "\xc5\xa2" => 'T', "\xc5\xa3" => 't',
                "\xc5\xa4" => 'T', "\xc5\xa5" => 't',
                "\xc5\xa6" => 'T', "\xc5\xa7" => 't',
                "\xc5\xa8" => 'U', "\xc5\xa9" => 'u',
                "\xc5\xaa" => 'U', "\xc5\xab" => 'u',
                "\xc5\xac" => 'U', "\xc5\xad" => 'u',
                "\xc5\xae" => 'U', "\xc5\xaf" => 'u',
                "\xc5\xb0" => 'U', "\xc5\xb1" => 'u',
                "\xc5\xb2" => 'U', "\xc5\xb3" => 'u',
                "\xc5\xb4" => 'W', "\xc5\xb5" => 'w',
                "\xc5\xb6" => 'Y', "\xc5\xb7" => 'y',
                "\xc5\xb8" => 'Y', "\xc5\xb9" => 'Z',
                "\xc5\xba" => 'z', "\xc5\xbb" => 'Z',
                "\xc5\xbc" => 'z', "\xc5\xbd" => 'Z',
                "\xc5\xbe" => 'z', "\xc5\xbf" => 's',
                // Decompositions for Latin Extended-B
                "\xc8\x98" => 'S', "\xc8\x99" => 's',
                "\xc8\x9a" => 'T', "\xc8\x9b" => 't',
                // Euro Sign
                "\xe2\x82\xac" => 'E',
                // GBP (Pound) Sign
                "\xc2\xa3" => ''
            ];

            $string = strtr($string, $chars);
        } else {
            // Assume ISO-8859-1 if not UTF-8
            $chars['in'] = chr(128) . chr(131) . chr(138) . chr(142) . chr(154) . chr(158)
                . chr(159) . chr(162) . chr(165) . chr(181) . chr(192) . chr(193) . chr(194)
                . chr(195) . chr(196) . chr(197) . chr(199) . chr(200) . chr(201) . chr(202)
                . chr(203) . chr(204) . chr(205) . chr(206) . chr(207) . chr(209) . chr(210)
                . chr(211) . chr(212) . chr(213) . chr(214) . chr(216) . chr(217) . chr(218)
                . chr(219) . chr(220) . chr(221) . chr(224) . chr(225) . chr(226) . chr(227)
                . chr(228) . chr(229) . chr(231) . chr(232) . chr(233) . chr(234) . chr(235)
                . chr(236) . chr(237) . chr(238) . chr(239) . chr(241) . chr(242) . chr(243)
                . chr(244) . chr(245) . chr(246) . chr(248) . chr(249) . chr(250) . chr(251)
                . chr(252) . chr(253) . chr(255);

            $chars['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";

            $string = strtr($string, $chars['in'], $chars['out']);
            $double_chars['in'] = [chr(140), chr(156), chr(198), chr(208), chr(222), chr(223), chr(230), chr(240), chr(254)];
            $double_chars['out'] = ['OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th'];
            $string = str_replace($double_chars['in'], $double_chars['out'], $string);
        }

        return $string;
    }

    /**
     * removes accents from a string and converts it to lower case
     *
     * @param string $term
     * @return string
     */
    public static function transliterate($term)
    {
        if (function_exists('mb_strtolower')) {
            return self::remove_accents(mb_strtolower($term, 'utf-8'));
        } else {
            return self::remove_accents(strtolower($term));
        }
    }

    /**
     * simplify a string to insert it into an URL
     *
     * @param string $str
     * @return string
     */
    public static function str2url($str)
    {
        $str = $safe = self::transliterate($str);
        $str = preg_replace('/[^\x80-\xffa-z0-9_\s\'\:\/\[\],-]/', '', $str);
        $str = preg_replace('/[\s\'\:\/\[\],-]+/', ' ', trim((string) $str));
        $res = str_replace(' ', '_', (string) $str);

        if (empty($res)) {
            $res = str_replace(' ', '_', $safe);
        }

        return $res;
    }
}
