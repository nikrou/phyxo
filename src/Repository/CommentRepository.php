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

namespace App\Repository;

use Symfony\Component\Security\Core\User\UserInterface;


class CommentRepository extends BaseRepository
{
    public function count(? bool $validated = null) : int
    {
        $query = 'SELECT COUNT(1) FROM ' . self::COMMENTS_TABLE;
        if (!is_null($validated)) {
            $query .= ' WHERE validated = \'' . $this->conn->boolean_to_db($validated) . '\'';
        }
        list($nb_comments) = $this->conn->db_fetch_row($this->conn->db_query($query));

        return $nb_comments;
    }

    public function countByImage(int $image_id, bool $isAdmin = false) : int
    {
        $query = 'SELECT COUNT(1) FROM ' . self::COMMENTS_TABLE;
        $query .= ' WHERE image_id = ' . $this->conn->db_real_escape_string($image_id);
        if (!$isAdmin) {
            $query .= ' AND validated = \'' . $this->conn->boolean_to_db(true) . '\'';
        }
        list($nb_comments) = $this->conn->db_fetch_row($this->conn->db_query($query));

        return $nb_comments;
    }

    public function countGroupByImage(array $images, bool $validated = true)
    {
        $query = 'SELECT image_id, COUNT(1) AS nb_comments FROM ' . self::COMMENTS_TABLE;
        $query .= ' WHERE validated = \'' . $this->conn->boolean_to_db($validated) . '\'';
        $query .= ' AND image_id ' . $this->conn->in($images);
        $query .= ' GROUP BY image_id';

        return $this->conn->db_query($query);
    }

    public function countGroupByValidated()
    {
        $query = 'SELECT COUNT(1) AS counter, validated FROM ' . self::COMMENTS_TABLE . ' GROUP BY validated;';

        return $this->conn->db_query($query);
    }

    public function getLastComments(array $params = [], bool $count_only = false)
    {
        if ($count_only) {
            $query = 'SELECT count(1)';
        } else {
            $query = 'SELECT com.id AS comment_id, com.image_id, ic.category_id, com.author,';
            $query .= 'com.author_id, u.mail_address AS user_email, com.email,';
            $query .= 'com.date,com.website_url,com.content,com.validated';
        }
        $query .= ' FROM ' . self::COMMENTS_TABLE . ' AS com';
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON ic.image_id = com.image_id';
        $query .= ' LEFT JOIN ' . self::USERS_TABLE . ' As u ON u.id = com.author_id';
        $query .= ' WHERE ' . implode(' AND ', $params['where_clauses']);

        if (!$count_only) {
            $query .= ' GROUP BY com.id, ic.category_id, u.mail_address';
            $query .= ' ORDER BY ' . $params['order_by'];

            if (!empty($params['limit'])) {
                $query .= ' LIMIT ' . $params['limit'] . ' OFFSET ' . $params['offset'];
            }
        }

        if ($count_only) {
            list($nb_comments) = $this->conn->db_fetch_row($this->conn->db_query($query));

            return $nb_comments;
        } else {
            return $this->conn->db_query($query);
        }
    }

    public function getCommentsOnImage(int $image_id, string $order, int $limit, int $offset = 0, bool $isAdmin = false)
    {
        $query = 'SELECT com.id, author, author_id,u.mail_address AS user_email,';
        $query .= 'date,image_id,website_url,com.email,content, validated FROM ' . self::COMMENTS_TABLE . ' AS com';
        $query .= ' LEFT JOIN ' . self::USERS_TABLE . ' AS u ON u.id = author_id';
        $query .= ' WHERE image_id = ' . $image_id;
        if (!$isAdmin) {
            $query .= ' AND validated = \'' . $this->conn->boolean_to_db(true) . '\'';
        }
        $query .= ' ORDER BY date ' . $order;
        $query .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->conn->db_query($query);
    }

