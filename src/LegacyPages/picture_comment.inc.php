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

/**
 * This file is included by the picture page to manage user comments
 *
 */

use App\Repository\CommentRepository;

// the picture is commentable if it belongs at least to one category which
// is commentable
$page['show_comments'] = false;
foreach ($related_categories as $category) {
    if ($conn->get_boolean($category['commentable'])) {
        $page['show_comments'] = true;
        break;
    }
}


if ($page['show_comments'] and isset($_POST['content'])) {
    if ($services['users']->isGuest() and !$conf['comments_forall']) {
        die('Session expired'); // TODO : better end of request ; better response
    }

    $comm = [
        'author' => trim(@$_POST['author']),
        'content' => trim($_POST['content']),
        'website_url' => trim(@$_POST['website_url']),
        'email' => trim(@$_POST['email']),
        'image_id' => $page['image_id'],
    ];

    if (empty($_POST['key'])) {
        $comment_action = 'reject';
    } else {
        $comment_action = $commentMapper->insertUserComment($comm, $_POST['key'], $page['errors']);
    }

    switch ($comment_action) {
        case 'moderate':
            $page['infos'][] = \Phyxo\Functions\Language::l10n('An administrator must authorize your comment before it is visible.');
        case 'validate':
            $page['infos'][] = \Phyxo\Functions\Language::l10n('Your comment has been registered');
            break;
        case 'reject':
            \Phyxo\Functions\HTTP::set_status_header(403);
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Your comment has NOT been registered because it did not pass the validation rules');
            break;
        default:
            trigger_error('Invalid comment action ' . $comment_action, E_USER_WARNING);
    }

    // allow plugins to notify what's going on
    \Phyxo\Functions\Plugin::trigger_notify('user_comment_insertion', array_merge($comm, ['action' => $comment_action]));
} elseif (isset($_POST['content'])) {
    \Phyxo\Functions\HTTP::set_status_header(403);
    die('ugly spammer');
}

