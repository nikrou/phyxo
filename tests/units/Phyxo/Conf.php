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

namespace tests\units\Phyxo;

require_once __DIR__ . '/../bootstrap.php';

use atoum;
use PhpParser\Node\Expr\Cast\Object_;

class Conf extends atoum
{
    public function testLoadFile()
    {
        $controller = new \atoum\mock\controller();
        $controller->__construct = function () {
        };

        $conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $conf = new \Phyxo\Conf($conn);
        $conf->loadFromFile(TESTS_CONFIG_PATH . 'config_default.inc.php');

        $this
            ->string($conf['simple_value'])
            ->isIdenticalTo('value')
            ->boolean($conf['boolean_true'])
            ->isIdenticalTo(true)
            ->boolean($conf['boolean_false'])
            ->isIdenticalTo(false)
            ->array($conf['array'])
            ->isIdenticalTo(['one', 'two', 'three'])
            ->array($conf['hash'])
            ->isIdenticalTo(['key1' => 'value1', 'key2' => 'value2']);
    }

    public function testUpdateConfValue()
    {
        $controller = new \atoum\mock\controller();
        $controller->__construct = function () {
        };

        $conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $conf = new \Phyxo\Conf($conn);
        $conf->loadFromFile(TESTS_CONFIG_PATH . 'config_default.inc.php');

        $conf['simple_value'] = 'another value';

        $this
            ->string($conf['simple_value'])
            ->isIdenticalTo('another value')
            ->boolean($conf['boolean_true'])
            ->isIdenticalTo(true)
            ->boolean($conf['boolean_false'])
            ->isIdenticalTo(false)
            ->array($conf['array'])
            ->isIdenticalTo(['one', 'two', 'three'])
            ->array($conf['hash'])
            ->isIdenticalTo(['key1' => 'value1', 'key2' => 'value2']);
    }

    public function testDeleteConfParam()
    {
        $controller = new \atoum\mock\controller();
        $controller->__construct = function () {
        };

        $conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $conf = new \Phyxo\Conf($conn);
        $conf->loadFromFile(TESTS_CONFIG_PATH . 'config_default.inc.php');

        unset($conf['simple_value']);

        $this
            ->variable($conf['simple_value'])
            ->isNull()
            ->boolean($conf['boolean_true'])
            ->isIdenticalTo(true)
            ->boolean($conf['boolean_false'])
            ->isIdenticalTo(false)
            ->array($conf['array'])
            ->isIdenticalTo(['one', 'two', 'three'])
            ->array($conf['hash'])
            ->isIdenticalTo(['key1' => 'value1', 'key2' => 'value2']);
    }

    public function testAddNewKey()
    {
        $controller = new \atoum\mock\controller();
        $controller->__construct = function () {
        };

        $conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $conf = new \Phyxo\Conf($conn);
        $conf->loadFromFile(TESTS_CONFIG_PATH . 'config_default.inc.php');

        $conf['new_key'] = 'new value';

        $this
            ->string($conf['new_key'])
            ->isIdenticalTo('new value')
            ->string($conf['simple_value'])
            ->isIdenticalTo('value')
            ->boolean($conf['boolean_true'])
            ->isIdenticalTo(true)
            ->boolean($conf['boolean_false'])
            ->isIdenticalTo(false)
            ->array($conf['array'])
            ->isIdenticalTo(['one', 'two', 'three'])
            ->array($conf['hash'])
            ->isIdenticalTo(['key1' => 'value1', 'key2' => 'value2']);
    }
}
