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
use App\DataMapper\ImageMapper;
use App\Entity\Caddie;
use App\Entity\Image;
use App\Repository\CaddieRepository;
use App\Repository\ImageRepository;
use App\Security\AppUserService;
use Doctrine\Persistence\ManagerRegistry;
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminPhotosController extends AbstractController
{
    private TranslatorInterface $translator;

    protected function setTabsheet(string $section = 'direct'): TabSheet
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('direct', $this->translator->trans('Web Form', [], 'admin'), $this->generateUrl('admin_photos_add', ['section' => 'direct']), 'fa-upload');
        $tabsheet->select($section);

        return $tabsheet;
    }

    #[Route('/admin/photos/add/{album_id}', name: 'admin_photos_add', defaults: ['album_id' => null], requirements: ['album_id' => '\d+'])]
    public function direct(
        Request $request,
        Conf $conf,
        CsrfTokenManagerInterface $tokenManager,
        AlbumMapper $albumMapper,
        TranslatorInterface $translator,
        ImageMapper $imageMapper,
        ManagerRegistry $managerRegistry,
        ?int $album_id = null,
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $upload_max_filesize = min(
            $this->getIniSize('upload_max_filesize'),
            $this->getIniSize('post_max_size')
        );

        if ($upload_max_filesize == $this->getIniSize('upload_max_filesize')) {
            $upload_max_filesize_shorthand = $this->getIniSize('upload_max_filesize', false);
        } else {
            $upload_max_filesize_shorthand = $this->getIniSize('post_max_filesize', false);
        }

        $tpl_params['upload_max_filesize'] = $upload_max_filesize;
        $tpl_params['upload_max_filesize_shorthand'] = $upload_max_filesize_shorthand;

        // what is the maximum number of pixels permitted by the memory_limit?
        $fudge_factor = 1.7;
        $available_memory = (int) $this->getIniSize('memory_limit') - memory_get_usage();
        $max_upload_width = round(sqrt($available_memory / (2 * $fudge_factor)));
        $max_upload_height = round(2 * $max_upload_width / 3);

        // we don't want dimensions like 2995x1992 but 3000x2000
        $max_upload_width = round($max_upload_width / 100) * 100;
        $max_upload_height = round($max_upload_height / 100) * 100;

        $max_upload_resolution = floor($max_upload_width * $max_upload_height / 1_000_000);

        // no need to display a limitation warning if the limitation is huge like 20MP
        if ($max_upload_resolution < 25) {
            $tpl_params['max_upload_width'] = $max_upload_width;
            $tpl_params['max_upload_height'] = $max_upload_height;
            $tpl_params['max_upload_resolution'] = $max_upload_resolution;
        }

        // warn the user if the picture will be resized after upload
        if ($conf['original_resize']) {
            $tpl_params['original_resize_maxwidth'] = $conf['original_resize_maxwidth'];
            $tpl_params['original_resize_maxheight'] = $conf['original_resize_maxheight'];
        }

        $unique_exts = array_unique(array_map('strtolower', $conf['upload_form_all_types'] ? $conf['file_ext'] : $conf['picture_ext']));

        $tpl_params['upload_file_types'] = implode(', ', $unique_exts);
        $tpl_params['file_exts'] = implode(',', $unique_exts);

        // we need to know the category in which the last photo was added
        $selected_category = [];
        if ($album_id) {
            // test if album really exists
            $album = $albumMapper->getRepository()->find($album_id);
            if (!is_null($album)) {
                $selected_category = [$album_id];
                $request->getSession()->set('selected_category', json_encode($selected_category, JSON_THROW_ON_ERROR));
            }
        } elseif ($request->getSession()->has('selected_category')) {
            $selected_category = json_decode((string) $request->getSession()->get('selected_category'), true, 512, JSON_THROW_ON_ERROR);
        } elseif (($last_image = $imageMapper->getRepository()->findAlbumWithLastImageAdded()) instanceof Image) {
            // we need to know the category in which the last photo was added
            if ($first_image_albums = $last_image->getImageAlbums()->first()) {
                $selected_category = [$first_image_albums->getAlbum()->getId()];
            }
        }

        // existing album
        $tpl_params['selected_category'] = $selected_category;

        // image level options
        $selected_level = $request->request->get('level') ? (int) $request->request->get('level') : 0;
        $tpl_params['level_options'] = Utils::getPrivacyLevelOptions($translator, $conf['available_permission_levels'], 'admin');
        $tpl_params['level_options_selected'] = $selected_level;

        if (!function_exists('gd_info')) {
            $tpl_params['setup_errors'][] = $translator->trans('GD library is missing', [], 'admin');
        }

        if ($conf['use_exif'] && !function_exists('exif_read_data')) {
            $tpl_params['setup_warnings'][] = $translator->trans('Exif extension not available, admin should disable exif use', [], 'admin');
        }

        if ($this->getIniSize('upload_max_filesize') > $this->getIniSize('post_max_size')) {
            $tpl_params['setup_warnings'][] = $translator->trans(
                'In your php.ini file, the upload_max_filesize ({upload_max_filesize}B) is bigger than post_max_size ({post_max_size}B), you should change this setting',
                ['upload_max_filesize' => $this->getIniSize('upload_max_filesize', false),
                    'post_max_size' => $this->getIniSize('post_max_size', false),
                ],
                'admin'
            );
        }

        $tpl_params['CACHE_KEYS'] = Utils::getAdminClientCacheKeys($managerRegistry, ['categories'], $this->generateUrl('homepage'));
        $tpl_params['ws'] = $this->generateUrl('ws');

        $tpl_params['csrf_token'] = $tokenManager->getToken('authenticate');
        $tpl_params['U_EDIT_PATTERN'] = $this->generateUrl('admin_photo', ['image_id' => 0]);
        $tpl_params['U_ALBUM_PATTERN'] = $this->generateUrl('admin_album', ['album_id' => 0]);
        $tpl_params['F_ACTION_BATCH'] = $this->generateUrl('admin_photos_add_batch');
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_photos_add');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_photos_add');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_photos_add');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Photo', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('direct');

        return $this->render('photos_add_direct.html.twig', $tpl_params);
    }

    #[Route('/admin/photos/batch/{album_id}', name: 'admin_photos_add_batch', defaults: ['album_id' => null], requirements: ['album_id' => '\d+'])]
    public function batch(Request $request, AppUserService $appUserService, ImageRepository $imageRepository, CaddieRepository $caddieRepository): Response
    {
        $caddieRepository->emptyCaddies($appUserService->getUser()->getId());
        foreach ($imageRepository->findBy(['id' => explode(',', $request->request->get('batch'))]) as $image) {
            $caddie = new Caddie();
            $caddie->setImage($image);
            $caddie->setUser($appUserService->getUser());
            $caddieRepository->addOrUpdateCaddie($caddie);
        }

        return $this->redirectToRoute('admin_batch_manager_global', ['filter' => 'caddie']);
    }

    private function getIniSize(string $ini_key, bool $in_bytes = true): string
    {
        $size = ini_get($ini_key);

        if ($in_bytes) {
            $size = $this->convertShorthandNotationToBytes($size);
        }

        return $size;
    }

    private function convertShorthandNotationToBytes(string $value): string
    {
        $suffix = substr($value, -1);
        $multiply_by = null;

        if ($suffix === 'K') {
            $multiply_by = 1024;
        } elseif ($suffix === 'M') {
            $multiply_by = 1024 * 1024;
        } elseif ($suffix === 'G') {
            $multiply_by = 1024 * 1024 * 1024;
        }

        if (!is_null($multiply_by)) {
            $value = (int) substr($value, 0, -1);
            $value *= $multiply_by;
        }

        return $value;
    }
}
