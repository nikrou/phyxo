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

/**
 * A search scope applies to a single token and restricts the search to a subset of searchable fields.
 */
class QSearchScope
{
    const QST_QUOTED = 0x01;
    const QST_NOT = 0x02;
    const QST_OR = 0x04;
    const QST_WILDCARD_BEGIN = 0x08;
    const QST_WILDCARD_END = 0x10;
    const QST_WILDCARD = self::QST_WILDCARD_BEGIN|self::QST_WILDCARD_END;
    const QST_BREAK = 0x20;

    var $id;
    var $aliases;
    var $is_text;
    var $nullable;

    function __construct($id, $aliases, $nullable=false, $is_text=true) {
        $this->id = $id;
        $this->aliases = $aliases;
        $this->is_text = $is_text;
        $this->nullable =$nullable;
    }

    function parse($token) {
        if (!$this->nullable && 0==strlen($token->term)) {
            return false;
        }

        return true;
    }

    function process_char(&$ch, &$crt_token) {
        return false;
    }
}
