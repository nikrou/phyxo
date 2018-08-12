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

namespace Phyxo\Template;

use SmartyException;
use Smarty;
use Phyxo\Template\TemplateAdapter;
use Phyxo\Template\ScriptLoader;
use Phyxo\Template\CssLoader;
use Phyxo\Image\ImageStdParams;

use Phyxo\Functions\Plugin;
use Phyxo\Functions\Utils;

class Template
{
    /** @var Smarty */
    public $smarty;
    /** @var string */
    private $output = '';

    /** @var string[] - Hash of filenames for each template handle. */
    private $files = array();
    /** @var array - Templates prefilter from external sources (plugins) */
    private $external_filters = array();

    /** @var string - Content to add before </head> tag */
    private $html_head_elements = array();
    /** @var string - Runtime CSS rules */
    private $html_style = '';

    /** @const string */
    const COMBINED_SCRIPTS_TAG = '<!-- COMBINED_SCRIPTS -->';
    /** @var ScriptLoader */
    public $scriptLoader;

    /** @const string */
    const COMBINED_CSS_TAG = '<!-- COMBINED_CSS -->';
    /** @var CssLoader */
    private $cssLoader;

    /**
     * @var string $root
     * @var string $theme
     * @var string $path
     */
    public function __construct($root = '.', $theme = '', $path = 'template')
    {
        global $conf, $lang_info;

        SmartyException::$escape = false;

        $this->scriptLoader = new ScriptLoader();
        $this->cssLoader = new CssLoader();
        $this->smarty = new Smarty();
        $this->smarty->debugging = $conf['debug_template'];
        if (!$this->smarty->debugging) {
            $this->smarty->error_reporting = error_reporting() & ~E_NOTICE;
        }
        $this->smarty->compile_check = $conf['template_compile_check'];
        $this->smarty->force_compile = $conf['template_force_compile'];
        $compile_dir = PHPWG_ROOT_PATH . $conf['data_location'] . 'templates_c';
        Utils::mkgetdir($compile_dir);

        $params_url = parse_url($_SERVER['REQUEST_URI']);
        $this->smarty->assign('BASE_URL', preg_replace('`\/[^/]*$`', '', $params_url['path']));

        $this->smarty->setCompileDir($compile_dir);
        $this->smarty->assign('pwg', new TemplateAdapter());
        $this->smarty->registerPlugin('modifiercompiler', 'translate', array(__class__, 'modcompiler_translate'));
        $this->smarty->registerPlugin('modifiercompiler', 'translate_dec', array(__class__, 'modcompiler_translate_dec'));
        $this->smarty->registerPlugin('modifier', 'explode', array(__class__, 'mod_explode'));
        $this->smarty->registerPlugin('modifier', 'ternary', array(__class__, 'mod_ternary'));
        $this->smarty->registerPlugin('block', 'html_head', array($this, 'block_html_head'));
        $this->smarty->registerPlugin('block', 'html_style', array($this, 'block_html_style'));
        $this->smarty->registerPlugin('function', 'combine_script', array($this, 'func_combine_script'));
        $this->smarty->registerPlugin('function', 'get_combined_scripts', array($this, 'func_get_combined_scripts'));
        $this->smarty->registerPlugin('function', 'combine_css', array($this, 'func_combine_css'));
        $this->smarty->registerPlugin('function', 'define_derivative', array($this, 'func_define_derivative'));
        $this->smarty->registerPlugin('function', 'asset', array($this, 'func_asset'));
        $this->smarty->registerPlugin('compiler', 'get_combined_css', array($this, 'func_get_combined_css'));
        $this->smarty->registerPlugin('block', 'footer_script', array($this, 'block_footer_script'));
        $this->smarty->registerFilter('pre', array(__class__, 'prefilter_white_space'));
        if ($conf['compiled_template_cache_language']) {
            $this->smarty->registerFilter('post', array(__class__, 'postfilter_language'));
        }

        $this->smarty->setTemplateDir(array());
        if (!empty($theme)) {
            $this->set_theme($root, $theme, $path);
            if (!defined('IN_ADMIN')) {
                $this->set_prefilter('header', array(__class__, 'prefilter_local_css'));
            }
        } else {
            $this->set_template_dir($root);
        }


        // @TODO: to be removed ?
        if (isset($lang_info['code']) and !isset($lang_info['jquery_code'])) {
            $lang_info['jquery_code'] = $lang_info['code'];
        }

        if (isset($lang_info['jquery_code']) and !isset($lang_info['plupload_code'])) {
            $lang_info['plupload_code'] = str_replace('-', '_', $lang_info['jquery_code']);
        }

        $this->smarty->assign('lang_info', $lang_info);
    }

