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

use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Phyxo\Template\Template;
use Phyxo\Conf;

class SecurityController extends AbstractController
{
    public function __construct(Template $template, $default_language)
    {
        // default theme
        $template->set_template_dir(__DIR__ . '/../../themes/treflez/template');

        // to be removed
        define('PHPWG_ROOT_PATH', __DIR__ . '/../../');

        $language_load = \Phyxo\Functions\Language::load_language(
            'common.lang',
            __DIR__ . '/../../',
            ['language' => $default_language, 'return_vars' => true]
        );
        $template->setLang($language_load['lang']);
        $template->setLangInfo($language_load['lang_info']);
        $template->postConstruct();
    }

    public function login(AuthenticationUtils $authenticationUtils, Request $request, CsrfTokenManagerInterface $csrfTokenManager, Conf $conf, $phyxoVersion, $phyxoWebsite)
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $last_username = $authenticationUtils->getLastUsername();

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $token = $csrfTokenManager->getToken('authenticate');

        $tpl_params = [
            'VERSION' => $conf['show_version'] ? $phyxoVersion : '',
            'PHYXO_URL' => $phyxoWebsite,
            'login_action' => $this->generateUrl('login'),
            'last_username' => $last_username,
            'csrf_token' => $token,
            'error' => $error,
        ];

        return $this->render('identification.tpl', $tpl_params);
    }

    public function logout()
    {
    }
}
