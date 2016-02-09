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
 * Simple wrapper around a "struct" (php array whose keys are not consecutive
 * integers starting at 0). Provides naming clues for xml output (what is xml
 * attributes and what is element)
 */
class NamedStruct
{
    public $_content;
    public $_xmlAttributes;

    /**
     * Constructs a named struct (usually returned by web service function
     * implementation)
     * @param name string - containing xml element name
     * @param content array - the actual content (php array)
     * @param xmlAttributes array - name of the keys in $content that will be
     *    encoded as xml attributes (if null - automatically prefer xml attributes
     *    whenever possible)
     */
    public function __construct($content, $xmlAttributes=null, $xmlElements=null) {
        $this->_content = $content;
        if (isset($xmlAttributes)) {
            $this->_xmlAttributes = array_flip($xmlAttributes);
        } else {
            $this->_xmlAttributes = array();
            foreach ($this->_content as $key => $value) {
                if (!empty($key) and (is_scalar($value) or is_null($value))) {
                    if (empty($xmlElements) or !in_array($key, $xmlElements)) {
                        $this->_xmlAttributes[$key]=1;
                    }
                }
            }
        }
    }
}
