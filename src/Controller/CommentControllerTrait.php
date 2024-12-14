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

use App\Form\DeleteCommentType;
use App\Form\EditCommentType;
use App\Form\ValidateCommentType;
use App\Repository\CommentRepository;
use App\Security\AppUserService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

trait CommentControllerTrait
{
    public function manageComment(
        $comments,
        Request $request,
        CommentRepository $commentRepository,
        AppUserService $appUserService,
        TranslatorInterface $translator,
        RedirectResponse $redirectResponse
    ): array {
        $tpl_comments = [];

        foreach ($comments as $comment) {
            $tpl_comment = [];
            $tpl_comment['comment'] = $comment;
            $tpl_comment['image_url'] = $this->generateUrl(
                'picture',
                [
                    'image_id' => $comment->getImage()->getId(),
                    'element_id' => $comment->getImage()->getImageAlbums()->first()->getAlbum()->getId(),
                    'type' => 'category'
                ]
            );

            if ($appUserService->canManageComment('validate', $comment->getUser()->getId()) && !$comment->isValidated()) {
                $validateForm = $this->createForm(ValidateCommentType::class, null, ['id' => $comment->getId()]);
                $validateForm->handleRequest($request);

                if ($validateForm->isSubmitted() && $validateForm->isValid()) {
                    $commentRepository->validateUserComment([$validateForm['id']->getData()]);

                    $this->addFlash('success', $translator->trans('The comment has been validated'));

                    return $this->redirectToRoute($redirectResponse);
                }

                $tpl_comment['VALIDATE_FORM'] = $validateForm->createView();
            }

            if ($appUserService->canManageComment('delete', $comment->getUser()->getId())) {
                $deleteForm = $this->createForm(DeleteCommentType::class, null, ['id' => $comment->getId()]);
                $deleteForm->handleRequest($request);

                if ($deleteForm->isSubmitted() && $deleteForm->isValid()) {
                    $commentRepository->deleteByIds([$deleteForm['id']->getData()]);
                    $this->addFlash('success', $translator->trans('The comment has been deleted'));

                    return $this->redirectToRoute($redirectResponse);
                }

                $tpl_comment['DELETE_FORM'] = $deleteForm->createView();
            }

            $comment_id = 0;
            if ($comment_id === 0 || ($comment_id !== $comment->getId())) {
                $tpl_comment['IN_EDIT'] = false;
            } else {
                /** @var Form $form */
                $form = $this->createForm(EditCommentType::class, $comment, ['id' => $comment->getId()]);
                $form->handleRequest($request);
                $tpl_comment['IN_EDIT'] = true;

                if ($form->isSubmitted() && $form->isValid()) {
                    $clickedButton = $form->getClickedButton();
                    if ($clickedButton->getName() === 'validate') {
                        $commentRepository->validateUserComment([$form['id']->getData()]);

                        $this->addFlash('success', $translator->trans('The comment has been validated'));

                        return $this->redirectToRoute($redirectResponse);
                    } elseif ($clickedButton->getName() === 'update') {
                        $comment = $form->getData();
                        $commentRepository->addOrUpdateComment($comment);

                        $this->addFlash('success', $translator->trans('The comment has been updated'));

                        return $this->redirectToRoute($redirectResponse);
                    } elseif ($clickedButton->getName() === 'delete') {
                        $commentRepository->deleteByIds([$form['id']->getData()]);
                        $this->addFlash('success', $translator->trans('The comment has been deleted'));

                        return $this->redirectToRoute($redirectResponse);
                    }
                }
                $tpl_comment['FORM'] = $form->createView();
                $tpl_comment['U_CANCEL'] = $redirectResponse;
            }

            $start = 0;
            if ($appUserService->canManageComment('edit', $comment->getUser()->getId())) {
                $tpl_comment['U_EDIT'] = $this->generateUrl('comment_edit', array_merge(
                    ['_fragment' => 'comment-' . $comment->getId(), 'start' => $start, 'comment_id' => $comment->getId()],
                    $request->query->all()
                ));
            }

            $tpl_comments[] = $tpl_comment;
        }

        return $tpl_comments;
    }
}
