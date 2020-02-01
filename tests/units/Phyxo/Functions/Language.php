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

use mageekguy\atoum;

class Language extends atoum\test
{
    private $plugins_path = __DIR__ . '/../../fixtures/plugins';
    private $themes_path = __DIR__ . '/../../fixtures/themes';
    private $languages_path = __DIR__ . '/../../fixtures/languages';

    public function testLoadLanguagePluginDescription()
    {
        // load plugin description file
        $plugin3_dir = $this->plugins_path . '/plugin3/';

        $this
            ->string(trim(\Phyxo\Functions\Language::loadLanguageFile('description.en_GB.txt', $plugin3_dir)))
            ->isEqualTo('A simple description');
    }

    public function testLoadLanguageAboutPage()
    {
        $theme3_dir = $this->themes_path . '/theme3/';

        $this
            ->string(trim(\Phyxo\Functions\Language::loadLanguageFile('about.en_GB.html', $theme3_dir)))
            ->isEqualTo('<p>This photo gallery is based on Phyxo.</p>' . "\n" . '<p><a href="https://www.phyxo.net">Visit the Phyxo website</a></p>');
    }

    public function testLoadLanguageCommonLang()
    {
        // not existing language file
        $this
            ->string(\Phyxo\Functions\Language::loadLanguageFile('dummy.lang', dirname($this->languages_path)))
            ->isIdenticalTo('');

        // $this
        //     ->string(\Phyxo\Functions\Language::loadLanguageFile('common.lang', dirname($this->languages_path)))
        //     ->isIdenticalTo('');
    }

    public function _testLoadLanguageInArray()
    {
        $language_load = (function () {
            include($this->languages_path . '/en_GB/common.lang.php');

            return ['lang' => $lang, 'lang_info' => $lang_info];
        });

        $this
            ->array(\Phyxo\Functions\Language::loadLanguageFile('common.lang', dirname($this->languages_path)))
            ->isIdenticalTo($language_load());
    }
}
