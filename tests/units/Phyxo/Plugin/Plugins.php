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

namespace tests\units\Phyxo\Plugin;

require_once __DIR__ . '/../../bootstrap.php';

use atoum;
use Phyxo\DBLayer\pgsqlConnection;

define('PLUGINS_TABLE', 'plugins');

class Plugins extends atoum
{
    private function getLocalPlugins() {
        return array(
            'plugin1' => array(
                'name' => 'A simple plugin',
                'version' => '0.1.0',
                'uri' => 'http://phyxo.nikrou.net/ext/extension_view.php?eid=1',
                'description' => 'My first plugin',
                'author' => 'Nicolas',
                'author uri' => 'http://www.nikrou.net/',
                'extension' => '1'
            ),
            'plugin2' => array(
                'name' => 'ZZ Plugin',
                'version' => '1.0.0',
                'uri' => 'http://phyxo.nikrou.net/ext/extension_view.php?eid=2',
                'description' => 'My second plugin',
                'author' => 'Arthur',
                'extension' => '2'
            ),
            'plugin3' => array(
                'name' => 'My Plugin',
                'version' => '2.1.0',
                'uri' => 'http://phyxo.nikrou.net/ext/extension_view.php?eid=90',
                'description' => 'A simple description',
                'author' => 'Jean',
                'author uri' => 'http://www.nikrou.net/',
                'extension' => '90'
            ),
            'plugin4' => array(
                'name' => 'Photos Plugin',
                'version' => '3.1.3',
                'uri' => 'http://phyxo.nikrou.net/ext/extension_view.php?eid=44',
                'description' => 'The best plugin',
                'author' => 'Jean',
                'author uri' => 'http://www.nikrou.net/',
                'extension' => '44'
            ),

        );
    }

    public function testFsPlugins() {
        $controller = new \atoum\mock\controller();
		$controller->__construct = function() {};

		$conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $plugins = new \Phyxo\Plugin\Plugins($conn);

        $this
            ->array($plugins->fs_plugins)
            ->isEqualTo($this->getLocalPlugins());
    }

    public function testSortPlugins($sort_type, $order) {
        $controller = new \atoum\mock\controller();
		$controller->__construct = function() {};

		$conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $plugins = new \Phyxo\Plugin\Plugins($conn);

        $plugins->sort_fs_plugins($sort_type);

        $this
            ->array($plugins->fs_plugins)
            ->isEqualTo($this->getLocalPlugins())
            ->and()
            ->array($plugins->fs_plugins)
            ->keys->isEqualTo($order);
    }

    protected function testSortPluginsDataProvider() {
        return array(
            array('author', array('plugin2', 'plugin3', 'plugin4', 'plugin1')),
            array('id', array('plugin1', 'plugin2', 'plugin3', 'plugin4')),
            array('status', array('plugin1', 'plugin3', 'plugin4', 'plugin2')),
            array('name', array('plugin1', 'plugin3', 'plugin4', 'plugin2'))
        );
    }
}