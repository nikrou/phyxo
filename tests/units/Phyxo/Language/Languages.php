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

namespace tests\units\Phyxo\Language;

require_once __DIR__ . '/../../bootstrap.php';

use atoum;
use Phyxo\DBLayer\pgsqlConnection;

define('LANGUAGES_TABLE', 'languages');

class Languages extends atoum
{
    private function getLocalLanguages() {
        return array(
            'aa_AA' => array(
                'name' => 'AA Language [AA]',
                'code' => 'aa_AA',
                'version' => '1.0.0',
                'uri' => 'http://phyxo.nikrou.net/ext/extension_view.php?eid=16',
                'author' => 'Nicolas',
                'author uri' => 'http://www.nikrou.net/',
                'extension' => '16'
            ),
            'gg_GG' => array(
                'name' => 'GG Language [GG]',
                'code' => 'gg_GG',
                'version' => '3.0.0',
                'uri' => 'http://phyxo.nikrou.net/ext/extension_view.php?eid=61',
                'author' => 'Jean',
                'extension' => '61'
            ),
            'ss_SS' => array(
                'name' => 'SS Language [SS]',
                'code' => 'ss_SS',
                'version' => '1.2.0',
                'uri' => 'http://phyxo.nikrou.net/ext/extension_view.php?eid=33',
                'author' => 'Jean',
                'extension' => '33'
            ),
            'tt_TT' => array(
                'name' => 'TT Language [TT]',
                'code' => 'tt_TT',
                'version' => '0.3.0',
                'uri' => 'http://phyxo.nikrou.net/ext/extension_view.php?eid=99',
                'author' => 'Arthur',
                'author uri' => 'http://www.nikrou.net/',
                'extension' => '99'
            )
        );
    }

    public function testFsLanguages() {
        $controller = new \atoum\mock\controller();
		$controller->__construct = function() {};

		$conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $languages = new \Phyxo\Language\Languages($conn);

        $this
            ->array($languages->fs_languages)
            ->isEqualTo($this->getLocalLanguages());
    }
}