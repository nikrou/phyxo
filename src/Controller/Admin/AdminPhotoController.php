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
use App\DataMapper\TagMapper;
use App\DataMapper\UserMapper;
use App\Repository\ImageAlbumRepository;
use App\Repository\RateRepository;
use App\Repository\UserRepository;
use App\Security\AppUserService;
use App\Services\DerivativeService;
use Doctrine\Persistence\ManagerRegistry;
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\DerivativeParams;
use Phyxo\Image\ImageStandardParams;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminPhotoController extends AbstractController
{
    private TranslatorInterface $translator;

    /**
     * @param array<string, int|string|null> $params
     */
    protected function setTabsheet(string $section = 'properties', array $params = []): TabSheet
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('properties', $this->translator->trans('Properties', [], 'admin'), $this->generateUrl('admin_photo', $params));
        $tabsheet->add('coi', $this->translator->trans('Center of interest', [], 'admin'), $this->generateUrl('admin_photo_coi', $params), 'fa-crop');
        $tabsheet->select($section);

        return $tabsheet;
    }

    public function edit(
        Request $request,
        int $image_id,
        Conf $conf,
        TagMapper $tagMapper,
        AppUserService $appUserService,
        ImageStandardParams $image_std_params,
        UserMapper $userMapper,
        TranslatorInterface $translator,
        ImageMapper $imageMapper,
        UserRepository $userRepository,
        AlbumMapper $albumMapper,
        ImageAlbumRepository $imageAlbumRepository,
        RateRepository $rateRepository,
        ManagerRegistry $managerRegistry,
        int $category_id = null
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $represented_albums = [];
        foreach ($albumMapper->getRepository()->findBy(['representative_picture_id' => $image_id]) as $album) {
            $represented_albums[] = $album->getId();
        }

        $image = $imageMapper->getRepository()->find($image_id);

        if ($request->isMethod('POST')) {
            $image->setName($request->request->get('name'));
            $image->setAuthor($request->request->get('author'));
            $image->setLevel($request->request->get('level'));

            if ($conf['allow_html_descriptions']) {
                $image->setComment($request->request->get('description'));
            } else {
                $image->setComment(htmlentities($request->request->get('description'), ENT_QUOTES, 'utf-8'));
            }
            if ($request->request->get('date_creation')) {
                $image->setDateCreation(new \DateTime($request->request->get('date_creation')));
            }

            $imageMapper->getRepository()->addOrUpdateImage($image);

            // time to deal with tags
            $tag_ids = [];
            if ($request->request->get('tags')) {
                $tag_ids = $tagMapper->getTagsIds($request->request->get('tags'));
            }
            $tagMapper->setTags($tag_ids, $image_id, $appUserService->getUser());

            // association to albums
            if ($request->request->get('associate')) {
                $albumMapper->associateImagesToAlbums([$image_id], $request->request->all()['associate']);
            }

            $userMapper->invalidateUserCache();

            // thumbnail for albums
            $no_longer_thumbnail_for = array_diff($represented_albums, $request->request->get('represent') ?? []);
            if (count($no_longer_thumbnail_for) > 0) {
                $albumMapper->setRandomRepresentant($no_longer_thumbnail_for);
            }

            $new_thumbnail_for = array_diff($request->request->get('represent') ?? [], $represented_albums);
            if (count($new_thumbnail_for) > 0) {
                $albumMapper->getRepository()->updateAlbums(['representative_picture_id' => $image_id], $new_thumbnail_for);
            }

            $represented_albums = $request->request->get('represent') ?? [];
            $this->addFlash('success', $translator->trans('Photo informations updated', [], 'admin'));

            return $this->redirectToRoute('admin_photo', ['image_id' => $image_id, 'category_id' => $category_id]);
        }

        // tags
        $tags = [];
        foreach ($tagMapper->getRepository()->getTagsByImage($image_id, $validated = true) as $tag) {
            $tags[] = $tag;
        }
        $tag_selection = $tagMapper->prepareTagsListForUI($tags);

        // retrieving direct information about picture
        $storage_category_id = $image->getStorageCategoryId();

        $derivative_thumb = new DerivativeImage($image, $image_std_params->getByType(ImageStandardParams::IMG_THUMB), $image_std_params);
        $derivative_large = new DerivativeImage($image, $image_std_params->getByType(ImageStandardParams::IMG_LARGE), $image_std_params);

        $tpl_params['tag_selection'] = $tag_selection;
        $tpl_params['U_SYNC'] = $this->generateUrl('admin_photo_sync_metadata', ['image_id' => $image_id, 'category_id' => $category_id]);
        $tpl_params['U_DELETE'] = $this->generateUrl('admin_photo_delete', ['image_id' => $image_id, 'category_id' => $category_id]);
        $tpl_params['PATH'] = $image->getPath();
        $tpl_params['TN_SRC'] = $this->generateUrl('admin_media', ['path' => $image->getPathBasename(), 'derivative' => $derivative_thumb->getUrlType(), 'image_extension' => $image->getExtension()]);
        $tpl_params['FILE_SRC'] = $this->generateUrl('admin_media', ['path' => $image->getPathBasename(), 'derivative' => $derivative_large->getUrlType(), 'image_extension' => $image->getExtension()]);
        $tpl_params['NAME'] = $image->getName();
        $tpl_params['TITLE'] = Utils::render_element_name($image->toArray());
        $tpl_params['DIMENSIONS'] = $image->getWidth() . ' * ' . $image->getHeight();
        $tpl_params['FILESIZE'] = $image->getFilesize() . ' KB';
        $tpl_params['REGISTRATION_DATE'] = $image->getDateAvailable()->format('c');
        $tpl_params['AUTHOR'] = $image->getAuthor();
        $tpl_params['DATE_CREATION'] = $image->getDateCreation();
        $tpl_params['DESCRIPTION'] = $image->getComment();
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_photo', ['image_id' => $image_id]);

        $added_by = $userRepository->findOneBy(['id' => $image->getAddedBy()]);

        $intro_vars = [
            'file' => $translator->trans('Original file : {file}', ['file' => $image->getFile()], 'admin'),
            'add_date' => $translator->trans(
                'Posted {since} on {date}',
                [
                    'since' => $image->getDateAvailable()->format('Y'),
                    'date' => $image->getDateAvailable()->format('c')
                ],
                'admin'
            ),
            'added_by' => $translator->trans('Added by {by}', ['by' => is_null($added_by) ? 'N/A' : $added_by->getUsername()], 'admin'),
            'size' => $image->getWidth() . '&times;' . $image->getHeight() . ' pixels, ' . sprintf('%.2f', $image->getFilesize() / 1024) . 'MB',
            'stats' => $translator->trans('Visited {hit} times', ['hit' => $image->getHit()], 'admin'),
            'id' => $translator->trans('Numeric identifier : {id}', ['id' => $image_id], 'admin'),
        ];

        if ($conf['rate'] && $image->getRatingScore()) {
            $nb_rates = $rateRepository->count(['image' => $image_id]);
            $intro_vars['stats'] .= ', ' . $translator->trans('Rated {count} times, score : {score}', ['count' => $nb_rates, 'score' => sprintf('%.2f', $image->getRatingScore())], 'admin');
        }

        $tpl_params['INTRO'] = $intro_vars;

        // image level options
        $selected_level = $image->getLevel();
        $tpl_params['level_options'] = \Phyxo\Functions\Utils::getPrivacyLevelOptions($translator, $conf['available_permission_levels'], 'admin');
        $tpl_params['level_options_selected'] = $selected_level;

        // associate to albums
        $cache_albums = [];
        foreach ($albumMapper->getRepository()->findAll() as $album) {
            $cache_albums[$album->getId()] = $album;
        }

        $associated_albums = [];
        foreach ($albumMapper->getRepository()->findAlbumsForImage($image_id) as $album) {
            $associated_albums[] = $album->getId();
            $name = $albumMapper->getAlbumsDisplayNameCache($album->getUppercats());

            if ($album->getId() === $storage_category_id) {
                $tpl_params['STORAGE_CATEGORY'] = $name;
            } else {
                $tpl_params['related_categories'] = $name;
            }
        }

        // jump to link
        // 1. find all linked albums that are reachable for the current user.
        // 2. if an album is available in the URL, use it if reachable
        // 3. if URL album not available or reachable, use the first reachable linked album
        // 4. if no album reachable, no jumpto link

        $image_albums = [];
        foreach ($imageAlbumRepository->findBy(['image' => $image_id]) as $image_album) {
            $image_albums[] = $image_album->getAlbum()->getId();
        }

        $authorizeds = array_diff($image_albums, $appUserService->getUser()->getUserInfos()->getForbiddenAlbums());

        $url_img = '';
        if ($category_id && in_array($category_id, $authorizeds)) {
            $url_img = $this->generateUrl('picture', ['image_id' => $image_id, 'type' => 'album', 'element_id' => $category_id]);
        } else {
            if (count($authorizeds) > 0) {
                $album_id = $authorizeds[0];
                $url_img = $this->generateUrl('picture', ['image_id' => $image_id, 'type' => 'album', 'element_id' => $cache_albums[$album_id]->getId()]);
            }
        }

        if (!empty($url_img)) {
            $tpl_params['U_JUMPTO'] = $url_img;
        }

        $tpl_params['associated_albums'] = $associated_albums;
        $tpl_params['represented_albums'] = $represented_albums;
        $tpl_params['STORAGE_ALBUM'] = $storage_category_id;
        $tpl_params['CACHE_KEYS'] = Utils::getAdminClientCacheKeys($managerRegistry, ['tags', 'categories'], $this->generateUrl('homepage'));
        $tpl_params['ws'] = $this->generateUrl('ws');

        $tpl_params['F_ACTION'] = $this->generateUrl('admin_photo', ['image_id' => $image_id, 'category_id' => $category_id]);
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_photo', ['image_id' => $image_id, 'category_id' => $category_id]);
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_batch_manager_global');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Photo', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('properties', ['image_id' => $image_id, 'category_id' => $category_id]);

        return $this->render('photo_properties.html.twig', $tpl_params);
    }

    public function delete(
        int $image_id,
        AppUserService $appUserService,
        TranslatorInterface $translator,
        UserMapper $userMapper,
        ImageMapper $imageMapper,
        ImageAlbumRepository $imageAlbumRepository,
        int $category_id = null
    ): Response {
        $imageMapper->deleteElements([$image_id], true);
        $userMapper->invalidateUserCache();

        // where to redirect the user now?
        // 1. if an album is available in the URL, use it
        // 2. else use the first reachable linked album
        // 3. redirect to gallery root

        if (!is_null($category_id)) {
            return $this->redirectToRoute('admin_album', ['album_id' => $category_id]);
        }

        $image_albums = [];
        foreach ($imageAlbumRepository->findBy(['image' => $image_id]) as $image_album) {
            $image_albums[] = $image_album->getAlbum()->getId();
        }

        $authorizeds = array_diff($image_albums, $appUserService->getUser()->getUserInfos()->getForbiddenAlbums());

        if (count($authorizeds) > 0) {
            return $this->redirectToRoute('admin_album', ['album_id' => $authorizeds[0]]);
        }

        $this->addFlash('success', $translator->trans('Photo deleted', [], 'admin'));

        return $this->redirectToRoute('admin_home');
    }

    public function syncMetadata(int $image_id, AppUserService $appUserService, TagMapper $tagMapper, TranslatorInterface $translator, int $category_id = null): Response
    {
        $tagMapper->sync_metadata([$image_id], $appUserService->getUser());
        $this->addFlash('success', $translator->trans('Metadata synchronized from file', [], 'admin'));

        return $this->redirectToRoute('admin_photo', ['image_id' => $image_id, 'category_id' => $category_id]);
    }

    public function coi(
        Request $request,
        int $image_id,
        ImageStandardParams $image_std_params,
        ImageMapper $imageMapper,
        TranslatorInterface $translator,
        DerivativeService $derivativeService,
        int $category_id = null
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $image = $imageMapper->getRepository()->find($image_id);

        if ($request->isMethod('POST')) {
            if (strlen($request->request->get('l')) === 0) {
                $image->setCoi('');
            } else {
                $image->setCoi(
                    DerivativeParams::fraction_to_char($request->request->get('l'))
                            . DerivativeParams::fraction_to_char($request->request->get('t'))
                            . DerivativeParams::fraction_to_char($request->request->get('r'))
                            . DerivativeParams::fraction_to_char($request->request->get('b'))
                );
            }
            $imageMapper->getRepository()->addOrUpdateImage($image);

            foreach ($image_std_params->getDefinedTypeMap() as $std_params) {
                if ($std_params->sizing->max_crop !== 0) {
                    $derivativeService->deleteForElement($image->toArray(), $std_params->type);
                }
            }
            $derivativeService->deleteForElement($image->toArray(), ImageStandardParams::IMG_CUSTOM);

            return $this->redirectToRoute('admin_photo_coi', ['image_id' => $image_id, 'category_id' => $category_id]);
        }

        $tpl_params['TITLE'] = Utils::render_element_name($image->toArray());
        $tpl_params['ALT'] = $image->getFile();

        $derivative_large = new DerivativeImage($image, $image_std_params->getByType(ImageStandardParams::IMG_LARGE), $image_std_params);
        $tpl_params['U_IMG'] = $this->generateUrl(
            'admin_media',
            ['path' => $image->getPathBasename(), 'derivative' => $derivative_large->getUrlType(), 'image_extension' => $image->getExtension()]
        );

        if ($image->getCoi()) {
            $tpl_params['coi'] = [
                'l' => DerivativeParams::char_to_fraction($image->getCoi()[0]),
                't' => DerivativeParams::char_to_fraction($image->getCoi()[1]),
                'r' => DerivativeParams::char_to_fraction($image->getCoi()[2]),
                'b' => DerivativeParams::char_to_fraction($image->getCoi()[3]),
            ];
        }

        foreach ($image_std_params->getDefinedTypeMap() as $std_params) {
            if ($std_params->sizing->max_crop != 0) {
                $derivative = new DerivativeImage($image, $std_params, $image_std_params);
                $tpl_params['cropped_derivatives'][] = $derivative;
            }
        }

        $tpl_params['F_ACTION'] = $this->generateUrl('admin_photo_coi', ['image_id' => $image_id, 'category_id' => $category_id]);
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_photo_coi', ['image_id' => $image_id, 'category_id' => $category_id]);
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_batch_manager_global');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Photo', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('coi', ['image_id' => $image_id, 'category_id' => $category_id]);

        return $this->render('photo_coi.html.twig', $tpl_params);
    }
}
