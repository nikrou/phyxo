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

use App\Entity\User;
use SmartyException;
use Smarty;
use Phyxo\Template\ScriptLoader;
use Phyxo\Template\CssLoader;

use Phyxo\Conf;
use Phyxo\Functions\Plugin;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\Response;
use Phyxo\Extension\Theme;
use Phyxo\Image\ImageStandardParams;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Phyxo\Image\DerivativeImage;

class Template implements EngineInterface
{
    protected static $instance = null;

    private $stats = ['render_time' => null, 'files' => []];
    private $manifest_content = '';
    private $router;
    private $user;

    private $image_std_params;

    /** @var Smarty */
    public $smarty;
    /** @var string */
    private $output = '';

    /** @var string[] - Hash of filenames for each template handle. */
    private $files = [];
    /** @var array - Templates prefilter from external sources (plugins) */
    private $external_filters = [];

    /** @var string - Content to add before </head> tag */
    private $html_head_elements = [];
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

    private $themeconfs = [];
    /**
     * @var string $root
     * @var string $theme
     * @var string $path
     */
    private $options = [];

    private $theme = null;

    public function __construct(array $options = [])
    {
        $this->options = array_merge(
            ['conf' => [], 'lang' => [], 'lang_info' => []],
            $options
        );

        SmartyException::$escape = false;

        $this->scriptLoader = new ScriptLoader();
        $this->cssLoader = new CssLoader();
        $this->smarty = new Smarty();

        $this->smarty->registerPlugin('modifiercompiler', 'translate', [$this, 'modcompiler_translate']);
        $this->smarty->registerPlugin('modifiercompiler', 'translate_dec', [$this, 'modcompiler_translate_dec']);
        $this->smarty->registerPlugin('modifier', 'explode', [__class__, 'mod_explode']);
        $this->smarty->registerPlugin('modifier', 'ternary', [__class__, 'mod_ternary']);
        $this->smarty->registerPlugin('block', 'html_head', [$this, 'block_html_head']);
        $this->smarty->registerPlugin('block', 'html_style', [$this, 'block_html_style']);
        $this->smarty->registerPlugin('function', 'combine_script', [$this, 'func_combine_script']);
        $this->smarty->registerPlugin('function', 'get_combined_scripts', [$this, 'func_get_combined_scripts']);
        $this->smarty->registerPlugin('function', 'combine_css', [$this, 'func_combine_css']);
        $this->smarty->registerPlugin('function', 'define_derivative', [$this, 'func_define_derivative']);
        $this->smarty->registerPlugin('function', 'asset', [$this, 'func_asset']);
        $this->smarty->registerPlugin('compiler', 'get_combined_css', [$this, 'func_get_combined_css']);
        $this->smarty->registerPlugin('block', 'footer_script', [$this, 'block_footer_script']);
        $this->smarty->registerFilter('pre', [__class__, 'prefilter_white_space']);

        $this->smarty->registerPlugin('function', 'media', [$this, 'func_media']);
        $this->smarty->registerPlugin('function', 'path', [$this, 'func_path']);
        $this->smarty->registerPlugin('function', 'derivative_from_image', [$this, 'func_derivative_from_image']);
    }

    public static function init($compile_dir)
    {
        self::$instance = new Template();
        self::$instance->setCompileDir($compile_dir);

        return self::$instance;
    }

