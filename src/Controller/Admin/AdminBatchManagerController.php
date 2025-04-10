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
use App\DataMapper\SearchMapper;
use App\DataMapper\TagMapper;
use App\DataMapper\UserMapper;
use App\Entity\Caddie;
use App\Entity\User;
use App\Enum\ImageSizeType;
use App\Enum\UserPrivacyLevelType;
use App\ImageLibraryGuesser;
use App\Repository\AlbumRepository;
use App\Repository\CaddieRepository;
use App\Repository\FavoriteRepository;
use App\Repository\ImageAlbumRepository;
use App\Repository\ImageTagRepository;
use App\Repository\TagRepository;
use App\Security\AppUserService;
use App\Services\DerivativeService;
use DateTime;
use DateTimeInterface;
use Doctrine\Persistence\ManagerRegistry;
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageOptimizer;
use Phyxo\Image\ImageStandardParams;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminBatchManagerController extends AbstractController
{
    private TranslatorInterface $translator;
    private DerivativeService $derivativeService;
    private readonly User $user;

    public function __construct(AppUserService $appUserService)
    {
        $this->user = $appUserService->getUser();
    }

    protected function setTabsheet(string $section = 'global'): TabSheet
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('global', $this->translator->trans('global mode', [], 'admin'), $this->generateUrl('admin_batch_manager_global'));
        $tabsheet->add('unit', $this->translator->trans('unit mode', [], 'admin'), $this->generateUrl('admin_batch_manager_unit'));
        $tabsheet->select($section);

        return $tabsheet;
    }

    /**
     * @param array<string, int|float|bool|string|array<int>|null> $filter
     */
    protected function appendFilter(SessionInterface $session, array $filter = []): void
    {
        $previous_filter = $this->getFilter($session);
        if ($previous_filter === []) {
            $previous_filter = [];
        }

        $session->set('bulk_manager_filter', [...$previous_filter, ...$filter]);
    }

    /**
     * @return array<string, int|float|bool|string|UserPrivacyLevelType|array<int>|array<string>|null>
     */
    protected function getFilter(SessionInterface $session): array
    {
        $filter = $session->has('bulk_manager_filter') ? $session->get('bulk_manager_filter') : [];
        if (!isset($filter['prefilter'])) {
            $filter['prefilter'] = null;
        }

        return $filter;
    }

    #[Route(
        '/admin/batch/global/{start}/{filter}/{value}',
        name: 'admin_batch_manager_global',
        defaults: ['filter' => null, 'start' => 0, 'value' => null],
        requirements: ['filter' => 'caddie|favorites|last_import|album|no_album|tag|no_tag|duplicates|all_photos', 'start' => '\d+']
    )]
    public function global(
        Request $request,
        Conf $conf,
        AlbumMapper $albumMapper,
        DerivativeService $derivativeService,
        ImageStandardParams $image_std_params,
        SearchMapper $searchMapper,
        TagMapper $tagMapper,
        ImageMapper $imageMapper,
        CaddieRepository $caddieRepository,
        UserMapper $userMapper,
        TranslatorInterface $translator,
        AlbumRepository $albumRepository,
        ImageTagRepository $imageTagRepository,
        ImageAlbumRepository $imageAlbumRepository,
        FavoriteRepository $favoriteRepository,
        TagRepository $tagRepository,
        ManagerRegistry $managerRegistry,
        string $rootProjectDir,
        ImageLibraryGuesser $imageLibraryGuesser,
        ?string $filter = null,
        int $start = 0,
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;
        $this->derivativeService = $derivativeService;

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_batch_manager_global');

        $collection = [];
        $nb_thumbs_page = 0;

        $prefilters = [
            ['id' => 'caddie', 'name' => $translator->trans('Caddie', [], 'admin')],
            ['id' => 'favorites', 'name' => $translator->trans('Your favorites', [], 'admin')],
            ['id' => 'last_import', 'name' => $translator->trans('Last import', [], 'admin')],
            ['id' => 'no_album', 'name' => $translator->trans('With no album', [], 'admin')],
            ['id' => 'no_tag', 'name' => $translator->trans('With no tag', [], 'admin')],
            // ['id' => 'duplicates', 'name' => $translator->trans('Duplicates', [], 'admin')],
            ['id' => 'all_photos', 'name' => $translator->trans('All', [], 'admin')],
        ];

        if ($request->isMethod('POST')) {
            $start = 0;
            $request->getSession()->set('bulk_manager_filter', []);

            if ($request->request->get('filter_prefilter_use')) {
                $this->appendFilter($request->getSession(), ['prefilter' => $request->request->get('filter_prefilter')]);

                if ($request->request->get('filter_prefilter') === 'duplicates') {
                    if ($request->request->get('filter_duplicates_date')) {
                        $this->appendFilter($request->getSession(), ['duplicates_date' => true]);
                    }

                    if ($request->request->get('filter_duplicates_dimensions')) {
                        $this->appendFilter($request->getSession(), ['duplicates_dimensions' => true]);
                    }
                }
            }

            if ($request->request->get('filter_category_use')) {
                $this->appendFilter($request->getSession(), ['category' => $request->request->get('filter_category')]);

                if ($request->request->get('filter_category_recursive')) {
                    $this->appendFilter($request->getSession(), ['category_recursive' => true]);
                }
            }

            if ($request->request->get('filter_tags_use')) {
                $this->appendFilter($request->getSession(), ['tags' => $tagMapper->getTagsIds($request->request->get('filter_tags'))]);

                if ($request->request->get('tag_mode') && in_array($request->request->get('tag_mode'), ['AND', 'OR'])) {
                    $this->appendFilter($request->getSession(), ['tag_mode' => $request->request->get('tag_mode')]);
                }
            }

            if ($request->request->get('filter_level_use') && in_array($request->request->get('filter_level'), $conf['available_permission_levels'])) {
                $this->appendFilter($request->getSession(), ['level' => $request->request->get('filter_level')]);
                if ($request->request->get('filter_level_include_lower')) {
                    $this->appendFilter($request->getSession(), ['level_include_lower' => true]);
                }
            }

            if ($request->request->get('filter_dimension_use')) {
                foreach (['min_width', 'max_width', 'min_height', 'max_height'] as $type) {
                    if (filter_var($request->request->get('filter_dimension_' . $type), FILTER_VALIDATE_INT) !== false) {
                        $this->appendFilter($request->getSession(), ['dimension' => [$type => $request->request->get('filter_dimension_' . $type)]]);
                    }
                }

                foreach (['min_ratio', 'max_ratio'] as $type) {
                    if (filter_var($request->request->get('filter_dimension_' . $type), FILTER_VALIDATE_FLOAT) !== false) {
                        $this->appendFilter($request->getSession(), ['dimension' => [$type => $request->request->get('filter_dimension_' . $type)]]);
                    }
                }
            }

            if ($request->request->get('filter_filesize_use')) {
                foreach (['min', 'max'] as $type) {
                    if (filter_var($request->request->get('filter_filesize_' . $type), FILTER_VALIDATE_FLOAT) !== false) {
                        $this->appendFilter($request->getSession(), ['filesize' => [$type => $request->request->get('filter_filesize_' . $type)]]);
                    }
                }
            }

            if ($request->request->get('filter_search_use')) {
                $this->appendFilter($request->getSession(), ['search' => ['q' => $request->request->get('q')]]);
            }
        } elseif ($filter) {
            $request->getSession()->set('bulk_manager_filter', []);

            // filters in menu
            if ($filter === 'caddie') {
                $this->appendFilter($request->getSession(), ['prefilter' => 'caddie']);
                $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_batch_manager_global', ['filter' => 'caddie']);
            } elseif ($filter === 'last_import') {
                $this->appendFilter($request->getSession(), ['prefilter' => 'last_import']);
                $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_batch_manager_global', ['filter' => 'last_import']);
            } elseif ($filter === 'album' && $request->get('value') !== null) {
                $this->appendFilter($request->getSession(), ['category' => (int) $request->get('value')]);
                $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_batch_manager_global');
            } elseif ($filter === 'tag' && $request->get('value') !== null) {
                $this->appendFilter($request->getSession(), ['tags' => [(int) $request->get('value')]]);
                $this->appendFilter($request->getSession(), ['tag_mode' => 'AND']);
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

        $this->filterFromSession($request->getSession());
        $filter_sets = $this->getFilterSetsFromFilter($searchMapper, $imageMapper, $albumRepository, $favoriteRepository, $tagRepository, $request->getSession());

        $current_set = array_shift($filter_sets);
        if (empty($current_set)) {
            $current_set = [];
        }

        foreach ($filter_sets as $set) {
            $current_set = array_intersect($current_set, $set);
        }

        $tpl_params = array_merge($tpl_params, $this->setDimensions($imageMapper, $request->getSession()));

        // privacy level
        $level_options = [];
        foreach ($conf['available_permission_levels'] as $level) {
            $level_options[$level] = $translator->trans('Level ' . $level, [], 'admin');

            if (0 == $level) {
                $level_options[$level] = $translator->trans('Everybody', [], 'admin');
            }
        }

        $tpl_params['filter_level_options'] = $level_options;
        $tpl_params['filter_level_options_selected'] = $this->getFilter($request->getSession())['level'] ?? 0;

        // tags
        $filter_tags = [];
        if (!empty($this->getFilter($request->getSession())['tags'])) {
            $tags = [];
            foreach ($tagRepository->findBy(['id' => $this->getFilter($request->getSession())['tags']]) as $tag) {
                $tags[] = $tag;
            }

            $filter_tags = $tagMapper->prepareTagsListForUI($tags);
        }

        $tpl_params['filter_tags'] = $filter_tags;

        // in the filter box, which category to select by default
        $selected_category = [];
        if (!empty($this->getFilter($request->getSession())['category'])) {
            $selected_category[] = $this->getFilter($request->getSession())['category'];
        } else {
            // we need to know the category in which the last photo was added
            $last_image_album = $imageAlbumRepository->getAlbumWithLastPhotoAdded();
            if (!is_null($last_image_album)) {
                $selected_category[] = $last_image_album->getAlbum()->getId();
            }
        }

        $tpl_params['filter_category_selected'] = $selected_category;

        // Dissociate from an album : albums listed for dissociation can only
        // represent virtual links. We can't create orphans. Links to physical albums can't be broken.
        if ((is_countable($current_set) ? count($current_set) : 0) > 0) {
            $tpl_params['associated_categories'] = [];
            foreach ($imageMapper->getRepository()->findAlbumsWithImages($current_set) as $album) {
                $tpl_params['associated_categories'][] = $album['id'];
            }

            // remove tags
            $tpl_params['associated_tags'] = $tagMapper->getCommonTags($this->user, $current_set, -1);
        }

        // creation date
        $tpl_params['DATE_CREATION'] = $request->request->get('date_creation') ?: date('Y-m-d') . ' 00:00:00';

        // image level options
        $tpl_params['level_options'] = Utils::getPrivacyLevelOptions($translator, $conf['available_permission_levels'], 'admin');
        $tpl_params['level_options_selected'] = 0;

        // derivatives
        $del_deriv_map = [];
        foreach ($image_std_params->getDefinedTypeMap() as $derivative_params) {
            $del_deriv_map[$derivative_params->type->value] = $translator->trans($derivative_params->type->value, [], 'admin');
        }

        $gen_deriv_map = $del_deriv_map;
        $del_deriv_map[ImageSizeType::CUSTOM->value] = $translator->trans(ImageSizeType::CUSTOM->value, [], 'admin');
        $tpl_params['del_derivatives_types'] = $del_deriv_map;
        $tpl_params['generate_derivatives_types'] = $gen_deriv_map;

        if ($request->get('display')) {
            if ($request->get('display') === 'all') {
                $nb_images = is_countable($current_set) ? count($current_set) : 0;
            } else {
                $nb_images = (int) $request->get('display');
            }

            $request->getSession()->set('unit_display', $nb_images);
        } elseif ($request->getSession()->has('unit_display')) {
            $nb_images = $request->getSession()->get('unit_display');
        } else {
            $nb_images = 20;
        }

        $nb_thumbs_page = 0;

        if ((is_countable($current_set) ? count($current_set) : 0) > 0) {
            $is_category = false;
            if (isset($this->getFilter($request->getSession())['category']) && !isset($this->getFilter($request->getSession())['category_recursive'])) {
                $is_category = true;
            }

            if (isset($this->getFilter($request->getSession())['prefilter']) && $this->getFilter($request->getSession())['prefilter'] === 'duplicates') {
                $conf['order_by'] = ' ORDER BY file, id';
            }

            if ($is_category) {
                $album = $albumMapper->getRepository()->find($this->getFilter($request->getSession())['category']);

                $conf['order_by'] = $conf['order_by_inside_category'];
                if ($album->getImageOrder()) {
                    $conf['order_by'] = ' ORDER BY ' . $album->getImageOrder();
                }
            }

            $thumb_params = $image_std_params->getByType(ImageSizeType::THUMB);

            // template thumbnail initialization
            foreach ($imageMapper->getRepository()->findByImageIdsAndAlbumId(
                $current_set,
                $conf['order_by'] ?? [],
                $nb_images,
                $is_category ? ($this->getFilter($request->getSession())['category'] ?? null) : null,
                $start
            ) as $image) {
                $nb_thumbs_page++;

                $title = Utils::renderElementName($image->toArray());
                if ($title !== Utils::getNameFromFile($image->getFile())) { // @TODO: simplify. code difficult to read
                    $title .= ' (' . $image->getFile() . ')';
                }

                $derivative_thumb = new DerivativeImage($image, $thumb_params, $image_std_params);
                $derivative_large = new DerivativeImage($image, $image_std_params->getByType(ImageSizeType::LARGE), $image_std_params);

                $tpl_params['thumbnails'][] = array_merge(
                    $image->toArray(),
                    [
                        'thumb' => $this->generateUrl(
                            'admin_media',
                            ['path' => $image->getPathBasename(), 'derivative' => $derivative_thumb->getUrlType(), 'image_extension' => $image->getExtension()]
                        ),
                        'TITLE' => $title,
                        'FILE_SRC' => $this->generateUrl(
                            'admin_media',
                            ['path' => $image->getPathBasename(), 'derivative' => $derivative_large->getUrlType(), 'image_extension' => $image->getExtension()]
                        ),
                        'U_EDIT' => $this->generateUrl('admin_photo', ['image_id' => $image->getId()]),
                    ]
                );
            }

            $tpl_params['thumb_params'] = $thumb_params;
        }

        if ($request->isMethod('POST')) {
            if ($request->request->get('setSelected')) {
                $collection = $current_set;
            } elseif ($request->request->has('selection')) {
                $collection = $request->request->all('selection');
            }

            $this->actionOnCollection(
                $request,
                $tagMapper,
                $imageMapper,
                $userMapper,
                $imageAlbumRepository,
                $albumMapper,
                $caddieRepository,
                $imageTagRepository,
                $rootProjectDir,
                $imageLibraryGuesser,
                $collection,
            );
        }

        $tpl_params['START'] = $start;
        $tpl_params['IN_CADDIE'] = isset($this->getFilter($request->getSession())['prefilter']) && $this->getFilter($request->getSession())['prefilter'] === 'caddie';
        $tpl_params['U_EMPTY_CADDIE'] = $this->generateUrl('admin_batch_manager_global_empty_caddie', ['start' => $start]);
        $tpl_params['prefilters'] = $prefilters;
        $tpl_params['filter'] = $this->getFilter($request->getSession());
        $tpl_params['selection'] = $collection;
        $tpl_params['all_elements'] = $current_set;
        $tpl_params['nb_thumbs_page'] = $nb_thumbs_page;
        $tpl_params['nb_thumbs_set'] = is_countable($current_set) ? count($current_set) : 0;
        $tpl_params['CACHE_KEYS'] = Utils::getAdminClientCacheKeys($managerRegistry, ['tags', 'categories'], $this->generateUrl('homepage'));
        $tpl_params['ws'] = $this->generateUrl('ws');

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_batch_manager_global');
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_batch_manager_global');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Site manager', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('global');

        return $this->render('batch_manager_global.html.twig', $tpl_params);
    }

    #[Route(
        '/admin/batch/global/empty_caddie/{start}',
        name: 'admin_batch_manager_global_empty_caddie',
        defaults: ['start' => 0],
        requirements: ['start' => '\d+']
    )]
    public function emptyCaddie(CaddieRepository $caddieRepository, TranslatorInterface $translator, int $start = 0): Response
    {
        $caddieRepository->emptyCaddies($this->user->getId());
        $this->addFlash('success', $translator->trans('Caddie has been emptied', [], 'admin'));

        return $this->redirectToRoute('admin_batch_manager_global', ['start' => $start]);
    }

    /**
     * @param int[] $collection
     */
    protected function actionOnCollection(
        Request $request,
        TagMapper $tagMapper,
        ImageMapper $imageMapper,
        UserMapper $userMapper,
        ImageAlbumRepository $imageAlbumRepository,
        AlbumMapper $albumMapper,
        CaddieRepository $caddieRepository,
        ImageTagRepository $imageTagRepository,
        string $rootProjectDir,
        ImageLibraryGuesser $imageLibraryGuesser,
        array $collection = [],
    ): ?Response {
        // if the user tries to apply an action, it means that there is at least 1 photo in the selection
        if ($collection === [] && !$request->request->get('submitFilter')) {
            $this->addFlash('error', $this->translator->trans('Select at least one photo', [], 'admin'));

            return null;
        }

        $redirect = false;
        $action = $request->request->get('selectAction');

        if ($action === 'remove_from_caddie') {
            $caddieRepository->deleteElements($collection, $this->user->getId());
            $redirect = true;
        } elseif ($action === 'add_tags') {
            if (!$request->request->get('add_tags')) {
                $this->addFlash('error', $this->translator->trans('Select at least one tag', [], 'admin'));
            } else {
                $tag_ids = $tagMapper->getTagsIds($request->request->get('add_tags'));
                $tagMapper->addTags($tag_ids, $collection, $this->user);

                if ($this->getFilter($request->getSession())['prefilter'] === 'no_tag') {
                    $redirect = true;
                }
            }
        } elseif ($action === 'del_tags') {
            if ($request->request->has('del_tags')) {
                $imageTagRepository->deleteByImagesAndTags($collection, $request->request->all('del_tags'));

                if (!empty($this->getFilter($request->getSession())['tags']) && array_intersect($this->getFilter($request->getSession())['tags'], $request->request->all('del_tags')) !== []) {
                    $redirect = true;
                }
            } else {
                $this->addFlash('error', $this->translator->trans('Select at least one tag', [], 'admin'));
            }
        }

        if ($action === 'associate') {
            $albumMapper->associateImagesToAlbums($collection, [$request->request->get('associate')]);

            $this->addFlash('success', $this->translator->trans('Information data registered in database', [], 'admin'));

            // let's refresh the page because we the current set might be modified
            if ($this->getFilter($request->getSession())['prefilter'] === 'no_album') {
                $redirect = true;
            }
        } elseif ($action === 'move') {
            $albumMapper->moveImagesToAlbums($collection, [$request->request->get('move')]);

            $this->addFlash('success', $this->translator->trans('Information data registered in database', [], 'admin'));

            // let's refresh the page because we the current set might be modified
            if ($this->getFilter($request->getSession())['prefilter'] === 'no_album') {
                $redirect = true;
            } elseif (isset($this->getFilter($request->getSession())['category']) && $this->getFilter($request->getSession())['category'] !== $request->request->get('move')) {
                $redirect = true;
            }
        } elseif ($action === 'dissociate') {
            // physical links must not be broken, so we must first retrieve image_id
            // which create virtual links with the album to "dissociate from".
            $dissociables = [];
            foreach ($imageMapper->getRepository()->findImagesByAlbum($collection, $request->request->get('dissociate')) as $image) {
                $dissociables[] = $image->getId();
            }

            if ($dissociables !== []) {
                $imageAlbumRepository->deleteByAlbum([$request->request->get('dissociate')], $dissociables);
                $this->addFlash('success', $this->translator->trans('Information data registered in database', [], 'admin'));

                // let's refresh the page because the current set might be modified
                $redirect = true;
            }
        } elseif ($action === 'author') {
            $author = $request->request->get('remove_author') ? null : $request->request->get('author');
            $imageMapper->getRepository()->updateFieldForImages($collection, 'author', $author);
        } elseif ($action === 'title') {
            $title = $request->request->get('remove_title') ? null : $request->request->get('title');
            $imageMapper->getRepository()->updateFieldForImages($collection, 'name', $title);
        } elseif ($action === 'date_creation') {
            if ($request->request->get('remove_date_creation') || !$request->request->get('date_creation')) {
                $date_creation = null;
            } else {
                $date_creation = $request->request->get('date_creation');
            }

            $imageMapper->getRepository()->updateFieldForImages($collection, 'date_creation', new DateTime($date_creation));
        } elseif ($action === 'level') { // privacy_level
            $imageMapper->getRepository()->updateFieldForImages($collection, 'level', $request->request->get('level'));

            if (!empty($this->getFilter($request->getSession())['level']) && $request->request->get('level') < $this->getFilter($request->getSession())['level']) {
                $redirect = true;
            }
        } elseif ($action === 'add_to_caddie') {
            $userCaddies = $this->user->getCaddies();

            foreach ($imageMapper->getRepository()->findBy(['id' => $collection]) as $image) {
                $caddie = new Caddie();
                $caddie->setUser($this->user);
                $caddie->setImage($image);
                $userCaddies->add($caddie);
            }
        } elseif ($action === 'delete') {
            if ($request->request->get('confirm_deletion') == 1) {
                $deleted_count = $imageMapper->deleteElements($collection, true);
                if ($deleted_count > 0) {
                    $this->addFlash('success', $this->translator->trans('number_of_photos_deleted', ['count' => $deleted_count], 'admin'));
                    $redirect = true;
                } else {
                    $this->addFlash('error', $this->translator->trans('No photo can be deleted', [], 'admin'));
                }
            } else {
                $this->addFlash('error', $this->translator->trans('You need to confirm deletion', [], 'admin'));
            }
        } elseif ($action === 'metadata') {
            $tagMapper->sync_metadata($collection, $this->user);
            $this->addFlash('success', $this->translator->trans('Metadata synchronized from file', [], 'admin'));
        } elseif ($action === 'delete_derivatives' && $request->request->has('del_derivatives_type')) {
            foreach ($imageMapper->getRepository()->findBy($collection) as $image) {
                foreach ($request->request->all('del_derivatives_type') as $type) {
                    $this->derivativeService->deleteForElement($image->getPath(), $type);
                }
            }
        } elseif ($action === 'generate_derivatives') {
            if ($request->request->get('regenerateSuccess') != '0') {
                $this->addFlash('success', $this->translator->trans('{count} photos have been regenerated', ['count' => $request->request->get('regenerateSuccess')], 'admin'));
            }

            if ($request->request->get('regenerateError') != '0') {
                $this->addFlash('success', $this->translator->trans('{count} photos can not be regenerated', ['count' => $request->request->get('regenerateError')], 'admin'));
            }
        } elseif ($action === 'rotate_image') {
            $rotation_code = $request->request->get('rotation');

            foreach ($imageMapper->getRepository()->findBy(['id' => $collection]) as $image) {
                $image_src = sprintf('%s/%s', $rootProjectDir, $image->getPath());
                $imageOptimizer = new ImageOptimizer($image_src, $imageLibraryGuesser->getLibrary());
                $image->setRotation($rotation_code);
                $imageOptimizer->rotate(ImageOptimizer::getRotationAngleFromCode($rotation_code));
                $imageOptimizer->write($image_src);
                $imageMapper->getRepository()->addOrUpdateImage($image);
            }
        }

        if (!in_array($action, ['remove_from_caddie', 'add_to_caddie', 'delete_derivatives', 'generate_derivatives', 'rotate_image'])) {
            $userMapper->invalidateUserCache();
        }

        if ($redirect) {
            return $this->redirectToRoute('admin_batch_manager_global');
        } else {
            return null;
        }
    }

    protected function filterFromSession(SessionInterface $session): void
    {
        if (!$session->has('bulk_manager_filter')) {
            $this->appendFilter($session, ['prefilter' => 'caddie']);
        }
    }

    /**
     * @return array<int, array<int, int|string|null>|int>
     */
    protected function getFilterSetsFromFilter(
        SearchMapper $searchMapper,
        ImageMapper $imageMapper,
        AlbumRepository $albumRepository,
        FavoriteRepository $favoriteRepository,
        TagRepository $tagRepository,
        SessionInterface $session,
    ): array {
        $filter_sets = [];

        $bulk_manager_filter = $this->getFilter($session);

        if (!empty($bulk_manager_filter['prefilter'])) {
            switch ($bulk_manager_filter['prefilter']) {
                case 'caddie':
                    $filter_sets[] = $this->user->getCaddies()->map(fn (Caddie $caddie): ?int => $caddie->getImage()->getId())->toArray();

                    break;

                case 'favorites':
                    $user_favorites = [];
                    foreach ($favoriteRepository->findUserFavorites($this->user->getId(), $this->user->getUserInfos()->getForbiddenAlbums()) as $favorite) {
                        $user_favorites[] = $favorite->getImage()->getId();
                    }

                    $filter_sets[] = $user_favorites;
                    break;

                case 'last_import':
                    if (($max_date_available = $imageMapper->getRepository()->findMaxDateAvailable()) instanceof DateTimeInterface) {
                        $image_ids = [];
                        foreach ($imageMapper->getRepository()->findImagesFromLastImport($max_date_available) as $image) {
                            $image_ids[] = $image->getId();
                        }

                        $filter_sets[] = $image_ids;
                    }

                    break;

                case 'no_album':
                    $image_ids = [];
                    foreach ($imageMapper->getRepository()->findImageWithNoAlbum() as $image) {
                        $image_ids[] = $image->getId();
                    }

                    $filter_sets[] = $image_ids;
                    break;

                case 'no_tag':
                    $images_with_no_tags = [];
                    foreach ($tagRepository->findImageWithNoTag() as $tag) {
                        $images_with_no_tags[] = $tag['image_id'];
                    }

                    $filter_sets[] = $images_with_no_tags;
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
                    $array_of_ids_string = $imageMapper->getRepository()->findDuplicates($duplicates_on_fields);

                    $ids = [];
                    foreach ($array_of_ids_string as $ids_string) {
                        $ids = array_merge($ids, explode(',', $ids_string));
                    }

                    $filter_sets[] = $ids;
                    break;

                case 'all_photos':
                    if (count($bulk_manager_filter) === 1) { // make the query only if this is the only filter
                        $image_ids = [];
                        foreach ($imageMapper->getRepository()->findAll() as $image) {
                            $image_ids[] = $image->getId();
                        }

                        $filter_sets[] = $image_ids;
                    }

                    break;

                default:
                    break;
            }
        }

        if (!empty($bulk_manager_filter['category'])) {
            $image_ids = [];
            $albums = null;

            if (isset($bulk_manager_filter['category_recursive'])) {
                $albums = $albumRepository->getSubAlbums([$this->getFilter($session)['category']]);
            } else {
                $albums[] = $albumRepository->find($bulk_manager_filter['category']);
            }

            if (count($albums) > 0) {
                foreach ($albums as $album) {
                    foreach ($album->getImageAlbums() as $image_album) {
                        $image_ids[] = $image_album->getImage()->getId();
                    }
                }
            }

            $filter_sets[] = $image_ids;
        }

        if (!empty($bulk_manager_filter['level'])) {
            $operator = '=';
            if (!empty($bulk_manager_filter['level_include_lower'])) {
                $operator = '<=';
            }

            $image_ids = [];
            foreach ($imageMapper->getRepository()->filterByLevel($bulk_manager_filter['level'], $operator) as $image) {
                $image_ids[] = $image->getId();
            }

            $filter_sets[] = $image_ids;
        }

        if (!empty($bulk_manager_filter['tags'])) {
            $image_ids = [];
            foreach ($imageMapper->getRepository()->getImageIdsForTags(
                $this->user->getUserInfos()->getForbiddenAlbums(),
                $bulk_manager_filter['tags'],
                $bulk_manager_filter['tag_mode']
            ) as $image) {
                $image_ids[] = $image->getId();
            }

            $filter_sets[] = $image_ids;
        }

        if (!empty($bulk_manager_filter['dimension'])) {
            $image_ids = [];
            $images = null;

            if (!empty($bulk_manager_filter['dimension']['min_width'])) {
                $images = $imageMapper->getRepository()->findImagesByWidth($bulk_manager_filter['dimension']['min_width'], '>=');
            }

            if (!empty($bulk_manager_filter['dimension']['max_width'])) {
                $images = $imageMapper->getRepository()->findImagesByWidth($bulk_manager_filter['dimension']['max_width'], '<=');
            }

            if (!empty($bulk_manager_filter['dimension']['min_height'])) {
                $images = $imageMapper->getRepository()->findImagesByHeight($bulk_manager_filter['dimension']['min_height'], '>=');
            }

            if (!empty($bulk_manager_filter['dimension']['max_height'])) {
                $images = $imageMapper->getRepository()->findImagesByHeight($bulk_manager_filter['dimension']['max_height'], '<=');
            }

            if (!empty($bulk_manager_filter['dimension']['min_ratio'])) {
                $images = $imageMapper->getRepository()->findImagesByRatio($bulk_manager_filter['dimension']['min_ratio'], '>=');
            }

            if (!empty($bulk_manager_filter['dimension']['max_ratio'])) {
                // max_ratio is a floor value, so must be a bit increased
                $images = $imageMapper->getRepository()->findImagesByRatio($bulk_manager_filter['dimension']['max_ratio'] + 0.01, '<');
            }

            if (!is_null($images)) {
                foreach ($images as $image) {
                    $image_ids[] = $image->getId();
                }
            }

            $filter_sets[] = $image_ids;
        }

        if (!empty($bulk_manager_filter['filesize'])) {
            $image_ids = [];
            $images = null;

            if (!empty($bulk_manager_filter['filesize']['min'])) {
                $images = $imageMapper->getRepository()->findImagesByFilesize($bulk_manager_filter['filesize']['min'] * 1024, '>=');
            }

            if (!empty($bulk_manager_filter['filesize']['max'])) {
                $images = $imageMapper->getRepository()->findImagesByFilesize($bulk_manager_filter['filesize']['max'] * 1024, '<=');
            }

            if (!is_null($images)) {
                foreach ($images as $image) {
                    $image_ids[] = $image->getId();
                }
            }

            $filter_sets[] = $image_ids;
        }

        if (!empty($bulk_manager_filter['search']) && !empty($bulk_manager_filter['search']['q'])) {
            $result = $searchMapper->getQuickSearchResults($bulk_manager_filter['search']['q'], $this->user);
            // if (!empty($result['items']) && !empty($result['qs']['unmatched_terms'])) {
            //     // $tpl_params ??? $template->assign('no_search_results', $result['qs']['unmatched_terms']);
            // }
            $filter_sets[] = $result['items'];
        }

        return $filter_sets;
    }

    /**
     * @return array<string, mixed>
     */
    protected function setDimensions(ImageMapper $imageMapper, SessionInterface $session): array
    {
        $tpl_params = [];

        $widths = [];
        $heights = [];
        $ratios = [];
        $dimensions = [];
        $filesizes = [];
        $filesize = [];

        // get all width, height and ratios
        foreach ($imageMapper->getRepository()->findAll() as $image) {
            if ($image->getWidth() > 0 && $image->getHeight() > 0) {
                $widths[] = $image->getWidth();
                $heights[] = $image->getHeight();
                $ratios[] = floor($image->getWidth() / $image->getHeight() * 100) / 100;
            }

            $filesizes[] = sprintf('%.1f', $image->getFilesize() / 1024);
        }

        if ($widths === []) { // arbitrary values, only used when no photos on the gallery
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

        $tpl_params['orientations'] = [
            ['value' => 6, 'name' => $this->translator->trans('90° right', [], 'admin')],
            ['value' => 8, 'name' => $this->translator->trans('90° left', [], 'admin')],
            ['value' => 4, 'name' => $this->translator->trans('180°', [], 'admin')],
        ];

        foreach ($ratios as $ratio) {
            if ($ratio < 0.95) {
                $ratio_categories['portrait'][] = $ratio;
            } elseif ($ratio >= 0.95 && $ratio <= 1.05) {
                $ratio_categories['square'][] = $ratio;
            } elseif ($ratio > 1.05 && $ratio < 2) {
                $ratio_categories['landscape'][] = $ratio;
            } elseif ($ratio >= 2) {
                $ratio_categories['panorama'][] = $ratio;
            }
        }

        foreach (array_keys($ratio_categories) as $type) {
            if ($ratio_categories[$type] !== []) {
                $dimensions['ratio_' . $type] = [
                    'min' => $ratio_categories[$type][0],
                    'max' => end($ratio_categories[$type]),
                ];
            }
        }

        // selected=bound if nothing selected
        foreach (array_keys($dimensions['bounds']) as $type) {
            if (isset($this->getFilter($session)['dimension'][$type])) {
                $dimensions['selected'][$type] = $this->getFilter($session)['dimension'][$type];
            } else {
                $dimensions['selected'][$type] = $dimensions['bounds'][$type];
            }
        }

        $tpl_params['dimensions'] = $dimensions;

        if ($filesizes === []) { // arbitrary values, only used when no photos on the gallery
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
            if (isset($this->getFilter($session)['filesize'][$type])) {
                $filesize['selected'][$type] = $this->getFilter($session)['filesize'][$type];
            } else {
                $filesize['selected'][$type] = $filesize['bounds'][$type];
            }
        }

        $tpl_params['filesize'] = $filesize;

        return $tpl_params;
    }

    #[Route(
        '/admin/batch/unit/{start}/{filter}/{value}',
        name: 'admin_batch_manager_unit',
        defaults: ['filter' => null, 'start' => 0, 'value' => null],
        requirements: ['filter' => 'caddie|favorites|last_import|album|no_album|tag|no_tag|duplicates|all_photos', 'start' => '\d+']
    )]
    public function unit(
        Request $request,
        Conf $conf,
        SearchMapper $searchMapper,
        TagMapper $tagMapper,
        ImageStandardParams $image_std_params,
        AlbumMapper $albumMapper,
        UserMapper $userMapper,
        TranslatorInterface $translator,
        ImageMapper $imageMapper,
        AlbumRepository $albumRepository,
        ImageAlbumRepository $imageAlbumRepository,
        FavoriteRepository $favoriteRepository,
        ManagerRegistry $managerRegistry,
        ?string $filter = null,
        int $start = 0,
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        if ($request->isMethod('POST')) {
            $collection = explode(',', $request->request->get('element_ids'));

            foreach ($imageMapper->getRepository()->findBy(['id' => $collection]) as $image) {
                $image->setName($request->request->get('name-' . $image->getId()));
                $image->setAuthor($request->request->get('author-' . $image->getId()));
                $image->setLevel(UserPrivacyLevelType::from($request->request->get('level-' . $image->getId())));

                if ($conf['allow_html_descriptions']) {
                    $image->setComment($request->request->get('description-' . $image->getId()) ?? '');
                } else {
                    $image->setComment(htmlentities($request->request->get('description-' . $image->getId()), ENT_QUOTES, 'utf-8'));
                }

                $image->setDateCreation($request->request->get('date_creation-' . $image->getId()) ? new DateTime($request->request->get('date_creation-' . $image->getId())) : null);

                // tags management
                $tag_ids = [];
                if ($request->request->get('tags-' . $image->getId())) {
                    $tag_ids = $tagMapper->getTagsIds($request->request->get('tags-' . $image->getId()));
                }

                $tagMapper->setTags($tag_ids, $image->getId(), $this->user);
                $imageMapper->getRepository()->addOrUpdateImage($image);
            }

            $this->addFlash('success', $translator->trans('Photo informations updated', [], 'admin'));
            $userMapper->invalidateUserCache();

            return $this->redirectToRoute('admin_batch_manager_unit');
        }

        $this->filterFromSession($request->getSession());
        $filter_sets = $this->getFilterSetsFromFilter($searchMapper, $imageMapper, $albumRepository, $favoriteRepository, $tagMapper->getRepository(), $request->getSession());

        $current_set = array_shift($filter_sets);
        if (empty($current_set)) {
            $current_set = [];
        }

        foreach ($filter_sets as $set) {
            $current_set = array_intersect($current_set, $set);
        }

        $tpl_params = array_merge($tpl_params, $this->setDimensions($imageMapper, $request->getSession()));

        // privacy level
        $level_options = [];
        foreach ($conf['available_permission_levels'] as $level) {
            $level_options[$level] = $translator->trans('Level ' . $level, [], 'admin');

            if (0 == $level) {
                $level_options[$level] = $translator->trans('Everybody', [], 'admin');
            }
        }

        $tpl_params['filter_level_options'] = $level_options;
        $tpl_params['filter_level_options_selected'] = $this->getFilter($request->getSession())['level'] ?? 0;

        // tags
        $filter_tags = [];
        if (!empty($this->getFilter($request->getSession())['tags'])) {
            $tags = [];
            foreach ($tagMapper->getRepository()->findBy(['id' => $this->getFilter($request->getSession())['tags']]) as $tag) {
                $tags[] = $tag;
            }

            $filter_tags = $tagMapper->prepareTagsListForUI($tags);
        }

        $tpl_params['filter_tags'] = $filter_tags;

        // in the filter box, which category to select by default
        $selected_category = [];
        if (!empty($this->getFilter($request->getSession())['category'])) {
            $selected_category[] = $this->getFilter($request->getSession())['category'];
        } else {
            // we need to know the category in which the last photo was added
            $last_image_album = $imageAlbumRepository->getAlbumWithLastPhotoAdded();
            if (!is_null($last_image_album)) {
                $selected_category[] = $last_image_album->getAlbum()->getId();
            }
        }

        $tpl_params['filter_category_selected'] = $selected_category;

        // Dissociate from an album : albums listed for dissociation can only
        // represent virtual links. We can't create orphans. Links to physical albums can't be broken.
        if ((is_countable($current_set) ? count($current_set) : 0) > 0) {
            $tpl_params['associated_categories'] = [];
            foreach ($imageMapper->getRepository()->findAlbumsWithImages($current_set) as $album) {
                $tpl_params['associated_categories'][] = $album['id'];
            }

            // remove tags
            $tpl_params['associated_tags'] = $tagMapper->getCommonTags($this->user, $current_set, -1);
        }

        // creation date
        $tpl_params['DATE_CREATION'] = $request->request->get('date_creation') ?: date('Y-m-d') . ' 00:00:00';

        // image level options
        $tpl_params['level_options'] = Utils::getPrivacyLevelOptions($translator, $conf['available_permission_levels'], 'admin');
        $tpl_params['level_options_selected'] = 0;

        // derivatives
        $del_deriv_map = [];
        foreach ($image_std_params->getDefinedTypeMap() as $derivative_params) {
            $del_deriv_map[$derivative_params->type->value] = $translator->trans($derivative_params->type->value, [], 'admin');
        }

        $gen_deriv_map = $del_deriv_map;
        $del_deriv_map[ImageSizeType::CUSTOM->value] = $translator->trans(ImageSizeType::CUSTOM->value, [], 'admin');
        $tpl_params['del_derivatives_types'] = $del_deriv_map;
        $tpl_params['generate_derivatives_types'] = $gen_deriv_map;

        $element_ids = [];

        if ($request->get('display')) {
            $nb_images = (int) $request->get('display');
            $request->getSession()->set('global_display', $nb_images);
        } elseif ($request->getSession()->has('global_display')) {
            $nb_images = $request->getSession()->get('global_display');
        } else {
            $nb_images = 5;
        }

        if (count($current_set) > 0) {
            $is_category = false;
            if (isset($this->getFilter($request->getSession())['category']) && !isset($this->getFilter($request->getSession())['category_recursive'])) {
                $is_category = true;
            }

            if (isset($this->getFilter($request->getSession())['prefilter']) && $this->getFilter($request->getSession())['prefilter'] === 'duplicates') {
                $conf['order_by'] = ' ORDER BY file, id';
            }

            if ($is_category) {
                $album = $albumMapper->getRepository()->find($this->getFilter($request->getSession())['category']);

                $conf['order_by'] = $conf['order_by_inside_category'];
                if ($album->getImageOrder()) {
                    $conf['order_by'] = ' ORDER BY ' . $album->getImageOrder();
                }
            }

            // template thumbnail initialization
            foreach ($imageMapper->getRepository()->findByImageIdsAndAlbumId(
                $current_set,
                $conf['order_by'] ?? [],
                $nb_images,
                $this->getFilter($request->getSession())['category'] ?? null,
                $start
            ) as $image) {
                $element_ids[] = $image->getId();

                $tags = [];
                foreach ($tagMapper->getRepository()->getTagsByImage($image->getId()) as $tag) {
                    $tags[] = $tag;
                }

                $tag_selection = $tagMapper->prepareTagsListForUI($tags);

                $legend = Utils::renderElementName($image->toArray());
                if ($legend !== Utils::getNameFromFile($image->getFile())) {
                    $legend .= ' (' . $image->getFile() . ')';
                }

                $derivative_thumb = new DerivativeImage($image, $image_std_params->getByType(ImageSizeType::THUMB), $image_std_params);
                $derivative_large = new DerivativeImage($image, $image_std_params->getByType(ImageSizeType::LARGE), $image_std_params);

                $tpl_params['elements'][] = array_merge(
                    $image->toArray(),
                    [
                        'ID' => $image->getId(),
                        'TN_SRC' => $this->generateUrl(
                            'admin_media',
                            ['path' => $image->getPathBasename(), 'derivative' => $derivative_thumb->getUrlType(), 'image_extension' => $image->getExtension()]
                        ),
                        'FILE_SRC' => $this->generateUrl(
                            'admin_media',
                            ['path' => $image->getPathBasename(), 'derivative' => $derivative_large->getUrlType(), 'image_extension' => $image->getExtension()]
                        ),
                        'LEGEND' => $legend,
                        'U_EDIT' => $this->generateUrl('admin_photo', ['image_id' => $image->getId()]),
                        'NAME' => $image->getName(),
                        'AUTHOR' => $image->getAuthor(),
                        'LEVEL' => $image->getLevel(),
                        'DESCRIPTION' => $image->getComment(),
                        'DATE_CREATION' => $image->getDateCreation(),
                        'TAGS' => $tag_selection,
                    ]
                );
            }
        }

        $tpl_params['START'] = $start;
        $tpl_params['ELEMENT_IDS'] = implode(',', $element_ids);
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_batch_manager_unit');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_batch_manager_unit');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_batch_manager_global');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Site manager', [], 'admin');
        $tpl_params['CACHE_KEYS'] = Utils::getAdminClientCacheKeys($managerRegistry, ['tags', 'categories'], $this->generateUrl('homepage'));
        $tpl_params['ws'] = $this->generateUrl('ws');

        $tpl_params['tabsheet'] = $this->setTabsheet('unit');

        return $this->render('batch_manager_unit.html.twig', $tpl_params);
    }
}
