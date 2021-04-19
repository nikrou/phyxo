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

namespace Phyxo\Ws\Protocols;

use Phyxo\Ws\Server;

/**
 *
 * Base class for web service response encoder.
 */
abstract class ResponseEncoder
{
    /** encodes the web service response to the appropriate output format
     * response mixed the unencoded result of a service method call
     */
    public abstract function encodeResponse($response);

    /** default "Content-Type" http header for this kind of response format
     */
    public abstract function getContentType();

    /**
     * returns true if the parameter is a 'struct' (php array type whose keys are
     * NOT consecutive integers starting with 0)
     */
    public static function is_struct(&$data)
    {
        if (is_array($data)) {
            if (range(0, count($data) - 1) !== array_keys($data)) {
                // string keys, unordered, non-incremental keys, .. - whatever, make object
                return true;
            }
        }

        return false;
    }

    /**
     * removes all XML formatting from $response (named array, named structs, etc)
     * usually called by every response encoder, except rest xml.
     */
    public static function flattenResponse(&$value)
    {
        self::flatten($value);
    }

    private static function flatten(&$value)
    {
        if (!is_array($value)) {
            return;
        }

        if (self::is_struct($value)) {
            if (isset($value[Server::WS_XML_ATTRIBUTES])) {
                $value = array_merge($value, $value[Server::WS_XML_ATTRIBUTES]);
                unset($value[Server::WS_XML_ATTRIBUTES]);
            }
        }

        foreach ($value as $key => &$v) {
            self::flatten($v);
        }
    }
}
