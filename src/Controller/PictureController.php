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

namespace App\Controller;

use App\DataMapper\AlbumMapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Phyxo\Conf;
use Phyxo\MenuBar;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\SrcImage;
use Phyxo\Functions\DateTime;
use App\Repository\FavoriteRepository;
use App\DataMapper\TagMapper;
use Phyxo\Functions\URL;
use App\Repository\RateRepository;
use Phyxo\Functions\Utils;
use App\DataMapper\UserMapper;
use App\DataMapper\CommentMapper;
use App\DataMapper\ImageMapper;
use App\DataMapper\RateMapper;
use App\Metadata;
use App\Repository\ImageAlbumRepository;
use App\Security\TagVoter;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

class PictureController extends CommonController
{
    private $userMapper, $translator;
    private const VALID_COMMENT = 'valid_comment';

    public function picture(Request $request, int $image_id, string $type, string $element_id, Conf $conf, AlbumMapper $albumMapper,
                            MenuBar $menuBar, ImageStandardParams $image_std_params, TagMapper $tagMapper,
                            UserMapper $userMapper, CommentMapper $commentMapper, CsrfTokenManagerInterface $csrfTokenManager,
                            ImageMapper $imageMapper, Metadata $metadata, TranslatorInterface $translator, RateRepository $rateRepository,
                            FavoriteRepository $favoriteRepository, ImageAlbumRepository $imageAlbumRepository)
    {
        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $this->translator = $translator;
        $tpl_params = [];
        $this->conf = $conf;
        $this->userMapper = $userMapper;

        $this->image_std_params = $image_std_params;

        // @TODO : improve by verify token and redirect after changes
        if ($request->get('action') === 'edit_comment') {
            $edit_comment = $request->get('comment_to_edit');
        } elseif ($request->get('action') === 'delete_comment' && $request->get('comment_to_delete')) {
            if ($commentMapper->deleteUserComment([(int) $request->get('comment_to_delete')])) {
                $this->addFlash('info', $translator->trans('The comment has been deleted'));

                return $this->redirectToRoute($request->get('_route'), ['image_id' => $image_id, 'type' => $type, 'element_id' => $element_id]);
            }
        }

        $album = null;
        if ($type === 'list') {
            $tpl_params['TITLE'] = $translator->trans('Random photos');
            $tpl_params['items'] = [];
            foreach ($imageMapper->getRepository()->searchDistinctId($this->getUser()->getForbiddenCategories(), $conf['order_by']) as $image) {
                $tpl_params['items'][] = $image['id'];
            }
        } else {
            $album = $albumMapper->getRepository()->find((int) $element_id);
            $tpl_params['items'] = [];
            foreach ($imageMapper->getRepository()->searchDistinctIdInAlbum((int) $element_id, $this->getUser()->getForbiddenCategories(), $conf['order_by']) as $image) {
                $tpl_params['items'][] = $image['id'];
            }
        }

        if (count($tpl_params['items']) > 0) {
            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection($tpl_params['items'], $element_id, $type)
            );

            $tpl_params['derivative_params_square'] = $image_std_params->getByType(ImageStandardParams::IMG_SQUARE);
            $tpl_params['derivative_params_medium'] = $image_std_params->getByType(ImageStandardParams::IMG_MEDIUM);
            $tpl_params['derivative_params_large'] = $image_std_params->getByType(ImageStandardParams::IMG_LARGE);
            $tpl_params['derivative_params_xxlarge'] = $image_std_params->getByType(ImageStandardParams::IMG_XXLARGE);
        }

        $picture = $imageMapper->getRepository()->find($image_id)->toArray();
        $picture['src_image'] = new SrcImage($picture, $conf['picture_ext']);

        if ($conf['picture_download_icon']) {
            if ($picture['src_image']->is_original()) { // we have a photo
                if (!empty(['enabled_high'])) {
                    $picture['element_url'] = $picture['src_image']->getUrl();
                    $picture['U_DOWNLOAD'] = $this->generateUrl('action', ['image_id' => $image_id, 'part' => 'e', 'download' => 'download']);
                }
            } else { // not a pic - need download link
                $picture['download_url'] = $picture['element_url'] = \Phyxo\Functions\URL::get_element_url($picture);
            }
        }

        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('comment');
        $tpl_params['current'] = $picture;
        $tpl_params['current']['derivatives'] = $image_std_params->getAll($picture['src_image']);
        $tpl_params['type'] = $type;
        $tpl_params['element_id'] = $element_id;