    /**
     * Loads theme's parameters.
     *
     * @param string $root
     * @param string $theme
     * @param string $path
     * @param bool $load_css
     * @param bool $load_local_head
     */
    public function set_theme($root, $theme, $path, $load_css = true, $load_local_head = true, $colorscheme = 'dark')
    {
        $this->set_template_dir($root . '/' . $theme . '/' . $path);

        $themeconf = $this->load_themeconf($root . '/' . $theme);

        if (isset($themeconf['load_css'])) {
            $load_css = $themeconf['load_css'];
        }

        if (isset($themeconf['parent']) and $themeconf['parent'] != $theme) {
            $this->set_theme(
                $root,
                $themeconf['parent'],
                $path,
                isset($themeconf['load_parent_css']) ? $themeconf['load_parent_css'] : $load_css,
                isset($themeconf['load_parent_local_head']) ? $themeconf['load_parent_local_head'] : $load_local_head
            );
        }

        $tpl_var = array(
            'id' => $theme,
            'load_css' => $load_css,
        );
        if (!empty($themeconf['local_head']) and $load_local_head) {
            $tpl_var['local_head'] = realpath($root . '/' . $theme . '/' . $themeconf['local_head']);
        }
        $themeconf['id'] = $theme;
        if (!isset($themeconf['colorscheme'])) {
            $themeconf['colorscheme'] = $colorscheme;
        }

        $this->smarty->append('themes', $tpl_var);
        $this->smarty->append('themeconf', $themeconf, true);
    }

    /**
     * Adds template directory for this Template object.
     * Also set compile id if not exists.
     *
     * @param string $dir
     */
    public function set_template_dir($dir)
    {
        $this->smarty->addTemplateDir($dir);

        if (!isset($this->smarty->compile_id)) {
            $compile_id = "1";
            $compile_id .= ($real_dir = realpath($dir)) === false ? $dir : $real_dir;
            $this->smarty->compile_id = base_convert(crc32($compile_id), 10, 36);
        }
    }

    /**
     * Gets the template root directory for this Template object.
     *
     * @return string
     */
    public function get_template_dir()
    {
        return $this->smarty->getTemplateDir();
    }

    /**
     * Deletes all compiled templates.
     */
    public function delete_compiled_templates()
    {
        $save_compile_id = $this->smarty->compile_id;
        $this->smarty->compile_id = null;
        $this->smarty->clearCompiledTemplate();
        $this->smarty->compile_id = $save_compile_id;
    }

    /**
     * Returns theme's parameter.
     *
     * @param string $val
     * @return mixed
     */
    public function get_themeconf($val)
    {
        $tc = $this->smarty->getTemplateVars('themeconf');
        return isset($tc[$val]) ? $tc[$val] : '';
    }

    /**
     * Sets the template filename for handle.
     *
     * @param string $handle
     * @param string $filename
     * @return bool
     */
    public function set_filename($handle, $filename)
    {
        return $this->set_filenames(array($handle => $filename));
    }