    public function getCommentOnImages(int $limit, int $offset = 0, ? bool $validated)
    {
        $query = 'SELECT c.id,c.image_id,c.date,c.author,u.username,c.content,i.path,';
        $query .= 'i.representative_ext,validated,c.anonymous_id FROM ' . self::COMMENTS_TABLE . ' AS c';
        $query .= ' LEFT JOIN ' . self::IMAGES_TABLE . ' AS i ON i.id = c.image_id';
        $query .= ' LEFT JOIN ' . self::USERS_TABLE . ' AS u ON u.id = c.author_id';
        if (is_bool($validated)) {
            $query .= ' WHERE validated = \'' . $this->conn->boolean_to_db($validated) . '\'';
        }
        $query .= ' ORDER BY c.date DESC';
        $query .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->conn->db_query($query);
    }

    public function countAuthorMessageNewerThan(int $author_id, string $anti_flood_time, string $anonymous_id = null)
    {
        $reference_date = $this->conn->db_get_flood_period_expression($anti_flood_time);

        $query = 'SELECT count(1) FROM ' . self::COMMENTS_TABLE;
        $query .= ' WHERE date > ' . $reference_date . ' AND author_id = ' . $this->conn->db_real_escape_string($author_id);
        if ($anonymous_id) {
            $query .= ' AND anonymous_id LIKE \'' . $anonymous_id . '.%\'';
        }

        list($counter) = $this->conn->db_fetch_row($this->conn->db_query($query));

        return $counter;
    }

    public function addComment(array $values)
    {
        $fields = ['author', 'author_id', 'anonymous_id', 'content', 'date', 'validated', 'image_id', 'website_url', 'email'];
        if (!empty($values['validated'])) {
            $fields[] = 'validation_date';
        }

        $query = 'INSERT INTO ' . self::COMMENTS_TABLE;
        $query .= ' (' . implode(',', $fields) . ')';
        $query .= ' VALUES(';
        $query .= "'" . $this->conn->db_real_escape_string($values['author']) . "',";
        $query .= $this->conn->db_real_escape_string($values['author_id']) . ",";
        $query .= "'" . $this->conn->db_real_escape_string($values['anonymous_id']) . "',";
        $query .= "'" . $this->conn->db_real_escape_string($values['content']) . "',";
        $query .= $values['date'] . ",";
        $query .= "'" . $this->conn->boolean_to_db($values['validated']) . "',";
        $query .= $values['image_id'] . ",";
        $query .= "'" . $this->conn->db_real_escape_string($values['website_url']) . "',";
        $query .= "'" . $this->conn->db_real_escape_string($values['email']) . "'";
        if (!empty($values['validated'])) {
            $query .= ', now()';
        }
        $query .= ')';
        $this->conn->db_query($query);

        return $this->conn->db_insert_id(self::COMMENTS_TABLE);
    }

    // @comments can be an array of comment id or a comment id
    public function deleteByIds($comments, int $author_id = null)
    {
        $query = 'DELETE FROM ' . self::COMMENTS_TABLE;
        if (is_array($comments)) {
            $query .= ' WHERE id ' . $this->conn->in($comments);
        } else {
            $query .= ' WHERE id = ' . $comments;
        }

        if ($author_id) {
            $query .= ' AND author_id = ' . $author_id;
        }
        $result = $this->conn->db_query($query);

        return $this->conn->db_changes($result);
    }

    public function deleteByUserId(int $author_id)
    {
        $query = 'DELETE FROM ' . self::COMMENTS_TABLE;
        $query .= ' WHERE author_id = ' . $author_id;
        $result = $this->conn->db_query($query);

        return $this->conn->db_changes($result);
    }

    public function deleteByImage($images)
    {
        $query = 'DELETE FROM ' . self::COMMENTS_TABLE;
        if (is_array($images)) {
            $query .= ' WHERE image_id ' . $this->conn->in($images);
        } else {
            $query .= ' WHERE image_id = ' . $images;
        }
        $result = $this->conn->db_query($query);

        return $this->conn->db_changes($result);
    }

    /**
     * Returns the author id of a comment
     */
    public function getCommentAuthorId(int $comment_id) : ? int
    {
        $query = 'SELECT author_id FROM ' . self::COMMENTS_TABLE;
        $query .= ' WHERE id = ' . $this->conn->db_real_escape_string($comment_id);
        $result = $this->conn->db_query($query);
        if ($this->conn->db_num_rows($result) === 0) {
            return null;
        }

        list($author_id) = $this->conn->db_fetch_row($result);

        return $author_id;
    }

