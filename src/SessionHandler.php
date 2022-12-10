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

namespace App;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

class SessionHandler extends PdoSessionHandler
{
    public function __construct(array $db_params)
    {
        $dsn = sprintf('%s://%s/%s', str_replace('pdo_', '', (string) $db_params['driver']), $db_params['host'], $db_params['name']);

        parent::__construct($dsn, ['db_table' => $db_params['prefix'] . 'sessions', 'db_username' => $db_params['user'], 'db_password' => $db_params['password']]);
    }
}
