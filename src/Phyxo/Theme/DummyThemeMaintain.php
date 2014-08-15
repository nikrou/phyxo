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

namespace Phyxo\Theme;

use Phyxo\Theme\ThemeMaintain;

class DummyThemeMaintain extends ThemeMaintain
{
    public function activate($theme_version, &$errors=array()) {
        if (is_callable('theme_activate')) {
            return theme_activate($this->theme_id, $theme_version, $errors);
        }
    }

    public function deactivate() {
        if (is_callable('theme_deactivate')) {
            return theme_deactivate($this->theme_id);
        }
    }

    public function delete() {
        if (is_callable('theme_delete')) {
            return theme_delete($this->theme_id);
        }
    }
}
