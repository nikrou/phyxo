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

use App\Enum\ImageSizeType;
use App\Repository\CommentRepository;
use App\Repository\UserCacheRepository;
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminCommentsController extends AbstractController
{
    private TranslatorInterface $translator;

    protected function setTabsheet(string $section = 'all'): TabSheet
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('all', $this->translator->trans('All comments', [], 'admin'), $this->generateUrl('admin_comments'));
        $tabsheet->add('pending', $this->translator->trans('Pending comments', [], 'admin'), $this->generateUrl('admin_comments', ['section' => 'pending']));
        $tabsheet->select($section);

        return $tabsheet;
    }

    public function index(
        ImageStandardParams $image_std_params,
        Conf $conf,
        TranslatorInterface $translator,
        CommentRepository $commentRepository,
        RouterInterface $router,
        string $section = 'all',
        int $start = 0
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $nb_total = 0;
        $nb_pending = 0;

        foreach ($commentRepository->countGroupByValidated() as $row) {
            $nb_total += $row['counter'];

            if ($row['validated'] === false) {
                $nb_pending = $row['counter'];
            }
        }

        foreach ($commentRepository->getCommentsOnImages($validated = $section !== 'pending', $conf['comments_page_nb_comments'], $start) as $comment) {
            $derivative = new DerivativeImage($comment->getImage(), $image_std_params->getByType(ImageSizeType::THUMB), $image_std_params);
            $author_name = is_null($comment->getUser()) ? $comment->getAuthor() : $comment->getUser()->getUsername();

            $tpl_params['comments'][] = [
                'U_PICTURE' => $this->generateUrl('admin_photo', ['image_id' => $comment->getImage()->getId()]),
                'ID' => $comment->getId(),
                'TN_SRC' => $this->generateUrl('admin_media', ['path' => $comment->getImage()->getPathBasename(), 'derivative' => $derivative->getUrlType(), 'image_extension' => $comment->getImage()->getExtension()]),
                'AUTHOR' => $author_name,
                'DATE' => $comment->getDate()->format('c'), // ['day_name', 'day', 'month', 'year', 'time']),
                'CONTENT' => $comment->getContent(),
                'IS_PENDING' => $comment->isPending(),
                'IP' => $comment->getAnonymousId(),
            ];
        }

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_comments', ['section' => $section, 'start' => $start]);
        $tpl_params['PAGE_TITLE'] = $translator->trans('Comments', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet($section);

        $tpl_params['navbar'] = Utils::createNavigationBar(
            $router,
            'admin_comments',
            ['section' => $section],
            ('pending' === $section ? $nb_pending : $nb_total - $nb_pending),
            $start,
            $conf['comments_page_nb_comments']
        );

        if ($section === 'all') {
            $tpl_params['NB_ELEMENTS'] = $nb_total;
            $tpl_params['SECTION_TITLE'] = $translator->trans('All', [], 'admin');
        } else {
            $tpl_params['NB_ELEMENTS'] = '';
            $tpl_params['SECTION_TITLE'] = $translator->trans('number_of_comments_pending', ['count' => $nb_pending], 'admin');
        }

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_comments');
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_comments_update', ['section' => $section, 'start' => $start]);

        return $this->render('comments.html.twig', $tpl_params);
    }

    public function update(
        Request $request,
        CommentRepository $commentRepository,
        UserCacheRepository $userCacheRepository,
        TranslatorInterface $translator,
        string $section = 'all',
        int $start = 0
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$request->request->has('comments')) {
                $this->addFlash('error', $translator->trans('Select at least one comment', [], 'admin'));
            } else {
                if ($request->request->get('validate')) {
                    $commentRepository->validateUserComment($request->request->all('comments'));
                    $userCacheRepository->invalidateNumberAvailableComments();

                    $this->addFlash(
                        'info',
                        $translator->trans('number_of_comments_validated', ['count' => count($request->request->all('comments'))], 'admin')
                    );
                }

                if ($request->request->get('reject')) {
                    $commentRepository->deleteByIds($request->request->all('comments'));
                    $userCacheRepository->invalidateNumberAvailableComments();

                    $this->addFlash(
                        'info',
                        $translator->trans('number_of_comments_rejected', ['count' => count($request->request->all('comments'))], 'admin')
                    );
                }
            }
        }

        return $this->redirectToRoute('admin_comments', ['section' => $section, 'start' => $start]);
    }
}
