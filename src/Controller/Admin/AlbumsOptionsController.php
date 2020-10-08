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
use App\Entity\Album;
use App\Repository\AlbumRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\TabSheet\TabSheet;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class AlbumsOptionsController extends AdminCommonController
{
    private $translator;

    protected function setTabsheet(string $section = 'status', Conf $conf): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('status', $this->translator->trans('Public / Private', [], 'admin'), $this->generateUrl('admin_albums_options'), 'fa-lock');
        $tabsheet->add('lock', $this->translator->trans('Lock', [], 'admin'), $this->generateUrl('admin_albums_options', ['section' => 'lock']), 'fa-ban');
        if ($conf['activate_comments']) {
            $tabsheet->add('comments', $this->translator->trans('Comments', [], 'admin'), $this->generateUrl('admin_albums_options', ['section' => 'comments']), 'fa-comments');
        }
        if ($conf['allow_random_representative']) {
            $tabsheet->add('representative', $this->translator->trans('Representative', [], 'admin'), $this->generateUrl('admin_albums_options', ['section' => 'representative']));
        }
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function index(Request $request, string $section, EntityManager $em, Conf $conf, ParameterBagInterface $params, AlbumMapper $albumMapper,
                        AlbumRepository $albumRepository, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST')) {
            if ($request->request->get('falsify') && $request->request->get('cat_true') && count($request->request->get('cat_true')) > 0) {
                if ($section === 'comments') {
                    $albumRepository->updateAlbums(['commentable' => false], $request->request->get('cat_true'));
                } elseif ($section === 'lock') {
                    $albumMapper->setAlbumsVisibility($request->request->get('cat_true'), false);
                } elseif ($section === 'status') {
                    $albumMapper->setAlbumsStatus($request->request->get('cat_true'), Album::STATUS_PRIVATE);
                } elseif ($section === 'representative') {
                    $albumRepository->updateAlbums(['representative_picture_id' => null], $request->request->get('cat_true'));
                }
            } elseif ($request->request->get('trueify') && $request->request->get('cat_false') && count($request->request->get('cat_false')) > 0) {
                if ($section === 'comments') {
                    $albumRepository->updateAlbums(['commentable' => true], $request->request->get('cat_false'));
                } elseif ($section === 'lock') {
                    $albumMapper->setAlbumsVisibility($request->request->get('cat_false'), true);
                } elseif ($section === 'status') {
                    $albumMapper->setAlbumsStatus($request->request->get('cat_false'), Album::STATUS_PUBLIC);
                } elseif ($section === 'representative') {
                    // theoretically, all categories in $_POST['cat_false'] contain at least one element, so Phyxo can find a representant.
                    $albumMapper->setRandomRepresentant($request->request->get('cat_false'));
                }
            }

            return $this->redirectToRoute('admin_albums_options', ['section' => $section]);
        }

        $cats = $this->getCatsBySection($section, $em);
        $tpl_params['L_SECTION'] = $cats['L_SECTION'];
        $tpl_params['L_CAT_OPTIONS_TRUE'] = $cats['L_CAT_OPTIONS_TRUE'];
        $tpl_params['L_CAT_OPTIONS_FALSE'] = $cats['L_CAT_OPTIONS_FALSE'];

        $tpl_params = array_merge($tpl_params, $albumMapper->displaySelectAlbumsWrapper($cats['cats_true'], [], 'category_option_true'));
        $tpl_params = array_merge($tpl_params, $albumMapper->displaySelectAlbumsWrapper($cats['cats_false'], [], 'category_option_false'));

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_albums_options', ['section' => $section]);
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_albums_options');
        $tpl_params['PAGE_TITLE'] = $this->translator->trans('Public / Private', [], 'admin');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet($section, $conf), $tpl_params);

        return $this->render('albums_options.html.twig', $tpl_params);
    }

    protected function getCatsBySection(string $section, AlbumRepository $albumRepository): array
    {
        $cats_true = [];
        $cats_false = [];
        $l_section = '';
        $l_true = '';
        $l_false = '';
        if ($section === 'comments') {
            $cats_true = $cats_false = [];
            foreach ($albumRepository->findAll() as $album) {
                if ($album->isCommentable()) {
                    $cats_true[] = $album;
                } else {
                    $cats_false[] = $album;
                }
            }

            $l_section = $this->translator->trans('Authorize users to add comments on selected albums', [], 'admin');
            $l_true = $this->translator->trans('Authorized', [], 'admin');
            $l_false = $this->translator->trans('Forbidden', [], 'admin');
        } elseif ($section === 'lock') {
            foreach ($albumRepository->findAll() as $album) {
                if ($album->isVisible()) {
                    $cats_true[] = $album;
                } else {
                    $cats_false[] = $album;
                }
            }

            $l_section = $this->translator->trans('Lock albums', [], 'admin');
            $l_true = $this->translator->trans('Unlocked', [], 'admin');
            $l_false = $this->translator->trans('Locked', [], 'admin');
        } elseif ($section === 'status') {
            foreach ($albumRepository->findAll() as $album) {
                if ($album->getStatus() === Album::STATUS_PUBLIC) {
                    $cats_true[] = $album;
                } else {
                    $cats_false[] = $album;
                }
            }

            $l_section = $this->translator->trans('Manage authorizations for selected albums', [], 'admin');
            $l_true = $this->translator->trans('Public', [], 'admin');
            $l_false = $this->translator->trans('Private', [], 'admin');
        } elseif ($section === 'representative') {
            foreach ($albumRepository->findAll() as $album) {
                if ($album->getRepresentativePictureId()) {
                    $cats_true[] = $album;
                } else {
                    $cats_false[] = $album;
                }
            }

            $l_section = $this->translator->trans('Representative', [], 'admin');
            $l_true = $this->translator->trans('singly represented', [], 'admin');
            $l_false = $this->translator->trans('randomly represented', [], 'admin');
        }

        return ['cats_true' => $cats_true, 'cats_false' => $cats_false, 'L_SECTION' => $l_section, 'L_CAT_OPTIONS_TRUE' => $l_true, 'L_CAT_OPTIONS_FALSE' => $l_false];
    }
}
