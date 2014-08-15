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

namespace Phyxo\Plugin;

/**
 * Used to declare maintenance methods of a plugin.
 */
class PluginMaintain
{
    /** @var string $plugin_id */
    protected $plugin_id;

    /**
     * @param string $id
     */
    public function __construct($id) {
        $this->plugin_id = $id;
    }

    /**
     * @param string $plugin_version
     * @param array &$errors - used to return error messages
     */
    public function install($plugin_version, &$errors=array()) {}

    /**
     * @param string $plugin_version
     * @param array &$errors - used to return error messages
     */
    public function activate($plugin_version, &$errors=array()) {}

    public function deactivate() {}

    public function uninstall() {}

    /**
     * @param string $old_version
     * @param string $new_version
     * @param array &$errors - used to return error messages
     */
    public function update($old_version, $new_version, &$errors=array()) {}
}