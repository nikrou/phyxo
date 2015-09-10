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

namespace tests\units\Phyxo\Theme;

require_once __DIR__ . '/../../bootstrap.php';

use atoum;
use Phyxo\DBLayer\pgsqlConnection;

define('THEMES_TABLE', 'themes');

class Themes extends atoum
{
    private function getLocalThemes() {
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
                'screenshot' => \get_root_url().'admin/themes/default/images/missing_screenshot.png'
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
                'screenshot' => \get_root_url().'admin/themes/default/images/missing_screenshot.png'
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
                'screenshot' => \get_root_url().'admin/themes/default/images/missing_screenshot.png'
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
                'screenshot' => \get_root_url().'admin/themes/default/images/missing_screenshot.png'
            ),
        );
    }

    public function testFsThemes() {
        $controller = new \atoum\mock\controller();
		$controller->__construct = function() {};

		$conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $themes = new \Phyxo\Theme\Themes($conn);

        $this
            ->array($themes->fs_themes)
            ->isEqualTo($this->getLocalThemes());
    }

    public function testSortThemes($sort_type, $order) {
        $controller = new \atoum\mock\controller();
		$controller->__construct = function() {};

		$conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $controller);
        $themes = new \Phyxo\Theme\Themes($conn);

        $themes->sort_fs_themes($sort_type);

        $this
            ->array($themes->fs_themes)
            ->isEqualTo($this->getLocalThemes())
            ->and()
            ->array($themes->fs_themes)
            ->keys->isEqualTo($order);
    }

    protected function testSortThemesDataProvider() {
        return array(
            array('author', array('theme2', 'theme3', 'theme4', 'theme1')),
            array('id', array('theme1', 'theme2', 'theme3', 'theme4')),
            array('status', array('theme1', 'theme3', 'theme4', 'theme2')),
            array('name', array('theme1', 'theme3', 'theme4', 'theme2'))
        );
    }
}