    /**
     * Sets the template filenames for handles.
     *
     * @param string[] $filename_array hashmap of handle=>filename
     * @return true
     */
    public function set_filenames($filename_array)
    {
        if (!is_array($filename_array)) {
            return false;
        }
        foreach ($filename_array as $handle => $filename) {
            if (is_null($filename)) {
                unset($this->files[$handle]);
            } else {
                $this->files[$handle] = $filename;
            }
        }

        return true;
    }

    /**
     * Assigns a template variable.
     * @see http://www.smarty.net/manual/en/api.assign.php
     *
     * @param string|array $tpl_var can be a var name or a hashmap of variables
     *    (in this case, do not use the _$value_ parameter)
     * @param mixed $value
     */
    public function assign($tpl_var, $value = null)
    {
        $this->smarty->assign($tpl_var, $value);
    }

    /**
     * Defines _$varname_ as the compiled result of _$handle_.
     * This can be used to effectively include a template in another template.
     * This is equivalent to assign($varname, $this->parse($handle, true)).
     *
     * @param string $varname
     * @param string $handle
     * @return true
     */
    public function assign_var_from_handle($varname, $handle)
    {
        $this->assign($varname, $this->parse($handle, true));

        return true;
    }

    public function getVariable($var)
    {
        return $this->smarty->getVariable($var);
    }

    /**
     * Appends a new value in a template array variable, the variable is created if needed.
     * @see http://www.smarty.net/manual/en/api.append.php
     *
     * @param string $tpl_var
     * @param mixed $value
     * @param bool $merge
     */
    public function append($tpl_var, $value = null, $merge = false)
    {
        $this->smarty->append($tpl_var, $value, $merge);
    }

    /**
     * Performs a string concatenation.
     *
     * @param string $tpl_var
     * @param string $value
     */
    public function concat($tpl_var, $value)
    {
        $this->assign($tpl_var, $this->smarty->getTemplateVars($tpl_var) . $value);
    }

    /**
     * Removes an assigned template variable.
     * @see http://www.smarty.net/manual/en/api.clear_assign.php
     *
     * @param string $tpl_var
     */
    public function clear_assign($tpl_var)
    {
        $this->smarty->clearAssign($tpl_var);
    }

    /**
     * Returns an assigned template variable.
     * @see http://www.smarty.net/manual/en/api.get_template_vars.php
     *
     * @param string $tpl_var
     */
    public function get_template_vars($tpl_var = null)
    {
        return $this->smarty->getTemplateVars($tpl_var);
    }

    /**
     * Checks whether requested template exists.
     * @see http://www.smarty.net/manual/en/api.template.exists.php
     * @param  string $tpl_file
     *
     * @return boolean
     */
    public function isTemplateExists($tpl_file)
    {
        return $this->smarty->templateExists($tpl_file);
    }

    /**
     * Loads the template file of the handle, compiles it and appends the result to the output
     * (or returns it if _$return_ is true).
     *
     * @param string $handle
     * @param bool $return
     * @return null|string
     */
    public function parse($handle, $return = false)
    {
        global $conf, $lang_info;

        if (!isset($this->files[$handle])) {
            \Phyxo\Functions\HTTP::fatal_error("Template->parse(): Couldn't load template file for handle $handle");
        }

        $this->smarty->assign('ROOT_URL', \Phyxo\Functions\URL::get_root_url());

        $save_compile_id = $this->smarty->compile_id;
        $this->load_external_filters($handle);

        if ($conf['compiled_template_cache_language'] and isset($lang_info['code'])) {
            $this->smarty->compile_id .= '_' . $lang_info['code'];
        }

        $v = $this->smarty->fetch($this->files[$handle]);

        $this->smarty->compile_id = $save_compile_id;
        $this->unload_external_filters($handle);

        if ($return) {
            return $v;
        }
        $this->output .= $v;
    }

    /**
     * Loads the template file of the handle, compiles it and appends the result to the output,
     * then sends the output to the browser.
     *
     * @param string $handle
     */
    public function pparse($handle)
    {
        $this->parse($handle, false);
        $this->flush();
    }

