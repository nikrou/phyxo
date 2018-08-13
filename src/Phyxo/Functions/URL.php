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

class URL
{
    /**
     * returns a prefix for each url link on displayed page
     * and return an empty string for current path
     * @return string
     */
    public static function get_root_url()
    {
        global $page;

        if (!empty($_SERVER['PUBLIC_BASE_PATH'])) {
            return $_SERVER['PUBLIC_BASE_PATH'] . '/';
        } elseif (!empty($page['root_path'])) {
            $root_url = $page['root_path'];
        } else {
            $root_url = PHPWG_ROOT_PATH;
            if (strncmp($root_url, './', 2) == 0) {
                return substr($root_url, 2);
            }
        }

        return $root_url;
    }

    /**
     * build an index URL for a specific section
     *
     * @param array
     * @return string
     */
    public static function make_index_url($params = array())
    {
        global $conf;

        $url = self::get_root_url() . 'index';
        if ($conf['php_extension_in_urls']) {
            $url .= '.php';
        }
        if ($conf['question_mark_in_urls']) {
            $url .= '?';
        }

        $url_before_params = $url;

        $url .= self::make_section_in_url($params);
        $url = self::add_well_known_params_in_url($url, $params);

        if ($url == $url_before_params) {
            $url = self::get_absolute_root_url(self::url_is_remote($url));
        }

        return $url;
    }

    /**
     * create a picture URL on a specific section for a specific picture
     *
     * @param array
     * @return string
     */
    public static function make_picture_url($params)
    {
        global $conf;

        $url = self::get_root_url() . 'picture';
        if ($conf['php_extension_in_urls']) {
            $url .= '.php';
        }
        if ($conf['question_mark_in_urls']) {
            $url .= '?';
        }
        $url .= '/';
        switch ($conf['picture_url_style']) {
            case 'id-file':
                $url .= $params['image_id'];
                if (isset($params['image_file'])) {
                    $url .= '-' . \Phyxo\Functions\Language::str2url(\Phyxo\Functions\Utils::get_filename_wo_extension($params['image_file']));
                }
                break;
            case 'file':
                if (isset($params['image_file'])) {
                    $fname_wo_ext = \Phyxo\Functions\Utils::get_filename_wo_extension($params['image_file']);
                    if (ord($fname_wo_ext) > ord('9') or !preg_match('/^\d+(-|$)/', $fname_wo_ext)) {
                        $url .= $fname_wo_ext;
                        break;
                    }
                }
            default:
                $url .= $params['image_id'];
        }
        if (!isset($params['category'])) { // make urls shorter ...
            unset($params['flat']);
        }
        $url .= self::make_section_in_url($params);
        $url = self::add_well_known_params_in_url($url, $params);

        return $url;
    }

    /**
     * returns the absolute url to the root of PWG
     * @param boolean with_scheme if false - does not add http://toto.com
     */
    public static function get_absolute_root_url($with_scheme = true)
    {
        // @TODO - add HERE the possibility to call PWG functions from external scripts
        $url = '';
        if ($with_scheme) {
            $is_https = false;
            if (isset($_SERVER['HTTPS']) && ((strtolower($_SERVER['HTTPS']) == 'on') or ($_SERVER['HTTPS'] == 1))) {
                $is_https = true;
                $url .= 'https://';
            } else {
                $url .= 'http://';
            }
            $url .= $_SERVER['HTTP_HOST'];
            if ((!$is_https && $_SERVER['SERVER_PORT'] != 80)
                || ($is_https && $_SERVER['SERVER_PORT'] != 443)) {
                $url_port = ':' . $_SERVER['SERVER_PORT'];
                if (strrchr($url, ':') != $url_port) {
                    $url .= $url_port;
                }
            }
        }
        $url .= \Phyxo\Functions\Utils::cookie_path();

        return $url;
    }

