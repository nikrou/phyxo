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

class Utils
{
    /**
     * Returns the path to use for the Piwigo cookie.
     * If Phyxo is installed on :
     * http://domain.org/meeting/gallery/
     * it will return : "/meeting/gallery"
     *
     * @return string
     */
    public static function cookie_path()
    {
        if (!empty($_SERVER['REDIRECT_SCRIPT_NAME'])) {
            $scr = $_SERVER['REDIRECT_SCRIPT_NAME'];
        } elseif (!empty($_SERVER['REDIRECT_URL'])) {
            // mod_rewrite is activated for upper level directories. we must set the
            // cookie to the path shown in the browser otherwise it will be discarded.
            if (!empty($_SERVER['PATH_INFO']) && ($_SERVER['REDIRECT_URL'] !== $_SERVER['PATH_INFO'])
                && (substr($_SERVER['REDIRECT_URL'], -strlen($_SERVER['PATH_INFO'])) == $_SERVER['PATH_INFO'])) {
                $scr = substr($_SERVER['REDIRECT_URL'], 0, strlen($_SERVER['REDIRECT_URL']) - strlen($_SERVER['PATH_INFO']));
            } else {
                $scr = $_SERVER['REDIRECT_URL'];
            }
        } else {
            $scr = $_SERVER['SCRIPT_NAME'];
        }

        $scr = substr($scr, 0, strrpos($scr, '/'));

        // add a trailing '/' if needed
        if ((strlen($scr) == 0) or ($scr {
            strlen($scr) - 1} !== '/')) {
            $scr .= '/';
        }

        if (substr(PHPWG_ROOT_PATH, 0, 3) == '../') { // this is maybe a plugin inside pwg directory
            // @TODO - what if it is an external script outside PWG ?
            $scr = $scr . PHPWG_ROOT_PATH;
            while (1) {
                $new = preg_replace('#[^/]+/\.\.(/|$)#', '', $scr);
                if ($new == $scr) {
                    break;
                }
                $scr = $new;
            }
        }

        return $scr;
    }
}