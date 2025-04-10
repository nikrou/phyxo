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

use App\DataMapper\AlbumMapper;
use App\DataMapper\RateMapper;
use App\DataMapper\TagMapper;
use App\DataMapper\UserMapper;
use App\Enum\ConfEnum;
use App\Enum\ImageSizeType;
use App\Repository\HistoryRepository;
use App\Repository\HistorySummaryRepository;
use App\Repository\ImageRepository;
use App\Repository\SearchRepository;
use App\Repository\UpgradeRepository;
use App\Repository\UserFeedRepository;
use App\Services\DerivativeService;
use Exception;
use Phyxo\Conf;
use Phyxo\Image\ImageStandardParams;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminMaintenanceController extends AbstractController
{
    public function __construct(private readonly ImageRepository $imageRepository, private readonly Filesystem $fs, private readonly string $uploadDir, private readonly string $rootProjectDir)
    {
    }

    #[Route(
        '/admin/maintenace/{action}',
        name: 'admin_maintenance',
        defaults: ['action' => null],
        requirements: ['action' => 'configuration|lock_gallery|unlock_gallery|albums|albums_virtualize|images|delete_orphan_tags|app_cache|user_cache|history_summary|feeds|database|search|obsolete']
    )]
    public function index(
        Request $request,
        Conf $conf,
        ParameterBagInterface $params,
        HistoryRepository $historyRepository,
        KernelInterface $kernel,
        HistorySummaryRepository $historySummaryRepository,
        UserMapper $userMapper,
        RateMapper $rateMapper,
        TagMapper $tagMapper,
        ImageStandardParams $image_std_params,
        TranslatorInterface $translator,
        SearchRepository $searchRepository,
        UserFeedRepository $userFeedRepository,
        AlbumMapper $albumMapper,
        UpgradeRepository $upgradeRepository,
        ?string $action,
    ): Response {
        $purge_urls = [];
        $tpl_params = [];

        switch ($action) {
            case 'configuration':
                $this->fixConfiguration($conf);
                $this->addFlash('success', $translator->trans('Database configuration has been fixed.', [], 'admin'));

                return $this->redirectToRoute('admin_maintenance');
            case 'lock_gallery':
                $conf['gallery_locked'] = true;

                return $this->redirectToRoute('admin_maintenance');
            case 'unlock_gallery':
                $conf['gallery_locked'] = false;

                return $this->redirectToRoute('admin_maintenance');
            case 'albums':
                $albumMapper->updateUppercats();
                $albumMapper->updateAlbums();
                $albumMapper->updateGlobalRank();
                $userMapper->invalidateUserCache(true);

                return $this->redirectToRoute('admin_maintenance');
            case 'albums_virtualize':
                $this->virtualizeAlbums();
                $albumMapper->getRepository()->removePhysicalAlbums();
                $this->addFlash('success', $translator->trans('All albums were virtualized.', [], 'admin'));

                return $this->redirectToRoute('admin_maintenance');
            case 'images':
                $rateMapper->updateRatingScore();
                $userMapper->invalidateUserCache();

                return $this->redirectToRoute('admin_maintenance');
            case 'delete_orphan_tags':
                $tagMapper->deleteOrphanTags();
                $this->addFlash('success', $translator->trans('Orphan tags deleted', [], 'admin'));

                return $this->redirectToRoute('admin_maintenance');
            case 'app_cache':
                $application = new Application($kernel);
                $application->setAutoExit(true);
                $input = new ArrayInput(['command' => 'cache:clear']);
                $output = new NullOutput();
                $this->addFlash('success', $translator->trans('Application cache has been clear.', [], 'admin'));
                $result = $application->run($input, $output);
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(
                        [
                            'status' => 'ok',
                            'title' => 'application cache clear',
                        ]
                    );
                }

                return $this->redirectToRoute('admin_maintenance');
            case 'user_cache':
                $userMapper->invalidateUserCache();

                return $this->redirectToRoute('admin_maintenance');
            case 'history_detail':
                $historyRepository->deleteAll();

                return $this->redirectToRoute('admin_maintenance');
            case 'history_summary':
                $historySummaryRepository->deleteAll();

                return $this->redirectToRoute('admin_maintenance');
            case 'feeds':
                $userFeedRepository->deleteUserFeedNotChecked();

                return $this->redirectToRoute('admin_maintenance');
            case 'database':
                $applied_upgrades = [];
                foreach ($upgradeRepository->findAll() as $upgrade) {
                    $applied_upgrades[] = $upgrade->getId();
                }

                if (!in_array(142, $applied_upgrades)) {
                    $current_release = '1.0.0';
                } elseif (!in_array(144, $applied_upgrades)) {
                    $current_release = '1.1.0';
                } elseif (!in_array(145, $applied_upgrades)) {
                    $current_release = '1.2.0';
                // } elseif (in_array('validated', $columns_of[$em->getConnection()->getPrefix() . 'tags'])) {
                //     $current_release = '1.3.0';
                } elseif (!in_array(146, $applied_upgrades)) {
                    $current_release = '1.5.0';
                } elseif (!in_array(147, $applied_upgrades)) {
                    $current_release = '1.6.0';
                } elseif (!in_array(148, $applied_upgrades)) {
                    $current_release = '1.8.0';
                } elseif (!in_array(149, $applied_upgrades)) {
                    $current_release = '1.9.0';
                } else {
                    $current_release = '2.0.0';
                }

                $upgrade_file = $params->get('kernel.project_dir') . '/install/upgrade_' . $current_release . '.php';
                if (is_readable($upgrade_file)) {
                    // ob_start();
                    include $upgrade_file;
                    // ob_end_clean();
                }

                return $this->redirectToRoute('admin_maintenance');
            case 'search':
                $searchRepository->purge();

                return $this->redirectToRoute('admin_maintenance');
            case 'obsolete':
                $obsolete_file = $params->get('install_dir') . '/obsolete.list';
                if (!is_readable($obsolete_file)) {
                    return $this->redirectToRoute('admin_maintenance');
                }

                $fs = new Filesystem();
                $old_files = file($obsolete_file, FILE_IGNORE_NEW_LINES);
                $count_files = 0;
                $not_writable_files = 0;
                try {
                    foreach ($old_files as $old_file) {
                        $path = $params->get('root_project_dir') . '/' . $old_file;
                        if (is_readable($path)) {
                            if (is_writable($path)) {
                                $fs->remove($path);
                                $count_files++;
                            } else {
                                $not_writable_files++;
                            }
                        } elseif (is_dir($path)) {
                            $fs->remove($path);
                        }
                    }

                    if ($count_files > 0) {
                        $this->addFlash('success', $translator->trans('All old files ({count}) have been removed.', ['count' => $count_files], 'admin'));
                    }

                    if ($not_writable_files > 0) {
                        $this->addFlash('error', $translator->trans('Some files ({count}) could have not be removed.', ['count' => $not_writable_files], 'admin'));
                    }
                } catch (Exception) {
                    $this->addFlash('error', $translator->trans('Some files ({count}) could have not be removed.', [], 'admin'));
                }

                return $this->redirectToRoute('admin_maintenance');
            default:
                break;
        }

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_maintenance');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_maintenance');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Maintenance', [], 'admin');

        $tpl_params = array_merge($tpl_params, [
            'U_MAINT_ALBUMS' => $this->generateUrl('admin_maintenance', ['action' => 'albums']),
            'U_MAINT_ALBUMS_VIRTUALIZE' => $this->generateUrl('admin_maintenance', ['action' => 'albums_virtualize']),
            'U_MAINT_IMAGES' => $this->generateUrl('admin_maintenance', ['action' => 'images']),
            'U_MAINT_ORPHAN_TAGS' => $this->generateUrl('admin_maintenance', ['action' => 'delete_orphan_tags']),
            'U_MAINT_APP_CACHE' => $this->generateUrl('admin_maintenance', ['action' => 'app_cache']),
            'U_MAINT_USER_CACHE' => $this->generateUrl('admin_maintenance', ['action' => 'user_cache']),
            'U_MAINT_HISTORY_DETAIL' => $this->generateUrl('admin_maintenance', ['action' => 'history_detail']),
            'U_MAINT_HISTORY_SUMMARY' => $this->generateUrl('admin_maintenance', ['action' => 'history_summary']),
            'U_MAINT_FEEDS' => $this->generateUrl('admin_maintenance', ['action' => 'feeds']),
            'U_MAINT_DATABASE' => $this->generateUrl('admin_maintenance', ['action' => 'database']),
            'U_MAINT_CONFIGURATION' => $this->generateUrl('admin_maintenance', ['action' => 'configuration']),
            'U_MAINT_SEARCH' => $this->generateUrl('admin_maintenance', ['action' => 'search']),
            'U_MAINT_DERIVATIVES' => $this->generateUrl('admin_maintenance', ['action' => 'derivatives']),
            'U_MAINT_OBSOLETE' => $this->generateUrl('admin_maintenance', ['action' => 'obsolete']),
        ]);

        foreach ($image_std_params->getDefinedTypeMap() as $std_params) {
            $purge_urls[$translator->trans($std_params->type->value, [], 'admin')] = $this->generateUrl('admin_maintenance_derivatives', ['type' => $std_params->type->value]);
        }

        $purge_urls[$translator->trans(ImageSizeType::CUSTOM->value, [], 'admin')] = $this->generateUrl('admin_maintenance_derivatives', ['type' => ImageSizeType::CUSTOM->value]);

        $tpl_params['purge_derivatives'] = $purge_urls;

        if ($conf['gallery_locked']) {
            $tpl_params['U_MAINT_UNLOCK_GALLERY'] = $this->generateUrl('admin_maintenance', ['action' => 'unlock_gallery']);
        } else {
            $tpl_params['U_MAINT_LOCK_GALLERY'] = $this->generateUrl('admin_maintenance', ['action' => 'lock_gallery']);
        }

        return $this->render('maintenance.html.twig', $tpl_params);
    }

    private function fixConfiguration(Conf $conf): void
    {
        $conf->addOrUpdateParam('order_by', $conf['order_by'], ConfEnum::JSON);
        $conf->addOrUpdateParam('order_by_inside_category', $conf['order_by_inside_category'], ConfEnum::JSON);
    }

    private function virtualizeAlbums(): void
    {
        $baseDirectory = dirname(__DIR__, 2);

        foreach ($this->imageRepository->findImagesInPhysicalAlbums() as $image) {
            $currentPath = $baseDirectory . '/' . $image->getPath();

            if (file_exists($currentPath)) {
                $image->setMd5sum(md5_file($image->getPath()));
                $filenameDirectory = sprintf(
                    '%s/%s/%s/%s',
                    $this->uploadDir,
                    $image->getDateAvailable()->format('Y'),
                    $image->getDateAvailable()->format('m'),
                    $image->getDateAvailable()->format('d')
                );
                $this->fs->mkdir($filenameDirectory);
                $newPath = sprintf(
                    '%s/%s-%s.%s',
                    $filenameDirectory,
                    $image->getDateAvailable()->format('YmdHis'),
                    substr((string) $image->getMd5sum(), 0, 8),
                    pathinfo($currentPath, PATHINFO_EXTENSION)
                );
                $relativePath = sprintf('./%s/%s', $this->fs->makePathRelative(dirname($newPath), $this->rootProjectDir), basename($newPath));

                $image->setStorageCategoryId(null);
                $image->setPath($relativePath);
                $this->imageRepository->addOrUpdateImage($image);
                $this->fs->copy($currentPath, $newPath);
            }
        }
    }

    #[Route('/admin/maintenance/derivatives/{type}', name: 'admin_maintenance_derivatives', requirements: ['type' => new EnumRequirement(ImageSizeType::class)])]
    public function derivatives(string $type, DerivativeService $derivativeService, TranslatorInterface $translator): Response
    {
        $derivativeService->clearCache([ImageSizeType::from($type)], ImageSizeType::getAllTypes());

        $this->addFlash('success', $translator->trans('Image cache for size "%type%" has been clear.', ['%type%' => $type], 'admin'));

        return $this->redirectToRoute('admin_maintenance');
    }
}