    /**
     * adds one or more _GET style parameters to an url
     * example: add_url_params('/x', array('a'=>'b')) returns /x?a=b
     * add_url_params('/x?cat_id=10', array('a'=>'b')) returns /x?cat_id=10&amp;a=b
     * @param string url
     * @param array params
     * @return string
     */
    public static function add_url_params($url, $params, $arg_separator = '&amp;')
    {
        if (!empty($params)) {
            assert(is_array($params)); // @TODO: remove assert and use real error or exception
            $is_first = true;
            foreach ($params as $param => $val) {
                if ($is_first) {
                    $is_first = false;
                    $url .= (strpos($url, '?') === false) ? '?' : $arg_separator;
                } else {
                    $url .= $arg_separator;
                }
                $url .= $param;
                if (isset($val)) {
                    $url .= '=' . $val;
                }
            }
        }

        return $url;
    }

    /**
     * build an index URL with current page parameters, but with redefinitions
     * and removes.
     *
     * duplicate_index_url( array(
     *   'category' => array('id'=>12, 'name'=>'toto'),
     *   array('start')
     * ) will create an index URL on the current section (categories), but on
     * a redefined category and without the start URL parameter.
     *
     * @param array redefined keys
     * @param array removed keys
     * @return string
     */
    public static function duplicate_index_url($redefined = array(), $removed = array())
    {
        return self::make_index_url(
            self::params_for_duplication($redefined, $removed)
        );
    }

    /**
     * returns $page global array with key redefined and key removed
     *
     * @param array redefined keys
     * @param array removed keys
     * @return array
     */
    public static function params_for_duplication($redefined, $removed)
    {
        global $page;

        $params = $page;

        foreach ($removed as $param_key) {
            unset($params[$param_key]);
        }

        foreach ($redefined as $redefined_param => $redefined_value) {
            $params[$redefined_param] = $redefined_value;
        }

        return $params;
    }


    /**
     * create a picture URL with current page parameters, but with redefinitions
     * and removes. See duplicate_index_url.
     *
     * @param array redefined keys
     * @param array removed keys
     * @return string
     */
    public static function duplicate_picture_url($redefined = array(), $removed = array())
    {
        return self::make_picture_url(
            self::params_for_duplication($redefined, $removed)
        );
    }

    /**
     *adds to the url the chronology and start parameters
     */
    public static function add_well_known_params_in_url($url, $params)
    {
        if (isset($params['chronology_field'])) {
            $url .= '/' . $params['chronology_field'];
            $url .= '-' . $params['chronology_style'];
            if (isset($params['chronology_view'])) {
                $url .= '-' . $params['chronology_view'];
            }
            if (!empty($params['chronology_date'])) {
                $url .= '-' . implode('-', $params['chronology_date']);
            }
        }

        if (isset($params['flat'])) {
            $url .= '/flat';
        }

        if (isset($params['start']) and $params['start'] > 0) {
            $url .= '/start-' . $params['start'];
        }

        return $url;
    }

    /**
     * return the section token of an index or picture URL.
     *
     * Depending on section, other parameters are required (see function code for details)
     *
     * @param array
     * @return string
     */
    public static function make_section_in_url($params)
    {
        global $conf;

        $section_string = '';
        if (!empty($params['section'])) {
            $section = $params['section'];
        }
        if (!isset($section)) {
            $section_of = array(
                'category' => 'categories',
                'tags' => 'tags',
                'list' => 'list',
                'search' => 'search',
            );

            foreach ($section_of as $param => $s) {
                if (isset($params[$param])) {
                    $section = $s;
                }
            }

            if (!isset($section)) {
                $section = 'none';
            }
        }

        switch ($section) {
            case 'categories':
                {
                    if (!isset($params['category'])) {
                        $section_string .= '/categories';
                    } else {
                        isset($params['category']['name']) or trigger_error(
                            'make_section_in_url category name not set',
                            E_USER_WARNING
                        );

                        array_key_exists('permalink', $params['category']) or trigger_error(
                            'make_section_in_url category permalink not set',
                            E_USER_WARNING
                        );

                        $section_string .= '/category/';
                        if (empty($params['category']['permalink'])) {
                            $section_string .= $params['category']['id'];
                            if ($conf['category_url_style'] == 'id-name') {
                                $section_string .= '-' . \Phyxo\Functions\Language::str2url($params['category']['name']);
                            }
                        } else {
                            $section_string .= $params['category']['permalink'];
                        }
                    }

                    break;
                }
            case 'tags':
                {
                    $section_string .= '/tags';

                    foreach ($params['tags'] as $tag) {
                        switch ($conf['tag_url_style']) {
                            case 'id':
                                $section_string .= '/' . $tag['id'];
                                break;
                            case 'tag':
                                if (isset($tag['url_name']) and !is_numeric($tag['url_name'])) {
                                    $section_string .= '/' . $tag['url_name'];
                                    break;
                                }
                            default:
                                $section_string .= '/' . $tag['id'];
                                if (isset($tag['url_name'])) {
                                    $section_string .= '-' . $tag['url_name'];
                                }
                        }
                    }

                    break;
                }
            case 'search':
                {
                    $section_string .= '/search/' . $params['search'];
                    break;
                }
            case 'list':
                {
                    $section_string .= '/list/' . implode(',', $params['list']);
                    break;
                }
            case 'none':
                {
                    break;
                }
            default:
                {
                    $section_string .= '/' . $section;
                }
        }

        return $section_string;
    }

