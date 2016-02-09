<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2016 Nicolas Roudaire         http://www.phyxo.net/ |
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

namespace Phyxo\Ws;

/**
 * Simple wrapper around an array (keys are consecutive integers starting at 0).
 * Provides naming clues for xml output (xml attributes vs. xml child elements?)
 * Usually returned by web service function implementation.
 */
class NamedArray
{
    public $_content;
    public $_itemName;
    public $_xmlAttributes;

  /**
   * Constructs a named array
   * @param arr array (keys must be consecutive integers starting at 0)
   * @param itemName string xml element name for values of arr (e.g. image)
   * @param xmlAttributes array of sub-item attributes that will be encoded as
   *      xml attributes instead of xml child elements
   */
    public function __construct($arr, $itemName, $xmlAttributes=array()) {
        $this->_content = $arr;
        $this->_itemName = $itemName;
        $this->_xmlAttributes = array_flip($xmlAttributes);
    }
}
