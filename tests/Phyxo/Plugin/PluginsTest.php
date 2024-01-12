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

namespace App\Tests\Phyxo\Plugin;

use App\DataMapper\UserMapper;
use App\Repository\PluginRepository;
use PHPUnit\Framework\TestCase;
use Phyxo\Plugin\Plugins;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Filesystem\Filesystem;

class PluginsTest extends TestCase
{
    use ProphecyTrait;

    const PLUGINS_PATH = __DIR__ . '/../../fixtures/plugins';
    const PLUGINS_DIR = __DIR__ . '/../../tmp/plugins';
    const PLUGINS_ZIP_PATH = __DIR__ . '/../../fixtures/zip';

    public function setUp(): void
    {
        $fs = new Filesystem();
        $fs->mkdir(self::PLUGINS_DIR);
    }

    public function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove(self::PLUGINS_DIR);
    }

    private function mirrorToWorkspace(): string
    {
        $workspace = self::PLUGINS_DIR . '/' . md5(random_bytes(15));
        $fs = new Filesystem();
        $fs->mkdir($workspace);
        $fs->mirror(self::PLUGINS_PATH, $workspace);

        return $workspace;
    }

    public function testFsPlugins()
    {
        $workspace = $this->mirrorToWorkspace();

        $userMapper = $this->prophesize(UserMapper::class);
        $userMapper->getUser()->willReturn(new class {
            function getLanguage()
            {
                return 'en_GB';
            }
        });

        $pluginRepository = $this->prophesize(PluginRepository::class);
        $plugins = new Plugins($pluginRepository->reveal(), $userMapper->reveal());
        $plugins->setRootPath($workspace);

        $this->assertEquals($this->getLocalPlugins(), $plugins->getFsPlugins());
    }

    /**
     * @dataProvider sortPluginsDataProvider
     */
    public function testSortPlugins(string $sort_type, array $order)
    {
        $workspace = $this->mirrorToWorkspace();

        $userMapper = $this->prophesize(UserMapper::class);
        $userMapper->getUser()->willReturn(new class {
            function getLanguage()
            {
                return 'en_GB';
            }
        });

        $pluginRepository = $this->prophesize(PluginRepository::class);
        $plugins = new Plugins($pluginRepository->reveal(), $userMapper->reveal());
        $plugins->setRootPath($workspace);

        $plugins->sortFsPlugins($sort_type);

        $this->assertEquals($this->getLocalPlugins(), $plugins->getFsPlugins());
        $this->assertEquals($order, array_keys($plugins->getFsPlugins()));
    }

    public function testExtractPluginWithEmptyOrInvalidArchive()
    {
        $workspace = $this->mirrorToWorkspace();

        $userMapper = $this->prophesize(UserMapper::class);

        $userMapper->getUser()->willReturn(new class {
            function getLanguage()
            {
                return 'en_GB';
            }
        });

        $pluginRepository = $this->prophesize(PluginRepository::class);
        $plugins = new Plugins($pluginRepository->reveal(), $userMapper->reveal());
        $plugins->setRootPath($workspace);

        $plugin_id = 'myPlugin';
        $this->expectExceptionMessage("Cannot download plugin file");
        $plugins->extractPluginFiles('install', 10);
    }

    public function _testExtractPlugin()
    {
        $workspace = $this->mirrorToWorkspace();

        $userMapper = $this->prophesize(UserMapper::class);
        $userMapper->getUser()->willReturn(new class {
            function getLanguage()
            {
                return 'en_GB';
            }
        });

        $pluginRepository = $this->prophesize(PluginRepository::class);
        $plugins = new Plugins($pluginRepository->reveal(), $userMapper->reveal());
        $plugins->setExtensionsURL('http://localhost');
        $plugins->setRootPath($workspace);
        $plugins->download(Argument::any(), Argument::any())->will(function ($i, $j) {
            // copy archive in right place
            copy(self::PLUGINS_ZIP_PATH . '/myPlugin1-0.1.0.zip', 'my');
        });

        $plugin_id = 'myPlugin1';
        $plugins->extractPluginFiles('install', 10);
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
        $this->assertEquals(array_merge($myPlugin1, $this->getLocalPlugins()), $plugins->getFsPlugins());
    }

    // @TODO : need to update that test using Prophecy
    public function _testExtractPluginWithUpdate()
    {
        $workspace = $this->mirrorToWorkspace();

        $userMapper = $this->prophesize(UserMapper::class);
        $userMapper->getUser()->willReturn(new class {
            function getLanguage()
            {
                return 'en_GB';
            }
        });

        $pluginRepository = $this->prophesize(PluginRepository::class);
        $plugins = new Plugins($pluginRepository->reveal(), $userMapper->reveal());
        $plugins->setRootPath($workspace);

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

    public static function sortPluginsDataProvider()
    {
        return [
            ['author', ['Plugin2', 'Plugin3', 'Plugin4', 'plugin_lowercase', 'Plugin1']],
            ['id', ['Plugin1', 'Plugin2', 'Plugin3', 'Plugin4', 'plugin_lowercase']],
            ['status', ['Plugin1', 'plugin_lowercase', 'Plugin3', 'Plugin4', 'Plugin2']],
            ['name', ['Plugin1', 'plugin_lowercase', 'Plugin3', 'Plugin4', 'Plugin2']]
        ];
    }

    private function getLocalPlugins()
    {
        return [
            'Plugin1' => [
                'name' => 'A simple plugin',
                'version' => '0.1.0',
                'uri' => 'https://ext.phyxo.net/extension_view.php?eid=10',
                'description' => 'My first plugin',
                'author' => 'Nicolas',
                'author_uri' => 'https://www.nikrou.net/',
                'extension' => '10'
            ],
            'Plugin2' => [
                'name' => 'ZZ Plugin',
                'version' => '1.0.0',
                'uri' => 'https://ext.phyxo.net/extension_view.php?eid=20',
                'description' => 'My second plugin',
                'author' => 'Arthur',
                'extension' => '20'
            ],
            'Plugin3' => [
                'name' => 'My Plugin',
                'version' => '2.1.0',
                'uri' => 'https://ext.phyxo.net/extension_view.php?eid=30',
                'description' => 'Fake description replace by ones in description.txt file',
                'author' => 'Jean',
                'author_uri' => 'https://www.phyxo.net/',
                'extension' => '30'
            ],
            'Plugin4' => [
                'name' => 'Photos Plugin',
                'version' => '3.1.3',
                'uri' => 'https://ext.phyxo.net/extension_view.php?eid=40',
                'description' => 'The best plugin',
                'author' => 'Jean',
                'author_uri' => 'https://www.phyxo.net/',
                'extension' => '40'
            ],
            'plugin_lowercase' => [
                'name' => 'Awesome plugin',
                'version' => '0.4.0',
                'uri' => 'https://ext.phyxo.net/extension_view.php?eid=50',
                'description' => 'Another plugin',
                'author' => 'Momo',
                'author_uri' => 'https://www.momo.net/',
                'extension' => '50'
            ],
        ];
    }
}
