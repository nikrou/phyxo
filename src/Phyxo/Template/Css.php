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

namespace Phyxo\Template;

use Phyxo\Template\Combinable;

final class Css extends Combinable
{
    /** @var int */
    public $order;

    /**
     * @param string $id
     * @param string $path
     * @param string $version
     * @param int $order
     */
    public function __construct($id, $path, $version=0, $order=0) {
        parent::__construct($id, $path, $version);
        $this->order = $order;
    }
}
