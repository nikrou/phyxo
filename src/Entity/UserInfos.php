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

namespace App\Entity;

class UserInfos implements \ArrayAccess
{
    private $infos = [];

    public function __construct(array $infos = [])
    {
        $this->infos = $infos;
    }

    public function asArray()
    {
        return $this->infos;
    }

    public function offsetExists($offset)
    {
        return isset($this->infos[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->infos[$offset] ?? null;
    }

    public function offsetSet($offset, $value)
    {
        $this->infos[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->infos[$offset]);
    }

    public function getTheme()
    {
        return $this->infos['theme'] ?? null;
    }

    public function getNbImagePage()
    {
        return $this->infos['nb_image_page'] ?? null;
    }

    public function getNbAvailableTags()
    {
        return $this->infos['nb_available_tags'] ?? null;
    }

    public function setNbAvailableTags(int $number_of_tags)
    {
        $this->infos['nb_available_tags'] = $number_of_tags;
    }

    public function setNbAvailableComments(int $number_of_comments)
    {
        $this->infos['nb_available_comments'] = $number_of_comments;
    }

    public function getNbTotalImages()
    {
        return $this->infos['nb_total_images'] ?? null;
    }

    public function getRecentPeriod()
    {
        return $this->infos['recent_period'] ?? null;
    }

    public function getShowNbHits()
    {
        return $this->infos['show_nb_hits'] ?? null;
    }

    public function getLanguage() : string
    {
        return $this->infos['language'] ?? '';
    }

    public function getUserId()
    {
        return $this->infos['user_id'] ?? null;
    }

    public function getLevel()
    {
        return $this->infos['level'] ?? null;
    }

    public function wantExpand()
    {
        return $this->infos['expand'] ?? false;
    }

    /**
     * return an array which will be sent to template to display recent icon
     *
     * @param string $date
     * @param bool $is_child_date
     * @return array
     */
    public function getIcon(\DateTime $date = null, $is_child_date = false)
    {
        if ($date === null) {
            return [];
        }

        $icon = [
            'TITLE' => \Phyxo\Functions\Language::l10n(
                'photos posted during the last %d days',
                $this->getRecentPeriod()
            ),
            'IS_CHILD_DATE' => $is_child_date,
        ];

        $now = new \DateTime('now');
        $sql_recent_date = $now->sub(new \DateInterval(sprintf('P%dD', $this->getRecentPeriod())));

        if ($date > $sql_recent_date) {
            return $icon;
        } else {
            return [];
        }
    }

    /**
     * returns the number of available comments for the connected user
     *
     * @return int
     */
    public function getNbAvailableComments()
    {
        if (!isset($this->infos['nb_available_comments'])) {
            $where = [];

            if ($this->infos['status'] !== 'admin') { // @TODO: is admin ??? use sf role
                $where[] = 'validated=\'' . $conn->boolean_to_db(true) . '\'';
            }
            $where[] = \Phyxo\Functions\SQL::get_sql_condition_FandF(
                [
                    'forbidden_categories' => 'category_id',
                    'forbidden_images' => 'ic.image_id'
                ],
                '',
                true
            );

            $query = 'SELECT COUNT(DISTINCT(com.id)) FROM ' . IMAGE_CATEGORY_TABLE . ' AS ic';
            $query .= ' LEFT JOIN ' . COMMENTS_TABLE . ' AS com ON ic.image_id = com.image_id';
            $query .= ' WHERE ' . implode(' AND ', $where);
            list($this->infos['nb_available_comments']) = $conn->db_fetch_row($conn->db_query($query));

            $conn->single_update(
                USER_CACHE_TABLE,
                ['nb_available_comments' => $this->infos['nb_available_comments']],
                ['user_id' => $infos['user_id']]
            );
        }

        return $this->infos['nb_available_comments'];
    }

    /**
     * log the visit into history table
     *
     * @param int $image_id
     * @param string $image_type
     * @return bool
     */
    public function log($image_id = null, $image_type = null)
    {
        return false; // @TODO: find a better way and a better place to log infos

        $do_log = $conf['log'];
        // @TODO: is admin ??? use sf role
        if ($services['users']->isAdmin()) {
            $do_log = $conf['history_admin'];
        }
        if ($services['users']->isGuest()) {
            $do_log = $conf['history_guest'];
        }

        $do_log = \Phyxo\Functions\Plugin::trigger_change('pwg_log_allowed', $do_log, $image_id, $image_type);

        if (!$do_log) {
            return false;
        }

        $tags_string = null;
        if (!empty($page['section']) && $page['section'] == 'tags') {
            $tags_string = implode(',', $page['tag_ids']);
        }

        $query = 'INSERT INTO ' . HISTORY_TABLE;
        $query .= ' (date,time,user_id,IP,section,category_id,image_id,image_type,tag_ids)';
        $query .= ' VALUES(';
        $query .= ' CURRENT_DATE,CURRENT_TIME,';
        $query .= $user->getUserId() . ',\'' . $conn->db_real_escape_string($_SERVER['REMOTE_ADDR']) . '\',';
        $query .= (isset($page['section']) ? "'" . $conn->db_real_escape_string($page['section']) . "'" : 'NULL') . ',';
        $query .= (isset($page['category']['id']) ? $conn->db_real_escape_string($page['category']['id']) : 'NULL') . ',';
        $query .= (isset($image_id) ? $conn->db_real_escape_string($image_id) : 'NULL') . ',';
        $query .= (isset($image_type) ? "'" . $conn->db_real_escape_string($image_type) . "'" : 'NULL') . ',';
        $query .= (isset($tags_string) ? "'" . $conn->db_real_escape_string($tags_string) . "'" : 'NULL');
        $query .= ');';
        $conn->db_query($query);

        return true;
    }
}
