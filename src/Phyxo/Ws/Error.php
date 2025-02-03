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
    private readonly string $codeText;

    public function __construct($code, string $codeText)
    {
        if ($code >= 400 && $code < 600) {
            $this->setStatusHeader($code, $codeText);
        }

        $this->code = $code;
        $this->codeText = $codeText;
    }

    public function code()
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->codeText;
    }

    /**
     * Sets the http status header (200,401,...)
     */
    private function setStatusHeader(int $code, string $text = ''): void
    {
        if ($text === '' || $text === '0') {
            switch ($code) {
                case 200:
                    $text = 'OK';
                    break;
                case 301:
                    $text = 'Moved permanently';
                    break;
                case 302:
                    $text = 'Moved temporarily';
                    break;
                case 304:
                    $text = 'Not modified';
                    break;
                case 400:
                    $text = 'Bad request';
                    break;
                case 401:
                    $text = 'Authorization required';
                    break;
                case 403:
                    $text = 'Forbidden';
                    break;
                case 404:
                    $text = 'Not found';
                    break;
                case 500:
                    $text = 'Server error';
                    break;
                case 501:
                    $text = 'Not implemented';
                    break;
                case 503:
                    $text = 'Service unavailable';
                    break;
            }
        }

        $protocol = $_SERVER["SERVER_PROTOCOL"];
        if (('HTTP/1.1' != $protocol) && ('HTTP/1.0' != $protocol)) {
            $protocol = 'HTTP/1.0';
        }

        header(sprintf('%s %d %s', $protocol, $code, $text), true, $code);
    }
}
