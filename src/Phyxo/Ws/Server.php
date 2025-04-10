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

use App\DataMapper\AlbumMapper;
use App\DataMapper\CommentMapper;
use App\DataMapper\ImageMapper;
use App\DataMapper\RateMapper;
use App\DataMapper\SearchMapper;
use App\DataMapper\TagMapper;
use App\DataMapper\UserMapper;
use App\Security\AppUserService;
use App\Security\UserProvider;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Imagine\Image\ImagineInterface;
use Phyxo\Conf;
use Phyxo\Image\ImageStandardParams;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;

class Server
{
    private ?TagMapper $tagMapper = null;
    private ?CommentMapper $commentMapper = null;
    private AppUserService $appUserService;
    private ?UserMapper $userMapper = null;
    private ?AlbumMapper $albumMapper = null;
    private ?RateMapper $rateMapper = null;
    private SearchMapper $searchMapper;
    private ?ImageMapper $imageMapper = null;
    private ?string $phyxoVersion = null;
    private ?Conf $conf = null;
    private ?RouterInterface $router = null;
    private ?ImageStandardParams $image_std_params = null;
    private ?UserPasswordHasherInterface $passwordHasher = null;
    private ?string $pem_url = null;
    private ?Security $security = null;
    private ?ParameterBagInterface $params = null;
    private Request $request;
    private ManagerRegistry $managerRegistry;
    private ImagineInterface $imageLibrary;
    private UserProvider $userProvider;
    private array $_methods = [];
    final public const int WS_PARAM_ACCEPT_ARRAY = 0x010000;
    final public const int WS_PARAM_FORCE_ARRAY = 0x030000;
    final public const int WS_PARAM_OPTIONAL = 0x040000;
    final public const int WS_TYPE_BOOL = 0x01;
    final public const int WS_TYPE_INT = 0x02;
    final public const int WS_TYPE_FLOAT = 0x04;
    final public const int WS_TYPE_POSITIVE = 0x10;
    final public const int WS_TYPE_NOTNULL = 0x20;
    final public const WS_TYPE_ID = self::WS_TYPE_INT | self::WS_TYPE_POSITIVE | self::WS_TYPE_NOTNULL;
    final public const int WS_ERR_INVALID_METHOD = 501;
    final public const int WS_ERR_MISSING_PARAM = 1002;
    final public const int WS_ERR_INVALID_PARAM = 1003;
    final public const string WS_XML_ATTRIBUTES = 'attributes_xml_';

    public function __construct(private readonly string $upload_dir = '.')
    {
    }

    public function setParams(ParameterBagInterface $params): void
    {
        $this->params = $params;
    }

    public function getParams(): ?ParameterBagInterface
    {
        return $this->params;
    }

    public function setExtensionsURL(string $url): void
    {
        $this->pem_url = $url;
    }

    public function getExtensionsURL(): ?string
    {
        return $this->pem_url;
    }

    public function getUploadDir(): string
    {
        return $this->upload_dir;
    }

    public function addTagMapper(TagMapper $tagMapper): void
    {
        $this->tagMapper = $tagMapper;
    }

    public function getTagMapper(): ?TagMapper
    {
        return $this->tagMapper;
    }

    public function addCommentMapper(CommentMapper $commentMapper): void
    {
        $this->commentMapper = $commentMapper;
    }

    public function getCommentMapper(): ?CommentMapper
    {
        return $this->commentMapper;
    }

    public function addAppUserService(AppUserService $appUserService): void
    {
        $this->appUserService = $appUserService;
    }

    public function getAppUserService(): AppUserService
    {
        return $this->appUserService;
    }

    public function addUserMapper(UserMapper $userMapper): void
    {
        $this->userMapper = $userMapper;
    }

    public function getUserMapper(): ?UserMapper
    {
        return $this->userMapper;
    }

    public function addImageMapper(ImageMapper $imageMapper): void
    {
        $this->imageMapper = $imageMapper;
    }

    public function getImageMapper(): ?ImageMapper
    {
        return $this->imageMapper;
    }

