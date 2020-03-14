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
use App\DataMapper\TagMapper;
use App\DataMapper\UserMapper;
use App\Repository\CategoryRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\RateRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Security\UserProvider;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\DerivativeParams;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\SrcImage;
use Phyxo\TabSheet\TabSheet;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class PhotoController extends AdminCommonController
{
    private $translator;

    protected function setTabsheet(string $section = 'properties', array $params = []): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('properties', $this->translator->trans('Properties', [], 'admin'), $this->generateUrl('admin_photo', $params));
        $tabsheet->add('coi', $this->translator->trans('Center of interest', [], 'admin'), $this->generateUrl('admin_photo_coi', $params), 'fa-crop');
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function edit(Request $request, int $image_id, int $category_id = null, EntityManager $em, Conf $conf, ParameterBagInterface $params, TagMapper $tagMapper,
                        ImageStandardParams $image_std_params, CategoryMapper $categoryMapper, UserMapper $userMapper, UserProvider $userProvider, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $result = $em->getRepository(CategoryRepository::class)->findByField('representative_picture_id', $image_id);
        $represented_albums = $em->getConnection()->result2array($result, null, 'id');

        if ($request->isMethod('POST')) {
            $data = [];
            $data['id'] = $image_id;
            $data['name'] = $request->request->get('name');
            $data['author'] = $request->request->get('author');
            $data['level'] = $request->request->get('level');

            if ($conf['allow_html_descriptions']) {
                $data['comment'] = $request->request->get('description');
            } else {
                $data['comment'] = htmlentities($request->request->get('description'), ENT_QUOTES, 'utf-8');
            }
            $data['date_creation'] = $request->request->get('date_creation') ?? null;

            $em->getRepository(ImageRepository::class)->updateImage($data, $data['id']);

            // time to deal with tags
            $tag_ids = [];
            if ($request->request->get('tags')) {
                $tag_ids = $tagMapper->getTagsIds($request->request->get('tags'));
            }
            $tagMapper->setTags($tag_ids, $image_id);

            // association to albums
            $categoryMapper->moveImagesToCategories([$image_id], $request->request->get('associate') ?? []);

            $userMapper->invalidateUserCache();

            // thumbnail for albums
            $no_longer_thumbnail_for = array_diff($represented_albums, $request->request->get('represent') ?? []);
            if (count($no_longer_thumbnail_for) > 0) {
                $categoryMapper->setRandomRepresentant($no_longer_thumbnail_for);
            }

            $new_thumbnail_for = array_diff($request->request->get('represent') ?? [], $represented_albums);
            if (count($new_thumbnail_for) > 0) {
                $em->getRepository(CategoryRepository::class)->updateCategories(['representative_picture_id' => $image_id], $new_thumbnail_for);
            }

            $represented_albums = $request->request->get('represent') ?? [];
            $this->addFlash('info', $translator->trans('Photo informations updated', [], 'admin'));

            return $this->redirectToRoute('admin_photo', ['image_id' => $image_id, 'category_id' => $category_id]);
        }

        // tags
        $result = $em->getRepository(TagRepository::class)->getTagsByImage($image_id, $validated = true);
        $tags = $em->getConnection()->result2array($result);
        $tag_selection = $tagMapper->prepareTagsListForUI($tags);

        // retrieving direct information about picture
        $result = $em->getRepository(ImageRepository::class)->findById($this->getUser(), [], $image_id);
        $row = $em->getConnection()->db_fetch_assoc($result);

        $storage_category_id = null;
        if (!empty($row['storage_category_id'])) {
            $storage_category_id = $row['storage_category_id'];
        }

        $image_file = $row['file'];
        $src_image = new SrcImage($row, $conf['picture_ext']);

        $tpl_params['tag_selection'] = $tag_selection;
        $tpl_params['U_SYNC'] = $this->generateUrl('admin_photo_sync_metadata', ['image_id' => $image_id, 'category_id' => $category_id]);
        $tpl_params['U_DELETE'] = $this->generateUrl('admin_photo_delete', ['image_id' => $image_id, 'category_id' => $category_id]);
        $tpl_params['PATH'] = $row['path'];
        $tpl_params['TN_SRC'] = (new DerivativeImage($src_image, $image_std_params->getByType(ImageStandardParams::IMG_THUMB), $image_std_params))->getUrl();
        $tpl_params['FILE_SRC'] = (new DerivativeImage($src_image, $image_std_params->getByType(ImageStandardParams::IMG_LARGE), $image_std_params))->getUrl();
        $tpl_params['NAME'] = $row['name'];
        $tpl_params['TITLE'] = \Phyxo\Functions\Utils::render_element_name($row);
        $tpl_params['DIMENSIONS'] = $row['width'] . ' * ' . $row['height'];
        $tpl_params['FILESIZE'] = $row['filesize'] . ' KB';
        $tpl_params['REGISTRATION_DATE'] = \Phyxo\Functions\DateTime::format_date($row['date_available']);
        $tpl_params['AUTHOR'] = $row['author'];
        $tpl_params['DATE_CREATION'] = $row['date_creation'];
        $tpl_params['DESCRIPTION'] = $row['comment'];
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_photo', ['image_id' => $image_id]);

        $added_by = 'N/A';
        $result = $em->getRepository(UserRepository::class)->findById($row['added_by']);
        while ($user_row = $em->getConnection()->db_fetch_assoc($result)) {
            $added_by = $user_row['username'];
        }

        $intro_vars = [
            'file' => $translator->trans('Original file : {file}', ['file' => $image_file], 'admin'),
            'add_date' => $translator->trans(
                'Posted {since} on {date}',
                [
                    'since' => \Phyxo\Functions\DateTime::time_since($row['date_available'], 'year'),
                    'date' => \Phyxo\Functions\DateTime::format_date($row['date_available'], ['day', 'month', 'year'])
                ],
                'admin'
            ),
            'added_by' => $translator->trans('Added by {by}', ['by' => $added_by], 'admin'),
            'size' => $row['width'] . '&times;' . $row['height'] . ' pixels, ' . sprintf('%.2f', $row['filesize'] / 1024) . 'MB',
            'stats' => $translator->trans('Visited {hit} times', ['hit' => $row['hit']], 'admin'),
            'id' => $translator->trans('Numeric identifier : {id}', ['id' => $row['id']], 'admin'),
        ];

        if ($conf['rate'] && !empty($row['rating_score'])) {
            $nb_rates = $em->getRepository(RateRepository::class)->count($image_id);
            $intro_vars['stats'] .= ', ' . $translator->trans('Rated {count} times, score : {score}', ['count' => $nb_rates, 'score' => sprintf('%.2f', $row['rating_score'])], 'admin');
        }

        $tpl_params['INTRO'] = $intro_vars;

        // image level options
        $selected_level = $row['level'];
        $tpl_params['level_options'] = \Phyxo\Functions\Utils::getPrivacyLevelOptions($conf['available_permission_levels'], $translator, 'admin');
        $tpl_params['level_options_selected'] = [$selected_level];

        // associate to albums
        $result = $em->getRepository(CategoryRepository::class)->findAll();
        $cache_categories = $em->getConnection()->result2array($result, 'id');

        $result = $em->getRepository(CategoryRepository::class)->findCategoriesForImage($image_id);
        $associated_albums = $em->getConnection()->result2array($result, 'id');

        foreach ($associated_albums as $album) {
            $name = $categoryMapper->getCatDisplayNameCache($album['uppercats']);

            if ($album['category_id'] === $storage_category_id) {
                $tpl_params['STORAGE_CATEGORY'] = $name;
            } else {
                $tpl_params['related_categories'] = $name;
            }
        }

        // jump to link
        // 1. find all linked categories that are reachable for the current user.
        // 2. if a category is available in the URL, use it if reachable
        // 3. if URL category not available or reachable, use the first reachable linked category
        // 4. if no category reachable, no jumpto link

        $result = $em->getRepository(ImageCategoryRepository::class)->findByImageId($image_id);
        $authorizeds = array_diff(
            $em->getConnection()->result2array($result, null, 'category_id'),
            $userProvider->calculatePermissions($this->getUser()->getId(), $this->getUser()->getStatus())
        );

        $url_img = '';
        if ($category_id && in_array($category_id, $authorizeds)) {
            $url_img = $this->generateUrl('picture', ['image_id' => $image_id, 'type' => 'category', 'element_id' => $category_id]);
        } else {
            $url_img = $this->generateUrl('picture', ['image_id' => $image_id, 'type' => 'category', 'element_id' => $cache_categories[$authorizeds[0]]['id']]);
        }

        if (!empty($url_img)) {
            $tpl_params['U_JUMPTO'] = $url_img;
        }

        $tpl_params['associated_albums'] = $associated_albums;
        $tpl_params['represented_albums'] = $represented_albums;
        $tpl_params['STORAGE_ALBUM'] = $storage_category_id;
        $tpl_params['CACHE_KEYS'] = \Phyxo\Functions\Utils::getAdminClientCacheKeys(['tags', 'categories'], $em, $this->generateUrl('homepage'));

        $tpl_params['F_ACTION'] = $this->generateUrl('admin_photo', ['image_id' => $image_id, 'category_id' => $category_id]);
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_photo', ['image_id' => $image_id, 'category_id' => $category_id]);
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_batch_manager_global');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Photo', [], 'admin');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('properties', ['image_id' => $image_id, 'category_id' => $category_id]), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('photo_properties.html.twig', $tpl_params);
    }

    public function delete(int $image_id, int $category_id = null, EntityManager $em, UserMapper $userMapper, ImageMapper $imageMapper, UserProvider $userProvider)
    {
        $imageMapper->deleteElements([$image_id], true);
        $userMapper->invalidateUserCache();

        // where to redirect the user now?
        // 1. if a category is available in the URL, use it
        // 2. else use the first reachable linked category
        // 3. redirect to gallery root

        if (!is_null($category_id)) {
            return $this->redirectToRoute('admin_album', ['album_id' => $category_id]);
        }

        $result = $em->getRepository(ImageCategoryRepository::class)->findByImageId($image_id);
        $authorizeds = array_diff(
            $em->getConnection()->result2array($result, null, 'category_id'),
            $userProvider->calculatePermissions($this->getUser()->getId(), $this->getUser()->getStatus())
        );

        if (!empty($authorizeds)) {
            return $this->redirectToRoute('admin_album', ['album_id' => $authorizeds[0]]);
        }

        return $this->redirectToRoute('admin_home');
    }

    public function syncMetadata(int $image_id, int $category_id = null, TagMapper $tagMapper, TranslatorInterface $translator)
    {
        $tagMapper->sync_metadata([$image_id]);
        $this->addFlash('info', $translator->trans('Metadata synchronized from file', [], 'admin'));

        return $this->redirectToRoute('admin_photo', ['image_id' => $image_id, 'category_id' => $category_id]);
    }

    public function coi(Request $request, int $image_id, int $category_id = null, ImageStandardParams $image_std_params, EntityManager $em, Conf $conf,
                        ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $result = $em->getRepository(ImageRepository::class)->findById($this->getUser(), [], $image_id);
        $row = $em->getConnection()->db_fetch_assoc($result);

        if ($request->isMethod('POST')) {
            if (strlen($request->request->get('l')) === 0) {
                $em->getRepository(ImageRepository::class)->updateImage(['coi' => null], $image_id);
            } else {
                $em->getRepository(ImageRepository::class)->updateImage(
                    [
                        'coi' => \Phyxo\Image\DerivativeParams::fraction_to_char($request->request->get('l'))
                            . \Phyxo\Image\DerivativeParams::fraction_to_char($request->request->get('t'))
                            . \Phyxo\Image\DerivativeParams::fraction_to_char($request->request->get('r'))
                            . \Phyxo\Image\DerivativeParams::fraction_to_char($request->request->get('b'))
                    ],
                    $image_id
                );
            }

            foreach ($image_std_params->getDefinedTypeMap() as $std_params) {
                if ($std_params->sizing->max_crop != 0) {
                    \Phyxo\Functions\Utils::delete_element_derivatives($row, $std_params->type);
                }
            }
            \Phyxo\Functions\Utils::delete_element_derivatives($row, ImageStandardParams::IMG_CUSTOM);

            return $this->redirectToRoute('admin_photo_coi', ['image_id' => $image_id, 'category_id' => $category_id]);
        }

        $src_image = new SrcImage($row, $conf['picture_ext']);
        $tpl_params['TITLE'] = \Phyxo\Functions\Utils::render_element_name($row);
        $tpl_params['ALT'] = $row['file'];
        $tpl_params['U_IMG'] = (new DerivativeImage($src_image, $image_std_params->getByType(ImageStandardParams::IMG_LARGE), $image_std_params))->getUrl();

        if (!empty($row['coi'])) {
            $tpl_params['coi'] = [
                'l' => DerivativeParams::char_to_fraction($row['coi'][0]),
                't' => DerivativeParams::char_to_fraction($row['coi'][1]),
                'r' => DerivativeParams::char_to_fraction($row['coi'][2]),
                'b' => DerivativeParams::char_to_fraction($row['coi'][3]),
            ];
        }

        foreach ($image_std_params->getDefinedTypeMap() as $std_params) {
            if ($std_params->sizing->max_crop != 0) {
                $derivative = new DerivativeImage($src_image, $std_params, $image_std_params);
                $tpl_params['cropped_derivatives'][] = [
                    'U_IMG' => $derivative->getUrl(),
                    'HTM_SIZE' => $derivative->get_size_htm(),
                ];
            }
        }

        $tpl_params['F_ACTION'] = $this->generateUrl('admin_photo_coi', ['image_id' => $image_id, 'category_id' => $category_id]);
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_photo_coi', ['image_id' => $image_id, 'category_id' => $category_id]);
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_batch_manager_global');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Photo', [], 'admin');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('coi', ['image_id' => $image_id, 'category_id' => $category_id]), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('photo_coi.html.twig', $tpl_params);
    }
}
