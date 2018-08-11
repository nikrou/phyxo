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
    /** no option for mkgetdir() */
    const MKGETDIR_NONE = 0;
    /** sets mkgetdir() recursive */
    const MKGETDIR_RECURSIVE = 1;
    /** sets mkgetdir() exit script on error */
    const MKGETDIR_DIE_ON_ERROR = 2;
    /** sets mkgetdir() add a index.htm file */
    const MKGETDIR_PROTECT_INDEX = 4;
    /** sets mkgetdir() add a .htaccess file*/
    const MKGETDIR_PROTECT_HTACCESS = 8;
    /** default options for mkgetdir() = MKGETDIR_RECURSIVE | MKGETDIR_DIE_ON_ERROR | MKGETDIR_PROTECT_INDEX */
    const MKGETDIR_DEFAULT = self::MKGETDIR_RECURSIVE | self::MKGETDIR_DIE_ON_ERROR | self::MKGETDIR_PROTECT_INDEX;

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

    /**
     * returns a float value coresponding to the number of seconds since
     * the unix epoch (1st January 1970) and the microseconds are precised
     * e.g. 1052343429.89276600
     *
     * @deprecated since 1.9.0 and will be removed in 1.10 or 2.0
     * @return float
     */
    public static function get_moment()
    {
        trigger_error('get_moment function is deprecated. Use microtime instead.', E_USER_DEPRECATED);

        return microtime(true);
    }

    /**
     * returns the number of seconds (with 3 decimals precision)
     * between the start time and the end time given
     *
     * @param float $start
     * @param float $end
     * @return string "$TIME s"
     */
    public static function get_elapsed_time($start, $end)
    {
        return number_format($end - $start, 3, '.', ' ') . ' s';
    }

    /**
     * returns the part of the string after the last "."
     *
     * @param string $filename
     * @return string
     */
    public static function get_extension($filename)
    {
        return substr(strrchr($filename, '.'), 1, strlen($filename));
    }

    /**
     * returns the part of the string before the last ".".
     * get_filename_wo_extension( 'test.tar.gz' ) = 'test.tar'
     *
     * @param string $filename
     * @return string
     */
    public static function get_filename_wo_extension($filename)
    {
        $pos = strrpos($filename, '.');
        return ($pos === false) ? $filename : substr($filename, 0, $pos);
    }

    /**
     * returns the element name from its filename.
     * removes file extension and replace underscores by spaces
     *
     * @param string $filename
     * @return string name
     */
    public static function get_name_from_file($filename)
    {
        return str_replace('_', ' ', self::get_filename_wo_extension($filename));
    }

    /**
     * Return $conf['filter_pages'] value for the current page
     *
     * @param string $value_name
     * @return mixed
     */
    public static function get_filter_page_value($value_name)
    {
        global $conf;

        $page_name = self::script_basename();

        if (isset($conf['filter_pages'][$page_name][$value_name])) {
            return $conf['filter_pages'][$page_name][$value_name];
        } elseif (isset($conf['filter_pages']['default'][$value_name])) {
            return $conf['filter_pages']['default'][$value_name];
        } else {
            return null;
        }
    }

    /**
     * Transforms an original path to its pwg representative
     *
     * @param string $path
     * @param string $representative_ext
     * @return string
     */
    public static function original_to_representative($path, $representative_ext)
    {
        $pos = strrpos($path, '/');
        $path = substr_replace($path, 'pwg_representative/', $pos + 1, 0);
        $pos = strrpos($path, '.');
        return substr_replace($path, $representative_ext, $pos + 1);
    }

    /**
     * get the full path of an image
     *
     * @param array $element_info element information from db (at least 'path')
     * @return string
     */
    public static function get_element_path($element_info)
    {
        $path = $element_info['path'];
        if (!\Phyxo\Functions\URL::url_is_remote($path)) {
            $path = PHPWG_ROOT_PATH . $path;
        }
        return $path;
    }

    /**
     * fill the current user caddie with given elements, if not already in caddie
     *
     * @param int[] $elements_id
     */
    public static function fill_caddie($elements_id)
    {
        global $user, $conn;

        $query = 'SELECT element_id FROM ' . CADDIE_TABLE;
        $query .= ' WHERE user_id = ' . $conn->db_real_escape_string($user['id']);
        $in_caddie = $conn->query2array($query, null, 'element_id');

        $caddiables = array_diff($elements_id, $in_caddie);

        $datas = array();

        foreach ($caddiables as $caddiable) {
            $datas[] = array(
                'element_id' => $caddiable,
                'user_id' => $user['id'],
            );
        }

        if (count($caddiables) > 0) {
            $conn->mass_inserts(CADDIE_TABLE, array('element_id', 'user_id'), $datas);
        }
    }

    /**
     * Returns webmaster mail address depending on $conf['webmaster_id']
     *
     * @return string
     */
    public static function get_webmaster_mail_address()
    {
        global $conf, $conn;

        $query = 'SELECT ' . $conf['user_fields']['email'] . ' FROM ' . USERS_TABLE;
        $query .= ' WHERE ' . $conf['user_fields']['id'] . ' = ' . $conf['webmaster_id'] . ';';
        list($email) = $conn->db_fetch_row($conn->db_query($query));

        $email = \Phyxo\Functions\Plugin::trigger_change('get_webmaster_mail_address', $email);

        return $email;
    }

    /**
     * Prepends and appends strings at each value of the given array.
     *
     * @param array $array
     * @param string $prepend_str
     * @param string $append_str
     * @return array
     */
    public static function prepend_append_array_items($array, $prepend_str, $append_str)
    {
        array_walk(
            $array,
            function (&$s) use ($prepend_str, $append_str) {
                $s = $prepend_str . $s . $append_str;
            }
        );

        return $array;
    }

    /**
     * return the character set used by Phyxo
     * @return string
     */
    public static function get_charset()
    {
        $pwg_charset = 'utf-8';
        if (defined('PWG_CHARSET')) {
            $pwg_charset = PWG_CHARSET;
        }

        return $pwg_charset;
    }

    /**
     * converts a string from a character set to another character set
     *
     * @param string $str
     * @param string $source_charset
     * @param string $dest_charset
     */
    public static function convert_charset($str, $source_charset, $dest_charset)
    {
        if ($source_charset == $dest_charset) {
            return $str;
        }
        if ($source_charset == 'iso-8859-1' and $dest_charset == 'utf-8') {
            return utf8_encode($str);
        }
        if ($source_charset == 'utf-8' and $dest_charset == 'iso-8859-1') {
            return utf8_decode($str);
        }
        if (function_exists('iconv')) {
            return iconv($source_charset, $dest_charset, $str);
        }
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($str, $dest_charset, $source_charset);
        }

        return $str; // TODO
    }

    /**
     * Return the basename of the current script.
     * The lowercase case filename of the current script without extension
     *
     * @return string
     */
    public static function script_basename()
    {
        global $conf;

        foreach (array('SCRIPT_NAME', 'SCRIPT_FILENAME', 'PHP_SELF') as $value) {
            if (!empty($_SERVER[$value])) {
                $filename = strtolower($_SERVER[$value]);
                if ($conf['php_extension_in_urls'] and self::get_extension($filename) !== 'php') {
                    continue;
                }
                $basename = basename($filename, '.php');
                if (!empty($basename)) {
                    return $basename;
                }
            }
        }

        return '';
    }

    /**
     * returns a "secret key" that is to be sent back when a user posts a form
     *
     * @param int $valid_after_seconds - key validity start time from now
     * @param string $aditionnal_data_to_hash
     * @return string
     */
    public static function get_ephemeral_key($valid_after_seconds, $aditionnal_data_to_hash = '')
    {
        global $conf;

        $time = round(microtime(true), 1);
        return $time . ':' . $valid_after_seconds . ':'
            . hash_hmac(
            'md5',
            $time . substr($_SERVER['REMOTE_ADDR'], 0, 5) . $valid_after_seconds . $aditionnal_data_to_hash,
            $conf['secret_key']
        );
    }

    /**
     * verify a key sent back with a form
     *
     * @param string $key
     * @param string $aditionnal_data_to_hash
     * @return bool
     */
    public static function verify_ephemeral_key($key, $aditionnal_data_to_hash = '')
    {
        global $conf;

        $time = microtime(true);
        $key = explode(':', @$key);

        // page must have been retrieved more than X sec ago
        if (count($key) != 3 or $key[0] > $time - (float)$key[1] or $key[0] < $time - 3600
            or hash_hmac(
            'md5',
            $key[0] . substr($_SERVER['REMOTE_ADDR'], 0, 5) . $key[1] . $aditionnal_data_to_hash,
            $conf['secret_key']
        ) != $key[2]) {
            return false;
        }

        return true;
    }

    /**
     * check token comming from form posted or get params to prevent csrf attacks.
     * if pwg_token is empty action doesn't require token
     * else pwg_token is compare to server token
     *
     * @return void access denied if token given is not equal to server token
     */
    public static function check_token()
    {
        if (!empty($_REQUEST['pwg_token'])) {
            if (self::get_token() != $_REQUEST['pwg_token']) {
                access_denied();
            }
        } else {
            bad_request('missing token');
        }
    }

    /**
     * get pwg_token used to prevent csrf attacks
     *
     * @return string
     */
    public static function get_token()
    {
        global $conf;

        return hash_hmac('md5', session_id(), $conf['secret_key']);
    }

    /**
     * creates directory if not exists and ensures that directory is writable
     *
     * @param string $dir
     * @param int $flags combination of MKGETDIR_xxx
     * @return bool
     */
    public static function mkgetdir($dir, $flags = self::MKGETDIR_NONE)
    {
        global $conf;

        if (!is_dir($dir)) {
            if (substr(PHP_OS, 0, 3) == 'WIN') {
                $dir = str_replace('/', DIRECTORY_SEPARATOR, $dir);
            }
            $umask = umask(0);
            $mkd = @mkdir($dir, $conf['chmod_value'], ($flags & self::MKGETDIR_RECURSIVE) ? true : false);
            umask($umask);
            if ($mkd == false) {
                !($flags & self::MKGETDIR_DIE_ON_ERROR) or fatal_error("$dir " . \Phyxo\Functions\Language::l10n('no write access'));
                return false;
            }
            if ($flags & self::MKGETDIR_PROTECT_HTACCESS) {
                $file = $dir . '/.htaccess';
                file_exists($file) or @file_put_contents($file, 'deny from all');
            }
            if ($flags & self::MKGETDIR_PROTECT_INDEX) {
                $file = $dir . '/index.htm';
                file_exists($file) or @file_put_contents($file, 'Not allowed!');
            }
        }
        if (!is_writable($dir)) {
            !($flags & self::MKGETDIR_DIE_ON_ERROR) or fatal_error("$dir " . \Phyxo\Functions\Language::l10n('no write access'));
            return false;
        }

        return true;
    }

    /**
     * log the visit into history table
     *
     * @param int $image_id
     * @param string $image_type
     * @return bool
     */
    public static function log($image_id = null, $image_type = null)
    {
        global $conf, $user, $page, $conn, $services;

        $do_log = $conf['log'];
        if ($services['users']->isAdmin()) {
            $do_log = $conf['history_admin'];
        }
        if ($services['users']->isGuest()) {
            $do_log = $conf['history_guest'];
        }

        $do_log = \Phyxo\Functions\Plugin::trigger_change('pwg_log_allowed', $do_log, $image_id, $image_type);

        if (!$do_log) {
            return false;
        }

        $tags_string = null;
        if (!empty($page['section']) && $page['section'] == 'tags') {
            $tags_string = implode(',', $page['tag_ids']);
        }

        $query = 'INSERT INTO ' . HISTORY_TABLE;
        $query .= ' (date,time,user_id,IP,section,category_id,image_id,image_type,tag_ids)';
        $query .= ' VALUES(';
        $query .= ' CURRENT_DATE,CURRENT_TIME,';
        $query .= $conn->db_real_escape_string($user['id']) . ',\'' . $conn->db_real_escape_string($_SERVER['REMOTE_ADDR']) . '\',';
        $query .= (isset($page['section']) ? "'" . $conn->db_real_escape_string($page['section']) . "'" : 'NULL') . ',';
        $query .= (isset($page['category']['id']) ? $conn->db_real_escape_string($page['category']['id']) : 'NULL') . ',';
        $query .= (isset($image_id) ? $conn->db_real_escape_string($image_id) : 'NULL') . ',';
        $query .= (isset($image_type) ? "'" . $conn->db_real_escape_string($image_type) . "'" : 'NULL') . ',';
        $query .= (isset($tags_string) ? "'" . $conn->db_real_escape_string($tags_string) . "'" : 'NULL');
        $query .= ');';
        $conn->db_query($query);

        return true;
    }

    /**
     * return an array which will be sent to template to display navigation bar
     *
     * @param string $url base url of all links
     * @param int $nb_elements
     * @param int $start
     * @param int $nb_element_page
     * @param bool $clean_url
     * @param string $param_name
     * @return array
     */
    public static function create_navigation_bar($url, $nb_element, $start, $nb_element_page, $clean_url = false, $param_name = 'start')
    {
        global $conf;

        $navbar = array();
        $pages_around = $conf['paginate_pages_around'];
        $start_str = $clean_url ? '/' . $param_name . '-' : (strpos($url, '?') === false ? '?' : '&amp;') . $param_name . '=';

        if (!isset($start) or !is_numeric($start) or (is_numeric($start) and $start < 0)) {
            $start = 0;
        }

        // navigation bar useful only if more than one page to display !
        if ($nb_element > $nb_element_page) {
            $url_start = $url . $start_str;

            $cur_page = $navbar['CURRENT_PAGE'] = $start / $nb_element_page + 1;
            $maximum = ceil($nb_element / $nb_element_page);

            $start = $nb_element_page * round($start / $nb_element_page);
            $previous = $start - $nb_element_page;
            $next = $start + $nb_element_page;
            $last = ($maximum - 1) * $nb_element_page;

            // link to first page and previous page?
            if ($cur_page != 1) {
                $navbar['URL_FIRST'] = $url;
                $navbar['URL_PREV'] = $previous > 0 ? $url_start . $previous : $url;
            }
            // link on next page and last page?
            if ($cur_page != $maximum) {
                $navbar['URL_NEXT'] = $url_start . ($next < $last ? $next : $last);
                $navbar['URL_LAST'] = $url_start . $last;
            }

            // pages to display
            $navbar['pages'] = array();
            $navbar['pages'][1] = $url;
            for ($i = max(floor($cur_page) - $pages_around, 2), $stop = min(ceil($cur_page) + $pages_around + 1, $maximum); $i < $stop; $i++) {
                $navbar['pages'][$i] = $url . $start_str . (($i - 1) * $nb_element_page);
            }
            $navbar['pages'][$maximum] = $url_start . $last;
            $navbar['NB_PAGE'] = $maximum;
        }

        return $navbar;
    }

    /**
     * return an array which will be sent to template to display recent icon
     *
     * @param string $date
     * @param bool $is_child_date
     * @return array
     */
    public static function get_icon($date, $is_child_date = false)
    {
        global $cache, $user, $conn;

        if (empty($date)) {
            return false;
        }

        if (!isset($cache['get_icon']['title'])) {
            $cache['get_icon']['title'] = \Phyxo\Functions\Language::l10n(
                'photos posted during the last %d days',
                $user['recent_period']
            );
        }

        $icon = array(
            'TITLE' => $cache['get_icon']['title'],
            'IS_CHILD_DATE' => $is_child_date,
        );

        if (isset($cache['get_icon'][$date])) {
            return $cache['get_icon'][$date] ? $icon : array();
        }

        if (!isset($cache['get_icon']['sql_recent_date'])) {
        // Use MySql date in order to standardize all recent "actions/queries" ???
            $cache['get_icon']['sql_recent_date'] = $conn->db_get_recent_period($user['recent_period']);
        }

        $cache['get_icon'][$date] = $date > $cache['get_icon']['sql_recent_date'];

        return $cache['get_icon'][$date] ? $icon : array();
    }

    /*
     * breaks the script execution if the given value doesn't match the given
     * pattern. This should happen only during hacking attempts.
     *
     * @param string $param_name
     * @param array $param_array
     * @param boolean $is_array
     * @param string $pattern
     * @param boolean $mandatory
     */
    public static function check_input_parameter($param_name, $param_array, $is_array, $pattern, $mandatory = false)
    {
        $param_value = null;
        if (isset($param_array[$param_name])) {
            $param_value = $param_array[$param_name];
        }

        // it's ok if the input parameter is null
        if (empty($param_value)) {
            if ($mandatory) {
                fatal_error('[Hacking attempt] the input parameter "' . $param_name . '" is not valid');
            }
            return true;
        }

        if ($is_array) {
            if (!is_array($param_value)) {
                fatal_error('[Hacking attempt] the input parameter "' . $param_name . '" should be an array');
            }

            foreach ($param_value as $item_to_check) {
                if (!preg_match($pattern, $item_to_check)) {
                    fatal_error('[Hacking attempt] an item is not valid in input parameter "' . $param_name . '"');
                }
            }
        } else {
            if (!preg_match($pattern, $param_value)) {
                fatal_error('[Hacking attempt] the input parameter "' . $param_name . '" is not valid');
            }
        }
    }

    /**
     * get localized privacy level values
     *
     * @return string[]
     */
    public static function get_privacy_level_options()
    {
        global $conf;

        $options = array();
        $label = '';
        foreach (array_reverse($conf['available_permission_levels']) as $level) {
            if (0 == $level) {
                $label = \Phyxo\Functions\Language::l10n('Everybody');
            } else {
                if (strlen($label)) {
                    $label .= ', ';
                }
                $label .= \Phyxo\Functions\Language::l10n(sprintf('Level %d', $level));
            }
            $options[$level] = $label;
        }

        return $options;
    }

    /**
     * return the branch from the version. For example version 2.2.4 is for branch 2.2
     *
     * @param string $version
     * @return string
     */
    public static function get_branch_from_version($version)
    {
        return implode('.', array_slice(explode('.', $version), 0, 2));
    }

    // http
    /**
     * Redirects to the given URL (HTTP method).
     * once this function called, the execution doesn't go further
     * (presence of an exit() instruction.
     *
     * @param string $url
     * @return void
     */
    public static function redirect_http($url)
    {
        if (ob_get_length() !== false) {
            ob_clean();
        }

        // default url is on html format
        $url = html_entity_decode($url);
        header('Request-URI: ' . $url);
        header('Content-Location: ' . $url);
        header('Location: ' . $url);
        exit();
    }

    /**
     * Redirects to the given URL
     * once this function called, the execution doesn't go further
     * (presence of an exit() instruction.
     *
     * @param string $url
     * @param string $msg
     * @param integer $refresh_time
     * @return void
     */
    public static function redirect($url, $msg = '', $refresh_time = 0)
    {
        if (!headers_sent()) {
            redirect_http($url);
        }
    }

    /**
     * check url format
     *
     * @param string $url
     * @return bool
     */
    public static function url_check_format($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED) !== false;
    }

    /**
     * check email format
     *
     * @param string $mail_address
     * @return bool
     */
    public static function email_check_format($mail_address)
    {
        return filter_var($mail_address, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * returns the number of available comments for the connected user
     *
     * @return int
     */
    public static function get_nb_available_comments()
    {
        global $user, $conn, $services;

        if (!isset($user['nb_available_comments'])) {
            $where = array();
            if (!$services['users']->isAdmin()) {
                $where[] = 'validated=\'' . $conn->boolean_to_db(true) . '\'';
            }
            $where[] = \Phyxo\Functions\SQL::get_sql_condition_FandF(
                array(
                    'forbidden_categories' => 'category_id',
                    'forbidden_images' => 'ic.image_id'
                ),
                '',
                true
            );

            $query = 'SELECT COUNT(DISTINCT(com.id)) FROM ' . IMAGE_CATEGORY_TABLE . ' AS ic';
            $query .= ' LEFT JOIN ' . COMMENTS_TABLE . ' AS com ON ic.image_id = com.image_id';
            $query .= ' WHERE ' . implode(' AND ', $where);
            list($user['nb_available_comments']) = $conn->db_fetch_row($conn->db_query($query));

            $conn->single_update(
                USER_CACHE_TABLE,
                array('nb_available_comments' => $user['nb_available_comments']),
                array('user_id' => $user['id'])
            );
        }

        return $user['nb_available_comments'];
    }

    /**
     * Compare two versions with version_compare after having converted
     * single chars to their decimal values.
     * Needed because version_compare does not understand versions like '2.5.c'.
     *
     * @param string $a
     * @param string $b
     * @param string $op
     *
     * @deprecated since 1.9.0 and will be removed in 1.10 or 2.0
     */
    public static function safe_version_compare($a, $b, $op = null)
    {
        trigger_error('safe_version_compare function is deprecated. Use version_compare instead.', E_USER_DEPRECATED);

        $replace_chars = function ($m) {
            return ord(strtolower($m[1]));
        };

        // add dot before groups of letters (version_compare does the same thing)
        $a = preg_replace('#([0-9]+)([a-z]+)#i', '$1.$2', $a);
        $b = preg_replace('#([0-9]+)([a-z]+)#i', '$1.$2', $b);

        // apply ord() to any single letter
        $a = preg_replace_callback('#\b([a-z]{1})\b#i', $replace_chars, $a);
        $b = preg_replace_callback('#\b([a-z]{1})\b#i', $replace_chars, $b);

        if (empty($op)) {
            return version_compare($a, $b);
        } else {
            return version_compare($a, $b, $op);
        }
    }

    /**
     * Deletes favorites of the current user if he's not allowed to see them.
     */
    public static function check_user_favorites()
    {
        global $user, $conn;

        if ($user['forbidden_categories'] == '') {
            return;
        }

        // $filter['visible_categories'] and $filter['visible_images']
        // must be not used because filter <> restriction
        // retrieving images allowed : belonging to at least one authorized
        // category
        $query = 'SELECT DISTINCT f.image_id FROM ' . FAVORITES_TABLE . ' AS f';
        $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON f.image_id = ic.image_id';
        $query .= ' WHERE f.user_id = ' . $user['id'];
        $query .= ' ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(array('forbidden_categories' => 'ic.category_id'), ' AND ');
        $authorizeds = $conn->query2array($query, null, 'image_id');

        $query = 'SELECT image_id FROM ' . FAVORITES_TABLE;
        $query .= ' WHERE user_id = ' . $user['id'];
        $favorites = $conn->query2array($query, null, 'image_id');

        $to_deletes = array_diff($favorites, $authorizeds);
        if (count($to_deletes) > 0) {
            $query = 'DELETE FROM ' . FAVORITES_TABLE;
            $query .= ' WHERE image_id ' . $conn->in($to_deletes) . ' AND user_id = ' . $user['id'];
            $conn->db_query($query);
        }
    }

}