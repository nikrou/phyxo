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

namespace tests\units\Phyxo\Model\Repository;

require_once __DIR__ . '/../../../bootstrap.php';

use atoum;
use Phyxo\Conf;

class Users extends atoum
{
    public function testCheckStatus()
    {
        $conn_controller = new \atoum\mock\controller();
        $conn_controller->__construct = function () {
        };

        $conn = new \mock\Phyxo\DBLayer\pgsqlConnection('', '', '', '', $conn_controller);
        $conf = new Conf($conn);

        $user = [];
        $cache = [];
        $users = new \Phyxo\Model\Repository\Users($conn, $conf, $user, $cache);

        $this
            ->boolean($users->isAuthorizeStatus(ACCESS_ADMINISTRATOR, $user_status = ACCESS_GUEST))
            ->isFalse()
            ->and()
            ->boolean($users->isAuthorizeStatus(ACCESS_ADMINISTRATOR, $user_status = 'webmaster'))
            ->isTrue();
    }
}
