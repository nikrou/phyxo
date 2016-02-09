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

class RestRequestHandler extends RequestHandler
{
    function handleRequest(&$service) {
        $params = array();

        $param_array = $service->isPost() ? $_POST : $_GET;
        foreach ($param_array as $name => $value) {
            if ($name=='format') {
                continue; // ignore - special keys
            }
            if ($name=='method') {
                $method = $value;
            } else {
                $params[$name] = $value;
            }
        }
		if ( empty($method) && isset($_GET['method'])) {
			$method = $_GET['method'];
		}

        if (empty($method)) {
            $service->sendResponse(new Error(WS_ERR_INVALID_METHOD, 'Missing "method" name'));
            return;
        }
        $resp = $service->invoke($method, $params);
        $service->sendResponse($resp);
    }
}