    /**
     * Load and compile JS & CSS into the template and sends the output to the browser.
     */
    public function flush()
    {
        if (!$this->scriptLoader->did_head()) {
            $pos = strpos($this->output, self::COMBINED_SCRIPTS_TAG);
            if ($pos !== false) {
                $scripts = $this->scriptLoader->get_head_scripts();
                $content = array();
                foreach ($scripts as $script) {
                    $content[] = '<script src="' . self::make_script_src($script) . '"></script>';
                }

                $this->output = substr_replace($this->output, implode("\n", $content), $pos, strlen(self::COMBINED_SCRIPTS_TAG));
            } //else maybe error or warning ?
        }

        $css = $this->cssLoader->get_css();

        $content = array();
        foreach ($css as $combi) {
            $href = \Phyxo\Functions\URL::embellish_url(\Phyxo\Functions\URL::get_root_url() . $combi->path);
            if ($combi->version !== false) {
                $href .= '?v' . ($combi->version ? $combi->version : PHPWG_VERSION);
            }
            // trigger the event for eventual use of a cdn
            $href = Plugin::trigger_change('combined_css', $href, $combi);
            $content[] = '<link rel="stylesheet" type="text/css" href="' . $href . '">';
        }
        $this->output = str_replace(
            self::COMBINED_CSS_TAG,
            implode("\n", $content),
            $this->output
        );
        $this->cssLoader->clear();

        if (count($this->html_head_elements) || strlen($this->html_style)) {
            $search = "</head>";
            if (($pos = strpos($this->output, $search)) !== false) {
                $rep = implode("\n", $this->html_head_elements);
                if (strlen($this->html_style)) {
                    $rep .= '<style type="text/css">' . $this->html_style . '</style>' . "\n"; // @TODO: try to avoid inline style
                }
                $this->output = substr_replace($this->output, $rep, $pos, 0);
            }
            $this->html_head_elements = array();
            $this->html_style = '';
        }

        echo $this->output;
        $this->output = '';
    }

    /**
     * Same as flush() but with optional debugging.
     * @see Template::flush()
     */
    public function p()
    {
        global $t2;

        $this->flush();

        if ($this->smarty->debugging) {
            $this->smarty->assign(
                array(
                    'AAAA_DEBUG_TOTAL_TIME__' => Utils::get_elapsed_time($t2, microtime(true))
                )
            );
            $this->smarty->_debug->display_debug($this->smarty);
        }
    }

    /**
     * Eval a temp string to retrieve the original PHP value.
     *
     * @param string $str
     * @return mixed
     */
    public static function get_php_str_val($str)
    {
        if (is_string($str) && strlen($str) > 1) {
            if (($str[0] == '\'' && $str[strlen($str) - 1] == '\'') || ($str[0] == '"' && $str[strlen($str) - 1] == '"')) {
                eval('$tmp=' . $str . ';');
                return $tmp;
            }
        }
        return null;
    }

    /**
     * "translate" variable modifier.
     * Usage :
     *    - {'Comment'|translate}
     *    - {'%d comments'|translate:$count}
     * @see \Phyxo\Functions\Language::l10n()
     *
     * @param array $params
     * @return string
     */
    public static function modcompiler_translate($params)
    {
        global $conf, $lang;

        switch (count($params)) {
            case 1:
                if ($conf['compiled_template_cache_language']
                    && ($key = self::get_php_str_val($params[0])) !== null
                    && isset($lang[$key])) {
                    return var_export($lang[$key], true);
                }
                return '\Phyxo\Functions\Language::l10n(' . $params[0] . ')';

            default:
                if ($conf['compiled_template_cache_language']) {
                    $ret = 'sprintf(';
                    $ret .= self::modcompiler_translate(array($params[0]));
                    $ret .= ',' . implode(',', array_slice($params, 1));
                    $ret .= ')';
                    return $ret;
                }
                return '\Phyxo\Functions\Language::l10n(' . $params[0] . ',' . implode(',', array_slice($params, 1)) . ')';
        }
    }

