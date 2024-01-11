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

namespace Phyxo\Ws;

use Phyxo\Functions\HTTP;
class Error
{
    private $code;
    private $codeText;

    public function __construct($code, $codeText)
    {
        if ($code >= 400 && $code < 600) {
            HTTP::set_status_header($code, $codeText);
        }

        $this->code = $code;
        $this->codeText = $codeText;
    }

    public function code()
    {
        return $this->code;
    }

    public function message()
    {
        return $this->codeText;
    }
}
