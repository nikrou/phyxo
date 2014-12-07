<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire           http://phyxo.nikrou.net/ |
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

namespace Phyxo\Template;

use Phyxo\Template\Combinable;
use JShrink;
use CssMin;

/**
 * Allows merging of javascript and css files into a single one.
 */
final class FileCombiner
{
    /** @var string 'js' or 'css' */
    private $type;
    /** @var bool */
    private $is_css;
    /** @var Combinable[] */
    private $combinables;

    /**
     * @param string $type 'js' or 'css'
     * @param Combinable[] $combinables
     */
    public function __construct($type, $combinables=array()) {
        $this->type = $type;
        $this->is_css = $type=='css';
        $this->combinables = $combinables;
    }

    /**
     * Deletes all combined files from cache directory.
     */
    public static function clear_combined_files() {
        // @TODO: use glob
        $dir = opendir(PHPWG_ROOT_PATH.PWG_COMBINED_DIR);
        while ($file = readdir($dir)) {
            if (get_extension($file)=='js' || get_extension($file)=='css') {
                unlink(PHPWG_ROOT_PATH.PWG_COMBINED_DIR.$file);
            }
        }
        closedir($dir);
    }

    /**
     * @param Combinable|Combinable[] $combinable
     */
    public function add($combinable) {
        if (is_array($combinable)) {
            $this->combinables = array_merge($this->combinables, $combinable);
        } else {
            $this->combinables[] = $combinable;
        }
    }

    /**
     * @return Combinable[]
     */
    public function combine() {
        global $conf;

        $force = false;
        if (is_admin() && ($this->is_css || !$conf['template_compile_check'])) {
            $force = (isset($_SERVER['HTTP_CACHE_CONTROL']) && strpos($_SERVER['HTTP_CACHE_CONTROL'], 'max-age=0') !== false)
                || (isset($_SERVER['HTTP_PRAGMA']) && strpos($_SERVER['HTTP_PRAGMA'], 'no-cache'));
        }

        $result = array();
        $pending = array();
        $ini_key = $this->is_css ? array(get_absolute_root_url(false)): array(); //because for css we modify bg url;
        $key = $ini_key;

        foreach ($this->combinables as $combinable) {
            if ($combinable->is_remote()) {
                $this->flush_pending($result, $pending, $key, $force);
                $key = $ini_key;
                $result[] = $combinable;
                continue;
            } elseif (!$conf['template_combine_files']) {
                $this->flush_pending($result, $pending, $key, $force);
                $key = $ini_key;
            }

            $key[] = $combinable->path;
            $key[] = $combinable->version;
            if ($conf['template_compile_check']) {
                $key[] = filemtime( PHPWG_ROOT_PATH . $combinable->path );
            }
            $pending[] = $combinable;
        }
        $this->flush_pending($result, $pending, $key, $force);
        return $result;
    }

    /**
     * Process a set of pending files.
     *
     * @param array &$result
     * @param array &$pending
     * @param string[] $key
     * @param bool $force
     */
    private function flush_pending(&$result, &$pending, $key, $force) {
        if (count($pending)>1) {
            $key = join('>', $key);
            $file = PWG_COMBINED_DIR . base_convert(crc32($key),10,36) . '.' . $this->type;
            if ($force || !file_exists(PHPWG_ROOT_PATH.$file)) {
                $output = '';
                foreach ($pending as $combinable) {
                    $output .= "/*BEGIN $combinable->path */\n";
                    $output .= $this->process_combinable($combinable, true, $force, $header);
                    $output .= "\n";
                }
                $output = "/*BEGIN header */\n" . $header . "\n" . $output;
                mkgetdir( dirname(PHPWG_ROOT_PATH.$file) );
                file_put_contents( PHPWG_ROOT_PATH.$file, $output );
                @chmod(PHPWG_ROOT_PATH.$file, 0644);
            }
            $result[] = new Combinable("combi", $file, false);
        } elseif (count($pending)==1) {
            $header = '';
            $this->process_combinable($pending[0], false, $force, $header);
            $result[] = $pending[0];
        }
        $key = array();
        $pending = array();
    }

