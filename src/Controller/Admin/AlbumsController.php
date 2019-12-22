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

use App\DataMapper\CategoryMapper;
use App\Repository\CategoryRepository;
use App\Repository\ImageCategoryRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\TabSheet\TabSheet;
use Phyxo\Template\Template;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AlbumsController extends AdminCommonController
{
    private $translator;

    protected function setTabsheet(string $section = 'list'): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('list', $this->translator->trans('List', [], 'admin'), $this->generateUrl('admin_albums'), 'fa-bars');
        $tabsheet->add('move', $this->translator->trans('Move', [], 'admin'), $this->generateUrl('admin_albums_move'), 'fa-move');
        $tabsheet->add('permalinks', $this->translator->trans('Permalinks', [], 'admin'), $this->generateUrl('admin_albums_permalinks'), 'fa-link');
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function list(Request $request, int $parent_id = null, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params,
                        CsrfTokenManagerInterface $csrfTokenManager, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $sort_orders = [
            'name ASC' => $translator->trans('Album name, A &rarr; Z', [], 'admin'),
            'name DESC' => $translator->trans('Album name, Z &rarr; A', [], 'admin'),
            'date_creation DESC' => $translator->trans('Date created, new &rarr; old', [], 'admin'),
            'date_creation ASC' => $translator->trans('Date created, old &rarr; new', [], 'admin'),
            'date_available DESC' => $translator->trans('Date posted, new &rarr; old', [], 'admin'),
            'date_available ASC' => $translator->trans('Date posted, old &rarr; new', [], 'admin'),
        ];

        $categories = [];
        $sort_orders_checked = array_keys($sort_orders);

        $tpl_params['sort_orders'] = $sort_orders;
        $tpl_params['sort_order_checked'] = array_shift($sort_orders_checked);

        $result = $em->getRepository(CategoryRepository::class)->findByField('id_uppercat', $parent_id);
        $categories = $em->getConnection()->result2array($result, 'id');

        // get the categories containing images directly
        if (count($categories) > 0) {
            $result = $em->getRepository(ImageCategoryRepository::class)->findCategoriesWithImages();
            $nb_photos_in = $em->getConnection()->result2array($result, 'category_id', 'nb_photos');

            $result = $em->getRepository(CategoryRepository::class)->findAll();
            $all_categories = $em->getConnection()->result2array($result, 'id', 'uppercats');
            $subcats_of = [];

            foreach (array_keys($categories) as $cat_id) {
                foreach ($all_categories as $id => $uppercats) {
                    if (preg_match('/(^|,)' . $cat_id . ',/', $uppercats)) {
                        $subcats_of[$cat_id][] = $id;
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
        foreach ($categories as $category) {
            $tpl_cat = [
                'NAME' => $category['name'],
                'NB_PHOTOS' => isset($nb_photos_in[$category['id']]) ? $nb_photos_in[$category['id']] : 0,
                'NB_SUB_PHOTOS' => isset($nb_sub_photos[$category['id']]) ? $nb_sub_photos[$category['id']] : 0,
                'NB_SUB_ALBUMS' => isset($subcats_of[$category['id']]) ? count($subcats_of[$category['id']]) : 0,
                'ID' => $category['id'],
                'RANK' => $category['rank'] * 10,
                'U_JUMPTO' => $this->generateUrl('album', ['category_id' => $category['id']]),
                'U_CHILDREN' => $this->generateUrl('admin_albums', ['parent_id' => $category['id']]),
                'U_EDIT' => $this->generateUrl('admin_album', ['album_id' => $category['id'], 'parent_id' => $parent_id]),
                'IS_VIRTUAL' => empty($category['dir']),
                'IS_PRIVATE' => $category['status'] === 'private',
            ];

            if (empty($category['dir'])) {
                $tpl_cat['U_DELETE'] = $this->generateUrl('admin_album_delete', ['album_id' => $category['id'], 'parent_id' => $parent_id]);
            } else {
                if ($conf['enable_synchronization']) {
                    $tpl_cat['U_SYNC'] = $this->generateUrl('admin_site_update');
                }
            }

            $tpl_params['categories'][] = $tpl_cat;
        }


        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        $tpl_params['F_ACTION_UPDATE'] = $this->generateUrl('admin_albums_update', ['parent_id' => $parent_id]);
        $tpl_params['F_ACTION_CREATE'] = $this->generateUrl('admin_album_create', ['parent_id' => $parent_id]);
        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_albums');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_albums');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Albums', [], 'admin');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('list'), $tpl_params);

        return $this->render('albums_list.tpl', $tpl_params);
    }

    public function update(Request $request, int $parent_id = null, CategoryMapper $categoryMapper, EntityManager $em, TranslatorInterface $translator)
    {
        if ($request->isMethod('POST')) {
            if ($request->request->get('submitManualOrder')) { // save manual category ordering
                $categoriesOrder = $request->request->get('catOrd');
                asort($categoriesOrder, SORT_NUMERIC);
                $categoryMapper->saveCategoriesOrder(array_keys($categoriesOrder));

                $this->addFlash('info', $translator->trans('Album manual order was saved', [], 'admin'));
            } elseif ($request->request->get('submitAutoOrder')) {
                $result = $em->getRepository(CategoryRepository::class)->findByField('id_uppercat', $parent_id);
                $category_ids = $em->getConnection()->result2array($result, null, 'id');

                if ($request->request->get('recursive')) {
                    $category_ids = $em->getRepository(CategoryRepository::class)->getSubcatIds($category_ids);
                }

                $categories = [];
                $sort = [];

                list($order_by_field, $order_by_asc) = explode(' ', $request->request->get('order_by'));

                $order_by_date = false;
                if (strpos($order_by_field, 'date_') === 0) {
                    $order_by_date = true;

                    $ref_dates = $categoryMapper->getCategoriesRefDate($category_ids, $order_by_field, 'ASC' == $order_by_asc ? 'min' : 'max');
                }

                $result = $em->getRepository(CategoryRepository::class)->findByIds($category_ids);
                while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                    if ($order_by_date) {
                        $sort[] = $ref_dates[$row['id']];
                    } else {
                        $sort[] = $row['name'];
                    }

                    $categories[] = [
                        'id' => $row['id'],
                        'id_uppercat' => $row['id_uppercat'],
                    ];
                }

                array_multisort($sort, SORT_REGULAR, 'ASC' == $order_by_asc ? SORT_ASC : SORT_DESC, $categories);

                $categoryMapper->saveCategoriesOrder($categories);
                $this->addFlash('info', $translator->trans('Albums automatically sorted', [], 'admin'));
            }
        }

        return $this->redirectToRoute('admin_albums', ['parent_id' => $parent_id]);
    }

    public function move(Request $request, int $parent_id = null, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params,
                        CategoryMapper $categoryMapper, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST')) {
            if ($request->request->get('selection')) {
                $categoryMapper->moveCategories($request->request->get('selection'), $request->request->get('parent'));
            } else {
                $this->addFlash('error', $translator->trans('Select at least one album', [], 'admin'));
            }

            return $this->redirectToRoute('admin_albums_move', ['parent_id' => $parent_id]);
        }

        $result = $em->getRepository(CategoryRepository::class)->findWithCondition(['dir IS NULL']);
        $categories = $em->getConnection()->result2array($result);
        $tpl_params = array_merge($tpl_params, $categoryMapper->displaySelectCategoriesWrapper($categories, [], 'category_to_move_options'));

        $result = $em->getRepository(CategoryRepository::class)->findAll();
        $categories = $em->getConnection()->result2array($result);
        $tpl_params = array_merge($tpl_params, $categoryMapper->displaySelectCategoriesWrapper($categories, [], 'category_parent_options'));

        $tpl_params['F_ACTION'] = $this->generateUrl('admin_albums_move', ['parent_id' => $parent_id]);
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_albums_move');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_albums');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Albums', [], 'admin');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('move'), $tpl_params);

        return $this->render('albums_move.tpl', $tpl_params);
    }

    public function permalinks(Request $request, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        // @TODO: use symfony routing for permalinks. So remove for now

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_albums_permalinks');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_albums');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Albums', [], 'admin');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('permalinks'), $tpl_params);

        return $this->render('albums_permalinks.tpl', $tpl_params);
    }
}
