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
}