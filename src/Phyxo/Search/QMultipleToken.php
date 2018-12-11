<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

/** Represents an expression of several words or sub expressions to be searched.*/
class QMultipleToken
{
    var $is_single = false;
    var $modifier;
    var $tokens = []; // the actual array of QSingleToken or QMultiToken

    function __toString()
    {
        $s = '';
        for ($i = 0; $i < count($this->tokens); $i++) {
            $modifier = $this->tokens[$i]->modifier;
            if ($i) {
                $s .= ' ';
            }
            if ($modifier & QSearchScope::QST_OR) {
                $s .= 'OR ';
            }
            if ($modifier & QSearchScope::QST_NOT) {
                $s .= 'NOT ';
            }
            if (!$this->tokens[$i]->is_single) {
                $s .= '(';
                $s .= $this->tokens[$i];
                $s .= ')';
            } else {
                $s .= $this->tokens[$i];
            }
        }
        return $s;
    }

    private function push(&$token, &$modifier, &$scope)
    {
        if (strlen($token) || (isset($scope) && $scope->nullable)) {
            if (isset($scope)) {
                $modifier |= QSearchScope::QST_BREAK;
            }
            $this->tokens[] = new QSingleToken($token, $modifier, $scope);
        }
        $token = "";
        $modifier = 0;
        $scope = null;
    }

    /**
     * Parses the input query string by tokenizing the input, generating the modifiers (and/or/not/quotation/wildcards...).
     * Recursivity occurs when parsing ()
     * @param string $q the actual query to be parsed
     * @param int $qi the character index in $q where to start parsing
     * @param int $level the depth from root in the tree (number of opened and unclosed opening brackets)
     */
    protected function parse_expression($q, &$qi, $level, $root)
    {
        $crt_token = "";
        $crt_modifier = 0;
        $crt_scope = null;

        for ($stop = false; !$stop && $qi < strlen($q); $qi++) {
            $ch = $q[$qi];
            if (($crt_modifier & QSearchScope::QST_QUOTED) == 0) {
                switch ($ch) {
                    case '(':
                        if (strlen($crt_token)) {
                            $this->push($crt_token, $crt_modifier, $crt_scope);
                        }
                        $sub = new QMultipleToken();
                        $qi++;
                        $sub->parse_expression($q, $qi, $level + 1, $root);
                        $sub->modifier = $crt_modifier;
                        if (isset($crt_scope) && $crt_scope->is_text) {
                            $sub->apply_scope($crt_scope); // eg. 'tag:(John OR Bill)'
                        }
                        $this->tokens[] = $sub;
                        $crt_modifier = 0;
                        $crt_scope = null;
                        break;
                    case ')':
                        if ($level > 0) {
                            $stop = true;
                        }
                        break;
                    case ':':
                        $scope = @$root->scopes[strtolower($crt_token)];
                        if (!isset($scope) || isset($crt_scope)) { // white space
                            $this->push($crt_token, $crt_modifier, $crt_scope);
                        } else {
                            $crt_token = "";
                            $crt_scope = $scope;
                        }
                        break;
                    case '"':
                        if (strlen($crt_token)) {
                            $this->push($crt_token, $crt_modifier, $crt_scope);
                        }
                        $crt_modifier |= QSearchScope::QST_QUOTED;
                        break;
                    case '-':
                        if (strlen($crt_token) || isset($crt_scope)) {
                            $crt_token .= $ch;
                        } else {
                            $crt_modifier |= QSearchScope::QST_NOT;
                        }
                        break;
                    case '*':
                        if (strlen($crt_token)) {
                            $crt_token .= $ch; // wildcard end later
                        } else {
                            $crt_modifier |= QSearchScope::QST_WILDCARD_BEGIN;
                        }
                        break;
                    case '.':
                        if (isset($crt_scope) && !$crt_scope->is_text) {
                            $crt_token .= $ch;
                            break;
                        }
                        if (strlen($crt_token) && preg_match('/[0-9]/', substr($crt_token, -1))
                            && $qi + 1 < strlen($q) && preg_match('/[0-9]/', $q[$qi + 1])) { // dot between digits is not a separator e.g. F2.8
                            $crt_token .= $ch;
                            break;
                        }
                        // else white space go on..
                    default:
                        if (!$crt_scope || !$crt_scope->process_char($ch, $crt_token)) {
                            if (strpos(' ,.;!?', $ch) !== false) { // white space
                                $this->push($crt_token, $crt_modifier, $crt_scope);
                            } else {
                                $crt_token .= $ch;
                            }
                        }
                        break;
                }
            } else {// quoted
                if ($ch == '"') {
                    if ($qi + 1 < strlen($q) && $q[$qi + 1] == '*') {
                        $crt_modifier |= QSearchScope::QST_WILDCARD_END;
                        $qi++;
                    }
                    $this->push($crt_token, $crt_modifier, $crt_scope);
                } else {
                    $crt_token .= $ch;
                }
            }
        }

        $this->push($crt_token, $crt_modifier, $crt_scope);

        for ($i = 0; $i < count($this->tokens); $i++) {
            $token = $this->tokens[$i];
            $remove = false;
            if ($token->is_single) {
                if (($token->modifier & QSearchScope::QST_QUOTED) == 0 && substr($token->term, -1) == '*') {
                    $token->term = rtrim($token->term, '*');
                    $token->modifier |= QSearchScope::QST_WILDCARD_END;
                }

                if (!isset($token->scope) && ($token->modifier & (QSearchScope::QST_QUOTED | QSearchScope::QST_WILDCARD)) == 0) {
                    if ('not' == strtolower($token->term)) {
                        if ($i + 1 < count($this->tokens)) {
                            $this->tokens[$i + 1]->modifier |= QSearchScope::QST_NOT;
                        }
                        $token->term = "";
                    }
                    if ('or' == strtolower($token->term)) {
                        if ($i + 1 < count($this->tokens)) {
                            $this->tokens[$i + 1]->modifier |= QSearchScope::QST_OR;
                        }
                        $token->term = "";
                    }
                    if ('and' == strtolower($token->term)) {
                        $token->term = "";
                    }
                }

                if (!strlen($token->term) && (!isset($token->scope) || !$token->scope->nullable)) {
                    $remove = true;
                }

                if (isset($token->scope) && !$token->scope->parse($token)) {
                    $remove = true;
                }
            } elseif (!count($token->tokens)) {
                $remove = true;
            }
            if ($remove) {
                array_splice($this->tokens, $i, 1);
                if ($i < count($this->tokens) && $this->tokens[$i]->is_single) {
                    $this->tokens[$i]->modifier |= QSearchScope::QST_BREAK;
                }
                $i--;
            }
        }

        if ($level > 0 && count($this->tokens) && $this->tokens[0]->is_single) {
            $this->tokens[0]->modifier |= QSearchScope::QST_BREAK;
        }
    }

