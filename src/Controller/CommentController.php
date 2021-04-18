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
use Phyxo\Conf;
use Phyxo\MenuBar;
use App\Repository\CommentRepository;
use App\DataMapper\UserMapper;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use App\DataMapper\CommentMapper;
use Phyxo\Functions\Utils;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\SrcImage;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommentController extends CommonController
{
    public function index(int $start = 0, int $comment_id = 0, Request $request, Conf $conf, MenuBar $menuBar, AlbumMapper $albumMapper,
                        UserMapper $userMapper, CsrfTokenManagerInterface $csrfTokenManager, ImageStandardParams $image_std_params,
                        TranslatorInterface $translator, CommentRepository $commentRepository)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params['PAGE_TITLE'] = $translator->trans('User comments');

        $albums = [];
        foreach ($albumMapper->getRepository()->findAllowedAlbums($this->getUser()->getUserInfos()->getForbiddenCategories()) as $album) {
            $albums[] = $album;
        }

        // default values
        $tpl_params['items_number'] = $request->get('items_number') ? $request->get('items_number') : $conf['comments_page_nb_comments'];
        $tpl_params['sort_order'] = $request->get('sort_order') ? $request->get('sort_order') : 'DESC' ;
        $tpl_params['sort_by'] = $request->get('sort_by') ? $request->get('sort_by') : 'date';
        $tpl_params['since'] = $request->get('since') ? $request->get('since') : 'all';
        $tpl_params['category'] = $request->get('category') ? $request->get('category') : null;
        $tpl_params['author'] = $request->get('author') ? $request->get('author') : null;
        $tpl_params['keyword'] = $request->get('keyword') ? $request->get('keyword') : null;

        // form options
        $tpl_params['sort_order_options'] = [
            'DESC' => $translator->trans('descending'),
            'ASC' => $translator->trans('ascending')
        ];

        $tpl_params['sort_by_options'] = [
            'date' => $translator->trans('comment date'),
            'image_id' => $translator->trans('photo')
        ];

        $tpl_params['items_number_options'] = [5 => 5, 10 => 10, 20 => 20, 50 => 50, '' => $translator->trans('All comments')];

        $now = new \DateTimeImmutable();
        $since_options = [
            'today' => [
                'label' => $translator->trans('today'),
                'clause' => $now->sub(new \DateInterval('P1D'))
            ],
            'last7days' => [
                'label' => $translator->trans('last {count} days', ['count' => 7]),
                'clause' => $now->sub(new \DateInterval('P7D'))
            ],
            'last30days' => [
                'label' => $translator->trans('last {count} days', ['count' => 30]),
                'clause' => $now->sub(new \DateInterval('P30D'))
            ],
            'all' => ['label' => $translator->trans('the beginning'), 'clause' => null]
        ];
        $tpl_params['since_options'] = array_combine(array_keys($since_options), array_column($since_options, 'label'));

        $tpl_params = array_merge($tpl_params, $albumMapper->displaySelectAlbumsWrapper($albums, $tpl_params['category'] !== null ? [$tpl_params['category']]:[], 'categories', true));

        $query_params = [];

        $comments = [];
        $images = [];
        $album_ids = [];

        $filter_params = [];
        $filter_params['forbidden_categories'] = $this->getUser()->getUserInfos()->getForbiddenCategories();

        if ($tpl_params['category'] !== null) {
            $filter_params['album_ids'] = $albumMapper->getRepository()->getSubcatIds([$tpl_params['category']]);
        }

        if (!empty($tpl_params['since']) && !empty($since_options[$tpl_params['since']]['clause'])) {
            $filter_params['since'] = $since_options[$tpl_params['since']]['clause'];
        }

        if (!empty($tpl_params['author'])) {
            $filter_params['author'] = $tpl_params['author'];
        }

        if (!empty($tpl_params['keyword'])) {
            $filter_params['keywords'] = preg_split('/[\s,;]+/', $tpl_params['keyword']);
        }

        $filter_params['sort_by'] = $tpl_params['sort_by'];
        $filter_params['sort_order'] = $tpl_params['sort_order'];

        $nb_comments = $commentRepository->getLastComments($filter_params, 0, 0, $count_only = true);
        foreach ($commentRepository->getLastComments($filter_params, $start, $tpl_params['items_number'], 0) as $comment) {
            $comments[] = $comment;
            $images[$comment->getImage()->getId()] = $comment->getImage();
            foreach ($comment->getImage()->getImageAlbums() as $image_album) {
                $album_ids[] = $image_album->getAlbum()->getId();
            }
        }

        $query_params = [
            'author' => $tpl_params['author'],
            'category' => $tpl_params['category'],
            'since' => $tpl_params['since'],
            'sort_by' => $tpl_params['sort_by'],
            'sort_order' => $tpl_params['sort_order'],
            'items_number' => $tpl_params['items_number']
        ];

        $tpl_params['navbar'] = \Phyxo\Functions\Utils::createNavigationBar(
            $this->get('router'),
            'comments',
            $query_params,
            $nb_comments,
            $start,
            $tpl_params['items_number'],
            $conf['paginate_pages_around']
        );

        if (count($comments) > 0) {
            foreach ($comments as $comment) {
                if ($images[$comment->getImage()->getId()]->getName()) {
                    $name = $images[$comment->getImage()->getId()]->getName();
                } else {
                    $name = Utils::get_name_from_file($images[$comment->getImage()->getId()]->getFile());
                }

                // source of the thumbnail picture
                $src_image = new SrcImage($images[$comment->getImage()->getId()]->toArray(), $conf['picture_ext']);

                // link to the full size picture
                $picture_url = $this->generateUrl(
                    'picture',
                    [
                        'element_id' => $comment->getImage()->getImageAlbums()->first()->getAlbum()->getId(),
                        'image_id' => $comment->getImage()->getId(),
                        'type' => 'category',
                    ]
                );

                $email = null;
                if ($comment->getUser()->getMailAddress()) {
                    $email = $comment->getUser()->getMailAddress();
                } elseif ($comment->getEmail()) {
                    $email = $comment->getEmail();
                }

                $tpl_comment = [
                    'ID' => $comment->getId(),
                    'U_PICTURE' => $picture_url,
                    'src_image' => $src_image,
                    'ALT' => $name,
                    'AUTHOR' => $comment->getAuthor(),
                    'WEBSITE_URL' => $comment->getWebsiteUrl(),
                    'DATE' => $comment->getDate()->format('c'), // ['day_name', 'day', 'month', 'year', 'time']),
                    'CONTENT' => $comment->getContent(),
                ];

                if ($userMapper->isAdmin()) {
                    $tpl_comment['EMAIL'] = $email;
                }

                if ($userMapper->canManageComment('delete', $comment->getUser()->getId())) {
                    $tpl_comment['U_DELETE'] = $this->generateUrl('comment_delete', array_merge($query_params, ['start' => $start, 'comment_id' => $comment->getId()]));
                }
                if ($userMapper->canManageComment('edit', $comment->getUser()->getId())) {
                    $tpl_comment['U_EDIT'] = $this->generateUrl('comment_edit', array_merge($query_params, ['start' => $start, 'comment_id' => $comment->getId()]));
                    $tpl_comment['U_SAVE'] = $this->generateUrl('comment_save', array_merge($query_params, ['start' => $start, 'comment_id' => $comment->getId()]));

                    if ($comment->getId() === $comment_id) {
                        $tpl_comment['IN_EDIT'] = true;
                        $tpl_comment['IMAGE_ID'] = $comment->getImage()->getId();
                        $tpl_comment['CONTENT'] = $comment->getContent();
                        $tpl_comment['U_CANCEL'] = $this->generateUrl('comments', array_merge($query_params, ['start' => $start]));
                    }
                }

                if ($userMapper->canManageComment('validate', $comment->getUser()->getId())) {
                    if (!$comment->isValidated()) {
                        $tpl_comment['U_VALIDATE'] = $this->generateUrl('comment_validate', array_merge($query_params, ['start' => $start, 'comment_id' => $comment->getId()]));
                    }
                }

                $tpl_params['comments'][] = $tpl_comment;
            }
        }

        $derivative_params = $image_std_params->getByType(ImageStandardParams::IMG_THUMB);
        $tpl_params['derivative_params'] = $derivative_params;
        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('comment');
        $tpl_params['F_ACTION'] = $this->generateUrl('comments', array_merge($query_params, ['start' => $start]));

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('comments.html.twig', $tpl_params);
    }

    public function edit(int $comment_id, Request $request, CommentMapper $commentMapper, CsrfTokenManagerInterface $csrfTokenManager, TranslatorInterface $translator)
    {
        if ($request->isMethod('POST')) {
            $token = new CsrfToken('comment', $request->request->get('_csrf_token'));
            if (!$csrfTokenManager->isTokenValid($token)) {
                throw new InvalidCsrfTokenException();
            }

            $comment_action = $commentMapper->updateUserComment(
                [
                    'id' => $comment_id,
                    'image_id' => $request->request->get('image_id'),
                    'content' => $request->request->get('content'),
                    'website_url' => $request->request->get('website_url'),
                ],
                $request->request->get('key')
            );

            switch ($comment_action) {
                case 'moderate':
                    $this->addFlash('infos', $translator->trans('An administrator must authorize your comment before it is visible.'));
                    break;
                case 'validate':
                    $this->addFlash('infos', $translator->trans('Your comment has been registered'));
                    break;
                case 'reject':
                    $this->addFlash('errors', $translator->trans('Your comment has NOT been registered because it did not pass the validation rules'));
                    break;
            }
        }

        return $this->redirectToRoute(
            'comments',
            [
                'author' => $request->get('author'),
                'category' => $request->get('category'),
                'sort_by' => $request->get('sort_by'),
                'sort_order' => $request->get('sort_order'),
                'items_number' => $request->get('items_number'),
                'start' => $request->get('start'),
            ]
        );
    }

    public function delete(Request $request, CommentMapper $commentMapper, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $token = new CsrfToken('comment', $request->request->get('_csrf_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }
        $commentMapper->deleteUserComment([$request->get('comment_id')]);

        return $this->redirectToRoute(
            'comments',
            [
                'author' => $request->get('author'),
                'category' => $request->get('category'),
                'sort_by' => $request->get('sort_by'),
                'sort_order' => $request->get('sort_order'),
                'items_number' => $request->get('items_number'),
                'start' => $request->get('start'),
            ]
        );
    }

    public function  validate(Request $request, CommentMapper $commentMapper, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $token = new CsrfToken('comment', $request->request->get('_csrf_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }
        $commentMapper->validateUserComment([$request->get('comment_id')]);

        return $this->redirectToRoute(
            'comments',
            [
                'author' => $request->get('author'),
                'category' => $request->get('category'),
                'sort_by' => $request->get('sort_by'),
                'sort_order' => $request->get('sort_order'),
                'items_number' => $request->get('items_number'),
                'start' => $request->get('start'),
            ]
        );
    }
}
