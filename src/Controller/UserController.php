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

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;

class UserController extends BaseController
{
    public function password(string $legacyBaseDir, Request $request)
    {
        $legacy_file = sprintf('%s/password.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/password";

        return $this->doResponse($legacy_file, 'password.tpl');
    }

    public function profile(string $legacyBaseDir, Request $request)
    {
        $legacy_file = sprintf('%s/profile.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/profile";

        return $this->doResponse($legacy_file, 'profile.tpl');
    }
}
