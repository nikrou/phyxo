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
use Doctrine\DBAL\Connection;
use Exception;
use IntlDateFormatter;
use PDO;
use Phyxo\Conf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminDashboardController extends AbstractController
{
    #[Route('/admin/', name: 'admin_home', defaults: ['check_upgrade' => false])]
    #[Route('/admin/check_upgrade', name: 'admin_check_upgrade', defaults: ['check_upgrade' => true])]
    public function index(
        Request $request,
        Conf $conf,
        ParameterBagInterface $params,
        TranslatorInterface $translator,
        UserRepository $userRepository,
        GroupRepository $groupRepository,
        HttpClientInterface $client,
        AlbumRepository $albumRepository,
        ImageMapper $imageMapper,
        ImageAlbumRepository $imageAlbumRepository,
        CommentRepository $commentRepository,
        RateRepository $rateRepository,
        TagRepository $tagRepository,
        ImageTagRepository $imageTagRepository,
        Connection $connection,
        bool $check_upgrade = false,
    ): Response {
        $tpl_params = [];

        $tpl_params['DEV'] = preg_match('/.*-dev$/', $params->get('core_version'));

        if ($check_upgrade) {
            try {
                $response = $client->request('GET', $params->get('update_url'));
                if ($response->getStatusCode() == 200 && $response->getContent()) {
                    $versions = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
                    $latest_version = $versions[0]['version'];
                } else {
                    throw new Exception('Unable to check for upgrade.');
                }

                if ($tpl_params['DEV']) {
                    $tpl_params['infos'][] = $translator->trans('You are running on development sources, no check possible.', [], 'admin');
                    $tpl_params['DEV'] = true;
                } elseif (version_compare($params->get('core_version'), $latest_version) < 0) {
                    $tpl_params['infos'][] = '<a href="' . $this->generateUrl('admin_update') . '">' . $translator->trans('A new version of Phyxo is available.', [], 'admin') . '</a>';
                } else {
                    $tpl_params['infos'][] = $translator->trans('You are running the latest version of Phyxo.', [], 'admin');
                }
            } catch (Exception) {
                $tpl_params['errors'][] = $translator->trans('Unable to check for upgrade.', [], 'admin');
            }
        }

        $nb_elements = $imageMapper->getRepository()->count([]);
        $nb_tags = $tagRepository->count([]);
        $nb_image_tag = $imageTagRepository->count([]);
        $nb_users = $userRepository->count([]);
        $nb_groups = $groupRepository->count([]);
        $nb_rates = $rateRepository->count([]);

        /** @var PDO $nativeConnection */
        $nativeConnection = $connection->getNativeConnection();

        $tpl_params = array_merge(
            $tpl_params,
            [
                'OS' => PHP_OS,
                'PHP_VERSION' => \PHP_VERSION,
                'SYMFONY_VERSION' => Kernel::VERSION,
                'DB_ENGINE' => $nativeConnection->getAttribute(PDO::ATTR_DRIVER_NAME),
                'DB_VERSION' => $nativeConnection->getAttribute(PDO::ATTR_SERVER_VERSION),
                'DB_ELEMENTS' => $translator->trans('number_of_photos', ['count' => $nb_elements], 'admin'),
                'DB_ALBUMS' => $translator->trans('number_of_albums', ['count' => $albumRepository->count([])], 'admin'),
                'DB_IMAGE_ALBUM' => $translator->trans('number_of_associations', ['count' => $imageAlbumRepository->count([])], 'admin'),
                'DB_TAGS' => $translator->trans('number_of_tags', ['count' => $nb_tags], 'admin'),
                'DB_IMAGE_TAG' => $translator->trans('number_of_associations', ['count' => $nb_image_tag], 'admin'),
                'NB_PENDING_TAGS' => $imageTagRepository->getPendingTags($count_only = true),
                'U_PENDING_TAGS' => $this->generateUrl('admin_tags_pending'),
                'DB_USERS' => $translator->trans('number_of_users', ['count' => $nb_users], 'admin'),
                'DB_GROUPS' => $translator->trans('number_of_groups', ['count' => $nb_groups], 'admin'),
                'DB_RATES' => $translator->trans('number_of_rates', ['count' => $nb_rates], 'admin'),
                'U_CHECK_UPGRADE' => $this->generateUrl('admin_check_upgrade'),
            ]
        );

        if ($conf['activate_comments']) {
            $nb_comments = $commentRepository->count([]);
            $tpl_params['U_PENDING_COMMENTS'] = $this->generateUrl('admin_comments', ['section' => 'pending']);
            $tpl_params['DB_COMMENTS'] = $translator->trans('number_of_comments', ['count' => $nb_comments], 'admin');
        }

        if ($nb_elements > 0 && $min_date_available = $imageMapper->getRepository()->findMinDateAvailable()) {
            $fmt = new IntlDateFormatter($request->get('_locale'), IntlDateFormatter::FULL, IntlDateFormatter::NONE);
            $tpl_params['first_added'] = $translator->trans('first photo added on {date}', ['date' => $fmt->format($min_date_available)], 'admin');
        }

        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['U_UPDATE_EXTENSIONS'] = $this->generateUrl('admin_update_extensions');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_home');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Album', [], 'admin');

        return $this->render('dashboard.html.twig', $tpl_params);
    }
}