    /**
     * the reverse of make_section_in_url
     * returns the 'section' (categories/tags/...) and the data associated with it
     *
     * Depending on section, other parameters are returned (category/tags/list/...)
     *
     * @param array of url tokens to parse
     * @param int the index in the array of url tokens; in/out
     * @return array
     */
    public static function parse_section_url($tokens, &$next_token)
    {
        global $services;

        $page = array();
        if (strncmp(@$tokens[$next_token], 'categor', 7) == 0) { // @TODO: remove arobase, add test
            $page['section'] = 'categories';
            $next_token++;

            if (isset($tokens[$next_token])) {
                if (preg_match('/^(\d+)(?:-(.+))?$/', $tokens[$next_token], $matches)) {
                    if (isset($matches[2])) {
                        $page['hit_by']['cat_url_name'] = $matches[2];
                    }
                    $page['category'] = $matches[1];
                    $next_token++;
                } else { // try a permalink
                    $maybe_permalinks = array();
                    $current_token = $next_token;
                    while (isset($tokens[$current_token])
                        and strpos($tokens[$current_token], 'created-') !== 0
                        and strpos($tokens[$current_token], 'posted-') !== 0
                        and strpos($tokens[$next_token], 'start-') !== 0
                        and strpos($tokens[$next_token], 'startcat-') !== 0
                        and $tokens[$current_token] != 'flat') {
                        if (empty($maybe_permalinks)) {
                            $maybe_permalinks[] = $tokens[$current_token];
                        } else {
                            $maybe_permalinks[] = $maybe_permalinks[count($maybe_permalinks) - 1] . '/' . $tokens[$current_token];
                        }
                        $current_token++;
                    }

                    if (count($maybe_permalinks)) {
                        $cat_id = \Phyxo\Functions\Category::get_cat_id_from_permalinks($maybe_permalinks, $perma_index);
                        if (isset($cat_id)) {
                            $next_token += $perma_index + 1;
                            $page['category'] = $cat_id;
                            $page['hit_by']['cat_permalink'] = $maybe_permalinks[$perma_index];
                        } else {
                            \Phyxo\Functions\HTTP::page_not_found(\Phyxo\Functions\Language::l10n('Permalink for album not found'));
                        }
                    }
                }
            }

            if (isset($page['category'])) {
                $result = \Phyxo\Functions\Category::get_cat_info($page['category']);
                if (empty($result)) {
                    \Phyxo\Functions\HTTP::page_not_found(\Phyxo\Functions\Language::l10n('Requested album does not exist'));
                }
                $page['category'] = $result;
            }
        } elseif ('tags' == @$tokens[$next_token]) { // @TODO: remove arobase, add test
            global $conf;

            $page['section'] = 'tags';
            $page['tags'] = array();

            $next_token++;
            $i = $next_token;

            $requested_tag_ids = array();
            $requested_tag_url_names = array();

            while (isset($tokens[$i])) {
                if (strpos($tokens[$i], 'created-') === 0
                    or strpos($tokens[$i], 'posted-') === 0
                    or strpos($tokens[$i], 'start-') === 0) {
                    break;
                }

                if ($conf['tag_url_style'] != 'tag' and preg_match('/^(\d+)(?:-(.*)|)$/', $tokens[$i], $matches)) {
                    $requested_tag_ids[] = $matches[1];
                } else {
                    $requested_tag_url_names[] = $tokens[$i];
                }
                $i++;
            }
            $next_token = $i;

            if (empty($requested_tag_ids) && empty($requested_tag_url_names)) {
                \Phyxo\Functions\HTTP::bad_request('at least one tag required');
            }

            $page['tags'] = $services['tags']->findTags($requested_tag_ids, $requested_tag_url_names);
            if (empty($page['tags'])) {
                \Phyxo\Functions\HTTP::page_not_found(\Phyxo\Functions\Language::l10n('Requested tag does not exist'), self::get_root_url() . 'tags.php');
            }
        } elseif ('favorites' == @$tokens[$next_token]) { // @TODO: remove arobase, add test
            $page['section'] = 'favorites';
            $next_token++;
        } elseif ('most_visited' == @$tokens[$next_token]) { // @TODO: remove arobase, add test
            $page['section'] = 'most_visited';
            $next_token++;
        } elseif ('best_rated' == @$tokens[$next_token]) { // @TODO: remove arobase, add test
            $page['section'] = 'best_rated';
            $next_token++;
        } elseif ('recent_pics' == @$tokens[$next_token]) { // @TODO: remove arobase, add test
            $page['section'] = 'recent_pics';
            $next_token++;
        } elseif ('recent_cats' == @$tokens[$next_token]) { // @TODO: remove arobase, add test
            $page['section'] = 'recent_cats';
            $next_token++;
        } elseif ('search' == @$tokens[$next_token]) { // @TODO: remove arobase, add test
            $page['section'] = 'search';
            $next_token++;

            preg_match('/(\d+)/', @$tokens[$next_token], $matches); // @TODO: remove arobase, add test
            if (!isset($matches[1])) {
                \Phyxo\Functions\HTTP::bad_request('search identifier is missing');
            }
            $page['search'] = $matches[1];
            $next_token++;
        } elseif ('list' == @$tokens[$next_token]) { // @TODO: remove arobase, add test
            $page['section'] = 'list';
            $next_token++;

            $page['list'] = array();

            // No pictures
            if (empty($tokens[$next_token])) {
                // Add dummy element list
                $page['list'][] = -1;
            } else { // With pictures list
                if (!preg_match('/^\d+(,\d+)*$/', $tokens[$next_token])) {
                    \Phyxo\Functions\HTTP::bad_request('wrong format on list GET parameter');
                }
                foreach (explode(',', $tokens[$next_token]) as $image_id) {
                    $page['list'][] = $image_id;
                }
            }
            $next_token++;
        }

        return $page;
    }

