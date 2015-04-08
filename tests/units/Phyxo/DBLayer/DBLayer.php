<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2015 Nicolas Roudaire         http://www.phyxo.net/ |
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
    public function _testPublicMethods($dblayer, $dblayer_extra_methods) {
        $class_name = sprintf('\Phyxo\DBLayer\%sConnection', $dblayer);

        // method not overriden
        $other_methods = array('init', '__construct', 'getQueries', 'getQueriesCount', 'getQueriesTime');

        $dblayer_methods = get_class_methods($class_name);
        sort($dblayer_methods);
        $interface_methods = array_merge(get_class_methods('\Phyxo\DBLayer\iDBLayer'), $other_methods, $dblayer_extra_methods);
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

    public function testIn($dblayer) {
        $controller = new \atoum\mock\controller();
		$controller->__construct = function() {};

        $class_name = sprintf('\mock\Phyxo\DBLayer\%sConnection', $dblayer);
        $conn = new $class_name('', '', '', '', $controller);
        $this->calling($conn)->db_real_escape_string = function($s) {
            return $s;
        };

        $this
            ->string($conn->in(array(1,2,3)))
            ->isIdenticalTo(" IN('1','2','3') ")
            ->and()
            ->string($conn->in('1,2,3'))
            ->isIdenticalTo(" IN('1','2','3') ");
    }

    public function testMassInsertQuery($dblayer, $query) {
        $controller = new \atoum\mock\controller();
		$controller->__construct = function() {};

        $class_name = sprintf('\mock\Phyxo\DBLayer\%sConnection', $dblayer);
        $conn = new $class_name('', '', '', '', $controller);
        $this->calling($conn)->db_real_escape_string = function($s) {
            return $s;
        };
        if (in_array('getMaxAllowedPacket', get_class_methods($class_name))) {
            $this->calling($conn)->getMaxAllowedPacket = 16777216;
        }

        $this
            ->if($conn->mass_inserts(
                'dummy', array('user_id', 'cat_id'),
                array(array('user_id' => 1, 'cat_id' => 10)),
                array('ignore' => true)
            ))
            ->then()
                  ->mock($conn)
                  ->call('db_query')
                  ->withIdenticalArguments($query)
                  ->once();
    }

    /**
     */
    protected function testPublicMethodsDataProvider() {
        return array(
            array('pgsql', array()),
            array('mysql', array()),
            array('mysqli', array()),
            array('sqlite', array('_if','_now','_regexp','_std_finalize','_std_step','_unix_timestamp'))
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

    protected function testMassInsertQueryDataProvider() {
        return array(
            array('pgsql', "INSERT INTO dummy (user_id,cat_id) SELECT '1','10' WHERE NOT EXISTS( SELECT 1 FROM dummy WHERE user_id = '1' AND cat_id = '10')"),
            array('mysql', "INSERT IGNORE INTO dummy (user_id,cat_id) VALUES('1','10')"),
            array('mysqli', "INSERT IGNORE INTO dummy (user_id,cat_id) VALUES('1','10')"),
            array('sqlite', "INSERT OR IGNORE INTO dummy (user_id,cat_id) VALUES('1','10')")
        );
    }

    protected function testInDataProvider() {
        return array(
            array('pgsql'),
            array('mysql'),
            array('mysqli'),
            array('sqlite'),
        );
    }
}
