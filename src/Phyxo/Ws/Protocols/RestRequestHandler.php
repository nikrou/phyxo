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

use Phyxo\Ws\Error;
use Phyxo\Ws\Server;

class RestRequestHandler
{
    function handleRequest(&$service)
    {
        $params = [];

        $param_array = $service->isPost() ? $_POST : $_GET;
        foreach ($param_array as $name => $value) {
            if ($name == 'method') {
                $method = $value;
            } else {
                $params[$name] = $value;
            }
        }
        if (empty($method) && isset($_GET['method'])) {
            $method = $_GET['method'];
        }

        if (empty($method)) {
            $service->sendResponse(new Error(Server::WS_ERR_INVALID_METHOD, 'Missing "method" name'));
            return;
        }
        $resp = $service->invoke($method, $params);

        return $service->sendResponse($resp);
    }
}