        if (count($tpl_params['items']) > 0) {
            $current_index = array_search($image_id, $tpl_params['items']);
            if ($current_index > 0) {
                $tpl_params['first'] = [
                    'U_IMG' => $this->generateUrl('picture', ['image_id' => $tpl_params['items'][0], 'type' => $type, 'element_id' => $element_id]),
                ];
                $tpl_params['previous'] = [
                    'U_IMG' => $this->generateUrl('picture', ['image_id' => $tpl_params['items'][$current_index - 1], 'type' => $type, 'element_id' => $element_id]),
                ];
            }
            if ($current_index < (count($tpl_params['items']) - 1)) {
                $tpl_params['last'] = [
                    'U_IMG' => $this->generateUrl('picture', ['image_id' => $tpl_params['items'][count($tpl_params['items']) - 1], 'type' => $type, 'element_id' => $element_id]),
                ];
                $tpl_params['next'] = [
                    'U_IMG' => $this->generateUrl('picture', ['image_id' => $tpl_params['items'][$current_index + 1], 'type' => $type, 'element_id' => $element_id]),
                ];
            }

            $tpl_params['U_UP_SIZE_CSS'] = $tpl_params['current']['derivatives']['square']->get_size_css();
            $tpl_params['DISPLAY_NAV_BUTTONS'] = $conf['picture_navigation_icons'];
            $tpl_params['DISPLAY_NAV_THUMB'] = $conf['picture_navigation_thumb'];
        }

        if ($type === 'list') {
            $tpl_params['U_UP'] = $this->generateUrl('random_list', ['list' => $element_id]);
        } else {
            $tpl_params['U_UP'] = $this->generateUrl('album', ['category_id' => (int) $element_id]);
        }
        $deriv_type = $this->get('session')->has('picture_deriv') ? $this->get('session')->get('picture_deriv') : $conf['derivative_default_size'];
        $tpl_params['current']['selected_derivative'] = $tpl_params['current']['derivatives'][$deriv_type];

        $unique_derivatives = [];
        $show_original = isset($picture['element_url']);
        $added = [];
        foreach ($tpl_params['current']['derivatives'] as $_type => $derivative) {
            if ($_type == ImageStandardParams::IMG_SQUARE || $_type == ImageStandardParams::IMG_THUMB) {
                continue;
            }
            if (!array_key_exists($_type, $image_std_params->getDefinedTypeMap())) {
                continue;
            }
            $url = $derivative->getUrl();
            if (isset($added[$url])) {
                continue;
            }
            $added[$url] = 1;
            $show_original &= !($derivative->same_as_source());
            $unique_derivatives[$_type] = $derivative;
        }

        if ($show_original) {
            $tpl_params['U_ORIGINAL'] = $picture['element_url'];
        }

        $tpl_params['U_METADATA'] = $this->generateUrl('picture', ['image_id' => $image_id, 'type' => $type, 'element_id' => $element_id, 'metadata' => '']);
        $tpl_params['current']['unique_derivatives'] = $unique_derivatives;

        $tpl_params['INFO_POSTED_DATE'] = [
            'label' => DateTime::format_date($picture['date_available']),
            'url' => $this->generateUrl('calendar_categories_monthly', ['date_type' => 'posted', 'view_type' => 'calendar'])
        ];

        $tpl_params['INFO_CREATION_DATE'] = [
            'label' => DateTime::format_date($picture['date_creation']),
            'url' => $this->generateUrl('calendar_categories_monthly', ['date_type' => 'created', 'view_type' => 'calendar'])
        ];

        if (!empty($picture['author'])) {
            $tpl_params['INFO_AUTHOR'] = $picture['author'];
        }

        if (!empty($picture['comment'])) {
            $tpl_params['COMMENT_IMG'] = $picture['comment'];
        }


