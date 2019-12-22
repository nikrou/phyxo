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

use Phyxo\Conf;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\Response;
use Phyxo\Extension\Theme;
use Phyxo\Image\ImageStandardParams;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Phyxo\Image\DerivativeImage;
use Smarty_Internal_Template;
use Symfony\Contracts\Translation\TranslatorInterface;

class Template implements EngineInterface
{
    private $stats = ['render_time' => null, 'files' => []];
    private $manifest_content = '';
    private $router;
    private $user;

    private $image_std_params;

    private $smarty;
    /** @var string */
    private $output = '';

    /** @var string[] - Hash of filenames for each template handle. */
    private $files = [];
    private $conf, $translator, $domain = 'messages';
    private $theme = null;

    public function __construct(Conf $conf, TranslatorInterface $translator, RouterInterface $router, string $compileDir)
    {
        $this->conf = $conf;
        $this->translator = $translator;
        $this->router = $router;

        SmartyException::$escape = false;

        $this->smarty = new Smarty();

        $this->smarty->registerPlugin('modifier', 'translate', [$this, 'translateModifier']);
        $this->smarty->registerPlugin('block', 'translate', [$this, 'translateBlock']);
        $this->smarty->registerPlugin('function', 'asset', [$this, 'func_asset']);
        $this->smarty->registerPlugin('function', 'media', [$this, 'func_media']);
        $this->smarty->registerPlugin('function', 'path', [$this, 'func_path']);
        $this->smarty->registerPlugin('function', 'define_derivative', [$this, 'func_define_derivative']);
        $this->smarty->registerPlugin('function', 'derivative_from_image', [$this, 'func_derivative_from_image']);

        if (isset($this->conf['debug_template'])) {
            $this->smarty->debugging = $this->conf['debug_template'];
        }

        if (!$this->smarty->debugging) {
            $this->smarty->error_reporting = error_reporting() & ~E_NOTICE;
        }

        if (isset($this->conf['template_compile_check'])) {
            $this->smarty->compile_check = $this->conf['template_compile_check'];
        }

        if (isset($this->conf['template_force_compile'])) {
            $this->smarty->force_compile = $this->conf['template_force_compile'];
        }

        $this->smarty->setCompileDir($compileDir);
    }

    public function setDomain(string $domain)
    {
        $this->domain = $domain;
    }

    public function setUser(User $user)
    {
        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setImageStandardParams(ImageStandardParams $image_std_params)
    {
        $this->image_std_params = $image_std_params;
    }

    public function getImageStandardParams()
    {
        return $this->image_std_params;
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
        $themeconf = null;

        $themeconf_filename = realpath($dir) . '/themeconf.inc.php';
        if (!is_readable($themeconf_filename)) {
            return;
        }

        ob_start();
        // inject variables and objects in loaded theme
        $conf = $this->conf;
        $template = $this;
        $translator = $this->translator;
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
        $v = $this->smarty->fetch($this->files[$handle]);

        $this->smarty->compile_id = $save_compile_id;

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
        if ($return) {
            return $this->output;
        }

        echo $this->output;
        $this->output = '';
    }

    /**
     * Modifier plugin for Translation
     * @see TranslatorInterface::trans
     *
     * Usage: {'message'|translate}
     */
    public function translateModifier($message, array $params = [], $locale = null)
    {
        $domain = isset($params['domain']) ? $params['domain'] : $this->domain;

        return $this->translator->trans($message, $params, $domain, $locale);
    }

    public function translateBlock(array $params = [], $message = null, Smarty_Internal_Template $template, &$repeat)
    {
        // only output on the closing tag
        if (!$repeat && isset($message)) {
            $params = array_merge([
                'vars' => [],
                'domain' => $this->domain,
                'locale' => null,
            ], $params);

            return $this->translator->trans($message, $params['vars'], $params['domain'], $params['locale']);
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

    public function func_derivative_from_image(array $params = [], $smarty)
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
