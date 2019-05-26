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

use Symfony\Component\HttpFoundation\Request;
use Phyxo\Template\Template;
use Phyxo\Conf;
use Phyxo\MenuBar;
use Phyxo\Functions\Language;
use Phyxo\EntityManager;
use App\Repository\CommentRepository;
use App\Repository\BaseRepository;
use App\Repository\ImageRepository;
use App\Repository\CategoryRepository;
use Phyxo\Image\ImageStdParams;
use App\DataMapper\UserMapper;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use App\DataMapper\CommentMapper;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use App\DataMapper\CategoryMapper;

class CommentController extends CommonController
{
    public function index(int $start = 0, int $comment_id = 0, Request $request, EntityManager $em, Template $template, Conf $conf, string $phyxoVersion, string $phyxoWebsite, MenuBar $menuBar, string $themesDir,
    UserMapper $userMapper, CsrfTokenManagerInterface $csrfTokenManager, CategoryMapper $categoryMapper)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($em->getConnection()->getLayer() === 'mysql') {
            $conf_derivatives = @unserialize(stripslashes($conf['derivatives']));
        } else {
            $conf_derivatives = @unserialize($conf['derivatives']);
        }
        \Phyxo\Image\ImageStdParams::load_from_db($conf_derivatives);

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        $tpl_params['PAGE_TITLE'] = Language::l10n('User comments');

