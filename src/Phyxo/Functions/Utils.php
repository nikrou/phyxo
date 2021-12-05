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

use App\Entity\Album;
use App\Entity\Group;
use App\Entity\Image;
use App\Entity\Tag;
use App\Entity\UserInfos;
use Phyxo\Block\RegisteredBlock;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;

class Utils
{
    public static function phyxoInstalled(string $config_file)
    {
        return is_readable($config_file);
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
    public static function getPrivacyLevelOptions(TranslatorInterface $translator, array $available_permission_levels = [], string $domain = 'messages'): array
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
        return strcmp(Language::transliterate($a['name']), Language::transliterate($b['name']));
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
     * Returns the argument_ids array with new sequenced keys based on related
     * names. Sequence is not case sensitive.
     * Warning: By definition, this function breaks original keys.
     */
    public static function order_by_name(array $element_ids, array $name): array
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
     * Returns keys to identify the state of main tables. A key consists of the
     * last modification timestamp and the total of items (separated by a _).
     * Additionally returns the hash of root path.
     * Used to invalidate LocalStorage cache on admin pages.
     * list of keys to retrieve (categories,groups,images,tags,users)
     */
    public static function getAdminClientCacheKeys(ManagerRegistry $managerRegistry, array $requested = [], string $base_url = ''): array
    {
        $tables = [
            'categories' => Album::class,
            'images' => Image::class,
            'users' => UserInfos::class,
            'groups' => Group::class,
            'tags' => Tag::class,
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
                /** @phpstan-ignore-next-line */
                $tableInfos = $managerRegistry->getRepository($tables[$repository])->getMaxLastModified();

                $keys[$repository] = sprintf('%s_%s', (new \Datetime($tableInfos['max']))->getTimestamp(), $tableInfos['count']);
            }
        }

        return $keys;
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

        if ($suffix === 'K') {
            $multiply_by = 1024;
        } elseif ($suffix === 'M') {
            $multiply_by = 1024 * 1024;
        } elseif ($suffix === 'G') {
            $multiply_by = 1024 * 1024 * 1024;
        }

        if (!is_null($multiply_by)) {
            $value = (int) substr($value, 0, -1);
            $value = $value * $multiply_by;
        }

        return $value;
    }
}
