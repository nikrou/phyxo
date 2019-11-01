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

namespace Phyxo\Functions;

class HTTP
{
    /**
     * Exits the current script with 500 code.
     * @todo nice display if $template loaded
     *
     * @param string $msg
     * @param string|null $title
     * @param bool $show_trace
     */
    public static function fatal_error($msg, $title = null, $show_trace = true)
    {
        if (empty($title)) {
            $title = \Phyxo\Functions\Language::l10n('Phyxo encountered a non recoverable error');
        }

        $btrace_msg = '';
        if ($show_trace and function_exists('debug_backtrace')) {
            $bt = debug_backtrace();
            for ($i = 1; $i < count($bt); $i++) {
                $class = isset($bt[$i]['class']) ? (@$bt[$i]['class'] . '::') : '';
                $btrace_msg .= "#$i\t" . $class . @$bt[$i]['function'] . ' ' . @$bt[$i]['file'] . "(" . @$bt[$i]['line'] . ")\n";
            }
            $btrace_msg = trim($btrace_msg);
            $msg .= "\n";
        }

        $display = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        $display .= '<h1>' . $title . '</h1>';
        $display .= '<pre style="font-size:larger;background:white;color:red;padding:1em;margin:0;clear:both;display:block;width:auto;height:auto;overflow:auto">';
        $display .= '<b>' . $msg . '</b>';
        $display .= $btrace_msg;
        $display .= "</pre>\n";

        @self::set_status_header(500);
        echo $display . str_repeat(' ', 300); //IE6 doesn't error output if below a size

        if (function_exists('ini_set')) { // if possible turn off error display (we display it)
            ini_set('display_errors', false);
        }
        error_reporting(E_ALL);
        trigger_error(strip_tags($msg) . $btrace_msg, E_USER_ERROR);
        die(0); // just in case
    }

    /**
     * Sets the http status header (200,401,...)
     * @param int $code
     * @param string $text for exotic http codes
     */
    public static function set_status_header($code, $text = '')
    {
        if (empty($text)) {
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

        header("$protocol $code $text", true, $code);
        \Phyxo\Functions\Plugin::trigger_notify('set_status_header', $code, $text);
    }
}