    /**
     * "translate_dec" variable modifier.
     * Usage :
     *    - {$count|translate_dec:'%d comment':'%d comments'}
     * @see \Phyxo\Functions\Language::l10n_dec()
     *
     * @param array $params
     * @return string
     */
    public static function modcompiler_translate_dec($params)
    {
        global $conf, $lang, $lang_info;

        if ($conf['compiled_template_cache_language']) {
            $ret = 'sprintf(';
            if ($lang_info['zero_plural']) {
                $ret .= '($tmp=(' . $params[0] . '))>1||$tmp==0';
            } else {
                $ret .= '($tmp=(' . $params[0] . '))>1';
            }
            $ret .= '?';
            $ret .= self::modcompiler_translate(array($params[2]));
            $ret .= ':';
            $ret .= self::modcompiler_translate(array($params[1]));
            $ret .= ',$tmp';
            $ret .= ')';
            return $ret;
        }

        return '\Phyxo\Functions\Language::l10n_dec(' . $params[1] . ',' . $params[2] . ',' . $params[0] . ')';
    }

    /**
     * "explode" variable modifier.
     * Usage :
     *    - {assign var=valueExploded value=$value|explode:','}
     *
     * @param string $text
     * @param string $delimiter
     * @return array
     */
    public static function mod_explode($text, $delimiter = ',')
    {
        return explode($delimiter, $text);
    }

    /**
     * ternary variable modifier.
     * Usage :
     *    - {$variable|ternary:'yes':'no'}
     *
     * @param mixed $param
     * @param mixed $true
     * @param mixed $false
     * @return mixed
     */
    public static function mod_ternary($param, $true, $false)
    {
        return $param ? $true : $false;
    }

    /**
     * The "html_head" block allows to add content just before
     * </head> element in the output after the head has been parsed.
     *
     * @param array $params (unused)
     * @param string $content
     */
    public function block_html_head($params, $content)
    {
        $content = trim($content);
        if (!empty($content)) { // second call
            $this->html_head_elements[] = $content;
        }
    }

    /**
     * The "html_style" block allows to add CSS juste before
     * </head> element in the output after the head has been parsed.
     *
     * @param array $params (unused)
     * @param string $content
     */
    public function block_html_style($params, $content)
    { // @TODO: inject as an external stylesheet
        $content = trim($content);
        if (!empty($content)) { // second call
            $this->html_style .= "\n" . $content;
        }
    }

    /**
     * The "define_derivative" function allows to define derivative from tpl file.
     * It assigns a DerivativeParams object to _name_ template variable.
     *
     * @param array $params
     *    - name (required)
     *    - type (optional)
     *    - width (required if type is empty)
     *    - height (required if type is empty)
     *    - crop (optional, used if type is empty)
     *    - min_height (optional, used with crop)
     *    - min_height (optional, used with crop)
     * @param Smarty $smarty
     */
    public function func_define_derivative($params, $smarty)
    {
        !empty($params['name']) || \Phyxo\Functions\HTTP::fatal_error('define_derivative missing name');
        if (isset($params['type'])) {
            $derivative = ImageStdParams::get_by_type($params['type']);
            $smarty->assign($params['name'], $derivative);
            return;
        }
        !empty($params['width']) || \Phyxo\Functions\HTTP::fatal_error('define_derivative missing width');
        !empty($params['height']) || \Phyxo\Functions\HTTP::fatal_error('define_derivative missing height');

        $w = intval($params['width']);
        $h = intval($params['height']);
        $crop = 0;
        $minw = null;
        $minh = null;

        if (isset($params['crop'])) {
            if (is_bool($params['crop'])) {
                $crop = $params['crop'] ? 1 : 0;
            } else {
                $crop = round($params['crop'] / 100, 2);
            }

            if ($crop) {
                $minw = empty($params['min_width']) ? $w : intval($params['min_width']);
                $minw <= $w || \Phyxo\Functions\HTTP::fatal_error('define_derivative invalid min_width');
                $minh = empty($params['min_height']) ? $h : intval($params['min_height']);
                $minh <= $h || \Phyxo\Functions\HTTP::fatal_error('define_derivative invalid min_height');
            }
        }

        $smarty->assign($params['name'], ImageStdParams::get_custom($w, $h, $crop, $minw, $minh));
    }

