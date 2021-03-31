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

namespace tests\units\Phyxo\Language;

require_once __DIR__ . '/../../bootstrap.php';

use atoum;
use Prophecy\Prophet;

class Languages extends atoum
{
    private $languages_path = __DIR__ . '/../../fixtures/translations/';

    private function getLocalLanguages()
    {
        return [
            'aa_AA' => [
                'name' => 'AA Language [AA]',
                'code' => 'aa_AA',
                'version' => '1.0.0',
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=16',
                'author' => 'Nicolas',
                'author uri' => 'http://www.phyxo.net/',
                'extension' => '16'
            ],
            'gg_GG' => [
                'name' => 'GG Language [GG]',
                'code' => 'gg_GG',
                'version' => '3.0.0',
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=61',
                'author' => 'Jean',
                'extension' => '61'
            ],
            'ss_SS' => [
                'name' => 'SS Language [SS]',
                'code' => 'ss_SS',
                'version' => '1.2.0',
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=33',
                'author' => 'Jean',
                'extension' => '33'
            ],
            'tt_TT' => [
                'name' => 'TT Language [TT]',
                'code' => 'tt_TT',
                'version' => '0.3.0',
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=99',
                'author' => 'Arthur',
                'author uri' => 'http://www.phyxo.net/',
                'extension' => '99'
            ],
            'en_GB' => [
                'name' => 'English [GB]',
                'code' => 'en_GB',
                'version' => '1.9.0',
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=61',
                'author' => 'Nicolas Roudaire',
                'author uri' => 'https://www.phyxo.net',
                'extension' => '61'
            ]
        ];
    }

    public function testFsLanguages()
    {
        $prophet = new Prophet();
        $userMapper = $prophet->prophesize('App\DataMapper\UserMapper');
        $userMapper->getDefaultLanguage()->willReturn('en_GB');

        $languageRepository = $prophet->prophesize('App\Repository\LanguageRepository');

        $languages = new \Phyxo\Language\Languages($languageRepository->reveal(), $userMapper->reveal()->getDefaultLanguage());
        $languages->setRootPath($this->languages_path);

        $this
            ->array($languages->getFsLanguages())
            ->isEqualTo($this->getLocalLanguages());
    }
}
