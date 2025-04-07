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

namespace App\Tests\Phyxo\Language;

use App\DataMapper\UserMapper;
use App\Repository\LanguageRepository;
use PHPUnit\Framework\TestCase;
use Phyxo\Language\Languages;
use Prophecy\PhpUnit\ProphecyTrait;

class LanguagesTest extends TestCase
{
    use ProphecyTrait;
    final public const string LANGUAGES_PATH = __DIR__ . '/../../fixtures/translations/';

    public function testFsLanguages(): void
    {
        $userMapper = $this->prophesize(UserMapper::class);
        $userMapper->getDefaultLanguage()->willReturn('en_GB');

        $languageRepository = $this->prophesize(LanguageRepository::class);

        $languages = new Languages($languageRepository->reveal(), $userMapper->reveal()->getDefaultLanguage());
        $languages->setRootPath(self::LANGUAGES_PATH);

        $this->assertEquals($this->getLocalLanguages(), $languages->getFsLanguages());
    }

    private function getLocalLanguages(): array
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
}
