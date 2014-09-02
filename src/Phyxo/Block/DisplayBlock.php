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

namespace Phyxo\Block;

/**
 * Represents a menu block ready for display in the BlockManager object.
 */
class DisplayBlock
{
    /** @var RegisteredBlock */
    protected $_registeredBlock;
    /** @var int */
    protected $_position;
    /** @var string */
    protected $_title;

    /** @var mixed */
    public $data;
    /** @var string */
    public $template;
    /** @var string */
    public $raw_content;

    /**
     * @param RegisteredBlock $block
     */
    public function __construct($block) {
        $this->_registeredBlock = $block;
    }

    /**
     * @return RegisteredBlock
     */
    public function get_block() {
        return $this->_registeredBlock;
    }

    /**
     * @return int
     */
    public function get_position() {
        return $this->_position;
    }

    /**
     * @param int $position
     */
    public function set_position($position) {
        $this->_position = $position;
    }

    /**
     * @return string
     */
    public function get_title() {
        if (isset($this->_title)) {
            return $this->_title;
        } else {
            return $this->_registeredBlock->get_name();
        }
    }

    /**
     * @param string
     */
    public function set_title($title) {
        $this->_title = $title;
    }
}
