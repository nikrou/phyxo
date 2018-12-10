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

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class BaseController extends Controller
{
    protected function doResponse($legacy_file, $template_name)
    {
        $_SERVER['PHP_SELF'] = $legacy_file;
        $_SERVER['SCRIPT_NAME'] = $legacy_file;
        $_SERVER['SCRIPT_FILENAME'] = $legacy_file;

        $container = $this->container; // allow accessing container as global variable
        $tpl_params = [];

        try {
            global $conf, $conn, $pwg_loaded_plugins, $prefixeTable, $header_notes, $services, $filter, $template, $user, $page, $persistent_cache, $lang, $lang_info;

            ob_start();
            chdir(dirname($legacy_file));
            require $legacy_file;

            $tpl_params['GALLERY_TITLE'] = isset($page['gallery_title']) ? $page['gallery_title'] : $conf['gallery_title'];
            $tpl_params['PAGE_BANNER'] = \Phyxo\Functions\Plugin::trigger_change(
                'render_page_banner',
                str_replace(
                    '%gallery_title%',
                    $conf['gallery_title'],
                    isset($page['page_banner']) ? $page['page_banner'] : $conf['page_banner']
                )
            );

            $tpl_params['PAGE_TITLE'] = strip_tags($title);
            $tpl_params['U_HOME'] = \Phyxo\Functions\URL::get_root_url();
            $tpl_params['LEVEL_SEPARATOR'] = $conf['level_separator'];
            if (!empty($header_notes)) {
                $tpl_params['header_notes'] = $header_notes;
            }

            $tpl_params['VERSION'] = $conf['show_version'] ? PHPWG_VERSION : '';
            $tpl_params['PHPWG_URL'] = defined('PHPWG_URL') ? PHPWG_URL : '';
            if (!$services['users']->isGuest()) {
                $tpl_params['CONTACT_MAIL'] = \Phyxo\Functions\Utils::get_webmaster_mail_address();
            }
            $debug_vars = [];
            if ($conf['show_gt']) {
                if (!isset($page['count_queries'])) {
                    $page['count_queries'] = 0;
                    $page['queries_time'] = 0;
                }
                $time = \Phyxo\Functions\Utils::get_elapsed_time($t2, microtime(true));

                $debug_vars = array_merge(
                    $debug_vars,
                    [
                        'TIME' => $time,
                        'NB_QUERIES' => $conn->getQueriesCount(),
                        'SQL_TIME' => number_format($conn->getQueriesTime() * 1000, 2, '.', ' ') . ' ms'
                    ]
                );
            }

            $tpl_params['debug'] = $debug_vars;


            return $this->render($template_name, $tpl_params);
        } catch (Routing\Exception\ResourceNotFoundException $e) {
            return new Response('Not Found', 404);
        } catch (Exception $e) {
            return new Response('An error occurred', 500);
        }
    }
}
