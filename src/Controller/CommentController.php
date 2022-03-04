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
use App\Repository\CommentRepository;
use App\Form\CommentFilterType;
use App\Form\DeleteCommentType;
use App\Form\EditCommentType;
use App\Form\Model\CommentFilterModel;
use App\Form\ValidateCommentType;
use App\Security\AppUserService;
use Phyxo\Functions\Utils;
use Phyxo\Image\ImageStandardParams;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommentController extends CommonController
{
    public function index(
        Request $request,
        RouterInterface $router,
        Conf $conf,
        ImageStandardParams $image_std_params,
        TranslatorInterface $translator,
        CommentRepository $commentRepository,
        AppUserService $appUserService,
        AlbumMapper $albumMapper,
        int $comment_id,
        int $start = 0
    ): Response {
        $tpl_params = [];
        $tpl_params['PAGE_TITLE'] = $translator->trans('User comments');

        $filterParams = new CommentFilterModel();
        $filterParams->fromArray(
            [
                'items_number' => $conf['comments_page_nb_comments'],
                'sort_order' => 'DESC',
                'sort_by' => 'date',
                'since' => null,
                'album' => null,
                'author' => null,
                'keyword' => null
            ]
        );
        $queryParams = $request->query->all();
        if (isset($queryParams['album'])) {
            $queryParams['album'] = $albumMapper->getRepository()->find($queryParams['album']);
        }
        $filterParams->fromArray($queryParams);
        $filterParams->setPage($start);

        $commentFilterForm = $this->createForm(CommentFilterType::class, $filterParams, ['csrf_protection' => false]);
        $commentFilterForm->handleRequest($request);

        if ($commentFilterForm->isSubmitted() && $commentFilterForm->isValid()) {
            $filterParams = $commentFilterForm->getData();
            $filterParams->setPage(0);
        }

        $forbiddenCategories = $appUserService->getUser()->getUserInfos()->getForbiddenAlbums();

        if ($filterParams->getAlbum()) {
            $filterParams->setAlbums($albumMapper->getRepository()->getSubcatIds([$filterParams->getAlbum()->getId()]));
        }

        $tpl_params['comment_filter_form'] = $commentFilterForm->createView();

        $comment_route = 'comments';
        if ($start > 0) {
            $comment_route .= '__start';
        }

        $redirectRoute = $this->generateUrl($comment_route, array_merge(['start' => $start], $filterParams->toQueryParams()));

        $numberOfComments = $commentRepository->getLastComments(array_merge($filterParams->toArray(), ['forbidden_categories' => $forbiddenCategories]), 0, 0, $count_only = true);
        foreach ($commentRepository->getLastComments(
            array_merge($filterParams->toArray(), ['forbidden_categories' => $forbiddenCategories]),
            $filterParams->getPage(),
            $filterParams->getItemsNumber(),
            $count_only = false
        ) as $comment) {
            $tpl_comment = [];
            $tpl_comment['comment'] = $comment;
            $tpl_comment['image_url'] = $this->generateUrl(
                'picture',
                [
                    'image_id' => $comment->getImage()->getId(),
                    'element_id' => $comment->getImage()->getImageAlbums()->first()->getAlbum()->getId(),
                    'type' => 'album'
                ]
            );

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

            $tpl_comment['IN_EDIT'] = false;

            if ($appUserService->canManageComment('edit', $comment->getUser()->getId())) {
                if ($comment_id == $comment->getId()) {
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
                    $tpl_comment['U_CANCEL'] = $this->generateUrl($comment_route, array_merge(['start' => $start], $filterParams->toQueryParams()));
                }

                $tpl_comment['U_EDIT'] = $this->generateUrl('comment_edit', array_merge(
                    ['_fragment' => 'comment-' . $comment->getId(), 'start' => $start, 'comment_id' => $comment->getId()],
                    $filterParams->toQueryParams()
                ));
            }

            $tpl_params['comments'][] = $tpl_comment;
        }

        $tpl_params['navbar'] = Utils::createNavigationBar(
            $router,
            'comments',
            $filterParams->toQueryParams(),
            $numberOfComments,
            $filterParams->getPage(),
            $filterParams->getItemsNumber(),
            $conf['paginate_pages_around']
        );

        $tpl_params['derivative_params'] = $image_std_params->getByType(ImageStandardParams::IMG_THUMB);

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('comments.html.twig', $tpl_params);
    }

    public function updateComment(Request $request, int $comment_id, TranslatorInterface $translator, CommentRepository $commentRepository): Response
    {
        $editForm = $this->createForm(EditCommentType::class, $commentRepository->find($comment_id));
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $comment = $editForm->getData();
            $commentRepository->addOrUpdateComment($comment);

            $this->addFlash('success', $translator->trans('The comment has been updated'));
        }

        return $this->redirect($editForm['redirect']->getData());
    }

    public function validateComment(Request $request, int $comment_id, TranslatorInterface $translator, CommentRepository $commentRepository): Response
    {
        $validateForm = $this->createForm(ValidateCommentType::class, null, ['id' => $comment_id]);
        $validateForm->handleRequest($request);

        if ($validateForm->isSubmitted() && $validateForm->isValid()) {
            $commentRepository->validateUserComment([$validateForm['id']->getData()]);

            $this->addFlash('success', $translator->trans('The comment has been validated'));
        }

        return $this->redirect($validateForm['redirect']->getData());
    }

    public function deleteComment(Request $request, int $comment_id, TranslatorInterface $translator, CommentRepository $commentRepository): Response
    {
        $deleteForm = $this->createForm(DeleteCommentType::class, null, ['id' => $comment_id]);
        $deleteForm->handleRequest($request);

        if ($deleteForm->isSubmitted() && $deleteForm->isValid()) {
            $commentRepository->deleteByIds([$deleteForm['id']->getData()]);
            $this->addFlash('success', $translator->trans('The comment has been deleted'));
        }

        return $this->redirect($deleteForm['redirect']->getData());
    }
}