if ($page['show_comments']) {
    $nb_comments = (new CommentRepository($conn))->countByImage($page['image_id'], $services['users']->isAdmin());
    // navigation bar creation
    if (!isset($page['start'])) {
        $page['start'] = 0;
    }

    $navigation_bar = \Phyxo\Functions\Utils::create_navigation_bar(
        \Phyxo\Functions\URL::duplicate_picture_url([], ['start']),
        $nb_comments,
        $page['start'],
        $conf['nb_comment_page'],
        true // We want a clean URL
    );

    $template->assign(
        [
            'COMMENT_COUNT' => $nb_comments,
            'navbar' => $navigation_bar,
        ]
    );

    if ($nb_comments > 0) {
        // comments order (get, session, conf)
        if (!empty($_GET['comments_order']) && in_array(strtoupper($_GET['comments_order']), ['ASC', 'DESC'])) {
            $_SESSION['comments_order'] = $_GET['comments_order'];
        }
        $comments_order = isset($_SESSION['comments_order']) ? $_SESSION['comments_order'] : $conf['comments_order'];

        $template->assign([
            'COMMENTS_ORDER_URL' => \Phyxo\Functions\URL::add_url_params(
                \Phyxo\Functions\URL::duplicate_picture_url(),
                ['comments_order' => ($comments_order == 'ASC' ? 'DESC' : 'ASC')]
            ),
            'COMMENTS_ORDER_TITLE' => $comments_order == 'ASC' ? \Phyxo\Functions\Language::l10n('Show latest comments first') : \Phyxo\Functions\Language::l10n('Show oldest comments first'),
        ]);

        $result = (new CommentRepository($conn))->getCommentsOnImage(
            $page['image_id'],
            $comments_order,
            $conf['nb_comment_page'],
            $page['start'],
            $services['users']->isAdmin()
        );
        while ($row = $conn->db_fetch_assoc($result)) {
            if ($row['author'] == 'guest') {
                $row['author'] = \Phyxo\Functions\Language::l10n('guest');
            }

            $email = null;
            if (!empty($row['user_email'])) {
                $email = $row['user_email'];
            } elseif (!empty($row['email'])) {
                $email = $row['email'];
            }

            $tpl_comment =
                [
                    'ID' => $row['id'],
                    'AUTHOR' => \Phyxo\Functions\Plugin::trigger_change('render_comment_author', $row['author']),
                    'DATE' => \Phyxo\Functions\DateTime::format_date($row['date'], ['day_name', 'day', 'month', 'year', 'time']),
                    'CONTENT' => \Phyxo\Functions\Plugin::trigger_change('render_comment_content', $row['content']),
                    'WEBSITE_URL' => $row['website_url'],
                ];

            if ($services['users']->canManageComment('delete', $row['author_id'])) {
                $tpl_comment['U_DELETE'] = \Phyxo\Functions\URL::add_url_params(
                    $url_self,
                    [
                        'action' => 'delete_comment',
                        'comment_to_delete' => $row['id'],
                        // 'pwg_token' => \Phyxo\Functions\Utils::get_token(),
                    ]
                );
            }
            if ($services['users']->canManageComment('edit', $row['author_id'])) {
                $tpl_comment['U_EDIT'] = \Phyxo\Functions\URL::add_url_params(
                    $url_self,
                    [
                        'action' => 'edit_comment',
                        'comment_to_edit' => $row['id'],
                    ]
                );
                if (isset($edit_comment) and ($row['id'] == $edit_comment)) {
                    $tpl_comment['IN_EDIT'] = true;
                    $key = \Phyxo\Functions\Utils::get_ephemeral_key($conf['key_comment_valid_time'], $page['image_id']);
                    $tpl_comment['KEY'] = $key;
                    $tpl_comment['CONTENT'] = $row['content'];
                    //$tpl_comment['PWG_TOKEN'] = \Phyxo\Functions\Utils::get_token(); @TODO: use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
                    $tpl_comment['U_CANCEL'] = $url_self;
                }
            }
            if ($services['users']->isAdmin()) {
                $tpl_comment['EMAIL'] = $email;

                if ($row['validated'] != 'true') {
                    $tpl_comment['U_VALIDATE'] = \Phyxo\Functions\URL::add_url_params(
                        $url_self,
                        [
                            'action' => 'validate_comment',
                            'comment_to_validate' => $row['id'],
                            // 'pwg_token' => \Phyxo\Functions\Utils::get_token(),
                        ]
                    );
                }
            }
            $template->append('comments', $tpl_comment);
        }
    }

    $show_add_comment_form = true;
    if (isset($edit_comment)) {
        $show_add_comment_form = false;
    }
    if ($services['users']->isGuest() and !$conf['comments_forall']) {
        $show_add_comment_form = false;
    }

    if ($show_add_comment_form) {
        $key = \Phyxo\Functions\Utils::get_ephemeral_key($conf['key_comment_valid_time'], $page['image_id']);

        $tpl_var = [
            'F_ACTION' => $url_self,
            'KEY' => $key,
            'CONTENT' => '',
            'SHOW_AUTHOR' => !$services['users']->isClassicUser(),
            'AUTHOR_MANDATORY' => $conf['comments_author_mandatory'],
            'AUTHOR' => '',
            'WEBSITE_URL' => '',
            'SHOW_EMAIL' => !$services['users']->isClassicUser() or empty($user['email']),
            'EMAIL_MANDATORY' => $conf['comments_email_mandatory'],
            'EMAIL' => '',
            'SHOW_WEBSITE' => $conf['comments_enable_website'],
        ];

        if (!empty($comment_action) && $comment_action == 'reject') {
            foreach (['content', 'author', 'website_url', 'email'] as $k) {
                $tpl_var[strtoupper($k)] = htmlspecialchars(stripslashes(@$_POST[$k]));
            }
        }
        $template->assign('comment_add', $tpl_var);
    }
}