    /**
     * Process one combinable file.
     *
     * @param Combinable $combinable
     * @param bool $return_content
     * @param bool $force
     * @param string $header CSS directives that must appear first in
     *                       the minified file (only used when
     *                       $return_content===true)
     * @return null|string
     */
    private function process_combinable($combinable, $return_content, $force, &$header) {
        global $conf, $template;

        if ($combinable->is_template) {
            if (!$return_content) {
                $key = array($combinable->path, $combinable->version);
                if ($conf['template_compile_check']) {
                    $key[] = filemtime( PHPWG_ROOT_PATH . $combinable->path );
                }
                $file = PWG_COMBINED_DIR . 't' . base_convert(crc32(implode(',',$key)),10,36) . '.' . $this->type;
                if (!$force && file_exists(PHPWG_ROOT_PATH.$file)) {
                    $combinable->path = $file;
                    $combinable->version = false;
                    return;
                }
            }

            $handle = $this->type. '.' .$combinable->id;
            $template->set_filename($handle, realpath(PHPWG_ROOT_PATH.$combinable->path));
            trigger_notify('combinable_preparse', $template, $combinable, $this); //allow themes and plugins to set their own vars to template ...
            $content = $template->parse($handle, true);

            if ($this->is_css) {
                $content = self::process_css($content, $combinable->path, $header);
            } else {
                $content = self::process_js($content, $combinable->path);
            }

            if ($return_content) {
                return $content;
            }
            file_put_contents( PHPWG_ROOT_PATH.$file, $content );
            $combinable->path = $file;
        } elseif ($return_content) {
            $content = file_get_contents(PHPWG_ROOT_PATH . $combinable->path);
            if ($this->is_css) {
                $content = self::process_css($content, $combinable->path, $header);
            } else {
                $content = self::process_js($content, $combinable->path);
            }
            return $content;
        }
    }

    /**
     * Process a JS file.
     *
     * @param string $js file content
     * @param string $file
     * @return string
     */
    private static function process_js($js, $file) {
        if (strpos($file, '.min')===false and strpos($file, '.packed')===false ) {
            try {
                $js = JShrink\Minifier::minify($js);
            } catch(Exception $e) {}
        }
        return trim($js, " \t\r\n;").";\n";
    }

    /**
     * Process a CSS file.
     *
     * @param string $css file content
     * @param string $file
     * @param string $header CSS directives that must appear first in
     *                       the minified file
     * @return string
     */
    private static function process_css($css, $file, &$header) {
        $css = self::process_css_rec($css, dirname($file), $header);
        if (strpos($file, '.min')===false and version_compare(PHP_VERSION, '5.2.4', '>=')) {
            $css = CssMin::minify($css, array('Variables'=>false));
        }
        $css = trigger_change('combined_css_postfilter', $css);
        return $css;
    }

    /**
     * Resolves relative links in CSS file.
     *
     * @param string $css file content
     * @param string $dir
     * @param string $header CSS directives that must appear first in
     *                       the minified file.
     * @return string
     */
    private static function process_css_rec($css, $dir, &$header) {
        static $PATTERN_URL = "#url\(\s*['|\"]{0,1}(.*?)['|\"]{0,1}\s*\)#";
        static $PATTERN_IMPORT = "#@import\s*['|\"]{0,1}(.*?)['|\"]{0,1};#";

        if (preg_match_all($PATTERN_URL, $css, $matches, PREG_SET_ORDER)) {
            $search = $replace = array();
            foreach ($matches as $match) {
                if (!url_is_remote($match[1]) && $match[1][0] != '/' && strpos($match[1], 'data:image/')===false) {
                    $relative = $dir . "/$match[1]";
                    $search[] = $match[0];
                    $replace[] = 'url('.embellish_url(get_absolute_root_url(false).$relative).')';
                }
            }
            $css = str_replace($search, $replace, $css);
        }

        if (preg_match_all($PATTERN_IMPORT, $css, $matches, PREG_SET_ORDER)) {
            $search = $replace = array();
            foreach ($matches as $match) {
                $search[] = $match[0];

                if (
                    strpos($match[1], '..') !== false // Possible attempt to get out of Piwigo's dir
                    or strpos($match[1], '://') !== false // Remote URL
                    or !is_readable(PHPWG_ROOT_PATH . $dir . '/' . $match[1])
                ) {
                    // If anything is suspicious, don't try to process the
                    // @import. Since @import need to be first and we are
                    // concatenating several CSS files, remove it from here and return
                    // it through $header.
                    $header .= $match[0];
                    $replace[] = '';
                } else {
                    $sub_css = file_get_contents(PHPWG_ROOT_PATH . $dir . "/$match[1]");
                    $replace[] = self::process_css_rec($sub_css, dirname($dir . "/$match[1]"), $header);
                }
            }
            $css = str_replace($search, $replace, $css);
        }
        return $css;
    }
}