        $tpl_params['INFO_VISITS'] = $picture['hit'];
        $tpl_params['INFO_FILE'] = $picture['file'];
        if (!empty($picture['filesize'])) {
            $tpl_params['INFO_FILESIZE'] = $translator->trans('{size} Kb', ['size' => $picture['filesize']]);
        }
        if ($picture['src_image']->is_original() && isset($picture['width'])) {
            $tpl_params['INFO_DIMENSIONS'] = $picture['width'] . '*' . $picture['height'];
        }
        $tpl_params['display_info'] = $conf['picture_informations'];

        // admin links
        if ($userMapper->isAdmin()) {
            if (!is_null($album)) {
                $tpl_params['U_SET_AS_REPRESENTATIVE'] = $this->generateUrl('picture', ['image_id' => $image_id, 'type' => $type, 'element_id' => $element_id, 'action' => 'set_as_representative']);
            }

            $tpl_params['U_CADDIE'] = $this->generateUrl('picture', ['image_id' => $image_id, 'type' => $type, 'element_id' => $element_id, 'action' => 'add_to_caddie']);
            $tpl_params['U_PHOTO_ADMIN'] = $this->generateUrl('admin_photo', ['image_id' => $image_id, 'category_id' => (int) $element_id]);

            $tpl_params['available_permission_levels'] = Utils::getPrivacyLevelOptions($conf['available_permission_levels'], $translator);
        }

        if (!$this->getUser()->isGuest() && $conf['picture_favorite_icon']) {
            // verify if the picture is already in the favorite of the user
            $is_favorite = $favoriteRepository->isFavorite($this->getUser()->getId(), $image_id);
            $tpl_params['favorite'] = [
                'IS_FAVORITE' => $is_favorite,
                'U_FAVORITE' => $this->generateUrl(!$is_favorite ? 'add_to_favorites' : 'remove_from_favorites', ['image_id' => $image_id])
            ];
        }

        // related tags
        $tags = $tagMapper->getRelatedTags($this->getUser(), $image_id, -1);
        if (count($tags) > 0) {
            foreach ($tags as $tag) {
                $tpl_params['related_tags'][] = array_merge(
                    $tag->toArray(),
                    [
                        'URL' => $this->generateUrl('images_by_tags', ['tag_ids' => $tag->toUrl()]),
                        'U_TAG_IMAGE' => $this->generateUrl('images_by_tags', ['tag_ids' => $tag->toUrl()]),
                    ]
               );
            }
        }

        $image = $imageMapper->getRepository()->find($image_id);

        $tpl_params['TAGS_PERMISSION_ADD'] = (int) $this->isGranted(TagVoter::ADD, $image);
        $tpl_params['TAGS_PERMISSION_DELETE'] = (int) $this->isGranted(TagVoter::DELETE, $image);
        if (isset($conf['tags_existing_tags_only'])) {
            $tpl_params['TAGS_PERMISSION_ALLOW_CREATION'] = $conf['tags_existing_tags_only'] == 1 ? 0 : 1;
        } else {
            $tpl_params['TAGS_PERMISSION_ALLOW_CREATION'] = 1;
        }
        $tpl_params['USER_TAGS_WS_GETLIST'] = $this->generateUrl('ws', ['method' => 'pwg.tags.getFilteredList']);
        $tpl_params['USER_TAGS_UPDATE_SCRIPT'] = $this->generateUrl('ws', ['method' => 'pwg.images.setRelatedTags']);

        $tpl_params['related_categories'] = [];
        foreach ($imageAlbumRepository->findBy(['image' => $image_id]) as $image_album) {
            $tpl_params['related_categories'][] = $albumMapper->getBreadcrumb($image_album->getAlbum())[0];
        }

        if ($conf['rate']) {
            $tpl_params = array_merge($tpl_params, $this->addRateInfos($rateRepository, $picture, $request));
        }

        if (($conf['show_exif'] || $conf['show_iptc']) && !$picture['src_image']->is_mimetype()) {
            $tpl_params = array_merge($tpl_params, $this->addMetadataInfos($picture, $metadata));
        }

