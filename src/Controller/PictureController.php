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

use DateTimeInterface;
use IntlDateFormatter;
use DateTime;
use App\DataMapper\AlbumMapper;
use Symfony\Component\HttpFoundation\Request;
use Phyxo\Conf;
use Phyxo\Image\ImageStandardParams;
use App\Repository\FavoriteRepository;
use App\DataMapper\TagMapper;
use App\Repository\RateRepository;
use Phyxo\Functions\Utils;
use App\DataMapper\UserMapper;
use App\DataMapper\CommentMapper;
use App\DataMapper\ImageMapper;
use App\DataMapper\RateMapper;
use App\Entity\Comment;
use App\Entity\History;
use App\Entity\Image;
use App\Entity\User;
use App\Events\HistoryEvent;
use App\Form\DeleteCommentType;
use App\Form\EditCommentType;
use App\Form\ImageCommentType;
use App\Form\ValidateCommentType;
use App\Metadata;
use App\Repository\CommentRepository;
use App\Repository\ImageAlbumRepository;
use App\Security\AppUserService;
use App\Security\TagVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PictureController extends AbstractController
{
    private UserMapper $userMapper;
    private TranslatorInterface $translator;

    /**
     * @param array{current_day?: DateTimeInterface, date_type?: string, year?: int, month?: int, day?: int} $extra
     */
    public function picture(
        Request $request,
        int $image_id,
        string $type,
        string $element_id,
        Conf $conf,
        AlbumMapper $albumMapper,
        ImageStandardParams $image_std_params,
        TagMapper $tagMapper,
        UserMapper $userMapper,
        CommentMapper $commentMapper,
        ImageMapper $imageMapper,
        Metadata $metadata,
        TranslatorInterface $translator,
        RateRepository $rateRepository,
        FavoriteRepository $favoriteRepository,
        ImageAlbumRepository $imageAlbumRepository,
        EventDispatcherInterface $eventDispatcher,
        AppUserService $appUserService,
        CommentRepository $commentRepository,
        array $extra = []
    ): Response {
        $this->translator = $translator;
        $tpl_params = [];
        $this->userMapper = $userMapper;

        $history_section = '';

        $album = null;
        $order_by = $conf['order_by'];
        if ($type === 'list') {
            $history_section = History::SECTION_LIST;
            $tpl_params['TITLE'] = $translator->trans('Random photos');
            $tpl_params['items'] = [];
            foreach ($imageMapper->getRepository()->searchDistinctId($appUserService->getUser()->getUserInfos()->getForbiddenAlbums(), $order_by) as $image) {
                $tpl_params['items'][] = $image->getId();
            }
        } elseif ($type === 'from_calendar') {
            $tpl_params['items'] = [];
            foreach ($imageMapper->getRepository()->findImagesPerDate($extra['current_day'], $extra['date_type']) as $image) {
                $tpl_params['items'][] = $image->getId();
            }
        } else {
            $history_section = History::SECTION_ALBUMS;
            $album = $albumMapper->getRepository()->find((int) $element_id);
            if (!is_null($album) && in_array($album->getId(), $appUserService->getUser()->getUserInfos()->getForbiddenAlbums())) {
                throw new AccessDeniedHttpException("Access denied to that album");
            }

            $tpl_params['items'] = [];
            foreach ($imageMapper->getRepository()->searchDistinctIdInAlbum((int) $element_id, $appUserService->getUser()->getUserInfos()->getForbiddenAlbums(), $order_by) as $image) {
                $tpl_params['items'][] = $image->getId();
            }
        }

        if ($tpl_params['items'] !== []) {
            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection($element_id, $tpl_params['items'], $type, 0, $extra)
            );

            $tpl_params['derivative_params_square'] = $image_std_params->getByType(ImageStandardParams::IMG_SQUARE);
            $tpl_params['derivative_params_medium'] = $image_std_params->getByType(ImageStandardParams::IMG_MEDIUM);
            $tpl_params['derivative_params_large'] = $image_std_params->getByType(ImageStandardParams::IMG_LARGE);
            $tpl_params['derivative_params_xxlarge'] = $image_std_params->getByType(ImageStandardParams::IMG_XXLARGE);
        }

        $image = $imageMapper->getRepository()->find($image_id);
        $picture = $image->toArray();
        $picture['image'] = $image;

        if ($conf['picture_download_icon']) {
            $picture['U_DOWNLOAD'] = $this->generateUrl('download_picture', ['image_id' => $image_id]);
        }

        $tpl_params['album'] = $album;
        $tpl_params['current'] = $picture;
        $tpl_params['current']['derivatives'] = $image_std_params->getAll($image);
        $tpl_params['type'] = $type;
        $tpl_params['element_id'] = $element_id;

        if ((is_countable($tpl_params['items']) ? count($tpl_params['items']) : 0) > 0) {
            $current_index = array_search($image_id, $tpl_params['items']);
            if ($current_index > 0) {
                if ($type === 'from_calendar') {
                    $tpl_params['first'] = [
                        'U_IMG' => $this->generateUrl(
                            'picture_from_calendar',
                            [
                                'image_id' => $tpl_params['items'][0], 'date_type' => $extra['date_type'],
                                'year' => $extra['year'], 'month' => sprintf('%02d', $extra['month']), 'day' => sprintf('%02d', $extra['day'])
                            ]
                        )
                    ];
                    ;
                    $tpl_params['previous'] = [
                        'U_IMG' => $this->generateUrl(
                            'picture_from_calendar',
                            [
                                'image_id' => $tpl_params['items'][$current_index - 1], 'date_type' => $extra['date_type'],
                                'year' => $extra['year'], 'month' => sprintf('%02d', $extra['month']), 'day' => sprintf('%02d', $extra['day'])
                            ]
                        ),
                    ];
                } else {
                    $tpl_params['first'] = [
                        'U_IMG' => $this->generateUrl('picture', ['image_id' => $tpl_params['items'][0], 'type' => $type, 'element_id' => $element_id]),
                    ];
                    $tpl_params['previous'] = [
                        'U_IMG' => $this->generateUrl('picture', ['image_id' => $tpl_params['items'][$current_index - 1], 'type' => $type, 'element_id' => $element_id]),
                    ];
                }
            }
            if ($current_index < ((is_countable($tpl_params['items']) ? count($tpl_params['items']) : 0) - 1)) {
                if ($type === 'from_calendar') {
                    $tpl_params['last'] = [
                        'U_IMG' => $this->generateUrl(
                            'picture_from_calendar',
                            [
                                'image_id' => $tpl_params['items'][(is_countable($tpl_params['items']) ? count($tpl_params['items']) : 0) - 1], 'date_type' => $extra['date_type'],
                                'year' => $extra['year'], 'month' => sprintf('%02d', $extra['month']), 'day' => sprintf('%02d', $extra['day'])
                            ]
                        ),
                    ];
                    $tpl_params['next'] = [
                        'U_IMG' => $this->generateUrl(
                            'picture_from_calendar',
                            [
                                'image_id' => $tpl_params['items'][$current_index + 1], 'date_type' => $extra['date_type'],
                                'year' => $extra['year'], 'month' => sprintf('%02d', $extra['month']), 'day' => sprintf('%02d', $extra['day'])
                            ]
                        )
                    ];
                } else {
                    $tpl_params['last'] = [
                        'U_IMG' => $this->generateUrl('picture', ['image_id' => $tpl_params['items'][(is_countable($tpl_params['items']) ? count($tpl_params['items']) : 0) - 1], 'type' => $type, 'element_id' => $element_id]),
                    ];
                    $tpl_params['next'] = [
                        'U_IMG' => $this->generateUrl('picture', ['image_id' => $tpl_params['items'][$current_index + 1], 'type' => $type, 'element_id' => $element_id]),
                    ];
                }
            }

            $tpl_params['DISPLAY_NAV_BUTTONS'] = $conf['picture_navigation_icons'];
            $tpl_params['DISPLAY_NAV_THUMB'] = $conf['picture_navigation_thumb'];
        }

        if ($type === 'list') {
            $tpl_params['U_UP'] = $this->generateUrl('random_list', ['list' => $element_id]);
        } else {
            $tpl_params['U_UP'] = $this->generateUrl('album', ['album_id' => (int) $element_id]);
        }
        $deriv_type = $request->cookies->has('picture_deriv') ? $request->cookies->get('picture_deriv') : $conf['derivative_default_size'];
        $tpl_params['current']['selected_derivative'] = $tpl_params['current']['derivatives'][$deriv_type];

        $unique_derivatives = [];
        foreach ($tpl_params['current']['derivatives'] as $_type => $derivative) {
            if ($_type === ImageStandardParams::IMG_SQUARE || $_type === ImageStandardParams::IMG_THUMB) {
                continue;
            }
            if (!array_key_exists($_type, $image_std_params->getDefinedTypeMap())) {
                continue;
            }
            $unique_derivatives[$_type] = $derivative;
        }

        $tpl_params['U_METADATA'] = $this->generateUrl('picture', ['image_id' => $image_id, 'type' => $type, 'element_id' => $element_id, 'metadata' => '']);
        $tpl_params['current']['unique_derivatives'] = $unique_derivatives;

        $fmt = new IntlDateFormatter($request->get('_locale'), IntlDateFormatter::FULL, IntlDateFormatter::NONE);
        $tpl_params['INFO_POSTED_DATE'] = [
            'label' => $fmt->format($picture['date_available']),
            'url' => $this->generateUrl(
                'calendar_by_day',
                [
                    'date_type' => 'posted', 'year' => $picture['date_available']->format('Y'),
                    'month' => $picture['date_available']->format('m'), 'day' => $picture['date_available']->format('d')
                ]
            )
        ];

        if ($picture['date_creation']) {
            $tpl_params['INFO_CREATION_DATE'] = [
                'label' => $fmt->format($picture['date_creation']),
                'url' => $this->generateUrl(
                    'calendar_by_day',
                    [
                        'date_type' => 'created', 'year' => $picture['date_creation']->format('Y'),
                        'month' => $picture['date_creation']->format('m'), 'day' => $picture['date_creation']->format('d')
                    ]
                )
            ];
        } else {
            $tpl_params['INFO_CREATION_DATE'] = [
                'label' => 'N/A',
                'url' => $this->generateUrl('calendar', ['date_type' => 'created'])
            ];
        }

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

        if (isset($picture['width'], $picture['height'])) {
            $tpl_params['INFO_DIMENSIONS'] = $picture['width'] . '*' . $picture['height'];
        }
        $tpl_params['display_info'] = $conf['picture_informations'];

        // admin links
        if ($userMapper->isAdmin()) {
            if (!is_null($album)) {
                $tpl_params['U_SET_AS_REPRESENTATIVE'] = $this->generateUrl('picture', ['image_id' => $image_id, 'type' => $type, 'element_id' => $element_id, 'action' => 'set_as_representative']);
            }

            $tpl_params['U_CADDIE'] = $this->generateUrl('picture', ['image_id' => $image_id, 'type' => $type, 'element_id' => $element_id, 'action' => 'add_to_caddie']);
            $tpl_params['U_PHOTO_ADMIN'] = $this->generateUrl('admin_photo', ['image_id' => $image_id, 'album_id' => (int) $element_id]);

            $tpl_params['available_permission_levels'] = Utils::getPrivacyLevelOptions($translator, $conf['available_permission_levels']);
        }

        if (!$appUserService->isGuest() && $conf['picture_favorite_icon']) {
            // verify if the picture is already in the favorite of the user
            $is_favorite = $favoriteRepository->isFavorite($appUserService->getUser()->getId(), $image_id);
            $tpl_params['favorite'] = [
                'IS_FAVORITE' => $is_favorite,
                'U_FAVORITE' => $this->generateUrl($is_favorite ? 'remove_from_favorites' : 'add_to_favorites', ['image_id' => $image_id])
            ];
        }

        // related tags
        $tags = $tagMapper->getRelatedTags($appUserService->getUser(), $image_id, -1);
        foreach ($tags as $tag) {
            $tpl_params['related_tags'][] = array_merge(
                $tag->toArray(),
                [
                    'URL' => $this->generateUrl('images_by_tags', ['tag_ids' => $tag->toUrl()]),
                    'U_TAG_IMAGE' => $this->generateUrl('images_by_tags', ['tag_ids' => $tag->toUrl()]),
                ]
            );
        }

        $image = $imageMapper->getRepository()->find($image_id);
        $image->setHit($image->getHit() + 1);
        $imageMapper->getRepository()->addOrUpdateImage($image);

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
            $tpl_params = array_merge($tpl_params, $this->addRateInfos($rateRepository, $conf, $image, $request, $appUserService->getUser()));
        }

        if (($conf['show_exif'] || $conf['show_iptc'])) {
            $tpl_params = array_merge($tpl_params, $this->addMetadataInfos($metadata, $conf, $picture['path']));
        }

        if ($conf['activate_comments']) {
            $tpl_params['COMMENT_COUNT'] = $commentMapper->getRepository()->countForImage($image_id, $userMapper->isAdmin());

            if ($tpl_params['COMMENT_COUNT'] > 0) {
                $commentsOrder = 'DESC';
                $redirectRoute = $this->generateUrl('picture', ['image_id' => $image_id, 'type' => $type, 'element_id' => $element_id]);

                foreach ($commentMapper->getRepository()->getCommentsOnImage($image_id, $commentsOrder, $conf['nb_comment_page'], 0, $userMapper->isAdmin()) as $comment) {
                    $tpl_comment = [
                        'comment' => $comment,
                        'image_url' => $this->generateUrl(
                            'picture',
                            [
                                'image_id' => $comment->getImage()->getId(),
                                'element_id' => $comment->getImage()->getImageAlbums()->first()->getAlbum()->getId(),
                                'type' => 'album'
                            ]
                        )

                    ];

                    $tpl_comment['IN_EDIT'] = false;

                    if ($appUserService->canManageComment('edit', $comment->getUser()->getId())) {
                        if ($request->query->get('comment_id') && $request->query->get('comment_id') == $comment->getId()) {
                            $tpl_comment['IN_EDIT'] = true;
                            $editForm = $this->createForm(
                                EditCommentType::class,
                                $comment,
                                [
                                    'redirect' => $redirectRoute,
                                    'action' => $this->generateUrl('comment_update', ['comment_id' => $comment->getId()])
                                ]
                            );
                            $tpl_comment['FORM'] = $editForm->createView();
                            $tpl_comment['U_CANCEL'] = $redirectRoute;
                        }

                        $tpl_comment['U_EDIT'] = $this->generateUrl(
                            'picture',
                            [
                                '_fragment' => 'comment-' . $comment->getId(),
                                'comment_id' => $comment->getId(),
                                'image_id' => $image_id,
                                'type' => $type,
                                'element_id' => $element_id
                            ]
                        );
                    }

                    if ($appUserService->canManageComment('validate', $comment->getUser()->getId()) && !$comment->isValidated()) {
                        $validateForm = $this->createForm(
                            ValidateCommentType::class,
                            $comment,
                            [
                                'id' => $comment->getId(),
                                'redirect' => $redirectRoute,
                                'action' => $this->generateUrl('comment_validate', ['comment_id' => $comment->getId()])
                            ]
                        );
                        $tpl_comment['VALIDATE_FORM'] = $validateForm->createView();
                    }

                    if ($appUserService->canManageComment('delete', $comment->getUser()->getId())) {
                        $deleteForm = $this->createForm(
                            DeleteCommentType::class,
                            $comment,
                            [
                                'id' => $comment->getId(),
                                'redirect' => $redirectRoute,
                                'action' => $this->generateUrl('comment_delete', ['comment_id' => $comment->getId()])
                            ]
                        );

                        $tpl_comment['DELETE_FORM'] = $deleteForm->createView();
                    }

                    $tpl_params['comments'][] = $tpl_comment;
                }

                if ($tpl_params['COMMENT_COUNT'] > $conf['comments_page_nb_comments']) {
                    $tpl_params['MORE_COMMENTS'] = $this->generateUrl('comments', ['album' => $element_id]);
                }
            }

            $tpl_params['show_add_comment_form'] = true;
            if ($appUserService->isGuest() && !$conf['comments_forall']) {
                $tpl_params['show_add_comment_form'] = false;
            }

            if ($tpl_params['show_add_comment_form']) {
                $comment_form = $this->createForm(ImageCommentType::class);
                $comment_form->handleRequest($request);

                if ($comment_form->isSubmitted() && $comment_form->isValid()) {
                    $imageCommentModel = $comment_form->getData();

                    $validated = !$conf['comments_validation'] || $appUserService->isAdmin();

                    $comment = new Comment();
                    $comment->setContent($imageCommentModel->getContent());
                    $comment->setEmail($imageCommentModel->getMailAddress());
                    $comment->setAuthor($imageCommentModel->getAuthor());
                    $comment->setWebsiteUrl($imageCommentModel->getWebsiteUrl());
                    $comment->setImage($image);
                    $comment->setUser($appUserService->getUser());
                    $comment->setAnonymousId(md5((string) $request->getClientIp()));
                    $comment->setDate(new DateTime());
                    $comment->setValidated($validated);
                    if ($validated) {
                        $comment->setValidationDate(new DateTime());
                    }
                    $commentRepository->addOrUpdateComment($comment);

                    if ($validated) {
                        $this->addFlash('success', $translator->trans('Your comment has been registered'));
                    } else {
                        $this->addFlash('success', $translator->trans('An administrator must authorize your comment before it is visible.'));
                    }

                    return $this->redirectToRoute('picture', ['image_id' => $image_id, 'type' => $type, 'element_id' => $element_id]);
                }

                $tpl_params['comment_form'] = $comment_form->createView();
            }
        }

        if ($type === 'list') {
            $tpl_params['TITLE'] = [[
                'url' => $this->generateUrl('random_list', ['list' => $element_id]),
                'label' => $translator->trans('Random photos'),
            ]];
        } elseif ((is_countable($tpl_params['related_categories']) ? count($tpl_params['related_categories']) : 0) > 1) {
            // @TODO: assign TITLE another way
            $tpl_params['TITLE'] = [$tpl_params['related_categories'][0]];
        } else {
            $tpl_params['TITLE'] = $tpl_params['related_categories'];
        }

        $historyEvent = new HistoryEvent($history_section);
        $historyEvent->setImage($image);
        if (!is_null($album)) {
            $historyEvent->setAlbum($album);
        }

        if ($tags !== []) {
            $historyEvent->setTagIds(
                implode(
                    ',',
                    array_map(
                        fn ($tag) => $tag->getId(),
                        $tags
                    )
                )
            );
        }
        $historyEvent->setIp($request->getClientIp());
        $eventDispatcher->dispatch($historyEvent);

        $tpl_params['TITLE'][] = ['label' => $picture['name']];
        $tpl_params['SECTION_TITLE'] = '<a href="' . $this->generateUrl('homepage') . '">' . $translator->trans('Home') . '</a>';

        return $this->render('picture.html.twig', $tpl_params);
    }

    public function picturesByTypes(int $image_id, string $type): Response
    {
        return $this->forward(
            'App\Controller\PictureController::picture',
            [
                'image_id' => $image_id,
                'type' => 'album',
                'element_id' => 'n/a'
            ]
        );
    }

    public function pictureBySearch(int $image_id, int $search_id): Response
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

    public function pictureFromCalendar(int $image_id, int $year, int $month, int $day, string $date_type): Response
    {
        $current_day = new DateTime(sprintf('%d-%02d-%02d', $year, $month, $day));

        return $this->forward(
            'App\Controller\PictureController::picture',
            [
                'image_id' => $image_id,
                'type' => 'from_calendar',
                'element_id' => 'extra',
                'extra' => ['year' => $year, 'month' => $month, 'day' => $day, 'current_day' => $current_day, 'date_type' => $date_type]
            ]
        );
    }

    protected function addRateInfos(RateRepository $rateRepository, Conf $conf, Image $image, Request $request, ?User $user): array
    {
        $tpl_params = [];

        $rate_summary = ['count' => 0, 'score' => $image->getRatingScore(), 'average' => null];
        if (!is_null($rate_summary['score'])) {
            $calculated_rate = $rateRepository->calculateRateSummary($image->getId());
            $rate_summary['count'] = $calculated_rate['count'];
            $rate_summary['average'] = round($calculated_rate['average'], 2);
        }
        $tpl_params['rate_summary'] = $rate_summary;

        $user_rate = null;
        $anonymous_id = null;
        if ($conf['rate_anonymous'] || $this->userMapper->isClassicUser()) {
            if ($rate_summary['count'] > 0) {
                if (!$this->userMapper->isClassicUser()) {
                    $anonymous_id = $request->getClientIp();
                }

                $rate = $rateRepository->findOneBy([
                    'user' => $user->getId(),
                    'image' => $image->getId(),
                    'anonymous_id' => $anonymous_id
                ]);
                if (!is_null($rate)) {
                    $user_rate = $rate->getRate();
                }
            }

            $tpl_params['rating'] = [
                'F_ACTION' => $this->generateUrl('picture_rate'),
                'image_id' => $image->getId(),
                'USER_RATE' => $user_rate,
                'marks' => $conf['rate_items']
            ];
        }

        return $tpl_params;
    }

    public function rate(Request $request, ImageMapper $imageMapper, Conf $conf, RateMapper $rateMapper, AppUserService $appUserService): Response
    {
        $result = [];
        $result['score'] = null;

        if ($request->isMethod('POST')) {
            if (!$imageMapper->getRepository()->isAuthorizedToUser($request->request->get('image_id'), $appUserService->getUser()->getUserInfos()->getForbiddenAlbums())) {
                throw new AccessDeniedException("Cannot rate that image");
            }

            if (!$appUserService->isGuest() || $conf['rate_anonymous']) {
                $result = $rateMapper->ratePicture(
                    $request->request->get('image_id'),
                    $request->request->get('rating'),
                    $request->getClientIp(),
                    $request->cookies->has('anonymous_rater') ? $request->cookies->get('anonymous_rater') : $request->getClientIp()
                );
            }

            if (is_null($result['score'])) {
                throw new AccessDeniedException('Forbidden or rate not in ' . implode(',', $conf['rate_items']));
            }
        }

        $response = new RedirectResponse(
            $this->generateUrl(
                'picture',
                ['image_id' => $request->request->get('image_id'), 'type' => $request->request->get('type'), 'element_id' => $request->request->get('element_id')]
            )
        );
        if (!is_null($result['score']) && $appUserService->isGuest()) {
            $cookie = Cookie::create('anonymous_rater', $request->getClientIp(), strtotime('+1year'));
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    protected function addMetadataInfos(Metadata $metadata, Conf $conf, string $path): array
    {
        $tpl_params = [];

        if (($conf['show_exif']) && (function_exists('exif_read_data'))) {
            $exif_mapping = [];
            foreach ($conf['show_exif_fields'] as $field) {
                $exif_mapping[$field] = $field;
            }

            $exif = $metadata->getExifData($path, $exif_mapping);

            if ($exif !== []) {
                $tpl_meta = [
                    'TITLE' => $this->translator->trans('EXIF Metadata'),
                    'lines' => [],
                ];

                foreach ($conf['show_exif_fields'] as $field) {
                    if (!str_contains((string) $field, ';')) {
                        if (isset($exif[$field])) {
                            $key = $this->translator->trans('exif_field_' . $field);

                            $tpl_meta['lines'][$key] = $exif[$field];
                        }
                    } else {
                        $tokens = explode(';', (string) $field);
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

        if ($conf['show_iptc']) {
            $iptc = $metadata->getIptcData($path, $conf['show_iptc_mapping'], ', ');

            if ($iptc !== []) {
                $tpl_meta = [
                    'TITLE' => $this->translator->trans('IPTC Metadata'),
                    'lines' => [],
                ];

                foreach ($iptc as $field => $value) {
                    $key = $this->translator->trans($field);

                    $tpl_meta['lines'][$key] = $value;
                }

                $tpl_params['metadata'][] = $tpl_meta;
            }
        }

        return $tpl_params;
    }
}
