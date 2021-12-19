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
use App\Repository\ImageAlbumRepository;
use Phyxo\Conf;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminAlbumsController extends AbstractController
{
    private $translator;

    protected function setTabsheet(string $section = 'list'): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('list', $this->translator->trans('List', [], 'admin'), $this->generateUrl('admin_albums'), 'fa-bars');
        $tabsheet->add('move', $this->translator->trans('Move', [], 'admin'), $this->generateUrl('admin_albums_move'), 'fa-move');
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function list(
        int $parent_id = null,
        Conf $conf,
        AlbumRepository $albumRepository,
        CsrfTokenManagerInterface $csrfTokenManager,
        TranslatorInterface $translator,
        ImageAlbumRepository $imageAlbumRepository
    ) {
        $tpl_params = [];
        $this->translator = $translator;

        $sort_orders = [
            'name ASC' => $translator->trans('Album name, A &rarr; Z', [], 'admin'),
            'name DESC' => $translator->trans('Album name, Z &rarr; A', [], 'admin'),
            'date_creation DESC' => $translator->trans('Date created, new &rarr; old', [], 'admin'),
            'date_creation ASC' => $translator->trans('Date created, old &rarr; new', [], 'admin'),
            'date_available DESC' => $translator->trans('Date posted, new &rarr; old', [], 'admin'),
            'date_available ASC' => $translator->trans('Date posted, old &rarr; new', [], 'admin'),
        ];

        $sort_orders_checked = array_keys($sort_orders);
        $tpl_params['sort_orders'] = $sort_orders;
        $tpl_params['sort_order_checked'] = array_shift($sort_orders_checked);

        $albums = [];
        foreach ($albumRepository->findBy(['parent' => $parent_id], ['rank' => 'asc']) as $album) {
            $albums[$album->getId()] = $album;
        }

        // get the albums containing images directly
        if (count($albums) > 0) {
            $nb_photos_in = $imageAlbumRepository->countImagesByAlbum();

            $all_albums = [];
            foreach ($albumRepository->findBy([], ['rank' => 'asc']) as $album) {
                $all_albums[$album->getId()] = $album->getUppercats();
            }
            $subcats_of = [];

            foreach (array_keys($albums) as $album_id) {
                foreach ($all_albums as $id => $uppercats) {
                    if (preg_match('/(^|,)' . $album_id . ',/', $uppercats)) {
                        $subcats_of[$album_id][] = $id;
                    }
                }
            }

            $nb_sub_photos = [];
            foreach ($subcats_of as $cat_id => $subcat_ids) {
                $nb_photos = 0;
                foreach ($subcat_ids as $id) {
                    if (isset($nb_photos_in[$id])) {
                        $nb_photos += $nb_photos_in[$id];
                    }
                }

                $nb_sub_photos[$cat_id] = $nb_photos;
            }
        }

        if ($parent_id) {
            $tpl_params['PARENT_EDIT'] = $this->generateUrl('admin_album', ['album_id' => $parent_id]);
        }

        $tpl_params['categories'] = [];
        foreach ($albums as $album) {
            $tpl_cat = [
                'NAME' => $album->getName(),
                'NB_PHOTOS' => $nb_photos_in[$album->getId()] ?? 0,
                'NB_SUB_PHOTOS' => $nb_sub_photos[$album->getId()] ?? 0,
                'NB_SUB_ALBUMS' => $subcats_of[$album->getId()] ?? 0,
                'ID' => $album->getId(),
                'RANK' => $album->getRank() * 10,
                'U_JUMPTO' => $this->generateUrl('album', ['category_id' => $album->getId()]),
                'U_CHILDREN' => $this->generateUrl('admin_albums', ['parent_id' => $album->getId()]),
                'U_EDIT' => $this->generateUrl('admin_album', ['album_id' => $album->getId(), 'parent_id' => $parent_id]),
                'IS_VIRTUAL' => $album->isVirtual(),
                'IS_PRIVATE' => $album->getStatus() === Album::STATUS_PRIVATE,
            ];

            if ($album->isVirtual()) {
                $tpl_cat['U_DELETE'] = $this->generateUrl('admin_album_delete', ['album_id' => $album->getId(), 'parent_id' => $parent_id]);
            } else {
                if ($conf['enable_synchronization']) {
                    $tpl_cat['U_SYNC'] = $this->generateUrl('admin_site_update');
                }
            }

            $tpl_params['categories'][] = $tpl_cat;
        }

        $tpl_params['F_ACTION_UPDATE'] = $this->generateUrl('admin_albums_update', ['parent_id' => $parent_id]);
        $tpl_params['F_ACTION_CREATE'] = $this->generateUrl('admin_album_create', ['parent_id' => $parent_id]);
        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_albums');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_albums');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Albums', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('list'), $tpl_params);

        return $this->render('albums_list.html.twig', $tpl_params);
    }

    public function update(Request $request, int $parent_id = null, AlbumRepository $albumRepository, AlbumMapper $albumMapper, TranslatorInterface $translator)
    {
        if ($request->isMethod('POST')) {
            if ($request->request->get('submitManualOrder')) { // save manual category ordering
                $categoriesOrder = $request->request->all()['catOrd'];
                asort($categoriesOrder, SORT_NUMERIC);

                $albums = [];
                foreach (array_keys($categoriesOrder) as $catId) {
                    $albums[] = ['id' => $catId, 'id_uppercat' => null];
                }
                $albumMapper->saveAlbumsOrder($albums);

                $this->addFlash('success', $translator->trans('Album manual order was saved', [], 'admin'));
            } elseif ($request->request->get('submitAutoOrder')) {
                $category_ids = $albumMapper->getUppercatIds([$parent_id]);

                if ($request->request->get('recursive')) {
                    $category_ids = $albumRepository->getSubcatIds($category_ids);
                }

                $categories = [];
                $sort = [];

                list($order_by_field, $order_by_asc) = explode(' ', $request->request->get('order_by'));

                $order_by_date = false;
                $ref_dates = [];
                if (strpos($order_by_field, 'date_') === 0) {
                    $order_by_date = true;

                    $ref_dates = $albumMapper->getAlbumsRefDate($category_ids, $order_by_field, 'ASC' == $order_by_asc ? 'min' : 'max');
                }

                foreach ($albumRepository->findBy(['id' => $category_ids]) as $album) {
                    if ($order_by_date) {
                        $sort[] = $ref_dates[$album->getId()];
                    } else {
                        $sort[] = $album->getName();
                    }

                    $categories[] = [
                        'id' => $album->getId(),
                        'id_uppercat' => $album->getParent()->getId(),
                    ];
                }

                array_multisort($sort, SORT_REGULAR, 'ASC' == $order_by_asc ? SORT_ASC : SORT_DESC, $categories);

                $albumMapper->saveAlbumsOrder($categories);
                $this->addFlash('success', $translator->trans('Albums automatically sorted', [], 'admin'));
            }
        }

        return $this->redirectToRoute('admin_albums', ['parent_id' => $parent_id]);
    }

    public function move(Request $request, int $parent_id = null, AlbumRepository $albumRepository, AlbumMapper $albumMapper, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        if ($request->isMethod('POST')) {
            if ($request->request->get('selection')) {
                $albumMapper->moveAlbums($request->request->all()['selection'], $request->request->get('parent'));
            } else {
                $this->addFlash('error', $translator->trans('Select at least one album', [], 'admin'));
            }

            return $this->redirectToRoute('admin_albums_move', ['parent_id' => $parent_id]);
        }

        $virtual_albums = [];
        foreach ($albumRepository->findVirtualAlbums() as $album) {
            $virtual_albums[] = $album;
        }
        $tpl_params = array_merge($tpl_params, $albumMapper->displaySelectAlbumsWrapper($virtual_albums, [], 'category_to_move_options'));

        $albums = [];
        foreach ($albumRepository->findAll() as $album) {
            $albums[] = $album;
        }
        $tpl_params = array_merge($tpl_params, $albumMapper->displaySelectAlbumsWrapper($albums, [], 'category_parent_options'));

        $tpl_params['F_ACTION'] = $this->generateUrl('admin_albums_move', ['parent_id' => $parent_id]);
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_albums_move');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_albums');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Albums', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('move'), $tpl_params);

        return $this->render('albums_move.html.twig', $tpl_params);
    }
}
