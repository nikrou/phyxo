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

namespace App\Controller\Admin;

use App\Repository\BaseRepository;
use App\Repository\CategoryRepository;
use App\Repository\CommentRepository;
use App\Repository\GroupRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\ImageTagRepository;
use App\Repository\RateRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use GuzzleHttp\Client;
use IntlDateFormatter;
use Phyxo\Conf;
use Phyxo\DBLayer\DBLayer;
use Phyxo\EntityManager;
use Phyxo\Functions\Language;
use Phyxo\Template\Template;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

class DashboardController extends AdminCommonController
{
    public function index(Request $request, bool $check_upgrade = false, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $tpl_params['DEV'] = preg_match('/.*-dev$/', $params->get('core_version'));

        if ($check_upgrade) {
            try {
                $client = new Client();
                $response = $client->request('GET', $params->get('update_url'));
                if ($response->getStatusCode() == 200 && $response->getBody()->isReadable()) {
                    $versions = json_decode($response->getBody(), true);
                    $latest_version = $versions[0]['version'];
                } else {
                    throw new \Exception('Unable to check for upgrade.');
                }

                if ($tpl_params['DEV']) {
                    $tpl_params['infos'][] = Language::l10n('You are running on development sources, no check possible.');
                    $tpl_params['DEV'] = true;
                } elseif (version_compare($params->get('core_version'), $latest_version) < 0) {
                    $tpl_params['infos'][] = '<a href="' . $this->generateUrl('admin_update') . '">' . Language::l10n('A new version of Phyxo is available.') . '</a>';
                } else {
                    $tpl_params['infos'][] = Language::l10n('You are running the latest version of Phyxo.');
                }
            } catch (\Exception $e) {
                $tpl_params['errors'][] = Language::l10n('Unable to check for upgrade.');
            }
        }

        $nb_elements = $em->getRepository(ImageRepository::class)->count();
        $nb_categories = $em->getRepository(CategoryRepository::class)->count();
        $nb_virtual = $em->getRepository(CategoryRepository::class)->count('dir IS NULL');
        $nb_physical = $em->getRepository(CategoryRepository::class)->count('dir IS NOT NULL');
        $nb_image_category = $em->getRepository(ImageCategoryRepository::class)->count();
        $nb_tags = $em->getRepository(TagRepository::class)->count();
        $nb_image_tag = $em->getRepository(ImageTagRepository::class)->count();
        $nb_users = $em->getRepository(UserRepository::class)->count();
        $nb_groups = $em->getRepository(GroupRepository::class)->count();
        $nb_rates = $em->getRepository(RateRepository::class)->count();

        $tpl_params = array_merge($tpl_params,
            [
                'PHPWG_URL' => $params->get('phyxo_website'),
                'PWG_VERSION' => $params->get('core_version'),
                'OS' => PHP_OS,
                'PHP_VERSION' => phpversion(),
                'DB_ENGINE' => DBLayer::availableEngines()[$em->getConnection()->getLayer()],
                'DB_VERSION' => $em->getConnection()->db_version(),
                'DB_ELEMENTS' => Language::l10n_dec('%d photo', '%d photos', $nb_elements),
                'DB_CATEGORIES' => Language::l10n_dec('%d album including', '%d albums including', $nb_categories),
                'PHYSICAL_CATEGORIES' => Language::l10n_dec('%d physical', '%d physicals', $nb_physical),
                'VIRTUAL_CATEGORIES' => Language::l10n_dec('%d virtual', '%d virtuals', $nb_virtual),
                'DB_IMAGE_CATEGORY' => Language::l10n_dec('%d association', '%d associations', $nb_image_category),
                'DB_TAGS' => Language::l10n_dec('%d tag', '%d tags', $nb_tags),
                'DB_IMAGE_TAG' => Language::l10n_dec('%d association', '%d associations', $nb_image_tag),
                'DB_USERS' => Language::l10n_dec('%d user', '%d users', $nb_users),
                'DB_GROUPS' => Language::l10n_dec('%d group', '%d groups', $nb_groups),
                'DB_RATES' => ($nb_rates === 0) ? Language::l10n('no rate') : Language::l10n('%d rates', $nb_rates),
                'U_CHECK_UPGRADE' => $this->generateUrl('admin_check_upgrade'),
                'PHP_DATATIME' => date("Y-m-d H:i:s"),
                'DB_DATATIME' => $em->getRepository(BaseRepository::class)->getNow()
            ]
        );

        if ($conf['activate_comments']) {
            $nb_comments = $em->getRepository(CommentRepository::class)->count();
            $tpl_params['U_PENDING_COMMENTS'] = $this->generateUrl('admin_comments', ['section' => 'pending']);
            $tpl_params['DB_COMMENTS'] = Language::l10n_dec('%d comment', '%d comments', $nb_comments);
        }

        if ($nb_elements > 0) {
            $min_date_available = $em->getRepository(ImageRepository::class)->findMinDateAvailable();
            $tpl_params['first_added'] = Language::l10n('first photo added on %s', (new \DateTime($min_date_available))->format('l d M Y'));
        }

        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['U_UPDATE_EXTENSIONS'] = $this->generateUrl('admin_update_extensions');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_home');
        $tpl_params['PAGE_TITLE'] = Language::l10n('Album');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('dashboard.tpl', $tpl_params);
    }
}
