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

namespace tests\units\Phyxo\Functions;

require_once __DIR__ . '/../../bootstrap.php';

use atoum;

class Language extends atoum
{
    private $plugins_path = __DIR__ . '/../../fixtures/plugins';
    private $themes_path = __DIR__ . '/../../fixtures/themes';
    private $languages_path = __DIR__ . '/../../fixtures/languages';

    public function testLoadLanguagePluginDescription()
    {
        // load plugin description file
        $plugin3_dir = $this->plugins_path . '/plugin3/';

        $this
            ->string(trim(\Phyxo\Functions\Language::load_language('description.txt', $plugin3_dir, ['return' => true])))
            ->isEqualTo('A simple description');
    }

    public function testLoadLanguageAboutPage()
    {
        $theme3_dir = $this->themes_path . '/theme3/';

        $this
            ->string(trim(\Phyxo\Functions\Language::load_language('about.html', $theme3_dir, ['return' => true])))
            ->isEqualTo('<p>This photo gallery is based on Phyxo.</p>' . "\n" . '<p><a href="https://www.phyxo.net">Visit the Phyxo website</a></p>');
    }

    public function testLoadLanguageCommonLang()
    {
        // not existing language file
        $this
            ->boolean(\Phyxo\Functions\Language::load_language('dummy.lang', dirname($this->languages_path) . '/'))
            ->isIdenticalTo(false);

        $this
            ->boolean(\Phyxo\Functions\Language::load_language('common.lang', dirname($this->languages_path) . '/'))
            ->isIdenticalTo(true);
    }

    public function testLoadLanguageInArray()
    {
        $language_load = (function () {
            include($this->languages_path . '/en_GB/common.lang.php');

            return ['lang' => $lang, 'lang_info' => $lang_info];
        });

        $this
            ->array(\Phyxo\Functions\Language::load_language('common.lang', dirname($this->languages_path) . '/', ['return_vars' => true]))
            ->isIdenticalTo($language_load());
    }
}
