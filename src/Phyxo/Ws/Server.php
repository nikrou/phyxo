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
use Phyxo\Ws\Error;
use App\DataMapper\TagMapper;
use App\DataMapper\CommentMapper;
use App\DataMapper\CategoryMapper;
use App\DataMapper\ImageMapper;
use App\DataMapper\UserMapper;
use Phyxo\Conf;
use Phyxo\DBLayer\iDBLayer;
use Symfony\Component\Routing\RouterInterface;
use App\DataMapper\RateMapper;
use Phyxo\EntityManager;
use Phyxo\Image\ImageStandardParams;
use App\DataMapper\SearchMapper;
use App\Utils\UserManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Security;

class Server
{
    private $upload_dir, $tagMapper, $commentMapper, $userMapper, $categoryMapper, $rateMapper, $searchMapper, $imageMapper, $phyxoVersion, $conn,
            $em, $conf, $router, $image_std_params, $userManager, $passwordEncoder, $pem_url, $security, $params;

    private $_requestHandler;
    private $_requestFormat;
    private $_responseEncoder;
    private $_responseFormat = 'json';

    private $_methods = [];

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

    public function __construct(string $upload_dir = '.')
    {
        $this->upload_dir = $upload_dir;
    }

    public function setParams(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function setExtensionsURL(string $url)
    {
        $this->pem_url = $url;
    }

    public function getExtensionsURL()
    {
        return $this->pem_url;
    }

    public function getUploadDir()
    {
        return $this->upload_dir;
    }

    public function addTagMapper(TagMapper $tagMapper)
    {
        $this->tagMapper = $tagMapper;
    }

    public function getTagMapper()
    {
        return $this->tagMapper;
    }

    public function addCommentMapper(CommentMapper $commentMapper)
    {
        $this->commentMapper = $commentMapper;
    }

    public function getCommentMapper()
    {
        return $this->commentMapper;
    }

    public function addUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
    }

    public function getUserMapper()
    {
        return $this->userMapper;
    }

    public function addImageMapper(ImageMapper $imageMapper)
    {
        $this->imageMapper = $imageMapper;
    }

    public function getImageMapper()
    {
        return $this->imageMapper;
    }

    public function addCategoryMapper(CategoryMapper $categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
    }

    public function getCategoryMapper()
    {
        return $this->categoryMapper;
    }

    public function addRateMapper(RateMapper $rateMapper)
    {
        $this->rateMapper = $rateMapper;
    }

    public function getRateMapper()
    {
        return $this->rateMapper;
    }

    public function addSearchMapper(SearchMapper $searchMapper)
    {
        $this->searchMapper = $searchMapper;
    }

    public function getSearchMapper(): SearchMapper
    {
        return $this->searchMapper;
    }

    public function setCoreVersion(string $phyxoVersion)
    {
        $this->phyxoVersion = $phyxoVersion;
    }

    public function getCoreVersion()
    {
        return $this->phyxoVersion;
    }

    public function setConf(Conf $conf)
    {
        $this->conf = $conf;
    }

    public function getConf()
    {
        return $this->conf;
    }

    public function setConnection(iDBLayer $conn)
    {
        $this->conn = $conn;
    }

    public function getConnection()
    {
        return $this->conn;
    }

    public function setEntityManager(EntityManager $em)
    {
        $this->em = $em;
    }

    public function getEntityManager()
    {
        return $this->em;
    }

    public function setImageStandardParams(ImageStandardParams $image_std_params)
    {
        $this->image_std_params = $image_std_params;
    }

    public function getImageStandardParams()
    {
        return $this->image_std_params;
    }

