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
use Phyxo\EntityManager;
use Phyxo\Functions\Utils;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\SrcImage;
use Phyxo\TabSheet\TabSheet;
use Phyxo\Template\Template;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommentsController  extends AdminCommonController
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

    public function index(Request $request, string $section = 'all', int $start = 0, ImageStandardParams $image_std_params, Template $template, Conf $conf, EntityManager $em,
                        ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $nb_total = 0;
        $nb_pending = 0;

        $result = $em->getRepository(CommentRepository::class)->countGroupByValidated();
        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            $nb_total += $row['counter'];

            if ($em->getConnection()->get_boolean($row['validated']) == false) {
                $nb_pending = $row['counter'];
            }
        }

        $result = $em->getRepository(CommentRepository::class)->getCommentOnImages(
          $conf['comments_page_nb_comments'],
          $start,
          $validated = $section === 'pending' ? false : true
        );
        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            $thumb = (new DerivativeImage(new SrcImage($row, $conf['picture_ext']), $image_std_params->getByType(ImageStandardParams::IMG_THUMB), $image_std_params))->getUrl();
            if (empty($row['author_id'])) {
                $author_name = $row['author'];
            } else {
                $author_name = stripslashes($row['username']);
            }

            $tpl_params['comments'][] = [
                'U_PICTURE' => $this->generateUrl('admin_photo', ['image_id' => $row['image_id']]),
                'ID' => $row['id'],
                'TN_SRC' => $thumb,
                'AUTHOR' => $author_name,
                'DATE' => \Phyxo\Functions\DateTime::format_date($row['date'], ['day_name', 'day', 'month', 'year', 'time']),
                'CONTENT' => $row['content'],
                'IS_PENDING' => $em->getConnection()->get_boolean($row['validated']) === false,
                'IP' => $row['anonymous_id'],
            ];
        }

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_comments', ['section' => $section, 'start' => $start]);
        $tpl_params['PAGE_TITLE'] = $translator->trans('Comments', [], 'admin');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
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
            $tpl_params['NB_ELEMENTS'] = $nb_pending;
            $tpl_params['SECTION_TITLE'] = $translator->trans('Pending comments', [], 'admin');
        }

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_comments');
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_comments_update', ['section' => $section, 'start' => $start]);

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('comments.tpl', $tpl_params);
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
