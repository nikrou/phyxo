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
     * translation function.
     * returns the corresponding value from _$lang_ if existing else the key is returned
     * if more than one parameter is provided sprintf is applied
     *
     * @param string $key
     * @param mixed $args,... optional arguments
     * @return string
     */
    public static function l10n($key)
    {
        global $lang, $conf;

        if (($val = @$lang[$key]) === null) {
            if ($conf['debug_l10n'] and !isset($lang[$key]) and !empty($key)) {
                trigger_error('[l10n] language key "' . $key . '" not defined', E_USER_WARNING);
            }
            $val = $key;
        }

        if (func_num_args() > 1) {
            $args = func_get_args();
            $val = vsprintf($val, array_slice($args, 1));
        }

        return $val;
    }

    /**
     * returns the printf value for strings including %d
     * returned value is concorded with decimal value (singular, plural)
     *
     * @param string $singular_key
     * @param string $plural_key
     * @param int $decimal
     * @return string
     */
    public static function l10n_dec($singular_key, $plural_key, $decimal)
    {
        global $lang_info;

        return sprintf(
            self::l10n(((($decimal > 1) or ($decimal == 0 and $lang_info['zero_plural']))
                ? $plural_key
                : $singular_key)),
            $decimal
        );
    }

    /**
     * returns a single element to use with l10n_args
     *
     * @param string $key translation key
     * @param mixed $args arguments to use on sprintf($key, args)
     *   if args is a array, each values are used on sprintf
     * @return string
     */
    public static function get_l10n_args($key, $args = '')
    {
        if (is_array($args)) {
            $key_arg = array_merge(array($key), $args);
        } else {
            $key_arg = array($key, $args);
        }
        return array('key_args' => $key_arg);
    }

    /**
     * returns a string formated with l10n elements.
     * it is usefull to "prepare" a text and translate it later
     * @see get_l10n_args()
     *
     * @param array $key_args one l10n_args element or array of l10n_args elements
     * @param string $sep used when translated elements are concatened
     * @return string
     */
    public static function l10n_args($key_args, $sep = "\n")
    {
        if (is_array($key_args)) {
            foreach ($key_args as $key => $element) {
                if (isset($result)) {
                    $result .= $sep;
                } else {
                    $result = '';
                }

                if ($key === 'key_args') {
                    array_unshift($element, self::l10n(array_shift($element))); // translate the key
                    $result .= call_user_func_array('sprintf', $element);
                } else {
                    $result .= self::l10n_args($element, $sep);
                }
            }
        } else {
            fatal_error('l10n_args: Invalid arguments');
        }

        return $result;
    }

    /**
     * returns the parent (fallback) language of a language.
     * if _$lang_id_ is null it applies to the current language
     *
     * @param string $lang_id
     * @return string|null
     */
    public static function get_parent_language($lang_id = null)
    {
        global $lang_info;

        if (empty($lang_id)) {
            return !empty($lang_info['parent']) ? $lang_info['parent'] : null;
        } else {
            $f = PHPWG_ROOT_PATH . 'language/' . $lang_id . '/common.lang.php';
            if (file_exists($f)) {
                include($f);
                return !empty($lang_info['parent']) ? $lang_info['parent'] : null;
            }
        }

        return null;
    }

    /**
     * includes a language file or returns the content of a language file
     *
     * tries to load in descending order:
     *   param language, user language, default language
     *
     * @param string $filename
     * @param string $dirname
     * @param mixed options can contain
     *     @option string language - language to load
     *     @option bool return - if true the file content is returned
     *     @option bool no_fallback - if true do not load default language
     *     @option bool|string force_fallback - force pre-loading of another language
     *        default language if *true* or specified language
     *     @option bool local - if true load file from local directory
     * @return boolean|string
     */
    public static function load_language($filename, $dirname = '', $options = array())
    {
        global $user, $language_files, $services, $lang, $lang_info;

        $default_options = [
            'return' => false,
            'no_fallback' => false,
            'force_fallback' => false,
            'local' => false,
        ];

        $options = array_merge($default_options, $options);

        // keep trace of plugins loaded files for switch_lang_to() function
        if (!empty($dirname) && !empty($filename) && !$options['return'] && !isset($language_files[$dirname][$filename])) {
            $language_files[$dirname][$filename] = $options;
        }

        if (!$options['return']) {
            $filename .= '.php';
        }

        if (empty($dirname)) {
            $dirname = PHPWG_ROOT_PATH;
        }

        $dirname .= 'language/';
        $default_language = (defined('PHPWG_INSTALLED') and !defined('UPGRADES_PATH')) ? $services['users']->getDefaultLanguage() : PHPWG_DEFAULT_LANGUAGE;

        // construct list of potential languages
        $languages = array();
        if (!empty($options['language'])) { // explicit language
            $languages[] = $options['language'];
        }

        if (!empty($user['language'])) { // use language
            $languages[] = $user['language'];
        }

        if (($parent = self::get_parent_language()) !== null) { // parent language
            // this is only for when the "child" language is missing
            $languages[] = $parent;
        }

        if ($options['force_fallback']) { // fallback language
            $languages[] = $default_language;
        }

        if (!$options['no_fallback']) { // default language
            $languages[] = $default_language;
        }

        if (!empty($languages)) {
            $languages = array_unique($languages);
        }

        // find first existing
        $source_file = '';
        $selected_language = '';

        foreach ($languages as $language) {
            $f = $options['local'] ? $dirname . $language . '.' . $filename : $dirname . $language . '/' . $filename;

            if (is_readable($f)) {
                $selected_language = $language;
                $source_file = $f;
                break;
            }
        }

        if (!empty($source_file)) {
            if (!$options['return']) {
                // load forced fallback
                if ($options['force_fallback']) {
                    include(str_replace($selected_language, $options['force_fallback'], $source_file));
                }

                // load language content
                include($source_file);
                $load_lang = $lang;
                $load_lang_info = $lang_info;

                // access already existing values
                if (!isset($lang)) {
                    $lang = array();
                }

                if (!isset($lang_info)) {
                    $lang_info = array();
                }

                // load parent language content directly in global
                if (!empty($load_lang_info['parent'])) {
                    $parent_language = $load_lang_info['parent'];
                } elseif (!empty($lang_info['parent'])) {
                    $parent_language = $lang_info['parent'];
                } else {
                    $parent_language = null;
                }

                if (!empty($parent_language) && $parent_language != $selected_language) {
                    include(str_replace($selected_language, $parent_language, $source_file));
                }

                // merge contents
                $lang = array_merge($lang, (array)$load_lang);
                $lang_info = array_merge($lang_info, (array)$load_lang_info);

                return true;
            } else {
                $content = file_get_contents($source_file);
                return $content;
            }
        }

        return false;
    }

    /**
     * returns an array with a list of {language_code => language_name}
     *
     * @return string[]
     * @TODO: transform Phyxo\Language\Languages as service and use it
     */
    public static function get_languages()
    {
        global $conn;

        $query = 'SELECT id, name FROM ' . LANGUAGES_TABLE . ' ORDER BY name ASC;';
        $result = $conn->db_query($query);

        $languages = array();
        while ($row = $conn->db_fetch_assoc($result)) {
            if (is_dir(PHPWG_ROOT_PATH . 'language/' . $row['id'])) {
                $languages[$row['id']] = $row['name'];
            }
        }

        return $languages;
    }

    /**
     * Tries to find the browser language among available languages.
     * @todo : try to match 'fr_CA' before 'fr'
     *
     * @param string &$lang
     * @return bool
     */
    public static function get_browser_language(&$lang)
    {
        $browser_language = substr(@$_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2);
        foreach (self::get_languages() as $language_code => $language_name) {
            if (substr($language_code, 0, 2) == $browser_language) {
                $lang = $language_code;
                return true;
            }
        }

        return false;
    }

    // charset methods
    /**
     * finds out if a string is in ASCII, UTF-8 or other encoding
     *
     * @param string $str
     * @return int *0* if _$str_ is ASCII, *1* if UTF-8, *-1* otherwise
     */
    public static function qualify_utf8($Str)
    {
        $ret = 0;
        for ($i = 0; $i < strlen($Str); $i++) {
            if (ord($Str[$i]) < 0x80) continue; # 0bbbbbbb
            $ret = 1;
            if ((ord($Str[$i]) & 0xE0) == 0xC0) $n = 1; # 110bbbbb
            elseif ((ord($Str[$i]) & 0xF0) == 0xE0) $n = 2; # 1110bbbb
            elseif ((ord($Str[$i]) & 0xF8) == 0xF0) $n = 3; # 11110bbb
            elseif ((ord($Str[$i]) & 0xFC) == 0xF8) $n = 4; # 111110bb
            elseif ((ord($Str[$i]) & 0xFE) == 0xFC) $n = 5; # 1111110b
            else return -1; # Does not match any model
            for ($j = 0; $j < $n; $j++) { # n bytes matching 10bbbbbb follow ?
                if ((++$i == strlen($Str)) || ((ord($Str[$i]) & 0xC0) != 0x80))
                    return -1;
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
        $utf = self::qualify_utf8($string);
        if ($utf == 0) {
            return $string; // ascii
        }

        if ($utf > 0) {
            $chars = array(
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
            );

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
            $double_chars['in'] = array(chr(140), chr(156), chr(198), chr(208), chr(222), chr(223), chr(230), chr(240), chr(254));
            $double_chars['out'] = array('OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');
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
        if (function_exists('mb_strtolower') && defined('PWG_CHARSET')) {
            return self::remove_accents(mb_strtolower($term, PWG_CHARSET));
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
        $str = preg_replace('/[\s\'\:\/\[\],-]+/', ' ', trim($str));
        $res = str_replace(' ', '_', $str);

        if (empty($res)) {
            $res = str_replace(' ', '_', $safe);
        }

        return $res;
    }

}