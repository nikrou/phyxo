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
use App\DataMapper\ImageMapper;
use App\DataMapper\SearchMapper;
use App\DataMapper\TagMapper;
use App\DataMapper\UserMapper;
use App\Metadata;
use App\Repository\AlbumRepository;
use App\Repository\CaddieRepository;
use App\Repository\FavoriteRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\ImageTagRepository;
use App\Repository\TagRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\SrcImage;
use Phyxo\LocalSiteReader;
use Phyxo\TabSheet\TabSheet;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class BatchManagerController extends AdminCommonController
{
    private $translator;

    protected function setTabsheet(string $section = 'global')
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('global', $this->translator->trans('global mode', [], 'admin'), $this->generateUrl('admin_batch_manager_global'));
        $tabsheet->add('unit', $this->translator->trans('unit mode', [], 'admin'), $this->generateUrl('admin_batch_manager_unit'));
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    protected function appendFilter(array $filter = [])
    {
        $previous_filter = $this->getFilter();
        if (empty($previous_filter)) {
            $previous_filter = [];
        }

        $this->get('session')->set('bulk_manager_filter', array_merge($previous_filter, $filter));
    }

    protected function getFilter()
    {
        return $this->get('session')->get('bulk_manager_filter');
    }

    public function global(Request $request, string $filter = null, int $start = 0, EntityManager $em, Conf $conf, ParameterBagInterface $params,
                          CategoryMapper $categoryMapper, ImageStandardParams $image_std_params, SearchMapper $searchMapper, TagMapper $tagMapper, ImageMapper $imageMapper,
                          UserMapper $userMapper, Metadata $metadata, TranslatorInterface $translator, AlbumRepository $albumRepository)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_batch_manager_global');

        $collection = [];
        $nb_thumbs_page = 0;

        $prefilters = [
            ['id' => 'caddie', 'name' => $translator->trans('Caddie', [], 'admin')],
            ['id' => 'favorites', 'name' => $translator->trans('Your favorites', [], 'admin')],
            ['id' => 'last_import', 'name' => $translator->trans('Last import', [], 'admin')],
            ['id' => 'no_album', 'name' => $translator->trans('With no album', [], 'admin')],
            ['id' => 'no_tag', 'name' => $translator->trans('With no tag', [], 'admin')],
            ['id' => 'duplicates', 'name' => $translator->trans('Duplicates', [], 'admin')],
            ['id' => 'all_photos', 'name' => $translator->trans('All', [], 'admin')]
        ];

        if ($request->isMethod('POST')) {
            $start = 0;
            $this->get('session')->set('bulk_manager_filter', []);

            if ($request->request->get('filter_prefilter_use')) {
                $this->appendFilter(['prefilter' => $request->request->get('filter_prefilter')]);

                if ($request->request->get('filter_prefilter') === 'duplicates') {
                    if ($request->request->get('filter_duplicates_date')) {
                        $this->appendFilter(['duplicates_date' => true]);
                    }

                    if ($request->request->get('filter_duplicates_dimensions')) {
                        $this->appendFilter(['duplicates_dimensions' => true]);
                    }
                }
            }

            if ($request->request->get('filter_category_use')) {
                $this->appendFilter(['category' => $request->request->get('filter_category')]);

                if ($request->request->get('filter_category_recursive')) {
                    $this->appendFilter(['category_recursive' => true]);
                }
            }

            if ($request->request->get('filter_tags_use')) {
                $this->appendFilter(['tags' => $tagMapper->getTagsIds($request->request->get('filter_tags'))]);

                if ($request->request->get('tag_mode') && in_array($request->request->get('tag_mode'), ['AND', 'OR'])) {
                    $this->appendFilter(['tag_mode' => $request->request->get('tag_mode')]);
                }
            }

            if ($request->request->get('filter_level_use')) {
                if (in_array($request->request->get('filter_level'), $conf['available_permission_levels'])) {
                    $this->appendFilter(['level' => $request->request->get('filter_level')]);

                    if ($request->request->get('filter_level_include_lower')) {
                        $this->appendFilter(['level_include_lower' => true]);
                    }
                }
            }

            if ($request->request->get('filter_dimension_use')) {
                foreach (['min_width', 'max_width', 'min_height', 'max_height'] as $type) {
                    if (filter_var($request->request->get('filter_dimension_' . $type), FILTER_VALIDATE_INT) !== false) {
                        $this->appendFilter(['dimension' => [$type => $request->request->get('filter_dimension_' . $type)]]);
                    }
                }
                foreach (['min_ratio', 'max_ratio'] as $type) {
                    if (filter_var($request->request->get('filter_dimension_' . $type), FILTER_VALIDATE_FLOAT) !== false) {
                        $this->appendFilter(['dimension' => [$type => $request->request->get('filter_dimension_' . $type)]]);
                    }
                }
            }

            if ($request->request->get('filter_filesize_use')) {
                foreach (['min', 'max'] as $type) {
                    if (filter_var($request->request->get('filter_filesize_' . $type), FILTER_VALIDATE_FLOAT) !== false) {
                        $this->appendFilter(['filesize' => [$type => $request->request->get('filter_filesize_' . $type)]]);
                    }
                }
            }

            if ($request->request->get('filter_search_use')) {
                $this->appendFilter(['search' => ['q' => $request->request->get('q')]]);
            }
        } elseif ($filter) {
            $this->get('session')->set('bulk_manager_filter', []);

            // filters in menu
            if ($filter === 'caddie') {
                $this->appendFilter(['prefilter' => 'caddie']);
                $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_batch_manager_global', ['filter' => 'caddie']);
            } elseif ($filter === 'last_import') {
                $this->appendFilter(['prefilter' => 'last_import']);
                $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_batch_manager_global', ['filter' => 'last_import']);
            } elseif ($filter === 'album' && $request->get('value') !== null) {
                $this->appendFilter(['category' => (int) $request->get('value')]);
                $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_batch_manager_global');
            } elseif ($filter === 'tag' && $request->get('value') !== null) {
                $this->appendFilter(['tags' => [(int) $request->get('value')]]);
                $this->appendFilter(['tag_mode' => 'AND']);
                $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_batch_manager_global');
            }

            // @TODO : add others based on following code
            //       case 'level':
            //           if (is_numeric($value) && in_array($value, $conf['available_permission_levels'])) {
            //               $this->appendFilter(['level' => $value]);
            //           }
            //           break;

            //       case 'search':
            //       $this->appendFilter(['search' => ['q' => $value]]);
            //           break;

            //       case 'dimension':
            //           $dim_map = ['w' => 'width', 'h' => 'height', 'r' => 'ratio'];
            //           foreach (explode('-', $value) as $part) {
            //               $values = explode('..', substr($part, 1));
            //               if (isset($dim_map[$part[0]])) {
            //                   $type = $dim_map[$part[0]];
            //                   $this->appendFilter(['dimension' => ['min_' . $type => $values[0], 'max_' . $type => $values[1]]]);
            //               }
            //           }
            //           break;

            //       case 'filesize':
            //           $values = explode('..', $value);
            //           $this->appendFilter(['filesize' => ['min' => $values[0], 'max' => $values[1]]]);
            //           break;
        }

        $this->filterFromSession();
        $filter_sets = $this->getFilterSetsFromFilter($em, $conf, $searchMapper, $albumRepository);

        $current_set = array_shift($filter_sets);
        if (empty($current_set)) {
            $current_set = [];
        }
        foreach ($filter_sets as $set) {
            $current_set = array_intersect($current_set, $set);
        }

        $tpl_params = array_merge($tpl_params, $this->setDimensions($em));

        // privacy level
        foreach ($conf['available_permission_levels'] as $level) {
            $level_options[$level] = $translator->trans('Level ' . $level, [], 'admin');

            if (0 == $level) {
                $level_options[$level] = $translator->trans('Everybody', [], 'admin');
            }
        }
        $tpl_params['filter_level_options'] = $level_options;
        $tpl_params['filter_level_options_selected'] = isset($this->getFilter()['level']) ? $this->getFilter()['level'] : 0;

        // tags
        $filter_tags = [];
        if (!empty($this->getFilter()['tags'])) {
            $result = $em->getRepository(TagRepository::class)->findTags($this->getFilter()['tags']);
            $tags = $em->getConnection()->result2array($result);
            $filter_tags = $tagMapper->prepareTagsListForUI($tags);
        }
        $tpl_params['filter_tags'] = $filter_tags;

        // in the filter box, which category to select by default
        $selected_category = [];
        if (!empty($this->getFilter()['category'])) {
            $selected_category[] = $this->getFilter()['category'];
        } else {
            // we need to know the category in which the last photo was added
            $selected_category[] = $em->getRepository(ImageCategoryRepository::class)->getCategoryWithLastPhotoAdded();
        }
        $tpl_params['filter_category_selected'] = $selected_category;

        // Dissociate from a category : categories listed for dissociation can only
        // represent virtual links. We can't create orphans. Links to physical categories can't be broken.
        if (count($current_set) > 0) {
            $result = $em->getRepository(ImageRepository::class)->findVirtualCategoriesWithImages($current_set);
            $tpl_params['associated_categories'] = $em->getConnection()->result2array($result, 'id', 'id');

            // remove tags
            $tpl_params['associated_tags'] = $tagMapper->getCommonTags($this->getUser(), [], $current_set, -1);
        }

        // creation date
        $tpl_params['DATE_CREATION'] = !$request->request->get('date_creation') ? date('Y-m-d') . ' 00:00:00' : $request->request->get('date_creation');

        // image level options
        $tpl_params['level_options'] = \Phyxo\Functions\Utils::getPrivacyLevelOptions($conf['available_permission_levels'], $translator, 'admin');
        $tpl_params['level_options_selected'] = 0;

        // metadata
        $site_reader = new LocalSiteReader('./', $conf, $metadata); // @TODO : in conf or somewhere else but no direct path here
        $used_metadata = implode(', ', $site_reader->get_metadata_attributes());
        $tpl_params['used_metadata'] = $used_metadata;

        //derivatives
        $del_deriv_map = [];
        foreach ($image_std_params->getDefinedTypeMap() as $derivative_params) {
            $del_deriv_map[$derivative_params->type] = $translator->trans($derivative_params->type, [], 'admin');
        }
        $gen_deriv_map = $del_deriv_map;
        $del_deriv_map[ImageStandardParams::IMG_CUSTOM] = $translator->trans(ImageStandardParams::IMG_CUSTOM, [], 'admin');
        $tpl_params['del_derivatives_types'] = $del_deriv_map;
        $tpl_params['generate_derivatives_types'] = $gen_deriv_map;

        if ($request->get('display')) {
            if ($request->get('display') === 'all') {
                $nb_images = count($current_set);
            } else {
                $nb_images = (int) $request->get('display');
            }
            $this->get('session')->set('unit_display', $nb_images);
        } else {
            if ($this->get('session')->get('unit_display')) {
                $nb_images = $this->get('session')->get('unit_display');
            } else {
                $nb_images = 20;
            }
        }
        $nb_thumbs_page = 0;

        if (count($current_set) > 0) {
            $tpl_params['navbar'] = \Phyxo\Functions\Utils::createNavigationBar($this->get('router'), 'admin_batch_manager_global', ['filter' => $filter], count($current_set), $start, $nb_images);

            $is_category = false;
            if (isset($this->getFilter()['category']) && !isset($this->getFilter()['category_recursive'])) {
                $is_category = true;
            }

            if (isset($this->getFilter()['prefilter']) && $this->getFilter()['prefilter'] === 'duplicates') {
                $conf['order_by'] = ' ORDER BY file, id';
            }

            if ($is_category) {
                $category_info = $categoryMapper->getCatInfo($this->getFilter()['category']);

                $conf['order_by'] = $conf['order_by_inside_category'];
                if (!empty($category_info['image_order'])) {
                    $conf['order_by'] = ' ORDER BY ' . $em->getConnection()->db_real_escape_string($category_info['image_order']);
                }
            }
            $result = $em->getRepository(ImageRepository::class)->findByImageIdsAndCategoryId(
                $current_set,
                $is_category ? ($this->getFilter()['category'] ?? null) : null,
                $conf['order_by'] ?? '  ',
                $nb_images,
                $start
            );
            $thumb_params = $image_std_params->getByType(ImageStandardParams::IMG_THUMB);

            // template thumbnail initialization
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                $nb_thumbs_page++;
                $src_image = new SrcImage($row, $conf['picture_ext']);

                $ttitle = \Phyxo\Functions\Utils::render_element_name($row);
                if ($ttitle != \Phyxo\Functions\Utils::get_name_from_file($row['file'])) { // @TODO: simplify. code difficult to read
                    $ttitle .= ' (' . $row['file'] . ')';
                }

                $tpl_params['thumbnails'][] = array_merge(
                    $row,
                    [
                        'thumb' => new DerivativeImage($src_image, $thumb_params, $image_std_params),
                        'TITLE' => $ttitle,
                        'FILE_SRC' => (new DerivativeImage($src_image, $image_std_params->getByType(ImageStandardParams::IMG_LARGE), $image_std_params))->getUrl(),
                        'U_EDIT' => $this->generateUrl('admin_photo', ['image_id' => $row['id']])
                    ]
                );
            }

            $tpl_params['thumb_params'] = $thumb_params;
        }

        if ($request->isMethod('POST')) {
            if ($request->request->get('setSelected')) {
                $collection = $current_set;
            } elseif ($request->request->get('selection')) {
                $collection = $request->request->get('selection');
            }

            $this->actionOnCollection($request, $collection, $em, $tagMapper, $imageMapper, $categoryMapper, $userMapper);
        }

        $tpl_params['IN_CADDIE'] = isset($this->getFilter()['prefilter']) && $this->getFilter()['prefilter'] === 'caddie';
        $tpl_params['U_EMPTY_CADDIE'] = $this->generateUrl('admin_batch_manager_global_empty_caddie', ['start' => $start]);
        $tpl_params['prefilters'] = $prefilters;
        $tpl_params['filter'] = $this->getFilter();
        $tpl_params['selection'] = $collection;
        $tpl_params['all_elements'] = $current_set;
        $tpl_params['nb_thumbs_page'] = $nb_thumbs_page;
        $tpl_params['nb_thumbs_set'] = count($current_set);
        $tpl_params['CACHE_KEYS'] = \Phyxo\Functions\Utils::getAdminClientCacheKeys(['tags', 'categories'], $em, $this->getDoctrine(), $this->generateUrl('homepage'));
        $tpl_params['ws'] = $this->generateUrl('ws');

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_batch_manager_global');
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_batch_manager_global');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Site manager', [], 'admin');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('global'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('batch_manager_global.html.twig', $tpl_params);
    }

    public function emptyCaddie(Request $request, EntityManager $em, TranslatorInterface $translator)
    {
        $em->getRepository(CaddieRepository::class)->emptyCaddie($this->getUser()->getId());
        $this->addFlash('info', $translator->trans('Caddie has been emptied', [], 'admin'));

        return $this->redirectToRoute('admin_batch_manager_global', ['start' => $request->get('start')]);
    }

    protected function actionOnCollection(Request $request, array $collection = [], EntityManager $em, TagMapper $tagMapper, ImageMapper $imageMapper, CategoryMapper $categoryMapper,
                                        UserMapper $userMapper)
    {
        // if the user tries to apply an action, it means that there is at least 1 photo in the selection
        if (count($collection) === 0 && !$request->request->get('submitFilter')) {
            $this->addFlash('error', $this->translator->trans('Select at least one photo', [], 'admin'));
            return;
        }

        $redirect = false;
        $action = $request->request->get('selectAction');

        if ($action === 'remove_from_caddie') {
            $em->getRepository(CaddieRepository::class)->deleteElements($collection, $this->getUser()->getId());
            $redirect = true;
        } elseif ($action === 'add_tags') {
            if (!$request->request->get('add_tags')) {
                $this->addFlash('error', $this->translator->trans('Select at least one tag', [], 'admin'));
            } else {
                $tag_ids = $tagMapper->getTagsIds($request->request->get('add_tags'));
                $tagMapper->addTags($tag_ids, $collection);

                if ($this->getFilter()['prefilter'] === 'no_tag') {
                    $redirect = true;
                }
            }
        } elseif ($action === 'del_tags') {
            if ($request->request->get('del_tags') && count($request->request->get('del_tags')) > 0) {
                $em->getRepository(ImageTagRepository::class)->deleteByImagesAndTags($collection, $request->request->get('del_tags'));

                if (!empty($this->getFilter()['tags']) && count(array_intersect($this->getFilter()['tags'], $request->request->get('del_tags'))) > 0) {
                    $redirect = true;
                }
            } else {
                $this->addFlash('error', $this->translator->trans('Select at least one tag', [], 'admin'));
            }
        }

        if ($action === 'associate') {
            $categoryMapper->associateImagesToCategories($collection, [$request->request->get('associate')]);

            $this->addFlash('info', $this->translator->trans('Information data registered in database', [], 'admin'));

            // let's refresh the page because we the current set might be modified
            if ($this->getFilter()['prefilter'] === 'no_album') {
                $redirect = true;
            } elseif ($this->getFilter()['prefilter'] === 'no_virtual_album') {
                $category_info = $categoryMapper->getCatInfo($request->request->get('associate'));
                if (empty($category_info['dir'])) {
                    $redirect = true;
                }
            }
        } elseif ($action === 'move') {
            $categoryMapper->moveImagesToCategories($collection, [$request->request->get('move')]);

            $this->addFlash('info', $this->translator->trans('Information data registered in database', [], 'admin'));

            // let's refresh the page because we the current set might be modified
            if ($this->getFilter()['prefilter'] === 'no_album') {
                $redirect = true;
            } elseif ($this->getFilter()['prefilter'] === 'no_virtual_album') {
                $category_info = $categoryMapper->getCatInfo($request->request->get('move'));
                if (empty($category_info['dir'])) {
                    $redirect = true;
                }
            } elseif (isset($this->getFilter()['category']) && $this->getFilter()['category'] !== $request->request->get('move')) {
                $redirect = true;
            }
        } elseif ($action === 'dissociate') {
            // physical links must not be broken, so we must first retrieve image_id
            // which create virtual links with the category to "dissociate from".
            $result = $em->getRepository(ImageRepository::class)->findImagesInVirtualCategory($collection, $request->request->get('dissociate'));
            $dissociables = $em->getConnection()->result2array($result, null, 'id');

            if (!empty($dissociables)) {
                $em->getRepository(ImageCategoryRepository::class)->deleteByCategory([$request->request->get('dissociate')], $dissociables);
                $this->addFlash('info', $this->translator->trans('Information data registered in database', [], 'admin'));

                // let's refresh the page because the current set might be modified
                $redirect = true;
            }
        } elseif ($action === 'author') {
            if ($request->request->get('remove_author')) {
                $author = null;
            } else {
                $author = $request->request->get('author');
            }

            $datas = [];
            foreach ($collection as $image_id) {
                $datas[] = [
                    'id' => $image_id,
                    'author' => $author
                ];
            }

            $em->getRepository(ImageRepository::class)->massUpdates(['primary' => ['id'], 'update' => ['author']], $datas);
        } elseif ($action === 'title') {
            if ($request->request->get('remove_title')) {
                $title = null;
            } else {
                $title = $request->request->get('title');
            }

            $datas = [];
            foreach ($collection as $image_id) {
                $datas[] = [
                    'id' => $image_id,
                    'name' => $title
                ];
            }

            $em->getRepository(ImageRepository::class)->massUpdates(['primary' => ['id'], 'update' => ['name']], $datas);
        } elseif ($action === 'date_creation') {
            if ($request->request->get('remove_date_creation') || !$request->request->get('date_creation')) {
                $date_creation = null;
            } else {
                $date_creation = $request->request->get('date_creation');
            }

            $datas = [];
            foreach ($collection as $image_id) {
                $datas[] = [
                    'id' => $image_id,
                    'date_creation' => $date_creation
                ];
            }

            $em->getRepository(ImageRepository::class)->massUpdates(['primary' => ['id'], 'update' => ['date_creation']], $datas);
        } elseif ($action === 'level') { // privacy_level
            $datas = [];
            foreach ($collection as $image_id) {
                $datas[] = [
                    'id' => $image_id,
                    'level' => $request->request->get('level')
                ];
            }

            $em->getRepository(ImageRepository::class)->massUpdates(['primary' => ['id'], 'update' => ['level']], $datas);

            if (!empty($this->getFilter()['level'])) {
                if ($request->request->get('level') < $this->getFilter()['level']) {
                    $redirect = true;
                }
            }
        } elseif ($action === 'add_to_caddie') {
            $em->getRepository(CaddieRepository::class)->fillCaddie($this->getUser()->getId(), $collection);
        } elseif ($action === 'delete') {
            if ($request->request->get('confirm_deletion') == 1) {
                $deleted_count = $imageMapper->deleteElements($collection, true);
                if ($deleted_count > 0) {
                    $this->addFlash('info', $this->translator->trans('number_of_photos_deleted', ['count' => $deleted_count], 'admin'));
                    $redirect = true;
                } else {
                    $this->addFlash('error', $this->translator->trans('No photo can be deleted', [], 'admin'));
                }
            } else {
                $this->addFlash('error', $this->translator->trans('You need to confirm deletion', [], 'admin'));
            }
        } elseif ($action === 'metadata') {
            $tagMapper->sync_metadata($collection);
            $this->addFlash('info', $this->translator->trans('Metadata synchronized from file', [], 'admin'));
        } elseif ($action === 'delete_derivatives' && $request->request->get('del_derivatives_type')) {
            $result = $em->getRepository(ImageRepository::class)->findByIds($collection);
            while ($info = $em->getConnection()->db_fetch_assoc($result)) {
                foreach ($request->request->get('del_derivatives_type') as $type) {
                    \Phyxo\Functions\Utils::delete_element_derivatives($info, $type);
                }
            }
        } elseif ($action === 'generate_derivatives') {
            if ($request->request->get('regenerateSuccess') != '0') {
                $this->addFlash('info', $this->translator->trans('{count} photos have been regenerated', ['count' => $request->request->get('regenerateSuccess')], 'admin'));
            }
            if ($request->request->get('regenerateError') != '0') {
                $this->addFlash('info', $this->translator->trans('{count} photos can not be regenerated', ['count' => $request->request->get('regenerateError')], 'admin'));
            }
        }

        if (!in_array($action, ['remove_from_caddie', 'add_to_caddie', 'delete_derivatives', 'generate_derivatives'])) {
            $userMapper->invalidateUserCache();
        }

        if ($redirect) {
            return $this->redirectToRoute('admin_batch_manager_global');
        }
    }

    protected function filterFromSession()
    {
        if (!$this->get('session')->has('bulk_manager_filter')) {
            $this->appendFilter(['prefilter' => 'caddie']);
        }
    }

    protected function getFilterSetsFromFilter(EntityManager $em, Conf $conf, SearchMapper $searchMapper, AlbumRepository $albumRepository)
    {
        $filter_sets = [];

        $bulk_manager_filter = $this->getFilter();

        if (!empty($bulk_manager_filter['prefilter'])) {
            switch ($bulk_manager_filter['prefilter']) {
                case 'caddie':
                    $result = $em->getRepository(CaddieRepository::class)->getElements($this->getUser()->getId());
                    $filter_sets[] = $em->getConnection()->result2array($result, null, 'element_id');
                    break;

                case 'favorites':
                    $result = $em->getRepository(FavoriteRepository::class)->findAll($this->getUser()->getId());
                    $filter_sets[] = $em->getConnection()->result2array($result, null, 'image_id');
                    break;

                case 'last_import':
                    if ($max_date_available = $em->getRepository(ImageRepository::class)->findMaxDateAvailable()) {
                        $result = $em->getRepository(ImageRepository::class)->findImagesFromLastImport($max_date_available);
                        $filter_sets[] = $em->getConnection()->result2array($result, null, 'id');
                    }
                    break;

                case 'no_virtual_album':
                    // we are searching elements not linked to any virtual category
                    $result = $em->getRepository(ImageRepository::class)->findAll();
                    $all_elements = $em->getConnection()->result2array($result, null, 'id');

                    $virtual_album_ids = [];
                    foreach ($albumRepository->findVirtualAlbums() as $album) {
                        $virtual_album_ids[] = $album->getId();
                    }

                    if (count($virtual_album_ids) > 0) {
                        $result = $em->getRepository(ImageCategoryRepository::class)->getImageIdsLinked($virtual_album_ids);
                        $linked_to_virtual = $em->getConnection()->result2array($result, null, 'image_id');
                    }

                    $filter_sets[] = array_diff($all_elements, $linked_to_virtual);
                    break;

                case 'no_album':
                    $result = $em->getRepository(ImageRepository::class)->findImageWithNoAlbum();
                    $filter_sets[] = $em->getConnection()->result2array($result, null, 'id');
                    break;

                case 'no_tag':
                    $result = $em->getRepository(ImageRepository::class)->findImageWithNoTag();
                    $filter_sets[] = $em->getConnection()->result2array($result, null, 'id');
                    break;

                case 'duplicates':
                    $duplicates_on_fields = ['file'];
                    if (!empty($bulk_manager_filter['duplicates_date'])) {
                        $duplicates_on_fields[] = 'date_creation';
                    }

                    if (!empty($bulk_manager_filter['duplicates_dimensions'])) {
                        $duplicates_on_fields[] = 'width';
                        $duplicates_on_fields[] = 'height';
                    }

                    $result = $em->getRepository(ImageRepository::class)->findDuplicates($duplicates_on_fields);
                    $array_of_ids_string = $em->getConnection()->result2array($result, null, 'ids');

                    $ids = [];
                    foreach ($array_of_ids_string as $ids_string) {
                        $ids = array_merge($ids, explode(',', $ids_string));
                    }

                    $filter_sets[] = $ids;
                    break;

                case 'all_photos':
                    if (count($bulk_manager_filter) === 1) { // make the query only if this is the only filter
                        $result = $em->getRepository(ImageRepository::class)->findAll($conf['order_by']);
                        $filter_sets[] = $em->getConnection()->result2array($result, null, 'id');
                    }
                    break;

                default:
                    break;
            }
        }

        if (!empty($bulk_manager_filter['category'])) {
            $album_ids = [];

            if (isset($bulk_manager_filter['category_recursive'])) {
                $album_ids = $albumRepository->getSubcatIds([$this->getFilter()['category']]);
            } else {
                $categories = [$bulk_manager_filter['category']];
            }

            $result = $em->getRepository(ImageCategoryRepository::class)->getImageIdsLinked($album_ids);
            $filter_sets[] = $em->getConnection()->result2array($result, null, 'image_id');
        }

        if (!empty($bulk_manager_filter['level'])) {
            $operator = '=';
            if (!empty($bulk_manager_filter['level_include_lower'])) {
                $operator = '<=';
            }

            $result = $em->getRepository(ImageRepository::class)->filterByField('field', $operator, $bulk_manager_filter['level'], $conf['order_by']);
            $filter_sets[] = $em->getConnection()->result2array($result, null, 'id');
        }

        if (!empty($bulk_manager_filter['tags'])) {
            $filter_sets[] = $em->getConnection()->result2array(
              $em->getRepository(TagRepository::class)->getImageIdsForTags(
                  $this->getUser(),
                  [],
                  $bulk_manager_filter['tags'],
                  $bulk_manager_filter['tag_mode'],
                  null,
                  $conf['order_by'],
                  false // we don't apply permissions in administration screens
              ),
              null,
              'id'
          );
        }

        if (!empty($bulk_manager_filter['dimension'])) {
            $where_clause = [];
            if (!empty($bulk_manager_filter['dimension']['min_width'])) {
                $where_clause[] = 'width >= ' . $bulk_manager_filter['dimension']['min_width'];
            }
            if (!empty($bulk_manager_filter['dimension']['max_width'])) {
                $where_clause[] = 'width <= ' . $bulk_manager_filter['dimension']['max_width'];
            }
            if (!empty($bulk_manager_filter['dimension']['min_height'])) {
                $where_clause[] = 'height >= ' . $bulk_manager_filter['dimension']['min_height'];
            }
            if (!empty($bulk_manager_filter['dimension']['max_height'])) {
                $where_clause[] = 'height <= ' . $bulk_manager_filter['dimension']['max_height'];
            }
            if (!empty($bulk_manager_filter['dimension']['min_ratio'])) {
                $where_clause[] = 'width/height >= ' . $bulk_manager_filter['dimension']['min_ratio'];
            }
            if (!empty($bulk_manager_filter['dimension']['max_ratio'])) {
                // max_ratio is a floor value, so must be a bit increased
                $where_clause[] = 'width/height < ' . ($bulk_manager_filter['dimension']['max_ratio'] + 0.01);
            }

            $result = $em->getRepository(ImageRepository::class)->findWithConditions($where_clause, null, null, $conf['order_by'] ?? '');
            $filter_sets[] = $em->getConnection()->result2array($result, null, 'id');
        }

        if (!empty($bulk_manager_filter['filesize'])) {
            $where_clause = [];

            if (!empty($bulk_manager_filter['filesize']['min'])) {
                $where_clause[] = 'filesize >= ' . $bulk_manager_filter['filesize']['min'] * 1024;
            }

            if (!empty($bulk_manager_filter['filesize']['max'])) {
                $where_clause[] = 'filesize <= ' . $bulk_manager_filter['filesize']['max'] * 1024;
            }

            $result = $em->getRepository(ImageRepository::class)->findWithConditions($where_clause, null, null, $conf['order_by']);
            $filter_sets[] = $em->getConnection()->result2array($result, null, 'id');
        }

        if (!empty($bulk_manager_filter['search']) && !empty($bulk_manager_filter['search']['q'])) {
            $result = $searchMapper->getQuickSearchResultsNoCache($bulk_manager_filter['search']['q'], ['permissions' => false]);
            if (!empty($result['items']) && !empty($result['qs']['unmatched_terms'])) {
                // $tpl_params ??? $template->assign('no_search_results', $result['qs']['unmatched_terms']);
            }
            $filter_sets[] = $result['items'];
        }

        return $filter_sets;
    }

    protected function setDimensions(EntityManager $em): array
    {
        $tpl_params = [];

        $widths = [];
        $heights = [];
        $ratios = [];
        $dimensions = [];

        // get all width, height and ratios
        $result = $em->getRepository(ImageRepository::class)->findAllWidthAndHeight();
        if ($em->getConnection()->db_num_rows($result)) {
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                if ($row['width'] > 0 && $row['height'] > 0) {
                    $widths[] = $row['width'];
                    $heights[] = $row['height'];
                    $ratios[] = floor($row['width'] / $row['height'] * 100) / 100;
                }
            }
        }
        if (empty($widths)) { // arbitrary values, only used when no photos on the gallery
            $widths = [600, 1920, 3500];
            $heights = [480, 1080, 2300];
            $ratios = [1.25, 1.52, 1.78];
        }

        $widths = array_unique($widths);
        $dimensions['widths'] = implode(',', $widths);
        $heights = array_unique($widths);
        $dimensions['heights'] = implode(',', $heights);
        $ratios = array_unique($ratios);
        $dimensions['ratios'] = implode(',', $ratios);


        $dimensions['bounds'] = [
            'min_width' => $widths[0],
            'max_width' => end($widths),
            'min_height' => $heights[0],
            'max_height' => end($heights),
            'min_ratio' => $ratios[0],
            'max_ratio' => end($ratios),
        ];

        // find ratio categories
        $ratio_categories = [
            'portrait' => [],
            'square' => [],
            'landscape' => [],
            'panorama' => [],
        ];

        foreach ($ratios as $ratio) {
            if ($ratio < 0.95) {
                $ratio_categories['portrait'][] = $ratio;
            } elseif ($ratio >= 0.95 and $ratio <= 1.05) {
                $ratio_categories['square'][] = $ratio;
            } elseif ($ratio > 1.05 and $ratio < 2) {
                $ratio_categories['landscape'][] = $ratio;
            } elseif ($ratio >= 2) {
                $ratio_categories['panorama'][] = $ratio;
            }
        }

        foreach (array_keys($ratio_categories) as $type) {
            if (count($ratio_categories[$type]) > 0) {
                $dimensions['ratio_' . $type] = [
                    'min' => $ratio_categories[$type][0],
                    'max' => end($ratio_categories[$type]),
                ];
            }
        }

        // selected=bound if nothing selected
        foreach (array_keys($dimensions['bounds']) as $type) {
            if (isset($this->getFilter()['dimension'][$type])) {
                $dimensions['selected'][$type] = $this->getFilter()['dimension'][$type];
            } else {
                $dimensions['selected'][$type] = $dimensions['bounds'][$type];
            }
        }

        $tpl_params['dimensions'] = $dimensions;

        $filesizes = [];
        $filesize = [];

        $result = $em->getRepository(ImageRepository::class)->findFilesize();
        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            $filesizes[] = sprintf('%.1f', $row['filesize'] / 1024);
        }

        if (empty($filesizes)) { // arbitrary values, only used when no photos on the gallery
            $filesizes = [0, 1, 2, 5, 8, 15];
        }

        $filesizes = array_unique($filesizes);
        sort($filesizes);

        // add 0.1MB to the last value, to make sure the heavier photo will be in
        // the result
        $filesizes[count($filesizes) - 1] += 0.1;
        $filesize['list'] = implode(',', $filesizes);
        $filesize['bounds'] = [
            'min' => $filesizes[0],
            'max' => end($filesizes),
        ];

        // selected=bound if nothing selected
        foreach (array_keys($filesize['bounds']) as $type) {
            if (isset($this->getFilter()['filesize'][$type])) {
                $filesize['selected'][$type] = $this->getFilter()['filesize'][$type];
            } else {
                $filesize['selected'][$type] = $filesize['bounds'][$type];
            }
        }

        $tpl_params['filesize'] = $filesize;

        return $tpl_params;
    }

    public function unit(Request $request, string $filter = null, int $start = 0, EntityManager $em, Conf $conf, ParameterBagInterface $params, SearchMapper $searchMapper, TagMapper $tagMapper,
                        ImageStandardParams $image_std_params, CategoryMapper $categoryMapper, UserMapper $userMapper, Metadata $metadata, TranslatorInterface $translator,
                        AlbumRepository $albumRepository)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST')) {
            $collection = explode(',', $request->request->get('element_ids'));

            $datas = [];
            $result = $em->getRepository(ImageRepository::class)->findByIds($collection);
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                $data = [];

                $data['id'] = $row['id'];
                $data['name'] = $request->request->get('name-' . $row['id']);
                $data['author'] = $request->request->get('author-' . $row['id']);
                $data['level'] = $request->request->get('level-' . $row['id']);

                if ($conf['allow_html_descriptions']) {
                    $data['comment'] = $request->request->get('description-' . $row['id']) ?? '';
                } else {
                    $data['comment'] = htmlentities($request->request->get('description-' . $row['id']), ENT_QUOTES, 'utf-8');
                }

                $data['date_creation'] = !empty($request->request->get('date_creation-' . $row['id'])) ? $request->request->get('date_creation-' . $row['id']) : null;
                $datas[] = $data;

                // tags management
                $tag_ids = [];
                if ($request->request->get('tags-' . $row['id'])) {
                    $tag_ids = $tagMapper->getTagsIds($request->request->get('tags-' . $row['id']));
                }
                $tagMapper->setTags($tag_ids, $row['id']);
            }

            $em->getRepository(ImageRepository::class)->massUpdates(
                [
                    'primary' => ['id'],
                    'update' => ['name', 'author', 'level', 'comment', 'date_creation']
                ],
                $datas
            );

            $this->addFlash('info', $translator->trans('Photo informations updated', [], 'admin'));
            $userMapper->invalidateUserCache();

            return $this->redirectToRoute('admin_batch_manager_unit');
        }

        $this->filterFromSession();
        $filter_sets = $this->getFilterSetsFromFilter($em, $conf, $searchMapper, $albumRepository);

        $current_set = array_shift($filter_sets);
        if (empty($current_set)) {
            $current_set = [];
        }
        foreach ($filter_sets as $set) {
            $current_set = array_intersect($current_set, $set);
        }

        $tpl_params = array_merge($tpl_params, $this->setDimensions($em));

        // privacy level
        foreach ($conf['available_permission_levels'] as $level) {
            $level_options[$level] = $translator->trans('Level ' . $level, [], 'admin');

            if (0 == $level) {
                $level_options[$level] = $translator->trans('Everybody', [], 'admin');
            }
        }
        $tpl_params['filter_level_options'] = $level_options;
        $tpl_params['filter_level_options_selected'] = isset($this->getFilter()['level']) ? $this->getFilter()['level'] : 0;

        // tags
        $filter_tags = [];
        if (!empty($this->getFilter()['tags'])) {
            $result = $em->getRepository(TagRepository::class)->findTags($this->getFilter()['tags']);
            $tags = $em->getConnection()->result2array($result);
            $filter_tags = $tagMapper->prepareTagsListForUI($tags);
        }
        $tpl_params['filter_tags'] = $filter_tags;

        // in the filter box, which category to select by default
        $selected_category = [];
        if (!empty($this->getFilter()['category'])) {
            $selected_category[] = $this->getFilter()['category'];
        } else {
            // we need to know the category in which the last photo was added
            $selected_category[] = $em->getRepository(ImageCategoryRepository::class)->getCategoryWithLastPhotoAdded();
        }
        $tpl_params['filter_category_selected'] = $selected_category;

        // Dissociate from a category : categories listed for dissociation can only
        // represent virtual links. We can't create orphans. Links to physical categories can't be broken.
        if (count($current_set) > 0) {
            $result = $em->getRepository(ImageRepository::class)->findVirtualCategoriesWithImages($current_set);
            $tpl_params['associated_categories'] = $em->getConnection()->result2array($result, 'id', 'id');

            // remove tags
            $tpl_params['associated_tags'] = $tagMapper->getCommonTags($this->getUser(), [], $current_set, -1);
        }

        // creation date
        $tpl_params['DATE_CREATION'] = !$request->request->get('date_creation') ? date('Y-m-d') . ' 00:00:00' : $request->request->get('date_creation');

        // image level options
        $tpl_params['level_options'] = \Phyxo\Functions\Utils::getPrivacyLevelOptions($conf['available_permission_levels'], $translator, 'admin');
        $tpl_params['level_options_selected'] = 0;

        // metadata
        $site_reader = new LocalSiteReader('./', $conf, $metadata); // @TODO : in conf or somewhere else but no direct path here
        $used_metadata = implode(', ', $site_reader->get_metadata_attributes());
        $tpl_params['used_metadata'] = $used_metadata;

        //derivatives
        $del_deriv_map = [];
        foreach ($image_std_params->getDefinedTypeMap() as $derivative_params) {
            $del_deriv_map[$derivative_params->type] = $translator->trans($derivative_params->type, [], 'admin');
        }
        $gen_deriv_map = $del_deriv_map;
        $del_deriv_map[ImageStandardParams::IMG_CUSTOM] = $translator->trans(ImageStandardParams::IMG_CUSTOM, [], 'admin');
        $tpl_params['del_derivatives_types'] = $del_deriv_map;
        $tpl_params['generate_derivatives_types'] = $gen_deriv_map;

        $element_ids = [];

        if ($request->get('display')) {
            $nb_images = (int) $request->get('display');
            $this->get('session')->set('global_display', $nb_images);
        } else {
            if ($this->get('session')->get('global_display')) {
                $nb_images = $this->get('session')->get('global_display');
            } else {
                $nb_images = 5;
            }
        }
        if (count($current_set) > 0) {
            $tpl_params['navbar'] = \Phyxo\Functions\Utils::createNavigationBar($this->get('router'), 'admin_batch_manager_unit', ['filter' => $filter], count($current_set), $start, $nb_images);

            $is_category = false;
            if (isset($this->getFilter()['category']) && !isset($this->getFilter()['category_recursive'])) {
                $is_category = true;
            }

            if (isset($this->getFilter()['prefilter']) && $this->getFilter()['prefilter'] === 'duplicates') {
                $conf['order_by'] = ' ORDER BY file, id';
            }

            if ($is_category) {
                $category_info = $categoryMapper->getCatInfo($this->getFilter()['category']);

                $conf['order_by'] = $conf['order_by_inside_category'];
                if (!empty($category_info['image_order'])) {
                    $conf['order_by'] = ' ORDER BY ' . $em->getConnection()->db_real_escape_string($category_info['image_order']);
                }
            }
            $result = $em->getRepository(ImageRepository::class)->findByImageIdsAndCategoryId(
                $current_set,
                $this->getFilter()['category'] ?? null,
                $conf['order_by'] ?? '  ',
                $nb_images,
                $start
            );

            // template thumbnail initialization
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                $element_ids[] = $row['id'];

                $src_image = new SrcImage($row, $conf['picture_ext']);

                $tag_result = $em->getRepository(TagRepository::class)->getTagsByImage($row['id']);
                $tags = $em->getConnection()->result2array($tag_result);
                $tag_selection = $tagMapper->prepareTagsListForUI($tags);

                $legend = \Phyxo\Functions\Utils::render_element_name($row);
                if ($legend != \Phyxo\Functions\Utils::get_name_from_file($row['file'])) {
                    $legend .= ' (' . $row['file'] . ')';
                }

                $tpl_params['elements'][] = array_merge(
                    $row,
                    [
                        'ID' => $row['id'],
                        'TN_SRC' => (new DerivativeImage($src_image, $image_std_params->getByType(ImageStandardParams::IMG_THUMB), $image_std_params))->getUrl(),
                        'FILE_SRC' => (new DerivativeImage($src_image, $image_std_params->getByType(ImageStandardParams::IMG_LARGE), $image_std_params))->getUrl(),
                        'LEGEND' => $legend,
                        'U_EDIT' => $this->generateUrl('admin_photo', ['image_id' => $row['id']]),
                        'NAME' => htmlspecialchars(@$row['name']), // @TODO: remove arobase
                        'AUTHOR' => htmlspecialchars(@$row['author']), // @TODO: remove arobase
                        'LEVEL' => !empty($row['level']) ? $row['level'] : '0',
                        'DESCRIPTION' => htmlspecialchars(@$row['comment']), // @TODO: remove arobase
                        'DATE_CREATION' => $row['date_creation'],
                        'TAGS' => $tag_selection,
                    ]
                );
            }
        }

        $tpl_params['ELEMENT_IDS'] = implode(',', $element_ids);
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_batch_manager_unit');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_batch_manager_unit');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_batch_manager_global');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Site manager', [], 'admin');
        $tpl_params['CACHE_KEYS'] = \Phyxo\Functions\Utils::getAdminClientCacheKeys(['tags', 'categories'], $em, $this->getDoctrine(), $this->generateUrl('homepage'));
        $tpl_params['ws'] = $this->generateUrl('ws');

        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('unit'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('batch_manager_unit.html.twig', $tpl_params);
    }
}
