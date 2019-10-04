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
use Phyxo\Functions\Language;
use Phyxo\Functions\Utils;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\SrcImage;
use Phyxo\TabSheet\TabSheet;
use Phyxo\Template\Template;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

class CommentsController  extends AdminCommonController
{
    protected function setTabsheet(string $section = 'all')
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('all', Language::l10n('All'), $this->generateUrl('admin_comments'));
        $tabsheet->add('pending', Language::l10n('Pendings'), $this->generateUrl('admin_comments', ['section' => 'pending']));
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function index(Request $request, string $section = 'all', int $start = 0, ImageStandardParams $image_std_params, Template $template, Conf $conf, EntityManager $em, ParameterBagInterface $params)
    {
        $tpl_params = [];

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
          $validated = $section === 'pending' ? false : true,
      );
        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            $thumb = (new DerivativeImage(new SrcImage($row, $conf['picture_ext']), $image_std_params->getByType(ImageStandardParams::IMG_THUMB), $image_std_params))->getUrl();
            if (empty($row['author_id'])) {
                $author_name = $row['author'];
            } else {
                $author_name = stripslashes($row['username']);
            }

            $tpl_params['comments'][] = [
                'U_PICTURE' => \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=photo&image_id=' . $row['image_id'],
                'ID' => $row['id'],
                'TN_SRC' => $thumb,
                'AUTHOR' => \Phyxo\Functions\Plugin::trigger_change('render_comment_author', $author_name),
                'DATE' => \Phyxo\Functions\DateTime::format_date($row['date'], ['day_name', 'day', 'month', 'year', 'time']),
                'CONTENT' => \Phyxo\Functions\Plugin::trigger_change('render_comment_content', $row['content']),
                'IS_PENDING' => $em->getConnection()->get_boolean($row['validated']) === false,
                'IP' => $row['anonymous_id'],
            ];
        }

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_comments', ['section' => $section, 'start' => $start]);
        $tpl_params['PAGE_TITLE'] = Language::l10n('Comments');
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
            $tpl_params['SECTION_TITLE'] = Language::l10n('All');
        } else {
            $tpl_params['NB_ELEMENTS'] = $nb_pending;
            $tpl_params['SECTION_TITLE'] = Language::l10n('Pendings');
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

    public function update(Request $request, string $section = 'all', int $start = 0, CommentMapper $commentMapper)
    {
        if ($request->isMethod('POST')) {
            if (!$request->request->get('comments')) {
                $this->addFlash('error', Language::l10n('Select at least one comment'));
                $error = true;
            } else {
                if ($request->request->get('validate')) {
                    $commentMapper->validateUserComment($request->request->get('comments'));

                    $this->addFlash(
                      'info',
                      Language::l10n_dec('%d user comment validated', '%d user comments validated', count($request->request->get('comments')))
                    );
                }

                if ($request->request->get('reject')) {
                    $commentMapper->deleteUserComment($request->request->get('comments'));

                    $this->addFlash(
                      'info',
                      Language::l10n_dec('%d user comment rejected', '%d user comments rejected', count($request->request->get('comments')))
                    );
                }
            }
        }

        return $this->redirectToRoute('admin_comments', ['section' => $section, 'start' => $start]);
    }
}