    public function addAlbumMapper(AlbumMapper $albumMapper): void
    {
        $this->albumMapper = $albumMapper;
    }

    public function getAlbumMapper(): ?AlbumMapper
    {
        return $this->albumMapper;
    }

    public function addRateMapper(RateMapper $rateMapper): void
    {
        $this->rateMapper = $rateMapper;
    }

    public function getRateMapper(): ?RateMapper
    {
        return $this->rateMapper;
    }

    public function addSearchMapper(SearchMapper $searchMapper): void
    {
        $this->searchMapper = $searchMapper;
    }

    public function getSearchMapper(): SearchMapper
    {
        return $this->searchMapper;
    }

    public function setCoreVersion(string $phyxoVersion): void
    {
        $this->phyxoVersion = $phyxoVersion;
    }

    public function getCoreVersion(): ?string
    {
        return $this->phyxoVersion;
    }

    public function setConf(Conf $conf): void
    {
        $this->conf = $conf;
    }

    public function getConf(): ?Conf
    {
        return $this->conf;
    }

    public function setImageStandardParams(ImageStandardParams $image_std_params): void
    {
        $this->image_std_params = $image_std_params;
    }

    public function getImageStandardParams(): ?ImageStandardParams
    {
        return $this->image_std_params;
    }

    public function setRouter(RouterInterface $router): void
    {
        $this->router = $router;
    }

    public function getRouter(): ?RouterInterface
    {
        return $this->router;
    }

    public function setPasswordHasher(UserPasswordHasherInterface $passwordHasher): void
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function getPasswordHasher(): ?UserPasswordHasherInterface
    {
        return $this->passwordHasher;
    }

    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    public function getSecurity(): ?Security
    {
        return $this->security;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function setManagerRegistry(ManagerRegistry $managerRegistry): void
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function getManagerRegistry(): ManagerRegistry
    {
        return $this->managerRegistry;
    }

    public function setImageLibrary(ImagineInterface $imageLibrary): void
    {
        $this->imageLibrary = $imageLibrary;
    }

    public function getImageLibrary(): ImagineInterface
    {
        return $this->imageLibrary;
    }

    public function setUserProvider(UserProvider $userProvider): void
    {
        $this->userProvider = $userProvider;
    }

    public function getUserProvider(): UserProvider
    {
        return $this->userProvider;
    }

    /**
     * Runs the web service call (handler and response encoder should have been created).
     */
    public function run(Request $request): Error|array
    {
        // add reflection methods
        $this->addMethod(
            'reflection.getMethodList',
            self::getMethodList(...)
        );

        $this->addMethod(
            'reflection.getMethodDetails',
            self::getMethodDetails(...),
            ['methodName']
        );

        uksort($this->_methods, 'strnatcmp');

        $method = null;
        $request_params = [];
        $params = [];

        if ($request->getContentTypeFormat() === 'json') {
            $request_params = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new BadRequestHttpException('invalid json body: ' . json_last_error_msg());
            }
        } elseif ($request->isMethod('POST')) {
            $request_params = $request->request->all();
        } else {
            $request_params = $request->query->all();
        }

        foreach ($request_params as $name => $value) {
            if ($name === 'method') {
                $method = $value;
            } else {
                $params[$name] = $value;
            }
        }

        if (is_null($method)) {
            $method = $request->query->get('method');
        }

        if (is_null($method)) {
            return new Error(self::WS_ERR_INVALID_METHOD, 'Missing "method" name');
        }

        try {
            return ['stat' => 'ok', 'result' => $this->invoke($method, $params)];
        } catch (Exception $exception) {
            return ['stat' => 'fail', 'err' => 9999, 'message' => $exception->getMessage()];
        }
    }

