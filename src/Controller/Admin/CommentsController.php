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

use App\DataMapper\CommentMapper;
use App\Repository\CommentRepository;
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\SrcImage;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommentsController  extends AbstractController
{
    private $translator;

    protected function setTabsheet(string $section = 'all')
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('all', $this->translator->trans('All comments', [], 'admin'), $this->generateUrl('admin_comments'));
        $tabsheet->add('pending', $this->translator->trans('Pending comments', [], 'admin'), $this->generateUrl('admin_comments', ['section' => 'pending']));
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function index(Request $request, string $section = 'all', int $start = 0, ImageStandardParams $image_std_params, Conf $conf,
                        TranslatorInterface $translator, CommentRepository $commentRepository)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $nb_total = 0;
        $nb_pending = 0;

        foreach ($commentRepository->countGroupByValidated() as $row) {
            $nb_total += $row['counter'];

            if ($row['validated'] === false) {
                $nb_pending = $row['counter'];
            }
        }

        foreach ($commentRepository->getCommentsOnImages($conf['comments_page_nb_comments'], $start, $validated = $section === 'pending' ? false : true) as $comment) {
            $thumb = (new DerivativeImage(new SrcImage($comment->getImage()->toArray(), $conf['picture_ext']), $image_std_params->getByType(ImageStandardParams::IMG_THUMB), $image_std_params))->getUrl();
            if (!is_null($comment->getUser())) {
                $author_name = $comment->getUser()->getUsername();
            } else {
                $author_name = $comment->getAuthor();
            }

            $tpl_params['comments'][] = [
                'U_PICTURE' => $this->generateUrl('admin_photo', ['image_id' => $comment->getImage()->getId()]),
                'ID' => $comment->getId(),
                'TN_SRC' => $thumb,
                'AUTHOR' => $author_name,
                'DATE' => $comment->getDate()->format('c'), // ['day_name', 'day', 'month', 'year', 'time']),
                'CONTENT' => $comment->getContent(),
                'IS_PENDING' => $comment->isPending(),
                'IP' => $comment->getAnonymousId(),
            ];
        }

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_comments', ['section' => $section, 'start' => $start]);
        $tpl_params['PAGE_TITLE'] = $translator->trans('Comments', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet($section), $tpl_params);

        $tpl_params['navbar'] = Utils::createNavigationBar(
          $this->get('router'),
          'admin_comments',
          ['section' => $section],
           ('pending' == $section ? $nb_pending : $nb_total - $nb_pending),
           $start,
           $conf['comments_page_nb_comments']
        );

        if ($section === 'all') {
            $tpl_params['NB_ELEMENTS'] = $nb_total;
            $tpl_params['SECTION_TITLE'] = $translator->trans('All', [], 'admin');
        } else {
            $tpl_params['SECTION_TITLE'] = $translator->trans('number_of_comments_pending', ['count' => $nb_pending], 'admin');
        }

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_comments');
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_comments_update', ['section' => $section, 'start' => $start]);

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('comments.html.twig', $tpl_params);
    }

    public function update(Request $request, string $section = 'all', int $start = 0, CommentMapper $commentMapper, TranslatorInterface $translator)
    {
        if ($request->isMethod('POST')) {
            if (!$request->request->get('comments')) {
                $this->addFlash('error', $translator->trans('Select at least one comment', [], 'admin'));
                $error = true;
            } else {
                if ($request->request->get('validate')) {
                    $commentMapper->validateUserComment($request->request->get('comments'));

                    $this->addFlash(
                      'info',
                      $translator->trans('number_of_comments_validated', ['count' => count($request->request->get('comments'))], 'admin')
                    );
                }

                if ($request->request->get('reject')) {
                    $commentMapper->deleteUserComment($request->request->get('comments'));

                    $this->addFlash(
                      'info',
                      $translator->trans('number_of_comments_rejected', ['count' => count($request->request->get('comments'))], 'admin')
                    );
                }
            }
        }

        return $this->redirectToRoute('admin_comments', ['section' => $section, 'start' => $start]);
    }
}
