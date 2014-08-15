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

use Phyxo\Plugin\PluginMaintain;

/**
 * class DummyPluginMaintain
 * used when a plugin uses the old procedural declaration of maintenance methods
 */
class DummyPluginMaintain extends PluginMaintain
{
    public function install($plugin_version, &$errors=array()) {
        if (is_callable('plugin_install')) {
            return plugin_install($this->plugin_id, $plugin_version, $errors);
        }
    }

    public function activate($plugin_version, &$errors=array()) {
        if (is_callable('plugin_activate')) {
            return plugin_activate($this->plugin_id, $plugin_version, $errors);
        }
    }

    public function deactivate() {
        if (is_callable('plugin_deactivate')) {
            return plugin_deactivate($this->plugin_id);
        }
    }

    public function uninstall() {
        if (is_callable('plugin_uninstall')) {
            return plugin_uninstall($this->plugin_id);
        }
    }

    public function update($old_version, $new_version, &$errors=array()) {}
}
