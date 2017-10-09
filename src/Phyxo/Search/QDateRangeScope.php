<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2017 Nicolas Roudaire        https://www.phyxo.net/ |
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

namespace Phyxo\Search;

use Phyxo\Search\QSearchScope;

class QDateRangeScope extends QSearchScope
{
    function __construct($id, $aliases, $nullable=false) {
        parent::__construct($id, $aliases, $nullable, false);
    }

    function parse($token) {
        $str = $token->term;
        $strict = array(0,0);
        if (($pos = strpos($str, '..')) !== false) {
            $range = array( substr($str,0,$pos), substr($str, $pos+2));
        } elseif ('>' == @$str[0]) {
            $range = array( substr($str,1), '');
            $strict[0] = 1;
        } elseif ('<' == @$str[0]) {
            $range = array('', substr($str,1));
            $strict[1] = 1;
        } elseif ($token->modifier & self::QST_WILDCARD_BEGIN) {
            $range = array('', $str);
        } elseif ($token->modifier & self::QST_WILDCARD_END) {
            $range = array($str, '');
        } else {
            $range = array($str, $str);
        }

        foreach ($range as $i => &$val) {
            if (preg_match('/([0-9]{4})-?((?:1[0-2])|(?:0?[1-9]))?-?((?:(?:[1-3][0-9])|(?:0?[1-9])))?/', $val, $matches)) {
                array_shift($matches);
                if (!isset($matches[1])) {
                    $matches[1] = ($i ^ $strict[$i]) ? 12 : 1;
                }
                if (!isset($matches[2])) {
                    $matches[2] = ($i ^ $strict[$i]) ? 31 : 1;
                }
                $val = implode('-', $matches);
                if ($i ^ $strict[$i]) {
                    $val .= ' 23:59:59';
                }
            } elseif (strlen($val)) {
                return false;
            }
        }

        if (!$this->nullable && $range[0]=='' && $range[1] == '') {
            return false;
        }

        $token->scope_data = $range;
        return true;
    }

    function get_sql($field, $token) {
        $clauses = array();
        if ($token->scope_data[0]!='') {
            $clauses[] = $field.' >= \'' . $token->scope_data[0].'\'';
        }
        if ($token->scope_data[1]!='') {
            $clauses[] = $field.' <= \'' . $token->scope_data[1].'\'';
        }

        if (empty($clauses)) {
            if ($token->modifier & self::QST_WILDCARD) {
                return $field.' IS NOT NULL';
            } else {
                return $field.' IS NULL';
            }
        }
        return '('.implode(' AND ', $clauses).')';
    }
}
