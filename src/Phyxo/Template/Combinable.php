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

namespace Phyxo\Template;

class Combinable
{
    /** @var string */
    public $id;
    /** @var string */
    public $path;
    /** @var string */
    public $version;
    /** @var bool */
    public $is_template;

    /**
     * @param string $id
     * @param string $path
     * @param string $version
     */
    public function __construct($id, $path, $version=0) {
        $this->id = $id;
        $this->set_path($path);
        $this->version = $version;
        $this->is_template = false;
    }

    /**
     * @param string $path
     */
    public function set_path($path) {
        if (!empty($path)) {
            $this->path = $path;
        }
    }

    /**
     * @return bool
     */
    public function is_remote() {
        return url_is_remote($this->path) || strncmp($this->path, '//', 2)==0;
    }
}
