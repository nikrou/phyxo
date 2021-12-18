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

namespace App\Tests\Phyxo;

use App\Repository\ConfigRepository;
use PHPUnit\Framework\TestCase;
use Phyxo\Conf;
use Prophecy\PhpUnit\ProphecyTrait;

class ConfTest extends TestCase
{
    use ProphecyTrait;

    const TESTS_CONFIG_PATH = __DIR__ . '/../fixtures/config';

    public function testLoadFile(): void
    {
        $configRepository = $this->prophesize(ConfigRepository::class);
        $conf = new Conf($configRepository->reveal());
        $conf->loadFromFile(self::TESTS_CONFIG_PATH . '/config_default.inc.php');

        $this->assertEquals('value', $conf['simple_value']);
        $this->assertTrue($conf['boolean_true']);
        $this->assertFalse($conf['boolean_false']);
        $this->assertEquals($conf['array'], ['one', 'two', 'three']);
        $this->assertEquals($conf['hash'], ['key1' => 'value1', 'key2' => 'value2']);
    }

    public function testUpdateConfValue(): void
    {
        $conn = $this->prophesize(ConfigRepository::class);
        $conf = new Conf($conn->reveal());
        $conf->loadFromFile(self::TESTS_CONFIG_PATH . '/config_default.inc.php');

        $conf['simple_value'] = 'another value';

        $this->assertEquals('another value', $conf['simple_value']);
        $this->assertTrue($conf['boolean_true']);
        $this->assertFalse($conf['boolean_false']);
        $this->assertEquals(['one', 'two', 'three'], $conf['array']);
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $conf['hash']);
    }

    public function testDeleteConfParam(): void
    {
        $conn = $this->prophesize(ConfigRepository::class);
        $conf = new Conf($conn->reveal());
        $conf->loadFromFile(self::TESTS_CONFIG_PATH . '/config_default.inc.php');

        unset($conf['simple_value']);

        $this->assertNull($conf['simple_value']);
        $this->assertTrue($conf['boolean_true']);
        $this->assertFalse($conf['boolean_false']);
        $this->assertEquals(['one', 'two', 'three'], $conf['array']);
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $conf['hash']);
    }

    public function testDeleteMultipleConfParams(): void
    {
        $conn = $this->prophesize(ConfigRepository::class);
        $conf = new Conf($conn->reveal());
        $conf->loadFromFile(self::TESTS_CONFIG_PATH . '/config_default.inc.php');

        unset($conf['simple_value'], $conf['boolean_true']);

        $this->assertNull($conf['simple_value']);
        $this->assertNull($conf['boolean_true']);
        $this->assertFalse($conf['boolean_false']);
        $this->assertEquals(['one', 'two', 'three'], $conf['array']);
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $conf['hash']);
    }

    public function testAddNewKey(): void
    {
        $conn = $this->prophesize(ConfigRepository::class);
        $conf = new Conf($conn->reveal());
        $conf->loadFromFile(self::TESTS_CONFIG_PATH . '/config_default.inc.php');

        $conf['new_key'] = 'new value';

        $this->assertEquals('new value', $conf['new_key']);
        $this->assertEquals('value', $conf['simple_value']);
        $this->assertTrue($conf['boolean_true']);
        $this->assertFalse($conf['boolean_false']);
        $this->assertEquals(['one', 'two', 'three'], $conf['array']);
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $conf['hash']);
    }
}
