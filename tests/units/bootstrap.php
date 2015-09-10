<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2015 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

require_once __DIR__.'/../../vendor/autoload.php';

define('PHPWG_ROOT_PATH', './../../');

define('PHPWG_THEMES_PATH', __DIR__.'/fixtures/themes/');
define('PHPWG_PLUGINS_PATH', __DIR__.'/fixtures/plugins/');
define('PHPWG_LANGUAGES_PATH', __DIR__.'/fixtures/language/');


$conf['admin_theme'] = 'default';

// copy from include/functions.inc.php
function load_language($filename, $dirname='', $options=array()) {
    if ((!empty($options['return']) && $options['return']) && ($filename=='description.txt')
    && is_readable($dirname.$filename)) {
        return file_get_contents($dirname.$filename);
    }
}

// copy from include/functions_url.inc.php
function get_root_url() {
    global $page;
    if ( ($root_url = @$page['root_path']) == null ) {
        $root_url = PHPWG_ROOT_PATH;
        if (strncmp($root_url, './', 2) == 0) {
            return substr($root_url, 2);
        }
    }
    return $root_url;
}

// copy from include/functions_html.inc.php
function name_compare($a, $b) {
    return strcmp(strtolower($a['name']), strtolower($b['name']));
}

// copy from include/functions.inc.php
function get_pwg_charset() {
    $pwg_charset = 'utf-8';
    if (defined('PWG_CHARSET')) {
        $pwg_charset = PWG_CHARSET;
    }

    return $pwg_charset;
}

function convert_charset($str, $source_charset, $dest_charset) {
    return $str;
}