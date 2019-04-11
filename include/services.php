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

use Phyxo\Model\Repository\Comments;
use Phyxo\Model\Repository\Users;

$services = [];
$services['comments'] = new Comments($conn, $conf);
$services['users'] = new Users($conn, $conf, $user);
if (isset($passwordEncoder)) {
    $services['users']->setPasswordEncoder($passwordEncoder);
}
