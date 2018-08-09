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

use Phyxo\Functions\Plugin;

class Server
{
    private $_requestHandler;
    private $_requestFormat;
    private $_responseEncoder;
    public $_responseFormat;

    private $_methods = array();

    const WS_PARAM_ACCEPT_ARRAY = 0x010000;
    const WS_PARAM_FORCE_ARRAY = 0x030000;
    const WS_PARAM_OPTIONAL = 0x040000;

    const WS_TYPE_BOOL = 0x01;
    const WS_TYPE_INT = 0x02;
    const WS_TYPE_FLOAT = 0x04;
    const WS_TYPE_POSITIVE = 0x10;
    const WS_TYPE_NOTNULL = 0x20;
    const WS_TYPE_ID = self::WS_TYPE_INT | self::WS_TYPE_POSITIVE | self::WS_TYPE_NOTNULL;

    const WS_ERR_INVALID_METHOD = 501;
    const WS_ERR_MISSING_PARAM = 1002;
    const WS_ERR_INVALID_PARAM = 1003;

    const WS_XML_ATTRIBUTES = 'attributes_xml_';

    public function __construct()
    {
    }

    /**
     *  Initializes the request handler.
     */

    public function setHandler($requestFormat, &$requestHandler)
    {
        $this->_requestHandler = &$requestHandler;
        $this->_requestFormat = $requestFormat;
    }

    /**
     *  Initializes the request handler.
     */
    public function setEncoder($responseFormat, &$encoder)
    {
        $this->_responseEncoder = &$encoder;
        $this->_responseFormat = $responseFormat;
    }

    /**
     * Runs the web service call (handler and response encoder should have been
     * created)
     */
    public function run()
    {
        if (is_null($this->_responseEncoder)) {
            set_status_header(400);
            @header("Content-Type: text/plain");
            echo ("Cannot process your request. Unknown response format. Request format: " . @$this->_requestFormat . " Response format: " . @$this->_responseFormat . "\n");
            var_export($this);
            die(0);
        }

        if (is_null($this->_requestHandler)) {
            $this->sendResponse(new Error(400, 'Unknown request format'));
            return;
        }

        // add reflection methods
        $this->addMethod(
            'reflection.getMethodList',
            array('Phyxo\Ws\Server', 'ws_getMethodList')
        );
        $this->addMethod(
            'reflection.getMethodDetails',
            array('Phyxo\Ws\Server', 'ws_getMethodDetails'),
            array('methodName')
        );

        Plugin::trigger_notify('ws_add_methods', array(&$this));
        uksort($this->_methods, 'strnatcmp');
        $this->_requestHandler->handleRequest($this);
    }

    /**
     * Encodes a response and sends it back to the browser.
     */
    public function sendResponse($response)
    {
        $encodedResponse = $this->_responseEncoder->encodeResponse($response);
        $contentType = $this->_responseEncoder->getContentType();

        @header('Content-Type: ' . $contentType . '; charset=' . get_pwg_charset());
        print_r($encodedResponse);
        Plugin::trigger_notify('sendResponse', $encodedResponse);
    }

    /**
     * Registers a web service method.
     * @param methodName string - the name of the method as seen externally
     * @param callback mixed - php method to be invoked internally
     * @param params array - map of allowed parameter names with options
     *    @option mixed default (optional)
     *    @option int flags (optional)
     *      possible values: WS_PARAM_ALLOW_ARRAY, WS_PARAM_FORCE_ARRAY, WS_PARAM_OPTIONAL
     *    @option int type (optional)
     *      possible values: WS_TYPE_BOOL, WS_TYPE_INT, WS_TYPE_FLOAT, WS_TYPE_ID
     *                       WS_TYPE_POSITIVE, WS_TYPE_NOTNULL
     *    @option int|float maxValue (optional)
     * @param description string - a description of the method.
     * @param include_file string - a file to be included befaore the callback is executed
     * @param options array
     *    @option bool hidden (optional) - if true, this method won't be visible by reflection.getMethodList
     *    @option bool admin_only (optional)
     *    @option bool post_only (optional)
     */
    public function addMethod($methodName, $callback, $params = array(), $description = '', $include_file = '', $options = array())
    {
        if (!is_array($params)) {
            $params = array();
        }

        if (range(0, count($params) - 1) === array_keys($params)) {
            $params = array_flip($params);
        }

        foreach ($params as $param => $data) {
            if (!is_array($data)) {
                $params[$param] = array('flags' => 0, 'type' => 0);
            } else {
                if (!isset($data['flags'])) {
                    $data['flags'] = 0;
                }
                if (array_key_exists('default', $data)) {
                    $data['flags'] |= self::WS_PARAM_OPTIONAL;
                }
                if (!isset($data['type'])) {
                    $data['type'] = 0;
                }
                $params[$param] = $data;
            }
        }

        $this->_methods[$methodName] = array(
            'callback' => $callback,
            'description' => $description,
            'signature' => $params,
            'include' => $include_file,
            'options' => $options,
        );
    }