        if ($conf['activate_comments']) {
            // the picture is commentable if it belongs at least to one category which is commentable
            $errors = [];

            if ($request->isMethod('POST')) {
                $token = $request->request->get('_csrf_comment');

                if (!$this->isCsrfTokenValid(self::VALID_COMMENT, $token)) {
                    $comment_action = 'reject';
                } else {
                    $comment = [
                        'author' => $request->request->get('author'),
                        'content' => $request->request->get('content'),
                        'website_url' => $request->request->get('webiste_url'),
                        'email' => $request->request->get('email'),
                        'image_id' => $image_id,
                        'ip' => $request->getClientIp(),
                    ];

                    if ($request->get('action') === 'edit_comment' && $request->get('comment_to_edit')) {
                        $comment['comment_id'] = $request->get('comment_to_edit');
                        $comment_action = $commentMapper->updateUserComment($comment, $request->request->get('key'), $errors);
                    } else {
                        $comment_action = $commentMapper->insertUserComment($comment, $errors);
                    }
                }

                switch ($comment_action) {
                    case 'moderate':
                        $this->addFlash('info', $translator->trans('An administrator must authorize your comment before it is visible.'));
                    case 'validate':
                        $this->addFlash('info', $translator->trans('Your comment has been registered'));
                        break;
                    case 'reject':
                        $this->addFlash('error', $translator->trans('Your comment has NOT been registered because it did not pass the validation rules'));
                        break;
                    default:
                        $this->addFlash('error', 'Invalid comment action ' . $comment_action);
                }

                return $this->redirectToRoute($request->get('_route'), ['image_id' => $image_id, 'type' => $type, 'element_id' => $element_id]);
            }

            $tpl_params['COMMENT_COUNT'] = $commentMapper->getRepository()->countForImage($image_id, $userMapper->isAdmin());
            if ($tpl_params['COMMENT_COUNT'] > 0) {
                // comments order (get, session, conf)
                if ($request->get('comments_order') && in_array(strtoupper($request->get('comments_order')), ['ASC', 'DESC'])) {
                    $this->get('session')->set('comments_order', $request->get('comments_order'));
                }
                $comments_order = $this->get('session')->has('comments_order') ? $this->get('session')->get('comments_order') : $conf['comments_order'];

                $tpl_params['COMMENTS_ORDER_URL'] = $this->generateUrl(
                    'picture',
                    [
                        'image_id' => $image_id,
                        'type' => $type,
                        'element_id' => $element_id,
                        'comments_order' => ($comments_order == 'ASC' ? 'DESC' : 'ASC')
                    ]
                );
                $tpl_params['COMMENTS_ORDER_TITLE'] = $comments_order == 'ASC' ? $translator->trans('Show latest comments first') : $translator->trans('Show oldest comments first');

                foreach ($commentMapper->getRepository()->getCommentsOnImage($image_id, $comments_order, $conf['nb_comment_page'], 0, $userMapper->isAdmin()) as $comment) {
                    if ($comment->getAuthor() === 'guest') {
                        $author = $translator->trans('guest');
                    } else {
                        $author = $comment->getAuthor();
                    }

                    $email = null;
                    if ($comment->getUser()->getMailAddress()) {
                        $email = $comment->getUser()->getMailAddress();
                    } elseif ($comment->getEmail()) {
                        $email = $comment->getEmail();
                    }

                    $tpl_comment =
                        [
                            'ID' => $comment->getId(),
                            'AUTHOR' => $author,
                            'DATE' => $comment->getDate()->format('c'), // ['day_name', 'day', 'month', 'year', 'time']),
                            'CONTENT' => $comment->getContent(),
                            'WEBSITE_URL' => $comment->getWebsiteUrl(),
                        ];

                    if ($userMapper->canManageComment('delete', $comment->getUser()->getId())) {
                        $tpl_comment['U_DELETE'] = $this->generateUrl(
                            'picture',
                            [
                                'image_id' => $image_id,
                                'type' => $type,
                                'element_id' => $element_id,
                                'action' => 'delete_comment',
                                'comment_to_delete' => $comment->getId(),
                            ]
                        );
                    }
                    if ($userMapper->canManageComment('edit', $comment->getUser()->getId())) {
                        $tpl_comment['U_EDIT'] = $this->generateUrl(
                            'picture',
                            [
                                'image_id' => $image_id,
                                'type' => $type,
                                'element_id' => $element_id,
                                'action' => 'edit_comment',
                                'comment_to_edit' => $comment->getId(),
                            ]
                        );

                        if (isset($edit_comment) && ($comment->getId() === $edit_comment)) {
                            $tpl_comment['IN_EDIT'] = true;
                            $tpl_comment['CONTENT'] = $comment->getContent();
                            $tpl_comment['U_CANCEL'] = $this->generateUrl(
                                'picture',
                                [
                                    'image_id' => $image_id,
                                    'type' => $type,
                                    'element_id' => $element_id,
                                ]
                            );
                        }
                    }

                    if ($userMapper->isAdmin()) {
                        $tpl_comment['EMAIL'] = $email;

                        if ($comment->isPending()) {
                            $tpl_comment['U_VALIDATE'] = $this->generateUrl(
                                'picture',
                                [
                                    'image_id' => $image_id,
                                    'type' => $type,
                                    'element_id' => $element_id,
                                    'action' => 'validate_comment',
                                    'comment_to_validate' => $comment->getId(),
                                ]
                            );
                        }
                    }
                    $tpl_params['comments'][] = $tpl_comment;
                }
            }

            $show_add_comment_form = true;
            if (isset($edit_comment)) {
                $show_add_comment_form = false;
            }
            if ($userMapper->isGuest() && !$conf['comments_forall']) {
                $show_add_comment_form = false;
            }

            if ($show_add_comment_form) {
                $tpl_var = [
                    'F_ACTION' => $this->generateUrl('picture', ['image_id' => $image_id, 'type' => $type, 'element_id' => $element_id]),
                    'CONTENT' => '',
                    'SHOW_AUTHOR' => !$userMapper->isClassicUser(),
                    'AUTHOR_MANDATORY' => $conf['comments_author_mandatory'],
                    'AUTHOR' => '',
                    'WEBSITE_URL' => '',
                    'SHOW_EMAIL' => !$userMapper->isClassicUser() || empty($this->getUser()->getMailAddress()),
                    'EMAIL_MANDATORY' => $conf['comments_email_mandatory'],
                    'EMAIL' => '',
                    'SHOW_WEBSITE' => $conf['comments_enable_website'],
                    'KEY' => $csrfTokenManager->getToken(self::VALID_COMMENT),
                ];

                if (!empty($comment_action) && $comment_action === 'reject') {
                    foreach (['content', 'author', 'website_url', 'email'] as $k) {
                        $tpl_var[strtoupper($k)] = htmlspecialchars(stripslashes($request->request->get($k)));
                    }
                }
                $tpl_params['comment_add'] = $tpl_var;
            }
        }

