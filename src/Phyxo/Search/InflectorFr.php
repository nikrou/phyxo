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

namespace Phyxo\Search;

class InflectorFr
{
    private $exceptions;
    private $pluralizers;
    private $singularizers;

    public function __construct()
    {
        $tmp = array(
            'monsieur' => 'messieurs',
            'madame' => 'mesdames',
            'mademoiselle' => 'mesdemoiselles',
        );

        $this->exceptions = $tmp;
        foreach ($tmp as $k => $v) {
            $this->exceptions[$v] = $k;
        }

        $this->pluralizers = array_reverse(
            array(
                '/$/' => 's',
                '/(bijou|caillou|chou|genou|hibou|joujou|pou|au|eu|eau)$/' => '\1x',
                '/(bleu|émeu|landau|lieu|pneu|sarrau)$/' => '\1s',
                '/al$/' => 'aux',
                '/ail$/' => 'ails',
                '/(b|cor|ém|gemm|soupir|trav|vant|vitr)ail$/' => '\1aux',
                '/(s|x|z)$/' => '\1',
            )
        );

        $this->singularizers = array_reverse(
            array(
                '/s$/' => '',
                '/(bijou|caillou|chou|genou|hibou|joujou|pou|au|eu|eau)x$/' => '\1',
                '/(journ|chev)aux$/' => '\1al',
                '/ails$/' => 'ail',
                '/(b|cor|�m|gemm|soupir|trav|vant|vitr)aux$/' => '\1ail',
            )
        );
    }

    public function get_variants($word)
    {
        $res = array();

        $word = strtolower($word);

        $rc = @$this->exceptions[$word];
        if (isset($rc)) {
            if (!empty($rc)) {
                $res[] = $rc;
            }
            return $res;
        }

        foreach ($this->pluralizers as $rule => $replacement) {
            $rc = preg_replace($rule, $replacement, $word, -1, $count);
            if ($count) {
                $res[] = $rc;
                break;
            }
        }

        foreach ($this->singularizers as $rule => $replacement) {
            $rc = preg_replace($rule, $replacement, $word, -1, $count);
            if ($count) {
                $res[] = $rc;
                break;
            }
        }

        return $res;
    }
}
