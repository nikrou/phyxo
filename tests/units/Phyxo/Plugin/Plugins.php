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

namespace tests\units\Phyxo\Plugin;

require_once __DIR__ . '/../../bootstrap.php';

use atoum;
use Symfony\Component\Filesystem\Filesystem;

class Plugins extends atoum
{
    private $plugins_path = __DIR__ . '/../../fixtures/plugins';
    protected  $plugins_dir = PHPWG_TMP_PATH . '/plugins';

    public function setUp()
    {
        $fs = new Filesystem();
        $fs->mkdir($this->plugins_dir);
    }

    public function tearDown()
    {
        $fs = new Filesystem();
        $fs->remove($this->plugins_dir);
     }

    private function mirrorToWorkspace(): string
    {
        $workspace = $this->plugins_dir . '/' . md5(random_bytes(15));
        $fs = new Filesystem();
        $fs->mkdir($workspace);
        $fs->mirror($this->plugins_path, $workspace);

        return $workspace;
    }

    public function testFsPlugins()
    {
        $controller = new \atoum\mock\controller();
        $controller->__construct = function () {
        };

        $workspace = $this->mirrorToWorkspace();

        $conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $plugins = new \mock\Phyxo\Plugin\Plugins($conn);
        $plugins->setPluginsRootPath($workspace);

        $this
            ->array($plugins->getFsPlugins())
            ->isEqualTo($this->getLocalPlugins());
    }

    public function testSortPlugins($sort_type, $order)
    {
        $controller = new \atoum\mock\controller();
        $controller->__construct = function () {
        };

        $workspace = $this->mirrorToWorkspace();

        $conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $plugins = new \mock\Phyxo\Plugin\Plugins($conn);
        $plugins->setPluginsRootPath($workspace);

        $plugins->sortFsPlugins($sort_type);

        $this
            ->array($plugins->getFsPlugins())
            ->isEqualTo($this->getLocalPlugins())
            ->and()
            ->array($plugins->getFsPlugins())
            ->keys->isEqualTo($order);
    }

    public function testExtractPluginWithEmptyOrInvalidArchive()
    {
        $controller = new \atoum\mock\controller();
        $controller->__construct = function () {
        };

        $workspace = $this->mirrorToWorkspace();

        $conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $plugins = new \mock\Phyxo\Plugin\Plugins($conn);
        $plugins->setPluginsRootPath($workspace);

        $this->calling($plugins)->download = function () {
            // copy archive in right place
        };

        $plugin_id = 'myPlugin';
        $this->exception(
            function () use ($plugins, $plugin_id) {
                $plugins->extractPluginFiles('install', 10, 'myPlugin', $plugin_id);
            }
        )
            ->hasMessage("Can't read or extract archive.");
    }

    public function testExtractPlugin()
    {
        $controller = new \atoum\mock\controller();
        $controller->__construct = function () {
        };

        $workspace = $this->mirrorToWorkspace();

        $conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $plugins = new \mock\Phyxo\Plugin\Plugins($conn);
        $plugins->setPluginsRootPath($workspace);

        $this->calling($plugins)->download = function ($get_data, $archive) {
            // copy archive in right place
            copy(PHPWG_ZIP_PATH . '/myPlugin1-0.1.0.zip', $archive);
        };

        $plugin_id = 'myPlugin1';
        $plugins->extractPluginFiles('install', 10, 'myPlugin1', $plugin_id);
        $plugins->getFsPlugin($plugin_id); // refresh plugins list

        $myPlugin1 = [
            'myPlugin1' => [
                'name' => 'myPlugin1',
                'version' => '0.1.0',
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=1',
                'description' => 'My first plugin',
                'author' => 'Nicolas',
                'author uri' => 'http://www.phyxo.net/',
                'extension' => '1'
            ]
        ];
        // tests
        $plugins->sortFsPlugins('id');
        $this
            ->array($plugins->getFsPlugins())
            ->isEqualTo(array_merge($myPlugin1, $this->getLocalPlugins()));
    }

    public function testExtractPluginWithUpdate()
    {
        $controller = new \atoum\mock\controller();
        $controller->__construct = function () {
        };

        $workspace = $this->mirrorToWorkspace();

        $conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $plugins = new \mock\Phyxo\Plugin\Plugins($conn);
        $plugins->setPluginsRootPath($workspace);

        $this->calling($plugins)->download = function ($get_data, $archive) {
            // copy archive in right place
            copy(PHPWG_ZIP_PATH . '/myPlugin1-0.1.0.zip', $archive);
        };

        $plugin_id = 'myPlugin1';
        $plugins->extractPluginFiles('install', 10, 'myPlugin1', $plugin_id);
        $plugins->getFsPlugin($plugin_id); // refresh plugins list

        $new_plugin = [
            'myPlugin1' => [
                'name' => 'myPlugin1',
                'version' => '0.1.0',
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=1',
                'description' => 'My first plugin',
                'author' => 'Nicolas',
                'author uri' => 'http://www.phyxo.net/',
                'extension' => '1'
            ]
        ];
        // tests
        $plugins->sortFsPlugins('id');
        $this
            ->array($plugins->getFsPlugins())
            ->isEqualTo(array_merge($new_plugin, $this->getLocalPlugins()));

        $this->calling($plugins)->download = function ($get_data, $archive) {
            // copy archive in right place
            copy(PHPWG_ZIP_PATH . '/myPlugin1-0.2.0.zip', $archive);
        };

        $plugin_id = 'myPlugin1';
        $plugins->extractPluginFiles('upgrade', 10, 'myPlugin1', $plugin_id);
        $plugins->getFsPlugin($plugin_id); // refresh plugins list

        $plugins->sortFsPlugins('id');
        $new_plugin['myPlugin1']['version'] = '0.2.0';

        $this
            ->array($plugins->getFsPlugins())
            ->isEqualTo(array_merge($new_plugin, $this->getLocalPlugins()));
    }

    protected function testSortPluginsDataProvider()
    {
        return [
            ['author', ['plugin2', 'plugin3', 'plugin4', 'plugin1']],
            ['id', ['plugin1', 'plugin2', 'plugin3', 'plugin4']],
            ['status', ['plugin1', 'plugin3', 'plugin4', 'plugin2']],
            ['name', ['plugin1', 'plugin3', 'plugin4', 'plugin2']]
        ];
    }

    private function getLocalPlugins()
    {
        return [
            'plugin1' => [
                'name' => 'A simple plugin',
                'version' => '0.1.0',
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=10',
                'description' => 'My first plugin',
                'author' => 'Nicolas',
                'author uri' => 'http://www.phyxo.net/',
                'extension' => '10'
            ],
            'plugin2' => [
                'name' => 'ZZ Plugin',
                'version' => '1.0.0',
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=20',
                'description' => 'My second plugin',
                'author' => 'Arthur',
                'extension' => '20'
            ],
            'plugin3' => [
                'name' => 'My Plugin',
                'version' => '2.1.0',
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=30',
                'description' => 'A simple description',
                'author' => 'Jean',
                'author uri' => 'http://www.phyxo.net/',
                'extension' => '30'
            ],
            'plugin4' => [
                'name' => 'Photos Plugin',
                'version' => '3.1.3',
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=40',
                'description' => 'The best plugin',
                'author' => 'Jean',
                'author uri' => 'http://www.phyxo.net/',
                'extension' => '40'
            ],

        ];
    }
}