    public function updateComment(array $comment, string $user_where_clause = '')
    {
        $query = 'UPDATE ' . self::COMMENTS_TABLE;
        $query .= ' SET content = \'' . $this->conn->db_real_escape_string($comment['content']) . '\',';
        $query .= ' website_url = \'' . $this->conn->db_real_escape_string($comment['website_url']) . '\',';
        $query .= ' validated = \'' . $this->conn->boolean_to_db($comment['validated']) . '\'';
        if (!empty($comment['validated'])) {
            $query .= ', validation_date = now()';
        }
        $query .= ' WHERE id = ' . $this->conn->db_real_escape_string($comment['comment_id']);
        $query .= $user_where_clause;

        return $this->conn->db_query($query);
    }

    public function validateUserComment($comment_ids)
    {
        $query = 'UPDATE ' . self::COMMENTS_TABLE;
        $query .= ' SET validated = \'' . $this->conn->boolean_to_db(true) . '\', validation_date = NOW()';
        if (is_array($comment_ids)) {
            $query .= ' WHERE id ' . $this->conn->in($comment_ids);
        } else {
            $query .= ' WHERE id = ' . $comment_ids;
        }

        return $this->conn->db_query($query);
    }

    public function getCommentsByImagePerPage(int $image_id, int $limit, int $offset = 0)
    {
        $query = 'SELECT id, date, author, content FROM ' . self::COMMENTS_TABLE;
        $query .= ' WHERE image_id = ' . $this->conn->db_real_escape_string($image_id);
        $query .= ' ORDER BY date';
        $query .= ' LIMIT ' . $limit;
        $query .= ' OFFSET ' . $offset;

        return $this->conn->db_query($query);
    }

    public function getNewComments(UserInterface $user, array $filter = [], \DateTimeInterface $start = null, \DateTimeInterface $end = null, bool $count_only = false)
    {
        if ($count_only) {
            $query = 'SELECT count(1)';
        } else {
            $query = 'SELECT c.id';
        }
        $query .= ' FROM ' . self::COMMENTS_TABLE . ' AS c';
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON c.image_id = ic.image_id';
        $query .= ' WHERE';

        if (!is_null($start)) {
            $query .= ' c.validation_date > \'' . $this->conn->db_real_escape_string($start->format('Y-m-d H:m:i')) . '\'';
        }

        if (!is_null($end)) {
            if (!is_null($start)) {
                $query .= ' AND';
            }
            $query .= ' c.validation_date <= \'' . $this->conn->db_real_escape_string($end->format('Y-m-d H:m:i')) . '\'';
        }

        $query .= ' ' . $this->getStandardSQLWhereRestrictFilter($user, $filter, ' AND ');

        if ($count_only) {
            list($nb_comments) = $this->conn->db_fetch_row($this->conn->db_query($query));

            return $nb_comments;
        } else {
            return $this->conn->db_query($query);
        }
    }

    public function getUnvalidatedComments(\DateTimeInterface $start = null, \DateTimeInterface $end, bool $count_only)
    {
        if ($count_only) {
            $query = 'SELECT count(1)';
        } else {
            $query = 'SELECT id';
        }
        $query .= ' FROM ' . self::COMMENTS_TABLE;
        $query .= ' WHERE';

        if (!is_null($start)) {
            $query .= ' date > \'' . $this->conn->db_real_escape_string($start->format('Y-m-d H:m:i')) . '\'';
        }

        if (!is_null($end)) {
            if (!is_null($start)) {
                $query .= ' AND';
            }
            $query .= ' date <= \'' . $this->conn->db_real_escape_string($end->format('Y-m-d H:m:i')) . '\'';
        }

        $query .= ' AND validated = \'' . $this->conn->boolean_to_db(false) . '\'';

        if ($count_only) {
            list($nb_comments) = $this->conn->db_fetch_row($this->conn->db_query($query));

            return $nb_comments;
        } else {
            return $this->conn->db_query($query);
        }
    }
}