    public function func_asset($params, $smarty)
    {
        if (empty($params['manifest']) || empty($params['src'])) {
            return;
        }

        if (empty($this->manifest_content)) {
            if (is_readable($params['manifest'])) {
                $this->manifest_content = json_decode(file_get_contents($params['manifest']), true);
            } else {
                return '';
            }
        }

        if (!empty($this->manifest_content[$params['src']])) {
            return $this->manifest_content[$params['src']];
        } else {
            return '';
        }
    }

    /**
     * The "combine_script" functions allows inclusion of a javascript file in the current page.
     * The engine will combine several js files into a single one.
     *
     * @param array $params
     *   - id (required)
     *   - path (required)
     *   - load (optional) 'header', 'footer' or 'async'
     *   - require (optional) comma separated list of script ids required to be loaded
     *     and executed before this one
     *   - version (optional) used to force a browser refresh
     */
    public function func_combine_script($params)
    {
        if (!isset($params['id'])) {
            trigger_error("combine_script: missing 'id' parameter", E_USER_ERROR);
        }
        $load = 0;
        if (isset($params['load'])) {
            switch ($params['load']) {
                case 'header':
                    break;
                case 'footer':
                    $load = 1;
                    break;
                case 'async':
                    $load = 2;
                    break;
                default:
                    trigger_error("combine_script: invalid 'load' parameter", E_USER_ERROR);
            }
        }

        $this->scriptLoader->add(
            $params['id'],
            $load,
            empty($params['require']) ? array() : explode(',', $params['require']),
            @$params['path'],
            isset($params['version']) ? $params['version'] : 0,
            @$params['template']
        );
    }

    /**
     * The "get_combined_scripts" function returns HTML tag of combined scripts.
     * It can returns a placeholder for delayed JS files combination and minification.
     *
     * @param array $params
     *    - load (required)
     */
    public function func_get_combined_scripts($params)
    {
        if (!isset($params['load'])) {
            trigger_error("get_combined_scripts: missing 'load' parameter", E_USER_ERROR);
        }
        $load = $params['load'] == 'header' ? 0 : 1;
        $content = array();

        if ($load == 0) {
            return self::COMBINED_SCRIPTS_TAG;
        } else {
            $scripts = $this->scriptLoader->get_footer_scripts();
            foreach ($scripts[0] as $script) {
                $content[] = '<script type="text/javascript" src="' . self::make_script_src($script) . '"></script>';
            }
            if (count($this->scriptLoader->inline_scripts)) {
                $content[] = '<script type="text/javascript">//<![CDATA['; // @TODO: remove inline script
                $content = array_merge($content, $this->scriptLoader->inline_scripts);
                $content[] = '//]]></script>';
            }

            if (count($scripts[1])) { // @TODO: remove inline script
                $content[] = '<script>';
                $content[] = '(function() {var s,after = document.getElementsByTagName(\'script\')[document.getElementsByTagName(\'script\').length-1];';
                foreach ($scripts[1] as $id => $script) {
                    $content[] = 's=document.createElement(\'script\'); s.type=\'text/javascript\'; s.async=true; s.src=\''
                        . self::make_script_src($script)
                        . '\';';
                    $content[] = 'after = after.parentNode.insertBefore(s, after);';
                }
                $content[] = '})();';
                $content[] = '</script>';
            }
        }

        return implode("\n", $content);
    }