    /**
     * the reverse of add_well_known_params_in_url
     * parses start, flat and chronology from url tokens
     */
    public static function parse_well_known_params_url($tokens, &$i)
    {
        $page = array();
        while (isset($tokens[$i])) {
            if ('flat' == $tokens[$i]) {
            // indicate a special list of images
                $page['flat'] = true;
            } elseif (strpos($tokens[$i], 'created-') === 0 or strpos($tokens[$i], 'posted-') === 0) {
                $chronology_tokens = explode('-', $tokens[$i]);
                $page['chronology_field'] = $chronology_tokens[0];

                array_shift($chronology_tokens);
                $page['chronology_style'] = $chronology_tokens[0];

                array_shift($chronology_tokens);
                if (count($chronology_tokens) > 0) {
                    if ('list' == $chronology_tokens[0] or 'calendar' == $chronology_tokens[0]) {
                        $page['chronology_view'] = $chronology_tokens[0];
                        array_shift($chronology_tokens);
                    }
                    $page['chronology_date'] = $chronology_tokens;
                }
            } elseif (preg_match('/^start-(\d+)/', $tokens[$i], $matches)) {
                $page['start'] = $matches[1];
            } elseif (preg_match('/^startcat-(\d+)/', $tokens[$i], $matches)) {
                $page['startcat'] = $matches[1];
            }
            $i++;
        }

        return $page;
    }

    /**
     * @param id image id
     * @param what_part string one of 'e' (element), 'r' (representative)
     */
    public static function get_action_url($id, $what_part, $download)
    {
        $params = array('id' => $id, 'part' => $what_part);
        if ($download) {
            $params['download'] = null;
        }

        return self::add_url_params(self::get_root_url() . 'action.php', $params);
    }

