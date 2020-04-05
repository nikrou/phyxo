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

namespace tests\units\Phyxo\DBLayer;

require_once __DIR__ . '/../../bootstrap.php';

use mageekguy\atoum;

class DBLayer extends atoum\test
{
    public function testPublicMethods($dblayer, $dblayer_extra_methods)
    {
        $class_name = sprintf('\Phyxo\DBLayer\%sConnection', $dblayer);

        // method not overriden
        $other_methods = [
            'init', 'initFromDSN', '__construct', 'getLayer', 'getQueries', 'getQueriesCount', 'getQueriesTime',
            'getTemporaryTable', 'availableEngines', 'executeSqlFile', 'initFromConfigFile'
        ];

        $dblayer_methods = get_class_methods($class_name);
        sort($dblayer_methods);
        $interface_methods = array_merge(get_class_methods('\Phyxo\DBLayer\iDBLayer'), $other_methods, $dblayer_extra_methods);
        sort($interface_methods);

        $this
            ->array($dblayer_methods)
            ->isEqualTo($interface_methods);
    }

    public function testBoolean($dblayer, $field, $result)
    {
        $controller = new \mageekguy\atoum\mock\controller();
        $controller->__construct = function () {
        };

        $class_name = sprintf('\mock\Phyxo\DBLayer\%sConnection', $dblayer);
        $conn = new $class_name('', '', '', '', $controller);

        $this
            ->boolean($conn->is_boolean($field))
            ->isEqualTo($result);
    }

    public function testIn($dblayer)
    {
        $controller = new \mageekguy\atoum\mock\controller();
        $controller->__construct = function () {
        };

        $class_name = sprintf('\mock\Phyxo\DBLayer\%sConnection', $dblayer);
        $conn = new $class_name('', '', '', '', $controller);
        $this->calling($conn)->db_real_escape_string = function ($s) {
            return $s;
        };

        $this
            ->string($conn->in([1, 2, 3]))
            ->isIdenticalTo(" IN('1','2','3') ");
    }

    public function testMassInsertQuery($dblayer, $query)
    {
        $controller = new \mageekguy\atoum\mock\controller();
        $controller->__construct = function () {
        };

        $class_name = sprintf('\mock\Phyxo\DBLayer\%sConnection', $dblayer);
        $conn = new $class_name('', '', '', '', $controller);
        $this->calling($conn)->db_real_escape_string = function ($s) {
            return $s;
        };
        if (in_array('getMaxAllowedPacket', get_class_methods($class_name))) {
            $this->calling($conn)->getMaxAllowedPacket = 16777216;
        }

        $this
            ->if($conn->mass_inserts(
                'dummy',
                ['user_id', 'cat_id'],
                [['user_id' => 1, 'cat_id' => 10]],
                ['ignore' => true]
            ))
            ->then()
            ->mock($conn)
            ->call('db_query')
            ->withIdenticalArguments($query)
            ->once();
    }

    public function testSignleUpdate($dblayer, $query)
    {
        $controller = new \mageekguy\atoum\mock\controller();
        $controller->__construct = function () {
        };

        $class_name = sprintf('\mock\Phyxo\DBLayer\%sConnection', $dblayer);
        $conn = new $class_name('', '', '', '', $controller);
        $this->calling($conn)->db_real_escape_string = function ($s) {
            return $s;
        };

        $this
            ->if($conn->single_update(
                'dummy',
                ['id' => 1, 'name' => 'my name', 'comment' => ''],
                ['id' => 1]
            ))
            ->then()
            ->mock($conn)
            ->call('db_query')
            ->withIdenticalArguments($query)
            ->once();
    }

