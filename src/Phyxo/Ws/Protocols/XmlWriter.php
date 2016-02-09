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

namespace Phyxo\Ws\Protocols;

class XmlWriter
{
    private $_indent, $_indentStr, $_elementStack, $_lastTagOpen;
    private $_indentLevel, $_encodedXml;

    public function __construct() {
        $this->_elementStack = array();
        $this->_lastTagOpen = false;
        $this->_indentLevel = 0;

        $this->_encodedXml = '';
        $this->_indent = true;
        $this->_indentStr = "\t";
    }

    public function &getOutput() {
        return $this->_encodedXml;
    }


    public function start_element($name) {
        $this->_end_prev(false);
        if (!empty($this->_elementStack)) {
            $this->_eol_indent();
        }
        $this->_indentLevel++;
        $this->_indent();
        $diff = ord($name[0])-ord('0');
        if ($diff>=0 && $diff<=9) {
            $name='_'.$name;
        }
        $this->_output( '<'.$name );
        $this->_lastTagOpen = true;
        $this->_elementStack[] = $name;
    }

    public function end_element($x) {
        $close_tag = $this->_end_prev(true);
        $name = array_pop( $this->_elementStack );
        if ($close_tag) {
            $this->_indentLevel--;
            $this->_indent();
            $this->_output('</'.$name.">");
        }
    }

    public function write_content($value) {
        $this->_end_prev(false);
        $value = (string)$value;
        $this->_output(htmlspecialchars($value));
    }

    public function write_cdata($value) {
        $this->_end_prev(false);
        $value = (string)$value;
        $this->_output(
            '<![CDATA['
            . str_replace(']]>', ']]&gt;', $value)
            . ']]>' );
    }

    public function write_attribute($name, $value) {
        $this->_output(' '.$name.'="'.$this->encode_attribute($value).'"');
    }

    public function encode_attribute($value) {
        return htmlspecialchars((string)$value);
    }

    private function _end_prev($done) {
        $ret = true;
        if ($this->_lastTagOpen) {
            if ($done) {
                $this->_indentLevel--;
                $this->_output(' />');
                $ret = false;
            } else {
                $this->_output('>');
            }
            $this->_lastTagOpen = false;
        }

        return $ret;
    }

    private function _eol_indent() {
        if ($this->_indent) {
            $this->_output("\n");
        }
    }

    private function _indent() {
        if ($this->_indent && $this->_indentLevel > count($this->_elementStack)) {
            $this->_output(
                str_repeat( $this->_indentStr, count($this->_elementStack) )
            );
        }
    }

    private function _output($raw_content) {
        $this->_encodedXml .= $raw_content;
    }
}