    public function hasMethod($methodName)
    {
        return isset($this->_methods[$methodName]);
    }

    public function getMethodDescription($methodName)
    {
        $desc = @$this->_methods[$methodName]['description'];
        return isset($desc) ? $desc : '';
    }

    public function getMethodSignature($methodName)
    {
        $signature = @$this->_methods[$methodName]['signature'];
        return isset($signature) ? $signature : array();
    }

    function getMethodOptions($methodName)
    {
        $options = @$this->_methods[$methodName]['options'];
        return isset($options) ? $options : array();
    }

    public static function isPost()
    {
        return isset($HTTP_RAW_POST_DATA) or !empty($_POST); // @TODO: remove $HTTP_RAW_POST_DATA
    }

    public static function makeArrayParam(&$param)
    {
        if ($param == null) {
            $param = array();
        } else {
            if (!is_array($param)) {
                $param = array($param);
            }
        }
    }

    public static function checkType(&$param, $type, $name)
    {
        $opts = array();
        $msg = '';
        if (self::hasFlag($type, self::WS_TYPE_POSITIVE | self::WS_TYPE_NOTNULL)) {
            $opts['options']['min_range'] = 1;
            $msg = ' positive and not null';
        } elseif (self::hasFlag($type, self::WS_TYPE_POSITIVE)) {
            $opts['options']['min_range'] = 0;
            $msg = ' positive';
        }

        if (is_array($param)) {
            if (self::hasFlag($type, self::WS_TYPE_BOOL)) {
                foreach ($param as &$value) {
                    if (($value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) === null) {
                        return new Error(self::WS_ERR_INVALID_PARAM, $name . ' must only contain booleans');
                    }
                }
                unset($value);
            } elseif (self::hasFlag($type, self::WS_TYPE_INT)) {
                foreach ($param as &$value) {
                    if (($value = filter_var($value, FILTER_VALIDATE_INT, $opts)) === false) {
                        return new Error(self::WS_ERR_INVALID_PARAM, $name . ' must only contain' . $msg . ' integers');
                    }
                }
                unset($value);
            } elseif (self::hasFlag($type, self::WS_TYPE_FLOAT)) {
                foreach ($param as &$value) {
                    if (($value = filter_var($value, FILTER_VALIDATE_FLOAT)) === false
                        or (isset($opts['options']['min_range']) and $value < $opts['options']['min_range'])) {
                        return new Error(self::WS_ERR_INVALID_PARAM, $name . ' must only contain' . $msg . ' floats');
                    }
                }
                unset($value);
            }
        } elseif ($param !== '') {
            if (self::hasFlag($type, self::WS_TYPE_BOOL)) {
                if (($param = filter_var($param, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) === null) {
                    return new Error(self::WS_ERR_INVALID_PARAM, $name . ' must be a boolean');
                }
            } elseif (self::hasFlag($type, self::WS_TYPE_INT)) {
                if (($param = filter_var($param, FILTER_VALIDATE_INT, $opts)) === false) {
                    return new Error(self::WS_ERR_INVALID_PARAM, $name . ' must be an' . $msg . ' integer');
                }
            } elseif (self::hasFlag($type, self::WS_TYPE_FLOAT)) {
                if (($param = filter_var($param, FILTER_VALIDATE_FLOAT)) === false
                    or (isset($opts['options']['min_range']) and $param < $opts['options']['min_range'])) {
                    return new Error(self::WS_ERR_INVALID_PARAM, $name . ' must be a' . $msg . ' float');
                }
            }
        }

        return null;
    }

    public static function hasFlag($val, $flag)
    {
        return ($val & $flag) == $flag;
    }

    /**
     *  Invokes a registered method. Returns the return of the method (or
     *  a Error object if the method is not found)
     *  @param methodName string the name of the method to invoke
     *  @param params array array of parameters to pass to the invoked method
     */
    public function invoke($methodName, $params)
    {
        global $services;

        $method = @$this->_methods[$methodName];

        if ($method == null) {
            return new Error(self::WS_ERR_INVALID_METHOD, 'Method name is not valid');
        }

        if (isset($method['options']['post_only']) and $method['options']['post_only'] and !self::isPost()) {
            return new Error(405, 'This method requires HTTP POST');
        }

        if (isset($method['options']['admin_only']) and $method['options']['admin_only'] and !$services['users']->isAdmin()) {
            return new Error(401, 'Access denied');
        }

        // parameter check and data correction
        $signature = $method['signature'];
        $missing_params = array();

        foreach ($signature as $name => $options) {
            $flags = $options['flags'];

            // parameter not provided in the request
            if (!array_key_exists($name, $params)) {
                if (!self::hasFlag($flags, self::WS_PARAM_OPTIONAL)) {
                    $missing_params[] = $name;
                } elseif (array_key_exists('default', $options)) {
                    $params[$name] = $options['default'];
                    if (self::hasFlag($flags, self::WS_PARAM_FORCE_ARRAY)) {
                        self::makeArrayParam($params[$name]);
                    }
                }
            } elseif ($params[$name] === '' and !self::hasFlag($flags, self::WS_PARAM_OPTIONAL)) { // parameter provided but empty
                $missing_params[] = $name;
            } else { // parameter provided - do some basic checks
                $the_param = $params[$name];
                if (is_array($the_param) and !self::hasFlag($flags, self::WS_PARAM_ACCEPT_ARRAY)) {
                    return new Error(self::WS_ERR_INVALID_PARAM, $name . ' must be scalar');
                }

                if (self::hasFlag($flags, self::WS_PARAM_FORCE_ARRAY)) {
                    self::makeArrayParam($the_param);
                }

                if ($options['type'] > 0) {
                    if (($ret = self::checkType($the_param, $options['type'], $name)) !== null) {
                        return $ret;
                    }
                }

                if (isset($options['maxValue']) and $the_param > $options['maxValue']) {
                    $the_param = $options['maxValue'];
                }

                $params[$name] = $the_param;
            }
        }

        if (count($missing_params)) {
            return new Error(self::WS_ERR_MISSING_PARAM, 'Missing parameters: ' . implode(',', $missing_params));
        }

        $result = Plugin::trigger_change('ws_invoke_allowed', true, $methodName, $params);

        if (@get_class($result) != 'Phyxo\Ws\Error') {
            if (!empty($method['include'])) {
                include_once($method['include']);
            }
            $result = call_user_func_array($method['callback'], array($params, &$this));
        }

        return $result;
    }

    /**
     * WS reflection method implementation: lists all available methods
     */
    public static function ws_getMethodList($params, &$service)
    {
        $methods = array_filter(
            $service->_methods,
            function ($m) {
                return empty($m['options']['hidden']) || !$m['options']['hidden'];
            }
        );
        return array('methods' => new NamedArray(array_keys($methods), 'method'));
    }

    /**
     * WS reflection method implementation: gets information about a given method
     */
    public static function ws_getMethodDetails($params, &$service)
    {
        $methodName = $params['methodName'];

        if (!$service->hasMethod($methodName)) {
            return new Error(self::WS_ERR_INVALID_PARAM, 'Requested method does not exist');
        }

        $res = array(
            'name' => $methodName,
            'description' => $service->getMethodDescription($methodName),
            'params' => array(),
            'options' => $service->getMethodOptions($methodName),
        );

        foreach ($service->getMethodSignature($methodName) as $name => $options) {
            $param_data = array(
                'name' => $name,
                'optional' => self::hasFlag($options['flags'], self::WS_PARAM_OPTIONAL),
                'acceptArray' => self::hasFlag($options['flags'], self::WS_PARAM_ACCEPT_ARRAY),
                'type' => 'mixed',
            );

            if (isset($options['default'])) {
                $param_data['defaultValue'] = $options['default'];
            }
            if (isset($options['maxValue'])) {
                $param_data['maxValue'] = $options['maxValue'];
            }
            if (isset($options['info'])) {
                $param_data['info'] = $options['info'];
            }

            if (self::hasFlag($options['type'], self::WS_TYPE_BOOL)) {
                $param_data['type'] = 'bool';
            } elseif (self::hasFlag($options['type'], self::WS_TYPE_INT)) {
                $param_data['type'] = 'int';
            } elseif (self::hasFlag($options['type'], self::WS_TYPE_FLOAT)) {
                $param_data['type'] = 'float';
            }
            if (self::hasFlag($options['type'], self::WS_TYPE_POSITIVE)) {
                $param_data['type'] .= ' positive';
            }
            if (self::hasFlag($options['type'], self::WS_TYPE_NOTNULL)) {
                $param_data['type'] .= ' notnull';
            }

            $res['params'][] = $param_data;
        }
        return $res;
    }
}
