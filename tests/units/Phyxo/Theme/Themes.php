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

namespace tests\units\Phyxo\Theme;

require_once __DIR__ . '/../../bootstrap.php';

use atoum;
use Phyxo\DBLayer\pgsqlConnection;

define('THEMES_TABLE', 'themes');

class Themes extends atoum
{
    private function getLocalThemes()
    {
        return array(
            'theme1' => array(
                'id' => 'theme1',
                'name' => 'A simple theme',
                'version' => '1.2.3',
                'extension' => 123,
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=123',
                'description' => 'Simple Number One',
                'author' => 'Nicolas',
                'author uri' => 'http://www.phyxo.net',
                'mobile' => false,
                'screenshot' => \Phyxo\Functions\URL::get_root_url() . 'admin/theme/images/missing_screenshot.png'
            ),
            'theme2' => array(
                'id' => 'theme2',
                'name' => 'ZZ Theme',
                'version' => '4.5.6',
                'extension' => 456,
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=456',
                'description' => 'Theme mobile without author uri',
                'author' => 'Arthur',
                'mobile' => true,
                'screenshot' => \Phyxo\Functions\URL::get_root_url() . 'admin/theme/images/missing_screenshot.png'
            ),
            'theme3' => array(
                'id' => 'theme3',
                'name' => 'My first theme',
                'version' => '7.8.9',
                'extension' => 789,
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=789',
                'description' => 'A simple description',
                'author' => 'Jean',
                'author uri' => 'http://www.phyxo.net',
                'mobile' => false,
                'screenshot' => \Phyxo\Functions\URL::get_root_url() . 'admin/theme/images/missing_screenshot.png'
            ),
            'theme4' => array(
                'id' => 'theme4',
                'name' => 'Photos Theme',
                'version' => '10.11.12',
                'extension' => 10,
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=10',
                'description' => 'Simple Number Four',
                'author' => 'Jean',
                'author uri' => 'http://www.phyxo.net',
                'mobile' => false,
                'screenshot' => \Phyxo\Functions\URL::get_root_url() . 'admin/theme/images/missing_screenshot.png'
            ),
        );
    }

    public function testFsThemes()
    {
        $controller = new \atoum\mock\controller();
        $controller->__construct = function () {
        };

        $conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $themes = new \Phyxo\Theme\Themes($conn);

        $this
            ->array($themes->getFsThemes())
            ->isEqualTo($this->getLocalThemes());
    }

    public function testSortThemes($sort_type, $order)
    {
        $controller = new \atoum\mock\controller();
        $controller->__construct = function () {
        };

        $conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $themes = new \Phyxo\Theme\Themes($conn);

        $themes->sortFsThemes($sort_type);

        $this
            ->array($themes->getFsThemes())
            ->isEqualTo($this->getLocalThemes())
            ->and()
            ->array($themes->getFsThemes())
            ->keys->isEqualTo($order);
    }

    protected function testSortThemesDataProvider()
    {
        return array(
            array('author', array('theme2', 'theme3', 'theme4', 'theme1')),
            array('id', array('theme1', 'theme2', 'theme3', 'theme4')),
            array('status', array('theme1', 'theme3', 'theme4', 'theme2')),
            array('name', array('theme1', 'theme3', 'theme4', 'theme2'))
        );
    }
}