    /**
     * Returns clean relative URL to script file.
     *
     * @param Combinable $script
     * @return string
     */
    private static function make_script_src($script)
    {
        $ret = '';
        if ($script->is_remote()) {
            $ret = $script->path;
        } else {
            $ret = \Phyxo\Functions\URL::get_root_url() . $script->path;
            if ($script->version !== false) {
                $ret .= '?v' . ($script->version ? $script->version : PHPWG_VERSION);
            }
        }
        // trigger the event for eventual use of a cdn
        $ret = Plugin::trigger_change('combined_script', $ret, $script);

        return \Phyxo\Functions\URL::embellish_url($ret);
    }

    /**
     * The "footer_script" block allows to add runtime script in the HTML page.
     *
     * @param array $params
     *    - require (optional) comma separated list of script ids
     * @param string $content
     */
    public function block_footer_script($params, $content)
    {
        $content = trim($content);
        if (!empty($content)) { // second call
            $this->scriptLoader->add_inline(
                $content,
                empty($params['require']) ? array() : explode(',', $params['require'])
            );
        }
    }

    /**
     * The "combine_css" function allows inclusion of a css file in the current page.
     * The engine will combine several css files into a single one.
     *
     * @param array $params
     *    - id (optional) used to deal with multiple inclusions from plugins
     *    - path (required)
     *    - version (optional) used to force a browser refresh
     *    - order (optional)
     *    - template (optional) set to true to allow smarty syntax in the css file
     */
    public function func_combine_css($params)
    {
        if (empty($params['path'])) {
            \Phyxo\Functions\HTTP::fatal_error('combine_css missing path');
        }

        if (!isset($params['id'])) {
            $params['id'] = md5($params['path']);
        }

        $this->cssLoader->add(
            $params['id'],
            $params['path'],
            isset($params['version']) ? $params['version'] : 0,
            (int)@$params['order'],
            (bool)@$params['template']
        );
    }

    /**
     * The "get_combined_scripts" function returns a placeholder for delayed
     * CSS files combination and minification.
     *
     * @param array $params (unused)
     */
    public function func_get_combined_css($params)
    {
        return self::COMBINED_CSS_TAG;
    }

    /**
     * Declares a Smarty prefilter from a plugin, allowing it to modify template
     * source before compilation and without changing core files.
     * They will be processed by weight ascending.
     * @see http://www.smarty.net/manual/en/advanced.features.prefilters.php
     *
     * @param string $handle
     * @param Callable $callback
     * @param int $weight
     */
    public function set_prefilter($handle, $callback, $weight = 50)
    {
        $this->external_filters[$handle][$weight][] = array('pre', $callback);
        ksort($this->external_filters[$handle]);
    }

    /**
     * Declares a Smarty postfilter.
     * They will be processed by weight ascending.
     * @see http://www.smarty.net/manual/en/advanced.features.postfilters.php
     *
     * @param string $handle
     * @param Callable $callback
     * @param int $weight
     */
    public function set_postfilter($handle, $callback, $weight = 50)
    {
        $this->external_filters[$handle][$weight][] = array('post', $callback);
        ksort($this->external_filters[$handle]);
    }

    /**
     * Declares a Smarty outputfilter.
     * They will be processed by weight ascending.
     * @see http://www.smarty.net/manual/en/advanced.features.outputfilters.php
     *
     * @param string $handle
     * @param Callable $callback
     * @param int $weight
     */
    public function set_outputfilter($handle, $callback, $weight = 50)
    {
        $this->external_filters[$handle][$weight][] = array('output', $callback);
        ksort($this->external_filters[$handle]);
    }

