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

use App\Entity\User;
use App\Entity\UserInfos;
use Phyxo\Block\RegisteredBlock;
use App\Repository\CommentRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\UserCacheRepository;
use App\Repository\UserCacheCategoriesRepository;
use App\Repository\FavoriteRepository;
use App\Repository\RateRepository;
use App\Repository\ImageTagRepository;
use App\Repository\CaddieRepository;
use App\Repository\CategoryRepository;
use App\Repository\ThemeRepository;
use App\Repository\LanguageRepository;
use App\Repository\UserFeedRepository;
use App\Repository\ImageRepository;
use App\Repository\UserMailNotificationRepository;
use App\Repository\GroupRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Repository\UserInfosRepository;
use App\Repository\UserGroupRepository;
use Doctrine\Persistence\ManagerRegistry;
use Phyxo\EntityManager;
use Symfony\Component\Routing\RouterInterface;
use Phyxo\Image\ImageStandardParams;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;

class Utils
{
    public static function phyxoInstalled(string $config_file)
    {
        return is_readable($config_file);
    }

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
     * Returns the path to use for the Phyxo cookie.
     * If Phyxo is installed on :
     * http://domain.org/meeting/gallery/
     * it will return : "/meeting/gallery"
     *
     * @return string
     */
    public static function cookie_path()
    {
        if (!empty($_SERVER['PUBLIC_BASE_PATH'])) {
            $path = $_SERVER['PUBLIC_BASE_PATH'];

            // add a trailing '/' if needed
            if ((strlen($path) === 0) || ($path[strlen($path) - 1] !== '/')) {
                $path .= '/';
            }

            return $path;
        } elseif (!empty($_SERVER['REDIRECT_SCRIPT_NAME'])) {
            $path = $_SERVER['REDIRECT_SCRIPT_NAME'];
        } elseif (!empty($_SERVER['REDIRECT_URL'])) {
            // mod_rewrite is activated for upper level directories. we must set the
            // cookie to the path shown in the browser otherwise it will be discarded.
            if (
                !empty($_SERVER['PATH_INFO']) && ($_SERVER['REDIRECT_URL'] !== $_SERVER['PATH_INFO'])
                && (substr($_SERVER['REDIRECT_URL'], -strlen($_SERVER['PATH_INFO'])) == $_SERVER['PATH_INFO'])
            ) {
                $path = substr($_SERVER['REDIRECT_URL'], 0, strlen($_SERVER['REDIRECT_URL']) - strlen($_SERVER['PATH_INFO']));
            } else {
                $path = $_SERVER['REDIRECT_URL'];
            }
        } else {
            $path = $_SERVER['SCRIPT_NAME'];
        }

        $path = substr($path, 0, strrpos($path, '/'));

        // add a trailing '/' if needed
        if ((strlen($path) === 0) || ($path[strlen($path) - 1] !== '/')) {
            $path .= '/';
        }

        if (substr(__DIR__ . '/../../../', 0, 3) == '../') { // this is maybe a plugin inside pwg directory
            // @TODO - what if it is an external script outside PWG ?
            $path = $path . __DIR__ . '/../../../';
            while (1) {
                $new = preg_replace('#[^/]+/\.\.(/|$)#', '', $path);
                if ($new == $path) {
                    break;
                }
                $path = $new;
            }
        }

        return $path;
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
        return number_format(($end - $start) * 1000, 2, '.', ' ') . ' ms';
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
            $path = __DIR__ . '/../../../' . $path;
        }
        return $path;
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

        foreach (['SCRIPT_NAME', 'SCRIPT_FILENAME', 'PHP_SELF'] as $value) {
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

        return true;

        $time = microtime(true);
        $key = explode(':', @$key);

        // page must have been retrieved more than X sec ago
        if (
            count($key) != 3 or $key[0] > $time - (float)$key[1] or $key[0] < $time - 3600
            or hash_hmac(
                'md5',
                $key[0] . substr($_SERVER['REMOTE_ADDR'], 0, 5) . $key[1] . $aditionnal_data_to_hash,
                $conf['secret_key']
            ) != $key[2]
        ) {
            return false;
        }

        return true;
    }

