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

namespace Treflez;

use Phyxo\Functions\Language;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Phyxo\Conf;

class ThemeController
{
    private $config, $core_config, $template;

    public function __construct(Conf $conf, EngineInterface $template)
    {
        $this->core_config = $conf;
        $this->config = new Config($conf);
        $this->template = $template;
    }

    public function init()
    {
        $language_load = Language::load_language(
            'theme.lang',
            __DIR__ . '/../',
            ['language' => $this->template->getUser()->getLanguage(), 'return_vars' => true]
        );
        $this->template->setLang($language_load['lang']);

        $this->setInitValues();
        $this->assignConfig();
    }

    public function assignConfig()
    {
        if (isset($this->core_config['bootstrap_darkroom_navbar_main_style']) && !empty($this->core_config['bootstrap_darkroom_navbar_main_style'])) {
            $this->config->navbar_main_style = $this->core_config['bootstrap_darkroom_navbar_main_style'];
        }

        if (isset($this->core_config['bootstrap_darkroom_navbar_main_bg']) && !empty($this->core_config['bootstrap_darkroom_navbar_main_bg'])) {
            $this->config->navbar_main_bg = $this->core_config['bootstrap_darkroom_navbar_main_bg'];
        }

        if (isset($this->core_config['bootstrap_darkroom_navbar_contextual_style']) && !empty($this->core_config['bootstrap_darkroom_navbar_contextual_style'])) {
            $this->config->navbar_contextual_style = $this->core_config['bootstrap_darkroom_navbar_contextual_style'];
        }

        if (isset($this->core_config['bootstrap_darkroom_navbar_contextual_bg']) && !empty($this->core_config['bootstrap_darkroom_navbar_contextual_bg'])) {
            $this->config->navbar_contextual_bg = $this->core_config['bootstrap_darkroom_navbar_contextual_bg'];
        }

        $this->template->assign('theme_config', $this->config);
    }

    public function setInitValues()
    {
        $this->template->assign([
            'col_padding' => '',
            'meta_ref_enabled' => $this->core_config['meta_ref'],
            'AUTHORIZE_REMEMBERING' => $this->core_config['authorize_remembering'],
        ]);
    }
}