    /**
     * Register the filters for the tpl file.
     *
     * @param string $handle
     */
    public function load_external_filters($handle)
    {
        if (isset($this->external_filters[$handle])) {
            $compile_id = '';
            foreach ($this->external_filters[$handle] as $filters) {
                foreach ($filters as $filter) {
                    list($type, $callback) = $filter;
                    $compile_id .= $type . (is_array($callback) ? implode('', $callback) : $callback);
                    $this->smarty->registerFilter($type, $callback);
                }
            }
            $this->smarty->compile_id .= '.' . base_convert(crc32($compile_id), 10, 36);
        }
    }

    /**
     * Unregister the filters for the tpl file.
     *
     * @param string $handle
     */
    public function unload_external_filters($handle)
    {
        if (isset($this->external_filters[$handle])) {
            foreach ($this->external_filters[$handle] as $filters) {
                foreach ($filters as $filter) {
                    list($type, $callback) = $filter;
                    $this->smarty->unregisterFilter($type, $callback);
                }
            }
        }
    }

    /**
     * @TODO : description of Template::prefilter_white_space
     *
     * @param string $source
     * @param Smarty $smarty
     * @param return string
     */
    public static function prefilter_white_space($source, $smarty)
    {
        $ld = $smarty->left_delimiter;
        $rd = $smarty->right_delimiter;
        $ldq = preg_quote($ld, '#');
        $rdq = preg_quote($rd, '#');

        $regex = array();
        $tags = array('if', 'foreach', 'section', 'footer_script');
        foreach ($tags as $tag) {
            $regex[] = "#^[ \t]+($ldq$tag" . "[^$ld$rd]*$rdq)\s*$#m";
            $regex[] = "#^[ \t]+($ldq/$tag$rdq)\s*$#m";
        }
        $tags = array('include', 'else', 'combine_script', 'html_head');
        foreach ($tags as $tag) {
            $regex[] = "#^[ \t]+($ldq$tag" . "[^$ld$rd]*$rdq)\s*$#m";
        }
        $source = preg_replace($regex, "$1", $source);

        return $source;
    }

    /**
     * Postfilter used when $conf['compiled_template_cache_language'] is true.
     *
     * @param string $source
     * @param Smarty $smarty
     * @param return string
     */
    public static function postfilter_language($source, $smarty)
    {
        // replaces echo PHP_STRING_LITERAL; with the string literal value
        $source = preg_replace_callback(
            '/\\<\\?php echo ((?:\'(?:(?:\\\\.)|[^\'])*\')|(?:"(?:(?:\\\\.)|[^"])*"));\\?\\>\\n/',
            function ($matches) {
                eval('$tmp=' . $matches[1] . ';');
                return $tmp;
            }, // @TODO: remove eval
            $source
        );

        return $source;
    }

    /**
     * Prefilter used to add theme local CSS files.
     *
     * @param string $source
     * @param Smarty $smarty
     * @param return string
     */
    public static function prefilter_local_css($source, $smarty)
    {
        $css = array();
        foreach ($smarty->getTemplateVars('themes') as $theme) {
            $f = PWG_LOCAL_DIR . 'css/' . $theme['id'] . '-rules.css';
            if (file_exists(PHPWG_ROOT_PATH . $f)) {
                $css[] = "{combine_css path='$f' order=10}";
            }
        }
        $f = PWG_LOCAL_DIR . 'css/rules.css';
        if (file_exists(PHPWG_ROOT_PATH . $f)) {
            $css[] = "{combine_css path='$f' order=10}";
        }

        if (!empty($css)) {
            $source = str_replace("{get_combined_css}", implode("\n", $css) . "{get_combined_css}", $source);
        }

        return $source;
    }

    /**
     * Loads the configuration file from a theme directory and returns it.
     *
     * @param string $dir
     * @return array
     */
    public function load_themeconf($dir)
    {
        global $themeconfs, $conf;

        $dir = realpath($dir);
        if (!isset($themeconfs[$dir])) {
            $themeconf = array();
            include($dir . '/themeconf.inc.php');
            // Put themeconf in cache
            $themeconfs[$dir] = $themeconf;
        }
        return $themeconfs[$dir];
    }
}