    public function setUser(User $user)
    {
        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setRouter(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function setImageStandardParams(ImageStandardParams $image_std_params)
    {
        $this->image_std_params = $image_std_params;
    }

    public function getImageStandardParams()
    {
        return $this->image_std_params;
    }

    public function postConstruct()
    {
        if (isset($this->options['conf']['debug_template'])) {
            $this->smarty->debugging = $this->options['conf']['debug_template'];
        }

        if (!$this->smarty->debugging) {
            $this->smarty->error_reporting = error_reporting() & ~E_NOTICE;
        }

        if (isset($this->options['conf']['template_compile_check'])) {
            $this->smarty->compile_check = $this->options['conf']['template_compile_check'];
        }

        if (isset($this->options['conf']['template_force_compile'])) {
            $this->smarty->force_compile = $this->options['conf']['template_force_compile'];
        }

        if (!empty($this->options['conf']['compiled_template_cache_language'])) {
            $this->smarty->registerFilter('post', [__class__, 'postfilter_language']);
        }

        // @TODO: to be removed ?
        if (isset($this->options['lang_info']['code']) && !isset($this->options['lang_info']['jquery_code'])) {
            $this->options['lang_info']['jquery_code'] = $this->options['lang_info']['code'];
        }

        if (isset($this->options['lang_info']['jquery_code']) && !isset($this->options['lang_info']['plupload_code'])) {
            $this->options['lang_info']['plupload_code'] = str_replace('-', '_', $this->options['lang_info']['jquery_code']);
        }

        $this->smarty->assign('lang_info', $this->options['lang_info']);
    }

    public function setConf(Conf $conf)
    {
        $this->options['conf'] = $conf;
    }

    public function setLang(array $lang, bool $merge = true)
    {
        if ($merge) {
            $this->options['lang'] = array_merge($this->options['lang'], $lang);
        } else {
            $this->options['lang'] = $lang;
        }
    }

    public function setLangInfo(array $lang_info)
    {
        $this->options['lang_info'] = $lang_info;
    }

    public function setCompileDir($compile_dir)
    {
        $this->smarty->setCompileDir($compile_dir);
    }

    /*
     * @TODO ?
     * Add load_css for theme
     * Load parent theme
     */
    public function setTheme(Theme $theme)
    {
        $this->theme = $theme;

        $this->set_template_dir($theme->getRoot() . '/' . $theme->getId() . '/' . $theme->getTemplate());
        $themeconf = $this->loadThemeConf($theme->getRoot() . '/' . $theme->getId());

        if (isset($themeconf['parent']) && $themeconf['parent'] !== $theme->getId()) {
            $this->setTheme(new Theme($theme->getRoot(), $themeconf['parent']));
        }

        $tpl_var = ['id' => $theme->getId()];

        $this->smarty->append('themes', $tpl_var);
        $this->smarty->append('themeconf', ['id' => $theme->getId()]);
    }

    protected function loadThemeConf(string $dir)
    {
        $themeconf_filename = realpath($dir) . '/themeconf.inc.php';
        if (!is_readable($themeconf_filename)) {
            return;
        }

        ob_start();
        // inject variables and objects in loaded theme
        $conf = $this->options['conf'];
        $template = self::$instance;
        $image_std_params = $this->image_std_params;
        require $themeconf_filename;
        ob_end_clean();

        return $themeconf;
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
        return $this->set_filenames([$handle => $filename]);
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
        if (!isset($this->files[$handle])) {
            \Phyxo\Functions\HTTP::fatal_error("Template->parse(): Couldn't load template file for handle $handle");
        }

        $this->smarty->assign('ROOT_URL', \Phyxo\Functions\URL::get_root_url());

        $save_compile_id = $this->smarty->compile_id;
        $this->load_external_filters($handle);

        if (!empty($this->options['conf']['compiled_template_cache_language']) && isset($this->options['lang_info']['code'])) {
            $this->smarty->compile_id .= '_' . $this->options['lang_info']['code'];
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
    public function flush($return = false)
    {
        if (!$this->scriptLoader->did_head()) {
            $pos = strpos($this->output, self::COMBINED_SCRIPTS_TAG);
            if ($pos !== false) {
                $scripts = $this->scriptLoader->get_head_scripts();
                $content = [];
                foreach ($scripts as $script) {
                    $content[] = '<script src="' . self::make_script_src($script) . '"></script>';
                }

                $this->output = substr_replace($this->output, implode("\n", $content), $pos, strlen(self::COMBINED_SCRIPTS_TAG));
            } //else maybe error or warning ?
        }

        $css = $this->cssLoader->get_css();

        $content = [];
        foreach ($css as $combi) {
            $href = \Phyxo\Functions\URL::embellish_url(\Phyxo\Functions\URL::get_root_url() . $combi->path);
            if ($combi->version !== false) {
                $href .= '?v' . ($combi->version ? $combi->version : '1.10.0');
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
            $this->html_head_elements = [];
            $this->html_style = '';
        }

        if ($return) {
            return $this->output;
        }

        echo $this->output;
        $this->output = '';
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
    public function modcompiler_translate($params)
    {
        switch (count($params)) {
            case 1:
                if (!empty($this->options['conf']['compiled_template_cache_language'])
                    && ($key = self::get_php_str_val($params[0])) !== null
                    && isset($this->options['lang'][$key])) {
                    return var_export($this->options['lang'][$key], true);
                }
                return '\Phyxo\Functions\Language::l10n(' . $params[0] . ')';

            default:
                if (!empty($this->options['conf']['compiled_template_cache_language'])) {
                    $ret = 'sprintf(';
                    $ret .= self::modcompiler_translate([$params[0]]);
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
    public function modcompiler_translate_dec($params)
    {
        if (!empty($this->options['conf']['compiled_template_cache_language'])) {
            $ret = 'sprintf(';
            if ($this->options['lang_info']['zero_plural']) {
                $ret .= '($tmp=(' . $params[0] . '))>1||$tmp==0';
            } else {
                $ret .= '($tmp=(' . $params[0] . '))>1';
            }
            $ret .= '?';
            $ret .= $this->modcompiler_translate([$params[2]]);
            $ret .= ':';
            $ret .= $this->modcompiler_translate([$params[1]]);
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
            $derivative = $this->image_std_params->getByType($params['type']);
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

        $smarty->assign($params['name'], $this->image_std_params->makeCustom($w, $h, $crop, $minw, $minh));
    }

    public function func_derivative_from_image(array $params = [], $smarty) //SrcImage $img, DerivativeParams $params): DerivativeImage
    {
        if (empty($params['name']) || empty($params['image']) || empty($params['params'])) {
            return;
        }

        $smarty->assign($params['name'], new DerivativeImage($params['image'], $params['params'], $this->image_std_params));
    }

    public function func_asset($params, $smarty)
    {
        if (empty($params['manifest']) || empty($params['src'])) {
            return;
        }

        if (!empty($params['prefix'])) {
            $prefix = $params['prefix'];
        } else {
            $prefix = '';
        }

        if (empty($this->manifest_content)) {
            $manifest_file = $this->theme->getRoot() . '/' . $this->theme->getId() . '/' . $params['manifest'];
            if (is_readable($manifest_file)) {
                $this->manifest_content = json_decode(file_get_contents($manifest_file), true);
            } else {
                return '';
            }
        }

        if (!empty($this->manifest_content[$params['src']])) {
            if (strpos($this->manifest_content[$params['src']], 'http') === 0) {
                return $this->manifest_content[$params['src']];
            } else {
                return $prefix . $this->manifest_content[$params['src']];
            }
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
        trigger_error('combined_scripts is deprecated. Use plain html instead', E_USER_DEPRECATED);

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
            empty($params['require']) ? [] : explode(',', $params['require']),
            isset($params['path']) ? $params['path'] : '',
            isset($params['version']) ? $params['version'] : 0,
            isset($params['template']) ? $params['template'] : false
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
        trigger_error('get_combined_scripts is deprecated. Merge scripts on your own', E_USER_DEPRECATED);

        if (!isset($params['load'])) {
            trigger_error("get_combined_scripts: missing 'load' parameter", E_USER_ERROR);
        }
        $load = $params['load'] == 'header' ? 0 : 1;
        $content = [];

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
                $ret .= '?v' . ($script->version ? $script->version : '1.10.0');
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
        trigger_error('footer_script is deprecated. Use Smarty block instead', E_USER_DEPRECATED);

        $content = trim($content);
        if (!empty($content)) { // second call
            $this->scriptLoader->add_inline(
                $content,
                empty($params['require']) ? [] : explode(',', $params['require'])
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
     * @deprecated since 1.9.0 and will be removed in 1.10 or 2.0
     */
    public function func_combine_css($params)
    {
        trigger_error('combine_css is deprecated. Use plain html instead.', E_USER_DEPRECATED);

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
            isset($params['order']) ? (int)$params['order'] : 0,
            isset($params['template']) ? (bool)$params['template'] : false
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
        trigger_error('get_combine_css is deprecated. Merge css on your own', E_USER_DEPRECATED);

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
        $this->external_filters[$handle][$weight][] = ['pre', $callback];
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
        $this->external_filters[$handle][$weight][] = ['post', $callback];
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
        $this->external_filters[$handle][$weight][] = ['output', $callback];
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

        $regex = [];
        $tags = ['if', 'foreach', 'section', 'footer_script'];
        foreach ($tags as $tag) {
            $regex[] = "#^[ \t]+($ldq$tag" . "[^$ld$rd]*$rdq)\s*$#m";
            $regex[] = "#^[ \t]+($ldq/$tag$rdq)\s*$#m";
        }
        $tags = ['include', 'else', 'combine_script', 'html_head'];
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
        $css = [];
        foreach ($smarty->getTemplateVars('themes') as $theme) {
            $f = PWG_LOCAL_DIR . 'css/' . $theme['id'] . '-rules.css';
            if (file_exists(__DIR__ . '/../../../' . $f)) {
                $css[] = "{combine_css path='$f' order=10}";
            }
        }
        $f = PWG_LOCAL_DIR . 'css/rules.css';
        if (file_exists(__DIR__ . '/../../../' . $f)) {
            $css[] = "{combine_css path='$f' order=10}";
        }

        if (!empty($css)) {
            $source = str_replace("{get_combined_css}", implode("\n", $css) . "{get_combined_css}", $source);
        }

        return $source;
    }

    public function func_path(array $parameters = [], $smarty)
    {
        return $this->router->generate(
            $parameters['name'],
            isset($parameters['params']) ? $parameters['params'] : [],
            isset($parameters['absolute']) ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH
        );
    }

    public function func_media(array $parameters = [], $smarty)
    {
        return $this->router->generate($parameters['name'], $parameters['params']);
    }

    public function renderResponse($view, array $parameters = [], Response $response = null)
    {
    }

    public function render($name, array $parameters = [])
    {
        $time_before = microtime(true);

        $this->smarty->assign('ROOT_URL', \Phyxo\Functions\URL::get_root_url());
        $this->smarty->assign($parameters);

        $html = $this->smarty->fetch($name);

        $this->stats['render_time'] = microtime(true) - $time_before;

        return $html;
    }

    public function exists($name)
    {
        return $this->smarty->templateExists($name);
    }

    public function supports($name)
    {
        return preg_match('`.*\.tpl$`', $name);
    }

    public function getStats()
    {
        $this->stats['files'] = $this->files;

        return $this->stats;
    }
}
