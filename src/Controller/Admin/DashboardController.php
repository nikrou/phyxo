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

use App\DataMapper\ImageMapper;
use App\Kernel;
use App\Repository\AlbumRepository;
use App\Repository\CommentRepository;
use App\Repository\GroupRepository;
use App\Repository\ImageAlbumRepository;
use App\Repository\ImageTagRepository;
use App\Repository\RateRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use Phyxo\Conf;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DashboardController extends AdminCommonController
{
    public function index(Request $request, bool $check_upgrade = false, Conf $conf, ParameterBagInterface $params, TranslatorInterface $translator,
                          UserRepository $userRepository, GroupRepository $groupRepository, HttpClientInterface $client, AlbumRepository $albumRepository,
                          ImageMapper $imageMapper, ImageAlbumRepository $imageAlbumRepository, CommentRepository $commentRepository, RateRepository $rateRepository,
                          TagRepository $tagRepository, ImageTagRepository $imageTagRepository)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $tpl_params['DEV'] = preg_match('/.*-dev$/', $params->get('core_version'));

        if ($check_upgrade) {
            try {
                $response = $client->request('GET', $params->get('update_url'));
                if ($response->getStatusCode() == 200 && $response->getContent()) {
                    $versions = json_decode($response->getContent(), true);
                    $latest_version = $versions[0]['version'];
                } else {
                    throw new \Exception('Unable to check for upgrade.');
                }

                if ($tpl_params['DEV']) {
                    $tpl_params['infos'][] = $translator->trans('You are running on development sources, no check possible.', [], 'admin');
                    $tpl_params['DEV'] = true;
                } elseif (version_compare($params->get('core_version'), $latest_version) < 0) {
                    $tpl_params['infos'][] = '<a href="' . $this->generateUrl('admin_update') . '">' . $translator->trans('A new version of Phyxo is available.', [], 'admin') . '</a>';
                } else {
                    $tpl_params['infos'][] = $translator->trans('You are running the latest version of Phyxo.', [], 'admin');
                }
            } catch (\Exception $e) {
                $tpl_params['errors'][] = $translator->trans('Unable to check for upgrade.', [], 'admin');
            }
        }

        $nb_elements = $imageMapper->getRepository()->count([]);
        $nb_categories = $albumRepository->count([]);
        $nb_virtual = $albumRepository->countByType($virtual = true);
        $nb_physical = $albumRepository->countByType($virtual = false);
        $nb_image_category = $imageAlbumRepository->count([]);
        $nb_tags = $tagRepository->count([]);
        $nb_image_tag = $imageTagRepository->count([]);
        $nb_users = $userRepository->count([]);
        $nb_groups = $groupRepository->count([]);
        $nb_rates = $rateRepository->count([]);

        $tpl_params = array_merge($tpl_params,
            [
                'OS' => PHP_OS,
                'PHP_VERSION' => \PHP_VERSION,
                'SYMFONY_VERSION' => Kernel::VERSION,
                'DB_ENGINE' => $this->getDoctrine()->getConnection()->getDatabasePlatform()->getName(),
                'DB_VERSION' => $this->getDoctrine()->getConnection()->getWrappedConnection()->getServerVersion(),
                'DB_ELEMENTS' => $translator->trans('number_of_photos', ['count' => $nb_elements], 'admin'),
                'DB_CATEGORIES' => $translator->trans('number_of_albums_including', ['count' => $nb_categories], 'admin'),
                'PHYSICAL_CATEGORIES' => $translator->trans('number_of_physicals', ['count' => $nb_physical], 'admin'),
                'VIRTUAL_CATEGORIES' => $translator->trans('number_of_virtuals', ['count' => $nb_virtual], 'admin'),
                'DB_IMAGE_CATEGORY' => $translator->trans('number_of_associations', ['count' => $nb_image_category], 'admin'),
                'DB_TAGS' => $translator->trans('number_of_tags', ['count' => $nb_tags], 'admin'),
                'DB_IMAGE_TAG' => $translator->trans('number_of_associations', ['count' => $nb_image_tag], 'admin'),
                'NB_PENDING_TAGS' => $imageTagRepository->getPendingTags($count_only = true),
                'U_PENDING_TAGS' => $this->generateUrl('admin_tags_pending'),
                'DB_USERS' => $translator->trans('number_of_users', ['count' => $nb_users], 'admin'),
                'DB_GROUPS' => $translator->trans('number_of_groups', ['count' => $nb_groups], 'admin'),
                'DB_RATES' => $translator->trans('number_of_rates', ['count' => $nb_rates], 'admin'),
                'U_CHECK_UPGRADE' => $this->generateUrl('admin_check_upgrade')
            ]
        );

        if ($conf['activate_comments']) {
            $nb_comments = $commentRepository->count([]);
            $tpl_params['U_PENDING_COMMENTS'] = $this->generateUrl('admin_comments', ['section' => 'pending']);
            $tpl_params['DB_COMMENTS'] = $translator->trans('number_of_comments', ['count' => $nb_comments], 'admin');
        }

        if ($nb_elements > 0) {
            if ($min_date_available = $imageMapper->getRepository()->findMinDateAvailable()) {
                $tpl_params['first_added'] = $translator->trans('first photo added on {date}', ['date' => $min_date_available->format('l d M Y')], 'admin');
            }
        }

        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['U_UPDATE_EXTENSIONS'] = $this->generateUrl('admin_update_extensions');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_home');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Album', [], 'admin');

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $conf, $params->get('core_version')), $tpl_params);

        return $this->render('dashboard.html.twig', $tpl_params);
    }
}
