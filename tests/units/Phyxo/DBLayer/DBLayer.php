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

namespace tests\units\Phyxo\DBLayer;

require_once __DIR__ . '/../../bootstrap.php';

use atoum;

class DBLayer extends atoum
{
    public function testPublicMethods($dblayer) {
        $class_name = sprintf('\Phyxo\DBLayer\%sConnection', $dblayer);

        // method not overriden
        $other_methods = array('init', '__construct', 'getQueries', 'getQueriesCount', 'getQueriesTime');

        $dblayer_methods = get_class_methods($class_name);
        sort($dblayer_methods);
        $interface_methods = array_merge(get_class_methods('\Phyxo\DBLayer\iDBLayer'), $other_methods);
        sort($interface_methods);

		$this
			->array($dblayer_methods)
			->isEqualTo($interface_methods);
	}

    public function testBoolean($dblayer, $field, $result) {
        $controller = new \atoum\mock\controller();
		$controller->__construct = function() {};

        $class_name = sprintf('\mock\Phyxo\DBLayer\%sConnection', $dblayer);
        $conn = new $class_name('', '', '', '', $controller);

        $this
            ->boolean($conn->is_boolean($field))
            ->isEqualTo($result);
    }

    /**
     */
    protected function testPublicMethodsDataProvider() {
        return array(
            'pgsql',
            'mysql',
            'mysqli',
            'sqlite'
        );
    }

    protected function testBooleanDataProvider() {
        return array(
            array('pgsql', 't', true),
            array('pgsql', 'f', true),
            array('pgsql', 'dummy string', false),
            array('mysql', 'true', true),
            array('mysql', 'false', true),
            array('mysql', 'dummy string', false),
            array('mysqli', 'true', true),
            array('mysqli', 'false', true),
            array('mysqli', 'dummy string', false),
            array('sqlite', 'true', true),
            array('sqlite', 'false', true),
            array('sqlite', 'dummy string', false),
        );
    }
}