    /**
     * Applies recursively a search scope to all sub single tokens. We allow 'tag:(John Bill)' but we cannot evaluate
     * scopes on expressions so we rewrite as '(tag:John tag:Bill)'
     */
    private function apply_scope(QSearchScope $scope)
    {
        for ($i = 0; $i < count($this->tokens); $i++) {
            if ($this->tokens[$i]->is_single) {
                if (!isset($this->tokens[$i]->scope)) {
                    $this->tokens[$i]->scope = $scope;
                }
            } else {
                $this->tokens[$i]->apply_scope($scope);
            }
        }
    }

    private static function priority($modifier)
    {
        return $modifier & QSearchScope::QST_OR ? 0 : 1;
    }

    // because evaluations occur left to right, we ensure that 'a OR b c d' is interpreted as 'a OR (b c d)'
    protected function check_operator_priority()
    {
        for ($i = 0; $i < count($this->tokens); $i++) {
            if (!$this->tokens[$i]->is_single) {
                $this->tokens[$i]->check_operator_priority();
            }
            if ($i == 1) {
                $crt_prio = self::priority($this->tokens[$i]->modifier);
            }
            if ($i <= 1) {
                continue;
            }
            $prio = self::priority($this->tokens[$i]->modifier);
            if ($prio > $crt_prio) { // e.g. 'a OR b c d' i=2, operator(c)=AND -> prio(AND) > prio(OR) = operator(b)
                $term_count = 2; // at least b and c to be regrouped
                for ($j = $i + 1; $j < count($this->tokens); $j++) {
                    if (self::priority($this->tokens[$j]->modifier) >= $prio) {
                        $term_count++; // also take d
                    } else {
                        break;
                    }
                }

                $i--; // move pointer to b
                // crate sub expression (b c d)
                $sub = new QMultipleToken();
                $sub->tokens = array_splice($this->tokens, $i, $term_count);

                // rewrite ourseleves as a (b c d)
                array_splice($this->tokens, $i, 0, [$sub]);
                $sub->modifier = $sub->tokens[0]->modifier & QSearchScope::QST_OR;
                $sub->tokens[0]->modifier &= ~QSearchScope::QST_OR;

                $sub->check_operator_priority();
            } else {
                $crt_prio = $prio;
            }
        }
    }
}
