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

use Phyxo\Template\Combinable;

final class Script extends Combinable
{
    /** @var int 0,1,2 */
    public $load_mode;
    /** @var array */
    public $precedents;
    /** @var array */
    public $extra;

    /**
     * @param int 0,1,2
     * @param string $id
     * @param string $path
     * @param string $version
     * @param array $precedents
     */
    public function __construct($load_mode, $id, $path, $version=0, $precedents=array()) {
        parent::__construct($id, $path, $version);
        $this->load_mode = $load_mode;
        $this->precedents = $precedents;
        $this->extra = array();
    }
}
