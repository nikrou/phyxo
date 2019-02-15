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
    public function _testLoadLanguagePluginDescription()
    {
        // load plugin description file
        $plugin3_dir = PHPWG_PLUGINS_PATH . '/plugin3/';

        $this
            ->string(trim(\Phyxo\Functions\Language::load_language('description.txt', $plugin3_dir, ['return' => true])))
            ->isEqualTo('A simple description');
    }

    public function _testLoadLanguageAboutPage()
    {
        $theme3_dir = PHPWG_THEMES_PATH . '/theme3/';

        $this
            ->string(trim(\Phyxo\Functions\Language::load_language('about.html', $theme3_dir, ['return' => true])))
            ->isEqualTo('<p>This photo gallery is based on Phyxo.</p>' . "\n" . '<p><a href="https://www.phyxo.net">Visit the Phyxo website</a></p>');
    }

    public function _testLoadLanguageCommonLang()
    {
        // not existing language file
        $this
            ->boolean(\Phyxo\Functions\Language::load_language('dummy.lang', dirname(PHPWG_ROOT_PATH) . '/'))
            ->isIdenticalTo(false);

        $this
            ->boolean(\Phyxo\Functions\Language::load_language('common.lang', dirname(PHPWG_LANGUAGES_PATH) . '/'))
            ->isIdenticalTo(true);
    }

    public function testLoadLanguageInArray()
    {
        $language_load = (function () {
            include(PHPWG_LANGUAGES_PATH . 'en_GB/common.lang.php');

            return ['lang' => $lang, 'lang_info' => $lang_info];
        });

        $this
            ->array(\Phyxo\Functions\Language::load_language('common.lang', dirname(PHPWG_LANGUAGES_PATH) . '/', ['return_vars' => true]))
            ->isIdenticalTo($language_load());
    }
}