    /**
     * get pwg_token used to prevent csrf attacks
     *
     * @return string
     */
    public static function get_token()
    {
        if (!empty($_SESSION['_sf2_attributes']['_csrf/https-authenticate'])) {
            return $_SESSION['_sf2_attributes']['_csrf/https-authenticate'];
        } else {
            return $_SESSION['_sf2_attributes']['_csrf/authenticate'];
        }
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
        // no more log. @TODO: use monolog
        return;

        // global $conf, $user, $page, $conn, $services;

        // $do_log = $conf['log'];
        // if ($services['users']->isAdmin()) {
        //     $do_log = $conf['history_admin'];
        // }
        // if ($services['users']->isGuest()) {
        //     $do_log = $conf['history_guest'];
        // }


        // if (!$do_log) {
        //     return false;
        // }

        // $tags_string = null;
        // if (!empty($page['section']) && $page['section'] == 'tags') {
        //     $tags_string = implode(',', $page['tag_ids']);
        // }

        // (new HistoryRepository($conn))->addHistory(
        //     [
        //         'date' => 'CURRENT_DATE',
        //         'time' => 'CURRENT_TIME',
        //         'user_id' => $user['id'],
        //         'IP' => $_SERVER['REMOTE_ADDR'],
        //         'section' => $page['section'] ?? null,
        //         'category_id' => $page['category']['id'] ?? '',
        //         'image_id' => $image_id ?? '',
        //         'image_type' => $image_type ?? '',
        //         'tag_ids' => $tags_string ?? ''
        //     ]
        // );

        // return true;
    }

    // first/prev/next/last/current
    public static function createNavigationBar(RouterInterface $router, string $route, array $query_params, int $nb_elements, int $start, int $nb_element_page, int $pages_around = 2): array
    {
        $navbar = [];
        $start_param = 'start';
        $route_with_start = $route . '__' . $start_param;

        if ($nb_elements > $nb_element_page) {
            try {
                $cur_page = $navbar['CURRENT_PAGE'] = $start / $nb_element_page + 1;
                $maximum = (int) ceil($nb_elements / $nb_element_page);

                $start = $nb_element_page * round($start / $nb_element_page);
                $previous = $start - $nb_element_page;
                $next = $start + $nb_element_page;
                $last = ($maximum - 1) * $nb_element_page;

                if ($cur_page > 1) {
                    $navbar['URL_FIRST'] = $router->generate($route, $query_params);
                    $navbar['URL_PREV'] = $router->generate(($cur_page > 2 ? $route_with_start:$route), array_merge($query_params, [$start_param => $previous]));
                }
                if ($cur_page < $maximum) {
                    $navbar['URL_NEXT'] = $router->generate($route_with_start, array_merge($query_params, [$start_param => $next < $last ? $next : $last]));
                    $navbar['URL_LAST'] = $router->generate($route_with_start, array_merge($query_params, [$start_param => $last]));
                }

                $navbar['pages'] = [];
                $navbar['pages'][1] = $router->generate($route, array_merge($query_params, [$start_param => 0]));
                for ($i = max(floor($cur_page) - $pages_around, 2), $stop = min(ceil($cur_page) + $pages_around + 1, $maximum); $i < $stop; $i++) {
                    $navbar['pages'][$i] = $router->generate($route_with_start, array_merge($query_params, [$start_param => (($i - 1) * $nb_element_page)]));
                }
                $navbar['pages'][$maximum] = $router->generate($route_with_start, array_merge($query_params, [$start_param => $last]));
            } catch (RouteNotFoundException $e) {
                // do something : route with start param probably not exists
            }
        }

        return $navbar;
    }