    public function testMassUpdates($dblayer, $fields, $datas, $query)
    {
        $controller = new \mageekguy\atoum\mock\controller();
        $controller->__construct = function () {
        };

        $class_name = sprintf('\mock\Phyxo\DBLayer\%sConnection', $dblayer);
        $conn = new $class_name('', '', '', '', $controller);
        $this->calling($conn)->db_real_escape_string = function ($s) {
            return $s;
        };

        $this
            ->if($conn->mass_updates(
                'dummy_table',
                $fields,
                $datas
            ))
            ->then()
            ->mock($conn)
            ->call('db_query')
            ->withIdenticalArguments($query)
            ->once();
    }

    /**
     */
    protected function testPublicMethodsDataProvider()
    {
        return [
            ['pgsql', []],
            ['mysql', []],
            ['sqlite', ['_if', '_now', '_regexp', '_std_finalize', '_std_step', '_unix_timestamp']]
        ];
    }

    protected function testBooleanDataProvider()
    {
        return [
            ['pgsql', 't', true],
            ['pgsql', 'f', true],
            ['pgsql', 'dummy string', false],
            ['mysql', 'true', true],
            ['mysql', 'false', true],
            ['mysql', 'dummy string', false],
            ['sqlite', 'true', true],
            ['sqlite', 'false', true],
            ['sqlite', 'dummy string', false],
        ];
    }

    protected function testMassInsertQueryDataProvider()
    {
        return [
            ['pgsql', "INSERT INTO dummy (user_id,cat_id) SELECT '1','10' WHERE NOT EXISTS( SELECT 1 FROM dummy WHERE user_id = '1' AND cat_id = '10')"],
            ['mysql', "INSERT IGNORE INTO dummy (user_id,cat_id) VALUES('1','10')"],
            ['sqlite', "INSERT OR IGNORE INTO dummy (user_id,cat_id) VALUES('1','10')"]
        ];
    }

    protected function testSignleUpdateDataProvider()
    {
        return [
            ['pgsql', "UPDATE dummy SET id = '1', name = 'my name', comment = NULL WHERE id = '1'"],
            ['mysql', "UPDATE dummy SET id = '1', name = 'my name', comment = NULL WHERE id = '1'"],
            ['sqlite', "UPDATE dummy SET id = '1', name = 'my name', comment = NULL WHERE id = '1'"],
        ];
    }

    protected function testMassUpdatesDataProvider()
    {
        $fields = [];
        $fields = ['update' => ['author'], 'primary' => ['id']];
        $datas = [];
        $datas[] = ['id' => 10, 'author' => 'joe'];

        $fields2 = [];
        $fields2 = ['update' => ['author', 'active'], 'primary' => ['id']];
        $datas2 = [];
        $datas2[] = ['id' => 7, 'author' => 'alfred', 'active' => true];

        return [
            ['pgsql', $fields, $datas, sprintf("UPDATE dummy_table SET author = '%s' WHERE id = '%s'", $datas[0]['author'], $datas[0]['id'])],
            ['mysql', $fields, $datas, sprintf("UPDATE dummy_table SET author = '%s' WHERE id = '%s'", $datas[0]['author'], $datas[0]['id'])],
            ['sqlite', $fields, $datas, sprintf("UPDATE dummy_table SET author = '%s' WHERE id = '%s'", $datas[0]['author'], $datas[0]['id'])],
            ['pgsql', $fields2, $datas2, sprintf("UPDATE dummy_table SET author = '%s', active = 't' WHERE id = '%s'", $datas2[0]['author'], $datas2[0]['id'])],
            ['mysql', $fields2, $datas2, sprintf("UPDATE dummy_table SET author = '%s', active = 'true' WHERE id = '%s'", $datas2[0]['author'], $datas2[0]['id'])],
            ['sqlite', $fields2, $datas2, sprintf("UPDATE dummy_table SET author = '%s', active = '1' WHERE id = '%s'", $datas2[0]['author'], $datas2[0]['id'])],
        ];
    }

    protected function testInDataProvider()
    {
        return [
            ['pgsql'],
            ['mysql'],
            ['sqlite'],
        ];
    }
}
