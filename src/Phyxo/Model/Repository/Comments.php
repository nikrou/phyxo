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

namespace Phyxo\Model\Repository;

use Phyxo\Functions\Plugin;

class Comments extends BaseRepository
{
    protected $conn;

    /**
     * Does basic check on comment and returns action to perform.
     * This method is called by a trigger_change()
     *
     * @param string $action before check
     * @param array $comment
     * @return string validate, moderate, reject
     */
    public function userCommentCheck($action, $comment)
    {
        global $conf, $user, $services;

        if ($action == 'reject') {
            return $action;
        }

        $my_action = $conf['comment_spam_reject'] ? 'reject' : 'moderate';

        if ($action == $my_action) {
            return $action;
        }

        // we do here only BASIC spam check (plugins can do more)
        if (!$services['users']->isGuest()) {
            return $action;
        }

        $link_count = preg_match_all('/https?:\/\//', $comment['content'], $matches);

        if (strpos($comment['author'], 'http://') !== false) {
            $link_count++;
        }

        if ($link_count > $conf['comment_spam_max_links']) {
            $_POST['cr'][] = 'links';
            return $my_action;
        }

        return $action;
    }

    /**
     * Tries to insert a user comment and returns action to perform.
     *
     * @param array &$comm
     * @param string $key secret key sent back to the browser
     * @param array &$infos output array of error messages
     * @return string validate, moderate, reject
     */
    public function insertUserComment(&$comm, $key, &$infos)
    {
        global $conf, $user, $services;

        $comm = array_merge(
            $comm,
            array(
                'ip' => $_SERVER['REMOTE_ADDR'],
                'agent' => $_SERVER['HTTP_USER_AGENT']
            )
        );

        $infos = array();
        if (!$conf['comments_validation'] or $services['users']->isAdmin()) {
            $comment_action = 'validate'; //one of validate, moderate, reject
        } else {
            $comment_action = 'moderate'; //one of validate, moderate, reject
        }

        // display author field if the user status is guest or generic
        if (!$services['users']->isClassicUser()) {
            if (empty($comm['author'])) {
                if ($conf['comments_author_mandatory']) {
                    $infos[] = \Phyxo\Functions\Language::l10n('Username is mandatory');
                    $comment_action = 'reject';
                }
                $comm['author'] = 'guest';
            }
            $comm['author_id'] = $conf['guest_id'];
            // if a guest try to use the name of an already existing user, he must be rejected
            if ($comm['author'] != 'guest') {
                $query = 'SELECT COUNT(1) AS user_exists FROM ' . USERS_TABLE;
                $query .= ' WHERE ' . $conf['user_fields']['username'] . " = '" . $this->conn->db_real_escape_string($comm['author']) . "'";
                $row = $this->conn->db_fetch_assoc($this->conn->db_query($query));
                if ($row['user_exists'] == 1) {
                    $infos[] = \Phyxo\Functions\Language::l10n('This login is already used by another user');
                    $comment_action = 'reject';
                }
            }
        } else {
            $comm['author'] = addslashes($user['username']); // @TODO: remove addslashes
            $comm['author_id'] = $user['id'];
        }

        if (empty($comm['content'])) { // empty comment content
            $comment_action = 'reject';
        }

        if (!\Phyxo\Functions\Utils::verify_ephemeral_key($key, $comm['image_id'])) {
            $comment_action = 'reject';
            $_POST['cr'][] = 'key'; // @TODO: remove ? rvelices: I use this outside to see how spam robots work
        }

        // website
        if (!empty($comm['website_url'])) {
            if (!$conf['comments_enable_website']) { // honeypot: if the field is disabled, it should be empty !
                $comment_action = 'reject';
                $_POST['cr'][] = 'website_url';
            } else {
                $comm['website_url'] = strip_tags($comm['website_url']);
                if (!preg_match('/^https?/i', $comm['website_url'])) {
                    $comm['website_url'] = 'http://' . $comm['website_url'];
                }
                if (!\Phyxo\Functions\Utils::url_check_format($comm['website_url'])) {
                    $infos[] = \Phyxo\Functions\Language::l10n('Your website URL is invalid');
                    $comment_action = 'reject';
                }
            }
        }

        // email
        if (empty($comm['email'])) {
            if (!empty($user['email'])) {
                $comm['email'] = $user['email'];
            } elseif ($conf['comments_email_mandatory']) {
                $infos[] = \Phyxo\Functions\Language::l10n('Email address is missing. Please specify an email address.');
                $comment_action = 'reject';
            }
        } elseif (!\Phyxo\Functions\Utils::email_check_format($comm['email'])) {
            $infos[] = \Phyxo\Functions\Language::l10n('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
            $comment_action = 'reject';
        }

        // anonymous id = ip address
        $ip_components = explode('.', $comm['ip']);
        if (count($ip_components) > 3) {
            array_pop($ip_components);
        }
        $anonymous_id = implode('.', $ip_components);

        if ($comment_action != 'reject' and $conf['anti-flood_time'] > 0 and !$services['users']->isAdmin()) { // anti-flood system
            $reference_date = $this->conn->db_get_flood_period_expression($conf['anti-flood_time']);

            $query = 'SELECT count(1) FROM ' . COMMENTS_TABLE;
            $query .= ' WHERE date > ' . $reference_date . ' AND author_id = ' . $this->conn->db_real_escape_string($comm['author_id']);
            if (!$services['users']->isClassicUser()) {
                $query .= ' AND anonymous_id LIKE \'' . $anonymous_id . '.%\'';
            }

            list($counter) = $this->conn->db_fetch_row($this->conn->db_query($query));
            if ($counter > 0) {
                $infos[] = \Phyxo\Functions\Language::l10n('Anti-flood system : please wait for a moment before trying to post another comment');
                $comment_action = 'reject';
                $_POST['cr'][] = 'flood_time';
            }
        }

        // perform more spam check
        $comment_action = Plugin::trigger_change('user_comment_check', $comment_action, $comm);

        if ($comment_action != 'reject') {
            $query = 'INSERT INTO ' . COMMENTS_TABLE;
            $query .= ' (author, author_id, anonymous_id, content, date, validated, validation_date, image_id, website_url, email)';
            $query .= ' VALUES (\'' . $comm['author'] . '\',';
            $query .= $this->conn->db_real_escape_string($comm['author_id']) . ', \'' . $comm['ip'] . '\',';
            $query .= '\'' . $this->conn->db_real_escape_string($comm['content']) . '\', NOW(), \'';
            $query .= $comment_action == 'validate' ? $this->conn->boolean_to_db(true) : $this->conn->boolean_to_db(false);
            $query .= '\', ' . ($comment_action == 'validate' ? 'NOW()' : 'NULL') . ',' . $comm['image_id'] . ',';
            $query .= ' ' . (!empty($comm['website_url']) ? '\'' . $this->conn->db_real_escape_string($comm['website_url']) . '\'' : 'NULL') . ',';
            $query .= ' ' . (!empty($comm['email']) ? '\'' . $this->conn->db_real_escape_string($comm['email']) . '\'' : 'NULL') . ')';
            $this->conn->db_query($query);

            $comm['id'] = $this->conn->db_insert_id(COMMENTS_TABLE);

            $this->invalidateUserCacheNbComments();

            if (($conf['email_admin_on_comment'] && 'validate' == $comment_action)
                or ($conf['email_admin_on_comment_validation'] and 'moderate' == $comment_action)) {
                $comment_url = \Phyxo\Functions\URL::get_absolute_root_url() . 'comments.php?comment_id=' . $comm['id'];

                $keyargs_content = array(
                    \Phyxo\Functions\Language::get_l10n_args('Author: %s', stripslashes($comm['author'])),
                    \Phyxo\Functions\Language::get_l10n_args('Email: %s', stripslashes($comm['email'])),
                    \Phyxo\Functions\Language::get_l10n_args('Comment: %s', stripslashes($comm['content'])),
                    \Phyxo\Functions\Language::get_l10n_args(''),
                    \Phyxo\Functions\Language::get_l10n_args('Manage this user comment: %s', $comment_url),
                );

                if ('moderate' == $comment_action) {
                    $keyargs_content[] = \Phyxo\Functions\Language::get_l10n_args('(!) This comment requires validation');
                }

                \Phyxo\Functions\Mail::mail_notification_admins(
                    \Phyxo\Functions\Language::get_l10n_args('Comment by %s', stripslashes($comm['author'])),
                    $keyargs_content
                );
            }
        }

        return $comment_action;
    }

    /**
     * Tries to delete a (or more) user comment.
     *    only admin can delete all comments
     *    other users can delete their own comments
     *
     * @param int|int[] $comment_id
     * @return bool false if nothing deleted
     */
    public function deleteUserComment($comment_id)
    {
        global $services;

        $user_where_clause = '';
        if (!$services['users']->isAdmin()) {
            // @TODO : don't use GLOBALS
            $user_where_clause = ' AND author_id = \'' . $this->conn->db_real_escape_string($GLOBALS['user']['id']) . '\'';
        }

        if (is_array($comment_id)) {
            $where_clause = 'id ' . $this->conn->in($comment_id);
        } else {
            $where_clause = 'id = ' . $this->conn->db_real_escape_string($comment_id);
        }

        $query = 'DELETE FROM ' . COMMENTS_TABLE;
        $query .= ' WHERE ' . $where_clause . $user_where_clause . ';';

        if ($this->conn->db_changes($this->conn->db_query($query))) {
            $this->invalidateUserCacheNbComments();

            $this->email_admin(
                'delete',
                array(
                    'author' => $GLOBALS['user']['username'],
                    'comment_id' => $comment_id
                )
            );
            Plugin::trigger_notify('user_comment_deletion', $comment_id);

            return true;
        }

        return false;
    }

    /**
     * Returns the author id of a comment
     *
     * @param int $comment_id
     * @param bool $die_on_error
     * @return int
     */
    public function getCommentAuthorId($comment_id, $die_on_error = true)
    {
        $query = 'SELECT author_id FROM ' . COMMENTS_TABLE;
        $query .= ' WHERE id = ' . $this->conn->db_real_escape_string($comment_id);
        $result = $this->conn->db_query($query);
        if ($this->conn->db_num_rows($result) == 0) {
            if ($die_on_error) {
                \Phyxo\Functions\HTTP::fatal_error('Unknown comment identifier');
            } else {
                return false;
            }
        }

        list($author_id) = $this->conn->db_fetch_row($result);

        return $author_id;
    }

    /**
     * Tries to update a user comment
     *    only admin can update all comments
     *    users can edit their own comments if admin allow them
     *
     * @param array $comment
     * @param string $post_key secret key sent back to the browser
     * @return string validate, moderate, reject
     */
    public function updateUserComment($comment, $post_key)
    {
        global $conf, $page, $services;

        $comment_action = 'validate';

        if (!\Phyxo\Functions\Utils::verify_ephemeral_key($post_key, $comment['image_id'])) {
            $comment_action = 'reject';
        } elseif (!$conf['comments_validation'] or $services['users']->isAdmin()) { // should the updated comment must be validated
            $comment_action = 'validate'; //one of validate, moderate, reject
        } else {
            $comment_action = 'moderate'; //one of validate, moderate, reject
        }

        // perform more spam check
        $comment_action =
            Plugin::trigger_change(
            'user_comment_check',
            $comment_action,
            array_merge(
                $comment,
                array('author' => $GLOBALS['user']['username'])
            )
        );

        // website
        if (!empty($comment['website_url'])) {
            $comm['website_url'] = strip_tags($comm['website_url']);
            if (!preg_match('/^https?/i', $comment['website_url'])) {
                $comment['website_url'] = 'http://' . $comment['website_url'];
            }
            if (!\Phyxo\Functions\Utils::url_check_format($comment['website_url'])) {
                $page['errors'][] = \Phyxo\Functions\Language::l10n('Your website URL is invalid');
                $comment_action = 'reject';
            }
        }

        if ($comment_action != 'reject') {
            $user_where_clause = '';
            if (!$services['users']->isAdmin()) {
                $user_where_clause = ' AND author_id = \'' . $this->conn->db_real_escape_string($GLOBALS['user']['id']) . '\'';
            }

            $query = 'UPDATE ' . COMMENTS_TABLE;
            $query .= ' SET content = \'' . $comment['content'] . '\',';
            $query .= ' website_url = ' . (!empty($comment['website_url']) ? '\'' . $this->conn->db_real_escape_string($comment['website_url']) . '\'' : 'NULL') . ',';
            $query .= ' validated = \'' . ($comment_action == 'validate' ? '' . $this->conn->boolean_to_db(true) . '' : '' . $this->conn->boolean_to_db(false) . '') . '\',';
            $query .= ' validation_date = ' . ($comment_action == 'validate' ? 'NOW()' : 'NULL');
            $query .= ' WHERE id = ' . $this->conn->db_real_escape_string($comment['comment_id']) . $user_where_clause . ';';
            $result = $this->conn->db_query($query);

            // mail admin and ask to validate the comment
            if ($result and $conf['email_admin_on_comment_validation'] and 'moderate' == $comment_action) {
                $comment_url = \Phyxo\Functions\URL::get_absolute_root_url() . 'comments.php?comment_id=' . $comment['comment_id'];

                $keyargs_content = array(
                    \Phyxo\Functions\Language::get_l10n_args('Author: %s', stripslashes($GLOBALS['user']['username'])),
                    \Phyxo\Functions\Language::get_l10n_args('Comment: %s', stripslashes($comment['content'])),
                    \Phyxo\Functions\Language::get_l10n_args(''),
                    \Phyxo\Functions\Language::get_l10n_args('Manage this user comment: %s', $comment_url),
                    \Phyxo\Functions\Language::get_l10n_args('(!) This comment requires validation'),
                );

                \Phyxo\Functions\Mail::mail_notification_admins(
                    \Phyxo\Functions\Language::get_l10n_args('Comment by %s', stripslashes($GLOBALS['user']['username'])),
                    $keyargs_content
                );
            } elseif ($result) {
                // just mail admin
                $this->email_admin('edit', array('author' => $GLOBALS['user']['username'], 'content' => stripslashes($comment['content'])));
            }
        }

        return $comment_action;
    }

    /**
     * Tries to validate a user comment.
     *
     * @param int|int[] $comment_id
     */
    public function validateUserComment($comment_id)
    {
        if (is_array($comment_id)) {
            $where_clause = 'id ' . $this->conn->in($comment_id);
        } else {
            $where_clause = 'id = ' . $this->conn->db_real_escape_string($comment_id);
        }

        $query = 'UPDATE ' . COMMENTS_TABLE;
        $query .= ' SET validated = \'' . $this->conn->boolean_to_db(true) . '\', validation_date = NOW() WHERE ' . $where_clause . ';';
        $this->conn->db_query($query);

        $this->invalidateUserCacheNbComments();
        Plugin::trigger_notify('user_comment_validation', $comment_id);
    }

    /**
     * Clears cache of nb comments for all users
     */
    private function invalidateUserCacheNbComments()
    {
        global $user;

        unset($user['nb_available_comments']);

        $query = 'UPDATE ' . USER_CACHE_TABLE . ' SET nb_available_comments = NULL;';
        $this->conn->db_query($query);
    }

    /**
     * Notifies admins about updated or deleted comment.
     * Only used when no validation is needed, otherwise \Phyxo\Functions\Mail::mail_notification_admins() is used.
     *
     * @param string $action edit, delete
     * @param array $comment
     *
     * @TODO : move to services notification
     */
    private function email_admin($action, $comment)
    {
        global $conf;

        if (!in_array($action, array('edit', 'delete'))
            or (($action == 'edit') and !$conf['email_admin_on_comment_edition'])
            or (($action == 'delete') and !$conf['email_admin_on_comment_deletion'])) {
            return;
        }

        $keyargs_content = array(\Phyxo\Functions\Language::l10n_args('Author: %s', $comment['author']));

        if ($action == 'delete') {
            $keyargs_content[] = \Phyxo\Functions\Language::get_l10n_args('This author removed the comment with id %d', $comment['comment_id']);
        } else {
            $keyargs_content[] = \Phyxo\Functions\Language::get_l10n_args('This author modified following comment:');
            $keyargs_content[] = \Phyxo\Functions\Language::get_l10n_args('Comment: %s', $comment['content']);
        }

        \Phyxo\Functions\Mail::mail_notification_admins(
            \Phyxo\Functions\Language::get_l10n_args('Comment by %s', $comment['author']),
            $keyargs_content
        );
    }
}