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

use Phyxo\Conf;
use Phyxo\Template\Template;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExceptionController extends CommonController
{
    public function index(Request $request, FlattenException $exception, Template $template, Conf $conf, string $themesDir, string $phyxoVersion, string $phyxoWebsite)
    {
        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $code = $exception->getStatusCode();

        $tpl_params = [];
        $tpl_params['status_code'] = $code;
        $tpl_params['status_text'] = isset(Response::$statusTexts[$code]) ? Response::$statusTexts[$code] : '';

        $tpl_params['LOGIN_URL'] = $this->generateUrl('login');
        $tpl_params['HOME_URL'] = $this->generateUrl('homepage');

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);

        return $this->render('error.tpl', $tpl_params);
    }
}
