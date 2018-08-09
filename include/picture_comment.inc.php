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

    $comm = array(
        'author' => trim(@$_POST['author']),
        'content' => trim($_POST['content']),
        'website_url' => trim(@$_POST['website_url']),
        'email' => trim(@$_POST['email']),
        'image_id' => $page['image_id'],
    );

    if (empty($_POST['key'])) {
        $comment_action = 'reject';
    } else {
        $comment_action = $services['comments']->insertUserComment($comm, $_POST['key'], $page['errors']);
    }

    switch ($comment_action) {
        case 'moderate':
            $page['infos'][] = \Phyxo\Functions\Language::l10n('An administrator must authorize your comment before it is visible.');
        case 'validate':
            $page['infos'][] = \Phyxo\Functions\Language::l10n('Your comment has been registered');
            break;
        case 'reject':
            set_status_header(403);
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Your comment has NOT been registered because it did not pass the validation rules');
            break;
        default:
            trigger_error('Invalid comment action ' . $comment_action, E_USER_WARNING);
    }

    // allow plugins to notify what's going on
    \Phyxo\Functions\Plugin::trigger_notify('user_comment_insertion', array_merge($comm, array('action' => $comment_action)));
} elseif (isset($_POST['content'])) {
    set_status_header(403);
    die('ugly spammer');
}

if ($page['show_comments']) {
    if (!$services['users']->isAdmin()) {
        $validated_clause = '  AND validated = \'' . $conn->boolean_to_db(true) . '\'';
    } else {
        $validated_clause = '';
    }

    // number of comments for this picture
    $query = 'SELECT COUNT(1) AS nb_comments FROM ' . COMMENTS_TABLE;
    $query .= ' WHERE image_id = ' . $page['image_id'] . $validated_clause . ';';
    $row = $conn->db_fetch_assoc($conn->db_query($query));

    // navigation bar creation
    if (!isset($page['start'])) {
        $page['start'] = 0;
    }

    $navigation_bar = create_navigation_bar(
        \Phyxo\Functions\URL::duplicate_picture_url(array(), array('start')),
        $row['nb_comments'],
        $page['start'],
        $conf['nb_comment_page'],
        true // We want a clean URL
    );

    $template->assign(
        array(
            'COMMENT_COUNT' => $row['nb_comments'],
            'navbar' => $navigation_bar,
        )
    );

    if ($row['nb_comments'] > 0) {
        // comments order (get, session, conf)
        if (!empty($_GET['comments_order']) && in_array(strtoupper($_GET['comments_order']), array('ASC', 'DESC'))) {
            $_SESSION['comments_order'] = $_GET['comments_order'];
        }
        $comments_order = isset($_SESSION['comments_order']) ? $_SESSION['comments_order'] : $conf['comments_order'];

        $template->assign(array(
            'COMMENTS_ORDER_URL' => \Phyxo\Functions\URL::add_url_params(
                \Phyxo\Functions\URL::duplicate_picture_url(),
                array('comments_order' => ($comments_order == 'ASC' ? 'DESC' : 'ASC'))
            ),
            'COMMENTS_ORDER_TITLE' => $comments_order == 'ASC' ? \Phyxo\Functions\Language::l10n('Show latest comments first') : \Phyxo\Functions\Language::l10n('Show oldest comments first'),
        ));

        $query = 'SELECT com.id, author, author_id,u.' . $conf['user_fields']['email'] . ' AS user_email,';
        $query .= 'date,image_id,website_url,com.email,content, validated FROM ' . COMMENTS_TABLE . ' AS com';
        $query .= ' LEFT JOIN ' . USERS_TABLE . ' AS u ON u.' . $conf['user_fields']['id'] . ' = author_id';
        $query .= ' WHERE image_id = ' . $page['image_id'];
        $query .= ' ' . $validated_clause;
        $query .= ' ORDER BY date ' . $comments_order;
        $query .= ' LIMIT ' . $conf['nb_comment_page'] . ' OFFSET ' . $page['start'] . ';';
        $result = $conn->db_query($query);

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
                array(
                'ID' => $row['id'],
                'AUTHOR' => \Phyxo\Functions\Plugin::trigger_change('render_comment_author', $row['author']),
                'DATE' => format_date($row['date'], array('day_name', 'day', 'month', 'year', 'time')),
                'CONTENT' => \Phyxo\Functions\Plugin::trigger_change('render_comment_content', $row['content']),
                'WEBSITE_URL' => $row['website_url'],
            );

            if ($services['users']->canManageComment('delete', $row['author_id'])) {
                $tpl_comment['U_DELETE'] = \Phyxo\Functions\URL::add_url_params(
                    $url_self,
                    array(
                        'action' => 'delete_comment',
                        'comment_to_delete' => $row['id'],
                        'pwg_token' => get_pwg_token(),
                    )
                );
            }
            if ($services['users']->canManageComment('edit', $row['author_id'])) {
                $tpl_comment['U_EDIT'] = \Phyxo\Functions\URL::add_url_params(
                    $url_self,
                    array(
                        'action' => 'edit_comment',
                        'comment_to_edit' => $row['id'],
                    )
                );
                if (isset($edit_comment) and ($row['id'] == $edit_comment)) {
                    $tpl_comment['IN_EDIT'] = true;
                    $key = get_ephemeral_key($conf['key_comment_valid_time'], $page['image_id']);
                    $tpl_comment['KEY'] = $key;
                    $tpl_comment['CONTENT'] = $row['content'];
                    $tpl_comment['PWG_TOKEN'] = get_pwg_token();
                    $tpl_comment['U_CANCEL'] = $url_self;
                }
            }
            if ($services['users']->isAdmin()) {
                $tpl_comment['EMAIL'] = $email;

                if ($row['validated'] != 'true') {
                    $tpl_comment['U_VALIDATE'] = \Phyxo\Functions\URL::add_url_params(
                        $url_self,
                        array(
                            'action' => 'validate_comment',
                            'comment_to_validate' => $row['id'],
                            'pwg_token' => get_pwg_token(),
                        )
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
        $key = get_ephemeral_key($conf['key_comment_valid_time'], $page['image_id']);

        $tpl_var = array(
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
        );

        if (!empty($comment_action) && $comment_action == 'reject') {
            foreach (array('content', 'author', 'website_url', 'email') as $k) {
                $tpl_var[strtoupper($k)] = htmlspecialchars(stripslashes(@$_POST[$k]));
            }
        }
        $template->assign('comment_add', $tpl_var);
    }
}
