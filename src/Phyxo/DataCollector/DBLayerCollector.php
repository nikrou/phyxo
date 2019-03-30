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

namespace Phyxo\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Phyxo\DBLayer\iDBLayer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class DBLayerCollector extends DataCollector
{
    private $conn;

    public function __construct(iDBLayer $conn)
    {
        $this->conn = $conn;
    }

    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $queries = $this->conn->getQueries();
        foreach ($queries as &$query) {
            $query['sql'] = \SqlFormatter::format($query['sql']);
        }

        $this->data = [
            'queries' => $queries,
            'time' => array_sum(array_column($queries, 'time')) * 1000 // in milliseconds
        ];
    }

    public function getQueries()
    {
        return $this->data['queries'];
    }

    public function getTime()
    {
        return $this->data['time'];
    }

    public function reset()
    {
        $this->data = [];
    }

    public function getName()
    {
        return 'app.dblayer_collector';
    }
}
