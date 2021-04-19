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
     */
    public static function get_root_url(): string
    {
        if (!empty($_SERVER['PUBLIC_BASE_PATH'])) {
            return $_SERVER['PUBLIC_BASE_PATH'] . '/';
        }

        return '/';
    }

    public static function tagToUrl(array $tag, string $tag_url_style = 'id-tag'): string
    {
        $url_tag = $tag['id'];

        if (($tag_url_style === 'id-tag') && !empty($tag['url_name'])) {
            $url_tag .= '-' . $tag['url_name'];
        }

        return $url_tag;
    }

    /**
     * returns the absolute url to the root of PWG
     * with_scheme if false - does not add http://toto.com
     */
    public static function get_absolute_root_url(bool $with_scheme = false)
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
        $url .= self::get_root_url();

        return $url;
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
     * Embellish the url argument
     */
    public static function embellish_url(string $url): string
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
     * returns true if the url is absolute (begins with http)
     */
    public static function url_is_remote(string $url): bool
    {
        return (strncmp($url, 'http://', 7) == 0 or strncmp($url, 'https://', 8) == 0);
    }
}
