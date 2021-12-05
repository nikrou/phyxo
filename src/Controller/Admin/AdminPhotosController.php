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
use App\Repository\CaddieRepository;
use App\Repository\ImageRepository;
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminPhotosController extends AbstractController
{
    private $translator;

    protected function setTabsheet(string $section = 'direct', bool $enable_synchronization = false)
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('direct', $this->translator->trans('Web Form', [], 'admin'), $this->generateUrl('admin_photos_add', ['section' => 'direct']), 'fa-upload');
        if ($enable_synchronization) {
            $tabsheet->add('ftp', $this->translator->trans('FTP + Synchronization', [], 'admin'), $this->generateUrl('admin_photos_add', ['section' => 'ftp']), 'fa-exchange');
        }
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function direct(
        Request $request,
        int $album_id = null,
        Conf $conf,
        CsrfTokenManagerInterface $tokenManager,
        AlbumMapper $albumMapper,
        TranslatorInterface $translator,
        ImageMapper $imageMapper
    ) {
        $tpl_params = [];
        $this->translator = $translator;

        $upload_max_filesize = min(
            \Phyxo\Functions\Utils::get_ini_size('upload_max_filesize'),
            \Phyxo\Functions\Utils::get_ini_size('post_max_size')
        );

        if ($upload_max_filesize == \Phyxo\Functions\Utils::get_ini_size('upload_max_filesize')) {
            $upload_max_filesize_shorthand = \Phyxo\Functions\Utils::get_ini_size('upload_max_filesize', false);
        } else {
            $upload_max_filesize_shorthand = \Phyxo\Functions\Utils::get_ini_size('post_max_filesize', false);
        }

        $tpl_params['upload_max_filesize'] = $upload_max_filesize;
        $tpl_params['upload_max_filesize_shorthand'] = $upload_max_filesize_shorthand;

        // what is the maximum number of pixels permitted by the memory_limit?
        $fudge_factor = 1.7;
        $available_memory = \Phyxo\Functions\Utils::get_ini_size('memory_limit') - memory_get_usage();
        $max_upload_width = round(sqrt($available_memory / (2 * $fudge_factor)));
        $max_upload_height = round(2 * $max_upload_width / 3);

        // we don't want dimensions like 2995x1992 but 3000x2000
        $max_upload_width = round($max_upload_width / 100) * 100;
        $max_upload_height = round($max_upload_height / 100) * 100;

        $max_upload_resolution = floor($max_upload_width * $max_upload_height / (1000000));

        // no need to display a limitation warning if the limitation is huge like 20MP
        if ($max_upload_resolution < 25) {
            $tpl_params['max_upload_width'] = $max_upload_width;
            $tpl_params['max_upload_height'] = $max_upload_height;
            $tpl_params['max_upload_resolution'] = $max_upload_resolution;
        }


        //warn the user if the picture will be resized after upload
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
                $this->addFlash('selected_category', json_encode($selected_category));
            }
        } elseif ($this->get('session')->getFlashBag()->has('selected_category')) {
            $selected_category = json_decode($this->get('session')->getFlashBag()->get('selected_category'), true);
        } else {
            // we need to know the category in which the last photo was added
            if ($last_album = $imageMapper->getRepository()->findAlbumWithLastImageAdded()) {
                $selected_category = [$last_album->getId()];
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

        if (Utils::get_ini_size('upload_max_filesize') > Utils::get_ini_size('post_max_size')) {
            $tpl_params['setup_warnings'][] = $translator->trans(
                'In your php.ini file, the upload_max_filesize ({upload_max_filesize}B) is bigger than post_max_size ({post_max_size}B), you should change this setting',
                ['upload_max_filesize' => Utils::get_ini_size('upload_max_filesize', false),
                    'post_max_size' => Utils::get_ini_size('post_max_size', false)
                ],
                'admin'
            );
        }
        $tpl_params['CACHE_KEYS'] = Utils::getAdminClientCacheKeys($this->getDoctrine(), ['categories'], $this->generateUrl('homepage'));
        $tpl_params['ws'] = $this->generateUrl('ws');

        $tpl_params['csrf_token'] = $tokenManager->getToken('authenticate');
        $tpl_params['U_EDIT_PATTERN'] = $this->generateUrl('admin_photo', ['image_id' => 0]);
        $tpl_params['U_ALBUM_PATTERN'] = $this->generateUrl('admin_album', ['album_id' => 0]);
        $tpl_params['F_ACTION_BATCH'] = $this->generateUrl('admin_photos_add_batch');
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_photos_add');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_photos_add');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_photos_add');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Photo', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('direct', $conf['enable_synchronization']), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('photos_add_direct.html.twig', $tpl_params);
    }

    public function batch(Request $request, ImageRepository $imageRepository, CaddieRepository $caddieRepository)
    {
        $caddieRepository->emptyCaddies($this->getUser()->getId());
        foreach ($imageRepository->findBy(['id' => explode(',', $request->request->get('batch'))]) as $image) {
            $caddie = new Caddie();
            $caddie->setImage($image);
            $caddie->setUser($this->getUser());
            $caddieRepository->addOrUpdateCaddie($caddie);
        }

        return $this->redirectToRoute('admin_batch_manager_global', ['filter' => 'caddie']);
    }
}