        $filter = [];
        $result = $em->getRepository(CategoryRepository::class)->findWithCondition(
            [$em->getRepository(BaseRepository::class)->getSQLConditionFandF($this->getUser(), $filter, ['forbidden_categories' => 'id', 'visible_categories' => 'id'])]
        );
        $categories = $em->getConnection()->result2array($result);

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
            'DESC' => Language::l10n('descending'),
            'ASC' => Language::l10n('ascending')
        ];

        $tpl_params['sort_by_options'] = [
            'date' => Language::l10n('comment date'),
            'image_id' => Language::l10n('photo')
        ];

        $tpl_params['items_number_options'] = [5 => 5, 10 => 10, 20 => 20, 50 => 50, '' => Language::l10n('All comments')];

        $since_options = [
            'today' => ['label' => Language::l10n('today'), 'clause' => 'date > ' . $em->getConnection()->db_get_recent_period_expression(1)],
            'last7days' => ['label' => Language::l10n('last %d days', 7), 'clause' => 'date > ' . $em->getConnection()->db_get_recent_period_expression(7)],
            'last30days' => ['label' => Language::l10n('last %d days', 30), 'clause' => 'date > ' . $em->getConnection()->db_get_recent_period_expression(30)],
            'all' => ['label' => Language::l10n('the beginning'), 'clause' => '']
        ];
        $tpl_params['since_options'] = array_combine(array_keys($since_options), array_column($since_options, 'label'));

        $tpl_params = array_merge($tpl_params, $categoryMapper->displaySelectCategoriesWrapper($categories, [$tpl_params['category']], 'categories', true));

        $comments = [];
        $element_ids = [];

        // get params
        $where_clauses[] = $em->getRepository(BaseRepository::class)->getSQLConditionFandF(
            $this->getUser(),
            $filter,
            [
                'forbidden_categories' => 'category_id',
                'visible_categories' => 'category_id',
                'visible_images' => 'ic.image_id'
            ],
            '',
            true
        );
        $category_ids = $em->getRepository(CategoryRepository::class)->getSubcatIds([$tpl_params['category']]);
        if (!empty($category_ids)) {
            $where_clauses[] = 'category_id ' . $em->getConnection()->in($category_ids);
        }

        if (!empty($tpl_params['author'])) {
            $where_clauses[] = '(u.username = \'' . $em->getConnection()->db_real_escape_string($tpl_params['author'])
                . '\' OR author = \'' . $em->getConnection()->db_real_escape_string($tpl_params['author']) . '\')';
        }

        if (!empty($tpl_params['keyword'])) {
            $where_clauses[] = '(' . implode(
                ' AND ',
                array_map(
                    function ($s) {
                        return "content LIKE '%$s%'";
                    },
                    preg_split('/[\s,;]+/', $em->getConnection()->db_real_escape_string($tpl_params['keyword']))
                )
            ) . ')';
        }

        if (!empty($tpl_params['since']) && !empty($since_options[$tpl_params['since']]['clause'])) {
            $where_clauses[] = $since_options[$tpl_params['since']]['clause'];
        }

        $sql_params = [
            'where_clauses' => $where_clauses,
            'limit' => $tpl_params['items_number'],
            'offset' => $start,
            'order_by' => $tpl_params['sort_by'] . ' ' . $tpl_params['sort_order'],
        ];

        $nb_comments = $em->getRepository(CommentRepository::class)->getLastComments($sql_params, $count_only = true);
        $result = $em->getRepository(CommentRepository::class)->getLastComments($sql_params);
        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            $comments[] = $row;
            $element_ids[] = $row['image_id'];
            $category_ids[] = $row['category_id'];
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
            // retrieving element informations
            $result = $em->getRepository(ImageRepository::class)->findByIds($element_ids);
            $elements = $em->getConnection()->result2array($result, 'id');

            // retrieving category informations
            $result = $em->getRepository(CategoryRepository::class)->findByIds($category_ids);
            $categories = $em->getConnection()->result2array($result, 'id');

            foreach ($comments as $comment) {
                if (!empty($elements[$comment['image_id']]['name'])) {
                    $name = $elements[$comment['image_id']]['name'];
                } else {
                    $name = \Phyxo\Functions\Utils::get_name_from_file($elements[$comment['image_id']]['file']);
                }

                // source of the thumbnail picture
                $src_image = new \Phyxo\Image\SrcImage($elements[$comment['image_id']], $conf['picture_ext']);

                // link to the full size picture
                $picture_url = $this->generateUrl(
                    'picture',
                    [
                        'element_id' => $comment['category_id'],
                        'image_id' => $comment['image_id'],
                        'type' => 'category',
                    ]
                );

                $email = null;
                if (!empty($comment['user_email'])) {
                    $email = $comment['user_email'];
                } elseif (!empty($comment['email'])) {
                    $email = $comment['email'];
                }

                $tpl_comment = [
                    'ID' => $comment['comment_id'],
                    'U_PICTURE' => $picture_url,
                    'src_image' => $src_image,
                    'ALT' => $name,
                    'AUTHOR' => \Phyxo\Functions\Plugin::trigger_change('render_comment_author', $comment['author']),
                    'WEBSITE_URL' => $comment['website_url'],
                    'DATE' => \Phyxo\Functions\DateTime::format_date($comment['date'], ['day_name', 'day', 'month', 'year', 'time']),
                    'CONTENT' => \Phyxo\Functions\Plugin::trigger_change('render_comment_content', $comment['content']),
                ];

                if ($userMapper->isAdmin()) {
                    $tpl_comment['EMAIL'] = $email;
                }

                if ($userMapper->canManageComment('delete', $comment['author_id'])) {
                    $tpl_comment['U_DELETE'] = $this->generateUrl('comment_delete', array_merge($query_params, ['start' => $start, 'comment_id' => $comment['comment_id']]));
                }
                if ($userMapper->canManageComment('edit', $comment['author_id'])) {
                    $tpl_comment['U_EDIT'] = $this->generateUrl('comment_edit', array_merge($query_params, ['start' => $start, 'comment_id' => $comment['comment_id']]));
                    $tpl_comment['U_SAVE'] = $this->generateUrl('comment_save', array_merge($query_params, ['start' => $start, 'comment_id' => $comment['comment_id']]));

                    if ($comment['comment_id'] == $comment_id) {
                        $tpl_comment['IN_EDIT'] = true;
                        $key = \Phyxo\Functions\Utils::get_ephemeral_key($conf['key_comment_valid_time'], $comment['image_id']);
                        $tpl_comment['KEY'] = $key;
                        $tpl_comment['IMAGE_ID'] = $comment['image_id'];
                        $tpl_comment['CONTENT'] = $comment['content'];
                        $tpl_comment['U_CANCEL'] = $this->generateUrl('comments', array_merge($query_params, ['start' => $start]));
                    }
                }

                if ($userMapper->canManageComment('validate', $comment['author_id'])) {
                    if ($em->getConnection()->is_boolean($comment['validated']) && !$em->getConnection()->get_boolean($comment['validated'])) {
                        $tpl_comment['U_VALIDATE'] = $this->generateUrl('comment_validate', array_merge($query_params, ['start' => $start, 'comment_id' => $comment['comment_id']]));
                    }
                }

                $tpl_params['comments'][] = $tpl_comment;
            }
        }

        $derivative_params = \Phyxo\Functions\Plugin::trigger_change('get_comments_derivative_params', ImageStdParams::get_by_type(ImageStdParams::IMG_THUMB));
        $tpl_params['derivative_params'] = $derivative_params;
        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');
        $tpl_params['F_ACTION'] = $this->generateUrl('comments', array_merge($query_params, ['start' => $start]));

        // @TODO: retrieve flash messages
        // $tpl_params['infos'][] = 'validated';

        return $this->render('comments.tpl', $tpl_params);
    }

    public function edit(Request $request, CommentMapper $commentMapper, CsrfTokenManagerInterface $csrfTokenManager)
    {
        if ($request->isMethod('POST')) {
            $token = new CsrfToken('authenticate', $request->request->get('_csrf_token'));
            if (!$csrfTokenManager->isTokenValid($token)) {
                throw new InvalidCsrfTokenException();
            }

            $comment_action = $commentMapper->updateUserComment(
                [
                    'comment_id' => $request->get('comment_id'),
                    'image_id' => $request->request->get('image_id'),
                    'content' => $request->request->get('content'),
                    'website_url' => $request->request->get('website_url'),
                ],
                $request->request->get('key')
            );

            switch ($comment_action) {
                case 'moderate':
                    $this->addFlash('infos', Language::l10n('An administrator must authorize your comment before it is visible.'));
                    break;
                case 'validate':
                    $this->addFlash('infos', Language::l10n('Your comment has been registered'));
                    break;
                case 'reject':
                    $this->addFlash('errors', Language::l10n('Your comment has NOT been registered because it did not pass the validation rules'));
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
        $token = new CsrfToken('authenticate', $request->request->get('_csrf_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }
        $commentMapper->deleteUserComment($request->get('comment_id'));

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
        $token = new CsrfToken('authenticate', $request->request->get('_csrf_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }
        $commentMapper->validateUserComment($request->get('comment_id'));

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