    /*
     * @param element_info array containing element information from db;
     * at least 'id', 'path' should be present
     */
    public static function get_element_url($element_info)
    {
        $url = $element_info['path'];
        if (!self::url_is_remote($url)) {
            $url = self::embellish_url(self::get_root_url() . $url);
        }

        return $url;
    }

    /**
     * Indicate to build url with full path
     *
     * @param null
     * @return null
     */
    public static function set_make_full_url()
    {
        global $page;

        if (!isset($page['save_root_path'])) {
            if (isset($page['root_path'])) {
                $page['save_root_path']['path'] = $page['root_path'];
            }
            $page['save_root_path']['count'] = 1;
            $page['root_path'] = self::get_absolute_root_url();
        } else {
            $page['save_root_path']['count'] += 1;
        }
    }

    /**
     * Restore old parameter to build url with full path
     *
     * @param null
     * @return null
     */
    public static function unset_make_full_url()
    {
        global $page;

        if (isset($page['save_root_path'])) {
            if ($page['save_root_path']['count'] == 1) {
                if (isset($page['save_root_path']['path'])) {
                    $page['root_path'] = $page['save_root_path']['path'];
                } else {
                    unset($page['root_path']);
                }
                unset($page['save_root_path']);
            } else {
                $page['save_root_path']['count'] -= 1;
            }
        }
    }

    /**
     * Embellish the url argument
     *
     * @param $url
     * @return $url embellished
     */
    public static function embellish_url($url)
    {
        $url = str_replace('/./', '/', $url);
        while (($dotdot = strpos($url, '/../', 1)) !== false) {
            $before = strrpos($url, '/', -(strlen($url) - $dotdot + 1));
            if ($before !== false) {
                $url = substr_replace($url, '', $before, $dotdot - $before + 3);
            } else {
                break;
            }
        }

        return $url;
    }

    /**
     * Returns the 'home page' of this gallery
     */
    public static function get_gallery_home_url()
    {
        global $conf;

        if (!empty($conf['gallery_url'])) {
            if (self::url_is_remote($conf['gallery_url']) or $conf['gallery_url'][0] == '/') {
                return $conf['gallery_url'];
            }
            return self::get_root_url() . $conf['gallery_url'];
        } else {
            return self::make_index_url();
        }
    }

    /**
     * returns $_SERVER['QUERY_STRING'] whithout keys given in parameters
     *
     * @param string[] $rejects
     * @param boolean $escape escape *&* to *&amp;*
     * @returns string
     */
    public static function get_query_string_diff($rejects = array(), $escape = true)
    {
        if (empty($_SERVER['QUERY_STRING'])) {
            return '';
        }

        parse_str($_SERVER['QUERY_STRING'], $vars);

        $vars = array_diff_key($vars, array_flip($rejects));

        return '?' . http_build_query($vars, '', $escape ? '&amp;' : '&');
    }

    /**
     * returns true if the url is absolute (begins with http)
     *
     * @param string $url
     * @returns boolean
     */
    public static function url_is_remote($url)
    {
        return (strncmp($url, 'http://', 7) == 0 or strncmp($url, 'https://', 8) == 0);
    }

    /**
     * Event handler to protect src image urls.
     *
     * @param string $url
     * @param SrcImage $src_image
     * @return string
     */
    public static function get_src_image_url_protection_handler($url, $src_image)
    {
        return \Phyxo\Functions\URL::get_action_url($src_image->id, $src_image->is_original() ? 'e' : 'r', false);
    }

    /**
     * Event handler to protect element urls.
     *
     * @param string $url
     * @param array $infos id, path
     * @return string
     */
    public static function get_element_url_protection_handler($url, $infos)
    {
        global $conf;

        if ('images' == $conf['original_url_protection']) {
        // protect only images and not other file types (for example large movies that we don't want to send through our file proxy)
            $ext = \Phyxo\Functions\Utils::get_extension($infos['path']);
            if (!in_array($ext, $conf['picture_ext'])) {
                return $url;
            }
        }

        return \Phyxo\Functions\URL::get_action_url($infos['id'], 'e', false);
    }


}