    public function setRouter(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function getRouter()
    {
        return $this->router;
    }

    public function setUserManager(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    public function getUserManager()
    {
        return $this->userManager;
    }

    public function setPasswordEncoder(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function getPasswordEncoder()
    {
        return $this->passwordEncoder;
    }

    public function setSecurity(Security $security)
    {
        $this->security = $security;
    }

    public function getSecurity()
    {
        return $this->security;
    }

    /**
     *  Initializes the request handler.
     */
    public function setHandler($requestHandler)
    {
        $this->_requestHandler = $requestHandler;
    }

    /**
     *  Initializes the request handler.
     */
    public function setEncoder($encoder)
    {
        $this->_responseEncoder = $encoder;
    }

    /**
     * Runs the web service call (handler and response encoder should have been
     * created)
     */
    public function run()
    {
        if (is_null($this->_responseEncoder)) {
            \Phyxo\Functions\HTTP::set_status_header(400);
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
            ['Phyxo\Ws\Server', 'getMethodList']
        );
        $this->addMethod(
            'reflection.getMethodDetails',
            ['Phyxo\Ws\Server', 'getMethodDetails'],
            ['methodName']
        );

        Plugin::trigger_notify('ws_add_methods', [&$this]);
        uksort($this->_methods, 'strnatcmp');

        return $this->_requestHandler->handleRequest($this);
    }

    /**
     * Encodes a response and sends it back to the browser.
     */
    public function sendResponse($response)
    {
        $encodedResponse = $this->_responseEncoder->encodeResponse($response);
        Plugin::trigger_notify('sendResponse', $encodedResponse);

        return $encodedResponse;
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
     * @param options array
     *    @option bool hidden (optional) - if true, this method won't be visible by reflection.getMethodList
     *    @option bool admin_only (optional)
     *    @option bool post_only (optional)
     */
    public function addMethod($methodName, $callback, $params = [], $description = '', $options = [])
    {
        if (!is_array($params)) {
            $params = [];
        }

        if (range(0, count($params) - 1) === array_keys($params)) {
            $params = array_flip($params);
        }

        foreach ($params as $param => $data) {
            if (!is_array($data)) {
                $params[$param] = ['flags' => 0, 'type' => 0];
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

        $this->_methods[$methodName] = [
            'callback' => $callback,
            'description' => $description,
            'signature' => $params,
            'options' => $options,
        ];
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
        return isset($signature) ? $signature : [];
    }

    function getMethodOptions($methodName)
    {
        $options = @$this->_methods[$methodName]['options'];
        return isset($options) ? $options : [];
    }

    public static function isPost()
    {
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? trim($_SERVER['CONTENT_TYPE']) : '';
        if ($contentType === "application/json") {
            //Receive the RAW post data.
            $content = trim(file_get_contents("php://input"));

            return !empty($content);
        } else {
            return !empty($_POST);
        }
    }

    public static function makeArrayParam(&$param)
    {
        if ($param == null) {
            $param = [];
        } else {
            if (!is_array($param)) {
                $param = [$param];
            }
        }
    }

    public static function checkType(&$param, $type, $name)
    {
        $opts = [];
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
        $method = @$this->_methods[$methodName];

        if ($method == null) {
            return new Error(self::WS_ERR_INVALID_METHOD, 'Method name is not valid');
        }

        if (isset($method['options']['post_only']) && $method['options']['post_only'] && !self::isPost()) {
            return new Error(405, 'This method requires HTTP POST');
        }

        if (isset($method['options']['admin_only']) && $method['options']['admin_only'] && !$this->userMapper->isAdmin()) {
            return new Error(401, 'Access denied');
        }

        // parameter check and data correction
        $signature = $method['signature'];
        $missing_params = [];

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

        if ($result = $this->isInvokeAllowed($methodName, $params)) {
            $result = call_user_func_array($method['callback'], [$params, &$this]);
        }

        // $result = Plugin::trigger_change('ws_invoke_allowed', true, $methodName, $params);
        // if ((is_bool($result) && $result === true) || get_class($result) != 'Phyxo\Ws\Error') {
        // }

        return $result;
    }

    /**
     * Event handler for method invocation security check. Should return a Phyxo\Ws\Error
     * if the preconditions are not satifsied for method invocation.
     */
    public function isInvokeAllowed(string $methodName, array $params = []): bool
    {
        if (strpos($methodName, 'reflection.') === 0) { // OK for reflection
            return true;
        }

        if (!$this->userMapper->isClassicUser()) {
            return false;
        }

        return true;
    }

    /**
     * WS reflection method implementation: lists all available methods
     */
    public static function getMethodList($params, &$service)
    {
        $methods = array_filter(
            $service->_methods,
            function ($m) {
                return empty($m['options']['hidden']) || !$m['options']['hidden'];
            }
        );
        return ['methods' => new NamedArray(array_keys($methods), 'method')];
    }

    /**
     * WS reflection method implementation: gets information about a given method
     */
    public static function getMethodDetails($params, &$service)
    {
        $methodName = $params['methodName'];

        if (!$service->hasMethod($methodName)) {
            return new Error(self::WS_ERR_INVALID_PARAM, 'Requested method does not exist');
        }

        $res = [
            'name' => $methodName,
            'description' => $service->getMethodDescription($methodName),
            'params' => [],
            'options' => $service->getMethodOptions($methodName),
        ];

        foreach ($service->getMethodSignature($methodName) as $name => $options) {
            $param_data = [
                'name' => $name,
                'optional' => self::hasFlag($options['flags'], self::WS_PARAM_OPTIONAL),
                'acceptArray' => self::hasFlag($options['flags'], self::WS_PARAM_ACCEPT_ARRAY),
                'type' => 'mixed',
            ];

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
