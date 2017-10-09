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

/**
 * Analyzes and splits the quick/query search query $q into tokens.
 * q='john bill' => 2 tokens 'john' 'bill'
 * Special characters for MySql full text search (+,<,>,~) appear in the token modifiers.
 * The query can contain a phrase: 'Pierre "New York"' will return 'pierre' qnd 'new york'.
 *
 * @param string $q
 */

/** Represents a single word or quoted phrase to be searched.*/
class QSingleToken
{
    var $is_single = true;
    var $modifier;
    var $term; /* the actual word/phrase string*/
    var $variants = array();
    var $scope;

    var $scope_data;
    var $idx;

    function __construct($term, $modifier, $scope) {
        $this->term = $term;
        $this->modifier = $modifier;
        $this->scope = $scope;
    }

    function __toString() {
        $s = '';
        if (isset($this->scope)) {
            $s .= $this->scope->id .':';
        }
        if ($this->modifier & QSearchScope::QST_WILDCARD_BEGIN) {
            $s .= '*';
        }
        if ($this->modifier & QSearchScope::QST_QUOTED) {
            $s .= '"';
        }
        $s .= $this->term;
        if ($this->modifier & QSearchScope::QST_QUOTED) {
            $s .= '"';
        }
        if ($this->modifier & QSearchScope::QST_WILDCARD_END) {
            $s .= '*';
        }
        return $s;
    }
}