        if ($type === 'list') {
            $tpl_params['TITLE'] = [[
                'url' => $this->generateUrl('random_list', ['list' => $element_id]),
                'label' => $translator->trans('Random photos'),
            ]];
        } else {
            // @TODO: assign TITLE another way
            if (count($tpl_params['related_categories']) > 1) {
                $tpl_params['TITLE'] = [$tpl_params['related_categories'][0]];
            } else {
                $tpl_params['TITLE'] = $tpl_params['related_categories'];
            }
        }

        $tpl_params['TITLE'][] = ['label' => $picture['name']];
        $tpl_params['SECTION_TITLE'] = '<a href="' . $this->generateUrl('homepage') . '">' . $translator->trans('Home') . '</a>';

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('picture.html.twig', $tpl_params);
    }

    public function picturesByTypes($image_id, $type)
    {
        return $this->forward(
            'App\Controller\PictureController::picture',
            [
                'image_id' => $image_id,
                'type' => 'category',
                'element_id' => 'n/a'
            ]
        );
    }

    public function pictureBySearch($image_id, $search_id)
    {
        return $this->forward(
            'App\Controller\PictureController::picture',
            [
                'image_id' => $image_id,
                'type' => 'search',
                'element_id' => $search_id
            ]
        );
    }

    public function pictureFromCalendar(int $image_id)
    {
        return $this->forward(
            'App\Controller\PictureController::picture',
            [
                'image_id' => $image_id,
                'type' => 'category',
                'element_id' => 'extra'
            ]
        );
    }

    protected function addRateInfos(RateRepository $rateRepository, array $picture, Request $request): array
    {
        $tpl_params = [];

        $rate_summary = ['count' => 0, 'score' => $picture['rating_score'], 'average' => null];
        if (!is_null($rate_summary['score'])) {
            $calculated_rate = $rateRepository->calculateRateSummary($picture['id']);
            $rate_summary['count'] = $calculated_rate['count'];
            $rate_summary['average'] = round($calculated_rate['average'], 2);
        }
        $tpl_params['rate_summary'] = $rate_summary;

        $user_rate = null;
        $anonymous_id = null;
        if ($this->conf['rate_anonymous'] || $this->userMapper->isClassicUser()) {
            if ($rate_summary['count'] > 0) {
                if (!$this->userMapper->isClassicUser()) {
                    $anonymous_id = $request->getClientIp();
                }

                $rate = $rateRepository->findOneBy([
                    'user' => $this->getUser()->getId(),
                    'image' => $picture['id'],
                    'anonymous_id' => $anonymous_id
                ]);
                if (!is_null($rate)) {
                    $user_rate = $rate->getRate();
                }
            }

            $tpl_params['rating'] = [
                'F_ACTION' => $this->generateUrl('picture_rate'),
                'image_id' => $picture['id'],
                'USER_RATE' => $user_rate,
                'marks' => $this->conf['rate_items']
            ];
        }

        return $tpl_params;
    }

    public function rate(Request $request, ImageMapper $imageMapper, Conf $conf, RateMapper $rateMapper)
    {
        if ($request->isMethod('POST')) {
            if (!$imageMapper->getRepository()->isAuthorizedToUser($this->getUser()->getForbiddenCategories(), $request->request->get('image_id'))) {
                return new AccessDeniedException("Cannot rate that image");
            }

            $result = $rateMapper->ratePicture($request->request->get('image_id'), $request->request->get('rating'), $request->getClientIp());

            if (is_null($result['score'])) {
                return new AccessDeniedException('Forbidden or rate not in ' . implode(',', $conf['rate_items']));
            }
        }

        return $this->redirectToRoute(
            'picture',
            ['image_id' => $request->request->get('image_id'), 'type' => $request->request->get('type'), 'element_id' => $request->request->get('element_id')]
        );
    }

    protected function addMetadataInfos(array $picture = [], Metadata $metadata): array
    {
        $tpl_params = [];

        if (($this->conf['show_exif']) && (function_exists('exif_read_data'))) {
            $exif_mapping = [];
            foreach ($this->conf['show_exif_fields'] as $field) {
                $exif_mapping[$field] = $field;
            }

            $exif = $metadata->getExifData($picture['src_image']->get_path(), $exif_mapping);

            if (count($exif) > 0) {
                $tpl_meta = [
                    'TITLE' => $this->translator->trans('EXIF Metadata'),
                    'lines' => [],
                ];

                foreach ($this->conf['show_exif_fields'] as $field) {
                    if (strpos($field, ';') === false) {
                        if (isset($exif[$field])) {
                            $key = $field;
                            $key = $this->translator->trans('exif_field_' . $field);

                            $tpl_meta['lines'][$key] = $exif[$field];
                        }
                    } else {
                        $tokens = explode(';', $field);
                        if (isset($exif[$field])) {
                            $key = $tokens[1];
                            $key = $this->translator->trans('exif_field_' . $key);

                            $tpl_meta['lines'][$key] = $exif[$field];
                        }
                    }
                }

                $tpl_params['metadata'][] = $tpl_meta;
            }
        }

        if ($this->conf['show_iptc']) {
            $iptc = $metadata->getIptcData($picture['src_image']->get_path(), $this->conf['show_iptc_mapping'], ', ');

            if (count($iptc) > 0) {
                $tpl_meta = [
                    'TITLE' => $this->translator->trans('IPTC Metadata'),
                    'lines' => [],
                ];

                foreach ($iptc as $field => $value) {
                    $key = $field;
                    $key = $this->translator->trans($field);

                    $tpl_meta['lines'][$key] = $value;
                }

                $tpl_params['metadata'][] = $tpl_meta;
            }
        }

        return $tpl_params;
    }
}
