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

/**
 *
 * Base class for web service response encoder.
 */
abstract class ResponseEncoder
{
    /** encodes the web service response to the appropriate output format
     * @param response mixed the unencoded result of a service method call
     */
    public abstract function encodeResponse($response);

    /** default "Content-Type" http header for this kind of response format
     */
    public abstract function getContentType();

    /**
     * returns true if the parameter is a 'struct' (php array type whose keys are
     * NOT consecutive integers starting with 0)
     */
    public static function is_struct(&$data) {
        if (is_array($data)) {
            if (range(0, count($data) - 1) !== array_keys($data)) {
                # string keys, unordered, non-incremental keys, .. - whatever, make object
                return true;
            }
        }

        return false;
    }

    /**
     * removes all XML formatting from $response (named array, named structs, etc)
     * usually called by every response encoder, except rest xml.
     */
    public static function flattenResponse(&$value) {
        self::flatten($value);
    }

    private static function flatten(&$value) {
        if (is_object($value)) {
            $class = @get_class($value);
            if ($class == 'Phyxo\Ws\NamedArray') {
                $value = $value->_content;
            }
            if ($class == 'Phyxo\Ws\NamedStruct') {
                $value = $value->_content;
            }
        }

        if (!is_array($value)) {
            return;
        }

        if (self::is_struct($value)) {
            if (isset($value[WS_XML_ATTRIBUTES])) {
                $value = array_merge($value, $value[WS_XML_ATTRIBUTES]);
                unset( $value[WS_XML_ATTRIBUTES] );
            }
        }

        foreach ($value as $key => &$v) {
            self::flatten($v);
        }
    }
}
