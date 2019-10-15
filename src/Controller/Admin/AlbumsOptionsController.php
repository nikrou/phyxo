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

use App\DataMapper\CategoryMapper;
use App\Repository\CategoryRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\OldPermalinkRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Functions\Language;
use Phyxo\TabSheet\TabSheet;
use Phyxo\Template\Template;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

class AlbumsOptionsController extends AdminCommonController
{
    protected function setTabsheet(string $section = 'status', Conf $conf): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('status', Language::l10n('Public / Private'), $this->generateUrl('admin_albums_options'), 'fa-lock');
        $tabsheet->add('lock', Language::l10n('Lock'), $this->generateUrl('admin_albums_options', ['section' => 'lock']), 'fa-ban');
        if ($conf['activate_comments']) {
            $tabsheet->add('comments', Language::l10n('Comments'), $this->generateUrl('admin_albums_options', ['section' => 'comments']), 'fa-comments');
        }
        if ($conf['allow_random_representative']) {
            $tabsheet->add('representative', Language::l10n('Representative'), $this->generateUrl('admin_albums_options', ['section' => 'representative']));
        }
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function index(Request $request, string $section, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params, CategoryMapper $categoryMapper)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST')) {
            if ($request->request->get('falsify') && $request->request->get('cat_true') && count($request->request->get('cat_true')) > 0) {
                if ($section === 'comments') {
                    $em->getRepository(CategoryRepository::class)->updateCategories(['commentable' => false], $request->request->get('cat_true'));
                } elseif ($section === 'lock') {
                    $categoryMapper->setCatVisible($request->request->get('cat_true'), false);
                } elseif ($section === 'status') {
                    $categoryMapper->setCatStatus($request->request->get('cat_true'), 'private');
                } elseif ($section === 'representative') {
                    $em->getRepository(CategoryRepository::class)->updateCategories(['representative_picture_id' => null], $request->request->get('cat_true'));
                }
            } elseif ($request->request->get('trueify') && $request->request->get('cat_false') && count($request->request->get('cat_false')) > 0) {
                if ($section === 'comments') {
                    $em->getRepository(CategoryRepository::class)->updateCategories(['commentable' => true], $request->request->get('cat_false'));
                } elseif ($section === 'lock') {
                    $categoryMapper->setCatVisible($request->request->get('cat_false'), true);
                } elseif ($section === 'status') {
                    $categoryMapper->setCatStatus($request->request->get('cat_false'), 'public');
                } elseif ($section === 'representative') {
                    // theoretically, all categories in $_POST['cat_false'] contain at least one element, so Phyxo can find a representant.
                    $categoryMapper->setRandomRepresentant($request->request->get('cat_false'));
                }
            }

            return $this->redirectToRoute('admin_albums_options', ['section' => $section]);
        }

        $cats = $this->getCatsBySection($section, $em);
        $tpl_params['L_SECTION'] = $cats['L_SECTION'];
        $tpl_params['L_CAT_OPTIONS_TRUE'] = $cats['L_CAT_OPTIONS_TRUE'];
        $tpl_params['L_CAT_OPTIONS_FALSE'] = $cats['L_CAT_OPTIONS_FALSE'];

        $tpl_params = array_merge($tpl_params, $categoryMapper->displaySelectCategoriesWrapper($cats['cats_true'], [], 'category_option_true'));
        $tpl_params = array_merge($tpl_params, $categoryMapper->displaySelectCategoriesWrapper($cats['cats_false'], [], 'category_option_false'));

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_albums_options', ['section' => $section]);
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_albums_options');
        $tpl_params['PAGE_TITLE'] = Language::l10n('Public / Private');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet($section, $conf), $tpl_params);

        return $this->render('albums_options.tpl', $tpl_params);
    }

    protected function getCatsBySection(string $section, EntityManager $em): array
    {
        $cats_true = [];
        $cats_false = [];
        $l_section = '';
        $l_true = '';
        $l_false = '';
        if ($section === 'comments') {
            $result = $em->getRepository(CategoryRepository::class)->findByField('commentable', true);
            $cats_true = $em->getConnection()->result2array($result);
            $result = $em->getRepository(CategoryRepository::class)->findByField('commentable', false);
            $cats_false = $em->getConnection()->result2array($result);
            $l_section = Language::l10n('Authorize users to add comments on selected albums');
            $l_true = Language::l10n('Authorized');
            $l_false = Language::l10n('Forbidden');
        } elseif ($section === 'lock') {
            $result = $em->getRepository(CategoryRepository::class)->findByField('visible', true);
            $cats_true = $em->getConnection()->result2array($result);
            $result = $em->getRepository(CategoryRepository::class)->findByField('visible', false);
            $cats_false = $em->getConnection()->result2array($result);
            $l_section = Language::l10n('Lock albums');
            $l_true = Language::l10n('Unlocked');
            $l_false = Language::l10n('Locked');
        } elseif ($section === 'status') {
            $result = $em->getRepository(CategoryRepository::class)->findByField('status', 'public');
            $cats_true = $em->getConnection()->result2array($result);
            $result = $em->getRepository(CategoryRepository::class)->findByField('status', 'private');
            $cats_false = $em->getConnection()->result2array($result);
            $l_section = Language::l10n('Manage authorizations for selected albums');
            $l_true = Language::l10n('Public');
            $l_false = Language::l10n('Private');
        } elseif ($section === 'representative') {
            $result = $em->getRepository(CategoryRepository::class)->findWithRepresentant();
            $cats_true = $em->getConnection()->result2array($result);
            $result = $em->getRepository(CategoryRepository::class)->findWithNoRepresentant();
            $cats_false = $em->getConnection()->result2array($result);
            $l_section = Language::l10n('Representative');
            $l_true = Language::l10n('singly represented');
            $l_false = Language::l10n('randomly represented');
        }

        return ['cats_true' => $cats_true, 'cats_false' => $cats_false, 'L_SECTION' => $l_section, 'L_CAT_OPTIONS_TRUE' => $l_true, 'L_CAT_OPTIONS_FALSE' => $l_false];
    }
}