    /**
     * Registers a web service method.
     * methodName string -name of the method as seen externally
     * callback mixed - php method to be invoked internally
     * params array - map of allowed parameter names with options.
     *
     *    @option mixed default (optional)
     *    @option int flags (optional)
     *      possible values: WS_PARAM_ALLOW_ARRAY, WS_PARAM_FORCE_ARRAY, WS_PARAM_OPTIONAL
     *    @option int type (optional)
     *      possible values: WS_TYPE_BOOL, WS_TYPE_INT, WS_TYPE_FLOAT, WS_TYPE_ID
     *                       WS_TYPE_POSITIVE, WS_TYPE_NOTNULL
     *    @option int|float maxValue (optional)
     * description string - a description of the method.
     * options array
     *    @option bool hidden (optional) - if true, this method won't be visible by reflection.getMethodList
     *    @option bool admin_only (optional)
     *    @option bool post_only (optional)
     */
    public function addMethod(string $methodName, $callback, array $params = [], string $description = '', array $options = []): void
    {
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

    public function hasMethod($methodName): bool
    {
        return isset($this->_methods[$methodName]);
    }

    public function getMethodDescription($methodName)
    {
        $desc = @$this->_methods[$methodName]['description'];

        return $desc ?? '';
    }

    public function getMethodSignature($methodName)
    {
        $signature = @$this->_methods[$methodName]['signature'];

        return $signature ?? [];
    }

    public function getMethodOptions($methodName)
    {
        $options = @$this->_methods[$methodName]['options'];

        return $options ?? [];
    }

    public static function isPost(): bool
    {
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? trim((string) $_SERVER['CONTENT_TYPE']) : '';
        if ($contentType === 'application/json') {
            // Receive the RAW post data.
            $content = trim(file_get_contents('php://input'));

            return $content !== '' && $content !== '0';
        } else {
            return $_POST !== [];
        }
    }

    public static function makeArrayParam(&$param): void
    {
        if ($param == null) {
            $param = [];
        } elseif (!is_array($param)) {
            $param = [$param];
        }
    }

    public static function checkType(&$param, $type, string $name): ?Error
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
                    if ($value = filter_var($value, FILTER_VALIDATE_FLOAT) === false || isset($opts['options']['min_range']) && $value < $opts['options']['min_range']) {
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
                if ($param = filter_var($param, FILTER_VALIDATE_FLOAT) === false || isset($opts['options']['min_range']) && $param < $opts['options']['min_range']) {
                    return new Error(self::WS_ERR_INVALID_PARAM, $name . ' must be a' . $msg . ' float');
                }
            }
        }

        return null;
    }

    public static function hasFlag($val, $flag): bool
    {
        return ($val & $flag) == $flag;
    }

    /**
     *  Invokes a registered method. Returns the return of the method (or
     *  a Error object if the method is not found).
     */
    public function invoke(string $methodName, array $params)
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
            } elseif ($params[$name] === '' && !self::hasFlag($flags, self::WS_PARAM_OPTIONAL)) { // parameter provided but empty
                $missing_params[] = $name;
            } else { // parameter provided - do some basic checks
                $the_param = $params[$name];
                if (is_array($the_param) && !self::hasFlag($flags, self::WS_PARAM_ACCEPT_ARRAY)) {
                    return new Error(self::WS_ERR_INVALID_PARAM, $name . ' must be scalar');
                }

                if (self::hasFlag($flags, self::WS_PARAM_FORCE_ARRAY)) {
                    self::makeArrayParam($the_param);
                }

                if ($options['type'] > 0 && $ret = self::checkType($the_param, $options['type'], $name) instanceof Error) {
                    return $ret;
                }

                if (isset($options['maxValue']) && $the_param > $options['maxValue']) {
                    $the_param = $options['maxValue'];
                }

                $params[$name] = $the_param;
            }
        }

        if ($missing_params !== []) {
            return new Error(self::WS_ERR_MISSING_PARAM, 'Missing parameters: ' . implode(',', $missing_params));
        }

        return call_user_func_array($method['callback'], [$params, &$this]);
    }

    /**
     * WS reflection method implementation: lists all available methods.
     */
    public static function getMethodList($params, &$service): array
    {
        $methods = array_filter(
            $service->_methods,
            fn ($m): bool => !isset($m['options']['hidden']) || !$m['options']['hidden']
        );

        return ['methods' => array_keys($methods)];
    }

    /**
     * WS reflection method implementation: gets information about a given method.
     */
    public static function getMethodDetails(array $params, &$service): Error|array
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