    /**
     * get localized privacy level values
     */
    public static function getPrivacyLevelOptions(array $available_permission_levels = [], TranslatorInterface $translator, string $domain = 'messages'): array
    {
        $options = [];
        $label = '';
        foreach (array_reverse($available_permission_levels) as $level) {
            if (0 == $level) {
                $label = $translator->trans('Everybody', [], $domain);
            } else {
                if (strlen($label)) {
                    $label .= ', ';
                }
                $label .= $translator->trans('Level ' . $level, [], $domain);
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
            self::redirect_http($url);
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
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Callback used for sorting by global_rank
     */
    public static function global_rank_compare($a, $b)
    {
        return strnatcasecmp($a['global_rank'], $b['global_rank']);
    }

    /**
     * Callback used for sorting by rank
     */
    public static function rank_compare($a, $b)
    {
        return $a['rank'] - $b['rank'];
    }

    /**
     * Callback used for sorting by name.
     */
    public static function name_compare($a, $b)
    {
        return strcmp(strtolower($a['name']), strtolower($b['name']));
    }

    /**
     * Callback used for sorting by name (slug) with cache.
     */
    public static function tag_alpha_compare($a, $b)
    {
        global $cache;

        foreach ([$a, $b] as $tag) {
            if (!isset($cache[__FUNCTION__][$tag['name']])) {
                $cache[__FUNCTION__][$tag['name']] = \Phyxo\Functions\Language::transliterate($tag['name']);
            }
        }

        return strcmp($cache[__FUNCTION__][$a['name']], $cache[__FUNCTION__][$b['name']]);
    }

    public static function counter_compare($a, $b)
    {
        if ($a['counter'] == $b['counter']) {
            return self::id_compare($a, $b);
        }

        return ($a['counter'] < $b['counter']) ? +1 : -1;
    }

    public static function id_compare($a, $b)
    {
        return ($a['id'] < $b['id']) ? -1 : 1;
    }

    /**
     * Apply basic markdown formations to a text.
     * newlines becomes br tags
     * _word_ becomes underline
     * /word/ becomes italic
     * *word* becomes bolded
     * urls becomes a tags
     *
     * @param string $content
     * @return string
     */
    public static function render_comment_content($content)
    {
        $content = htmlspecialchars($content);
        $pattern = '/(https?:\/\/\S*)/';
        $replacement = '<a href="$1" rel="nofollow">$1</a>';
        $content = preg_replace($pattern, $replacement, $content);

        $content = nl2br($content);

        // replace _word_ by an underlined word
        $pattern = '/\b_(\S*)_\b/';
        $replacement = '<span style="text-decoration:underline;">$1</span>';
        $content = preg_replace($pattern, $replacement, $content);

        // replace *word* by a bolded word
        $pattern = '/\b\*(\S*)\*\b/';
        $replacement = '<span style="font-weight:bold;">$1</span>';
        $content = preg_replace($pattern, $replacement, $content);

        // replace /word/ by an italic word
        $pattern = "/\/(\S*)\/(\s)/";
        $replacement = '<span style="font-style:italic;">$1$2</span>';
        $content = preg_replace($pattern, $replacement, $content);

        // @TODO : add a trigger

        return $content;
    }

    /**
     * Add known menubar blocks.
     *
     * @param \Phyxo\Block\BlockManager[] $menu_ref_arr
     */
    public static function register_default_menubar_blocks($menu_ref_arr)
    {
        $menu = &$menu_ref_arr[0];
        if ($menu->getId() != 'menubar') {
            return;
        }
        $menu->registerBlock(new RegisteredBlock('mbLinks', 'Links', 'core'));
        $menu->registerBlock(new RegisteredBlock('mbCategories', 'Albums', 'core'));
        $menu->registerBlock(new RegisteredBlock('mbTags', 'Related tags', 'core'));
        $menu->registerBlock(new RegisteredBlock('mbSpecials', 'Specials', 'core'));
        $menu->registerBlock(new RegisteredBlock('mbMenu', 'Menu', 'core'));
        $menu->registerBlock(new RegisteredBlock('mbIdentification', 'Identification', 'core'));
    }

    /**
     * Returns display name for an element.
     * Returns 'name' if exists of name from 'file'.
     *
     * @param array $info at least file or name
     * @return string
     */
    public static function render_element_name($info)
    {
        if (!empty($info['name'])) {
            return $info['name'];
        }

        return \Phyxo\Functions\Utils::get_name_from_file($info['file']);
    }

    /**
     * Returns display description for an element.
     *
     * @param array $info at least comment
     * @param string $param used to identify the trigger
     * @return string
     */
    public static function render_element_description($info, $param = '')
    {
        if (!empty($info['comment'])) {
            return $info['comment'];
        }

        return '';
    }

    /**
     * Sends to the template all messages stored in $page and in the session.
     */
    public static function flush_page_messages()
    {
        global $template, $page;

        if ($template->get_template_vars('page_refresh') === null) {
            foreach (['errors', 'infos', 'warnings'] as $mode) {
                if (isset($_SESSION['page_' . $mode])) {
                    $page[$mode] = array_merge($page[$mode], $_SESSION['page_' . $mode]);
                    unset($_SESSION['page_' . $mode]);
                }

                if (count($page[$mode]) != 0) {
                    $template->assign($mode, $page[$mode]);
                }
            }
        }
    }

    /**
     * Returns slideshow default params.
     * - period
     * - repeat
     * - play
     *
     * @return array
     */
    public static function get_default_slideshow_params()
    {
        global $conf;

        return [
            'period' => $conf['slideshow_period'],
            'repeat' => $conf['slideshow_repeat'],
            'play' => true,
        ];
    }

    /**
     * Checks and corrects slideshow params
     *
     * @param array $params
     * @return array
     */
    public static function correct_slideshow_params($params = [])
    {
        global $conf;

        if ($params['period'] < $conf['slideshow_period_min']) {
            $params['period'] = $conf['slideshow_period_min'];
        } elseif ($params['period'] > $conf['slideshow_period_max']) {
            $params['period'] = $conf['slideshow_period_max'];
        }

        return $params;
    }

    /**
     * Decodes slideshow string params into array
     *
     * @param string $encode_params
     * @return array
     */
    public static function decode_slideshow_params($encode_params = null)
    {
        global $conn;

        $result = self::get_default_slideshow_params();

        if (is_numeric($encode_params)) {
            $result['period'] = $encode_params;
        } else {
            $matches = [];
            if (preg_match_all('/([a-z]+)-(\d+)/', $encode_params, $matches)) {
                $matchcount = count($matches[1]);
                for ($i = 0; $i < $matchcount; $i++) {
                    $result[$matches[1][$i]] = $matches[2][$i];
                }
            }

            if (preg_match_all('/([a-z]+)-(true|false)/', $encode_params, $matches)) {
                $matchcount = count($matches[1]);
                for ($i = 0; $i < $matchcount; $i++) {
                    $result[$matches[1][$i]] = $conn->get_boolean($matches[2][$i]);
                }
            }
        }

        return self::correct_slideshow_params($result);
    }

    /**
     * Encodes slideshow array params into a string
     *
     * @param array $decode_params
     * @return string
     */
    public static function encode_slideshow_params($decode_params = [])
    {
        global $conn;

        $params = array_diff_assoc(self::correct_slideshow_params($decode_params), self::get_default_slideshow_params());
        $result = '';

        foreach ($params as $name => $value) {
            // boolean_to_string return $value, if it's not a bool
            $result .= '+' . $name . '-' . $conn->boolean_to_string($value);
        }

        return $result;
    }

    /**
     * Generates a pseudo random string.
     * Characters used are a-z A-Z and numerical values.
     *
     * @param int $size
     * @return string
     */
    public static function generate_key($size)
    {
        return substr(
            str_replace(
                ['+', '/'],
                '',
                base64_encode(openssl_random_pseudo_bytes($size))
            ),
            0,
            $size
        );
    }

    /**
     * Checks and repairs IMAGE_CATEGORY_TABLE integrity.
     * Removes all entries from the table which correspond to a deleted image.
     */
    public static function imagesIntegrity(EntityManager $em)
    {
        $result = $em->getRepository(ImageRepository::class)->findOrphanImages();
        $orphan_image_ids = $em->getConnection()->result2array($result, null, 'image_id');

        if (count($orphan_image_ids) > 0) {
            $em->getRepository(ImageCategoryRepository::class)->deleteByCategory([], $orphan_image_ids);
        }
    }

    /**
     * Returns an array containing sub-directories which are potentially
     * a category.
     * Directories named ".svn", "thumbnail", "pwg_high" or "pwg_representative"
     * are omitted.
     *
     * @param string $basedir (eg: ./galleries)
     * @return string[]
     */
    public static function get_fs_directories($path, $recursive = true)
    {
        global $conf;

        $dirs = [];
        $path = rtrim($path, '/');

        $exclude_folders = array_merge(
            $conf['sync_exclude_folders'],
            [
                '.', '..', '.svn',
                'thumbnail', 'pwg_high',
                'pwg_representative',
            ]
        );
        $exclude_folders = array_flip($exclude_folders);


        // @TODO: use glob !!!
        if (is_dir($path)) {
            if ($contents = opendir($path)) {
                while (($node = readdir($contents)) !== false) {
                    if (is_dir($path . '/' . $node) and !isset($exclude_folders[$node])) {
                        $dirs[] = $path . '/' . $node;
                        if ($recursive) {
                            $dirs = array_merge($dirs, self::get_fs_directories($path . '/' . $node));
                        }
                    }
                }
                closedir($contents);
            }
        }

        return $dirs;
    }

    /**
     * Returns an array with all file system files according to $conf['file_ext']
     *
     * @param string $path
     * @param bool $recursive
     * @return array
     */
    public static function get_fs($path, $recursive = true)
    {
        global $conf;

        // because isset is faster than in_array...
        if (!isset($conf['flip_picture_ext'])) {
            $conf['flip_picture_ext'] = array_flip($conf['picture_ext']);
        }
        if (!isset($conf['flip_file_ext'])) {
            $conf['flip_file_ext'] = array_flip($conf['file_ext']);
        }

        $fs['elements'] = [];
        $fs['thumbnails'] = [];
        $fs['representatives'] = [];
        $subdirs = [];

        // @TODO: use glob
        if (is_dir($path)) {
            if ($contents = opendir($path)) {
                while (($node = readdir($contents)) !== false) {
                    if ($node == '.' or $node == '..') {
                        continue;
                    }

                    if (is_file($path . '/' . $node)) {
                        $extension = \Phyxo\Functions\Utils::get_extension($node);

                        if (isset($conf['flip_picture_ext'][$extension])) {
                            if (basename($path) == 'thumbnail') {
                                $fs['thumbnails'][] = $path . '/' . $node;
                            } elseif (basename($path) == 'pwg_representative') {
                                $fs['representatives'][] = $path . '/' . $node;
                            } else {
                                $fs['elements'][] = $path . '/' . $node;
                            }
                        } elseif (isset($conf['flip_file_ext'][$extension])) {
                            $fs['elements'][] = $path . '/' . $node;
                        }
                    } elseif (is_dir($path . '/' . $node) and $node != 'pwg_high' and $recursive) {
                        $subdirs[] = $node;
                    }
                }
            }
            closedir($contents);

            foreach ($subdirs as $subdir) {
                $tmp_fs = self::get_fs($path . '/' . $subdir);
                $fs['elements'] = array_merge($fs['elements'], $tmp_fs['elements']);
                $fs['thumbnails'] = array_merge($fs['thumbnails'], $tmp_fs['thumbnails']);
                $fs['representatives'] = array_merge($fs['representatives'], $tmp_fs['representatives']);
            }
        }

        return $fs;
    }

    /**
     * Returns the groupname corresponding to the given group identifier if exists.
     *
     * @param int $group_id
     * @return string|false
     */
    public static function get_groupname($group_id)
    {
        global $conn;

        $result = (new GroupRepository($conn))->findById($group_id);
        if ($conn->db_num_rows($result) > 0) {
            $row = $conn->db_fetch_assoc($result);

            return $row['name'];
        } else {
            return false;
        }
    }

    /**
     * Returns the argument_ids array with new sequenced keys based on related
     * names. Sequence is not case sensitive.
     * Warning: By definition, this function breaks original keys.
     *
     * @param int[] $elements_ids
     * @param string[] $name - names of elements, indexed by ids
     * @return int[]
     */
    public static function order_by_name($element_ids, $name)
    {
        $ordered_element_ids = [];
        foreach ($element_ids as $k_id => $element_id) {
            $key = strtolower($name[$element_id]) . '-' . $name[$element_id] . '-' . $k_id;
            $ordered_element_ids[$key] = $element_id;
        }
        ksort($ordered_element_ids);
        return $ordered_element_ids;
    }

    /**
     * Returns the list of admin users.
     *
     * @param boolean $include_webmaster
     * @return int[]
     */
    public static function get_admins($include_webmaster = true)
    {
        global $conn;

        $status_list = ['admin'];

        if ($include_webmaster) {
            $status_list[] = 'webmaster';
        }

        $result = (new UserInfosRepository($conn))->findByStatuses($status_list);

        return $conn->result2array($result, null, 'user_id');
    }

    /**
     * Delete all derivative files for one or several types
     */
    public static function clear_derivative_cache(array $types, array $all_types)
    {
        for ($i = 0; $i < count($types); $i++) {
            $type = $types[$i];
            if ($type === ImageStandardParams::IMG_CUSTOM) {
                $type = \Phyxo\Image\DerivativeParams::derivative_to_url($type) . '[a-zA-Z0-9]+';
            } elseif (in_array($type, $all_types)) {
                $type = \Phyxo\Image\DerivativeParams::derivative_to_url($type);
            } else { //assume a custom type
                $type = \Phyxo\Image\DerivativeParams::derivative_to_url(ImageStandardParams::IMG_CUSTOM) . '_' . $type;
            }
            $types[$i] = $type;
        }

        $pattern = '#.*-';
        if (count($types) > 1) {
            $pattern .= '(' . implode('|', $types) . ')';
        } else {
            $pattern .= $types[0];
        }
        $pattern .= '\.[a-zA-Z0-9]{3,4}$#';

        // @TODO: use glob
        $derivative_dir = '_data/i/';
        $base_dir = __DIR__ . '/../../../';
        if ($contents = @opendir($base_dir . $derivative_dir)) {
            while (($node = readdir($contents)) !== false) {
                if ($node != '.' and $node != '..' && is_dir($base_dir . $derivative_dir . $node)) {
                    self::clear_derivative_cache_rec($base_dir . $derivative_dir . $node, $pattern);
                }
            }
            closedir($contents);
        }
    }

    /**
     * Used by clear_derivative_cache()
     * @ignore
     */
    public static function clear_derivative_cache_rec($path, $pattern)
    {
        $rmdir = true;
        $rm_index = false;

        // @TODO: use glob
        if ($contents = opendir($path)) {
            while (($node = readdir($contents)) !== false) {
                if ($node == '.' or $node == '..') {
                    continue;
                }
                if (is_dir($path . '/' . $node)) {
                    $rmdir = self::clear_derivative_cache_rec($path . '/' . $node, $pattern);
                } else {
                    if (preg_match($pattern, $node)) {
                        unlink($path . '/' . $node);
                    } elseif ($node == 'index.htm') {
                        $rm_index = true;
                    } else {
                        $rmdir = false;
                    }
                }
            }
            closedir($contents);

            if ($rmdir) {
                if ($rm_index) {
                    unlink($path . '/index.htm');
                }
                clearstatcache();
                @rmdir($path);
            }
            return $rmdir;
        }
    }

    /**
     * Deletes derivatives of a particular element
     *
     * @param array $infos ('path'[, 'representative_ext'])
     * @param 'all'|int $type
     */
    public static function delete_element_derivatives($infos, $type = 'all')
    {
        $path = $infos['path'];
        if (!empty($infos['representative_ext'])) {
            $path = \Phyxo\Functions\Utils::original_to_representative($path, $infos['representative_ext']);
        }
        if (substr_compare($path, '../', 0, 3) == 0) {
            $path = substr($path, 3);
        }
        $dot = strrpos($path, '.');
        if ($type == 'all') {
            $pattern = '-*';
        } else {
            $pattern = '-' . \Phyxo\Image\DerivativeParams::derivative_to_url($type) . '*';
        }
        $fs = new Filesystem();
        $path = substr_replace($path, $pattern, $dot, 0);
        if (($glob = glob(__DIR__ . '/../../../_data/i/' . $path)) !== false) {
            foreach ($glob as $file) {
                $fs->remove($file);
            }
        }
    }

    /**
     * Returns keys to identify the state of main tables. A key consists of the
     * last modification timestamp and the total of items (separated by a _).
     * Additionally returns the hash of root path.
     * Used to invalidate LocalStorage cache on admin pages.
     * @param string|string[] list of keys to retrieve (categories,groups,images,tags,users)
     */
    public static function getAdminClientCacheKeys(array $requested = [], EntityManager $em, ManagerRegistry $managerRegistry, string $base_url = ''): array
    {
        $tables = [
            'categories' => CategoryRepository::class,
            'groups' => GroupRepository::class,
            'images' => ImageRepository::class,
            'tags' => TagRepository::class,
        ];

        $otherTables = [
            'users' => UserInfos::class
        ];

        if (empty($requested)) {
            $returned = array_keys($tables);
        } else {
            $returned = array_intersect($requested, array_keys($tables));
        }

        $keys = [
            '_hash' => md5($base_url),
        ];

        foreach ($returned as $repository) {
            if (isset($tables[$repository])) {
                $result = $em->getRepository($tables[$repository])->getMaxLastModified();
                $row = $em->getConnection()->db_fetch_row($result);

                $keys[$repository] = sprintf('%s_%s', $row[0], $row[1]);
            }
        }

        if (empty($requested)) {
            $returned = array_keys($otherTables);
        } else {
            $returned = array_intersect($requested, array_keys($otherTables));
        }

        foreach ($returned as $repository) {
            if (isset($otherTables[$repository])) {
                $tableInfos = $managerRegistry->getRepository($otherTables[$repository])->getMaxLastModified();
                $keys[$repository] = sprintf('%s_%s', (new \Datetime($tableInfos['max']))->getTimestamp(), $tableInfos['count']);
            }
        }

        return $keys;
    }

    public static function prepare_directory($directory)
    {
        if (!is_dir($directory)) {
            if (substr(PHP_OS, 0, 3) == 'WIN') {
                $directory = str_replace('/', DIRECTORY_SEPARATOR, $directory);
            }
            umask(0000);
            $recursive = true;
            if (!@mkdir($directory, 0777, $recursive)) {
                throw new \Exception('[prepare_directory] cannot create directory "' . $directory . '"');
            }
        }

        if (!is_writable($directory)) {
            // last chance to make the directory writable
            @chmod($directory, 0777);

            if (!is_writable($directory)) {
                throw new \Exception('[prepare_directory] directory "' . $directory . '" has no write access');
            }
        }
    }

    public static function need_resize($image_filepath, $max_width, $max_height)
    {
        // TODO : the resize check should take the orientation into account. If a
        // rotation must be applied to the resized photo, then we should test
        // invert width and height.
        $file_infos = self::image_infos($image_filepath);

        if ($file_infos['width'] > $max_width || $file_infos['height'] > $max_height) {
            return true;
        }

        return false;
    }

    public static function image_infos($path): array
    {
        list($width, $height) = getimagesize($path);
        $filesize = floor(filesize($path) / 1024);

        return [
            'width' => $width,
            'height' => $height,
            'filesize' => $filesize,
        ];
    }

    public static function ready_for_upload_message()
    {
        global $conf;

        $relative_dir = preg_replace('#^' . realpath(__DIR__ . '/../../../') . '#', '', $conf['upload_dir']);
        $absolute_dir = realpath(__DIR__ . '/../../../') . '/' . $conf['upload_dir'];

        if (!is_dir($absolute_dir)) {
            if (!is_writable(dirname($absolute_dir))) {
                return sprintf('Create the "%s" directory at the root of your Phyxo installation', $relative_dir);
            }
        } else {
            if (!is_writable($absolute_dir)) {
                @chmod($absolute_dir, 0777);

                if (!is_writable($absolute_dir)) {
                    return sprintf('Give write access (chmod 777) to "%s" directory at the root of your Phyxo installation', $relative_dir);
                }
            }
        }

        return null;
    }

    public static function get_ini_size($ini_key, $in_bytes = true)
    {
        $size = ini_get($ini_key);

        if ($in_bytes) {
            $size = self::convert_shorthand_notation_to_bytes($size);
        }

        return $size;
    }

    public static function convert_shorthand_notation_to_bytes($value)
    {
        $suffix = substr($value, -1);
        $multiply_by = null;

        if ('K' == $suffix) {
            $multiply_by = 1024;
        } elseif ('M' == $suffix) {
            $multiply_by = 1024 * 1024;
        } elseif ('G' == $suffix) {
            $multiply_by = 1024 * 1024 * 1024;
        }

        if (isset($multiply_by)) {
            $value = substr($value, 0, -1);
            $value *= $multiply_by;
        }

        return $value;
    }
}
