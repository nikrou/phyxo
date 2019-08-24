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

class UserRepository extends BaseRepository
{
    public function count() : int
    {
        $query = 'SELECT COUNT(1) FROM ' . self::USERS_TABLE;
        $result = $this->conn->db_query($query);
        list($nb_users) = $this->conn->db_fetch_row($result);

        return $nb_users;
    }

    public function findByUsername(string $username)
    {
        $query = 'SELECT id, username, password, mail_address FROM ' . self::USERS_TABLE;
        $query .= ' WHERE username = \'' . $this->conn->db_real_escape_string($username) . '\'';

        return $this->conn->db_query($query);
    }

    public function findByEmail(string $mail_address)
    {
        $query = 'SELECT id, username, password, mail_address FROM ' . self::USERS_TABLE;
        $query .= ' WHERE UPPER(mail_address) = UPPER(\'' . $this->conn->db_real_escape_string($mail_address) . '\');';

        return $this->conn->db_query($query);
    }

    public function findById(int $id)
    {
        $query = 'SELECT id, username, password, mail_address FROM ' . self::USERS_TABLE;
        $query .= ' WHERE id = ' . $id;

        return $this->conn->db_query($query);
    }

    public function findByActivationKey(string $key)
    {
        $query = 'SELECT id, username, password, mail_address FROM ' . self::USERS_TABLE;
        $query .= ' LEFT JOIN ' . self::USER_INFOS_TABLE . ' ON id = user_id';
        $query .= ' WHERE activation_key = \'' . $this->conn->db_real_escape_string($key) . '\'';
        $query .= ' AND activation_key_expire >= now()';

        return $this->conn->db_query($query);
    }

    public function findByIds(array $ids)
    {
        $query = 'SELECT id, username, password, mail_address FROM ' . self::USERS_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($ids);

        return $this->conn->db_query($query);
    }

    public function findAll(? string $order_by = null)
    {
        $query = 'SELECT id, username, password, mail_address FROM ' . self::USERS_TABLE;

        if (!is_null($order_by)) {
            $query .= ' ' . $order_by;
        }

        return $this->conn->db_query($query);
    }

    public function getList(array $fields, array $where, string $order, int $limit, int $offset = 0)
    {
        $query = 'SELECT DISTINCT ';

        $first = true;
        foreach ($fields as $field => $name) {
            if (!$first) {
                $query .= ', ';
            } else {
                $first = false;
            }
            $query .= $field . ' AS ' . $name;
        }

        $query .= ' FROM ' . self::USERS_TABLE . ' AS u';
        $query .= ' LEFT JOIN ' . self::USER_INFOS_TABLE . ' AS ui ON u.id = ui.user_id';
        $query .= ' LEFT JOIN ' . self::USER_GROUP_TABLE . ' AS ug ON u.id = ug.user_id';
        $query .= ' WHERE ' . implode(' AND ', $where);
        $query .= ' ORDER BY ' . $this->conn->db_real_escape_string($order);
        $query .= ' LIMIT ' . $limit;
        $query .= ' OFFSET ' . $offset;

        return $this->conn->db_query($query);
    }

    public function getUserInfosByUsername(string $username)
    {
        $query = 'SELECT u.password, ui.status FROM ' . self::USERS_TABLE . ' AS u';
        $query .= ' LEFT JOIN ' . self::USER_INFOS_TABLE . ' AS ui ON u.id = ui.user_id';
        $query .= ' WHERE username = \'' . $this->conn->db_real_escape_string($username) . '\'';

        return $this->conn->db_query($query);
    }

    public function getUserInfosList(array $wheres = [], array $search_wheres = [], string $order = '', ? int $limit = null, int $offset = 0)
    {
        $query = 'SELECT id, username, password, mail_address, status, nb_image_page, language,';
        $query .= ' expand, show_nb_comments, show_nb_hits, recent_period, theme, registration_date,';
        $query .= ' enabled_high, level, activation_key, activation_key_expire, lastmodified';
        $query .= ' FROM ' . self::USERS_TABLE . ' AS u';
        $query .= ' LEFT JOIN ' . self::USER_INFOS_TABLE . ' AS ui ON u.id = ui.user_id';
        if (count($wheres) > 0 || count($search_wheres) > 0) {
            $query .= ' WHERE';
        }
        if (count($wheres) > 0) {
            $query .= ' ' . implode(' AND ', $wheres);
        }
        if (count($search_wheres) > 0) {
            if (count($wheres) > 0) {
                $query .= ' AND';
            }
            $query .= ' ' . implode(' OR ', $wheres);
        }

        if ($order !== '') {
            $query .= ' ORDER BY ' . $order;
        }
        if (!is_null($limit)) {
            $query .= ' LIMIT ' . $limit;
            $query .= ' OFFSET ' . $offset;
        }

        return $this->conn->db_query($query);
    }

    public function getAdminsExceptOurself(int $user_id)
    {
        $query = 'SELECT username AS name,';
        $query .= 'u.mail_address AS email FROM ' . self::USERS_TABLE . ' AS u';
        $query .= ' LEFT JOIN ' . self::USER_INFOS_TABLE . ' AS i ON i.user_id =  u.id';
        $query .= ' WHERE i.status in (\'webmaster\',  \'admin\')';
        $query .= ' AND u.mail_address IS NOT NULL';
        $query .= ' AND i.user_id != ' . $user_id;
        $query .= ' ORDER BY name';

        return $this->conn->db_query($query);
    }

    public function isUserExists(string $value, string $field = 'username') : bool
    {
        $query = 'SELECT COUNT(1) AS user_exists FROM ' . self::USERS_TABLE;
        $query .= ' WHERE ' . $field . ' = \'' . $this->conn->db_real_escape_string($value) . '\'';
        $result = $this->conn->db_query($query);
        $row = $this->conn->db_fetch_assoc($result);

        return $row['user_exists'] == 1;
    }

    public function isEmailExistsExceptUser(string $mail_address, int $user_id) : bool
    {
        $query = 'SELECT COUNT(1) AS mail_address_exists FROM ' . self::USERS_TABLE;
        $query .= ' WHERE mail_address = \'' . $this->conn->db_real_escape_string($mail_address) . '\'';
        $query .= ' AND id != ' . $user_id;
        $result = $this->conn->db_query($query);
        $row = $this->conn->db_fetch_assoc($result);

        return $row['mail_address_exists'] == 1;
    }

    public function findUsersWithNoMailNotificationInfos()
    {
        $query = 'SELECT u.id AS user_id,';
        $query .= ' u.username,';
        $query .= ' u.mail_address FROM ' . self::USERS_TABLE . ' AS u';
        $query .= ' LEFT JOIN ' . self::USER_MAIL_NOTIFICATION_TABLE . ' AS um ON u.id = um.user_id';
        $query .= ' WHERE u.mail_address IS NOT NULL';
        $query .= ' AND um.user_id is null';
        $query .= ' ORDER BY user_id';

        return $this->conn->db_query($query);
    }

    public function getDistinctLanguagesForUsers(int $group_id, ? string $language = null)
    {
        $query = 'SELECT DISTINCT language FROM ' . self::USERS_TABLE . ' AS u';
        $query .= ' LEFT JOIN ' . self::USER_GROUP_TABLE . ' AS ug ON id = ug.user_id';
        $query .= ' LEFT JOIN ' . self::USER_INFOS_TABLE . ' AS ui ON ui.user_id = ug.user_id';
        $query .= ' WHERE group_id = ' . $group_id . ' AND mail_adress IS NOT NULL';

        if (!is_null($language)) {
            $query .= ' AND language = \'' . $this->conn->db_real_escape_string($language) . '\'';
        }

        return $this->conn->db_query($query);
    }

    public function getUsersByLanguage(int $group_id, string $languages)
    {
        $query = 'SELECT u.username AS name, u.mail_address AS email FROM ' . self::USERS_TABLE . ' AS u';
        $query .= ' LEFT JOIN ' . self::USER_GROUP_TABLE . ' AS ug ON id = ug.user_id';
        $query .= ' LEFT JOIN ' . self::USER_INFOS_TABLE . ' AS ui ON ui.user_id = ug.user_id';
        $query .= ' WHERE group_id = ' . $group_id . ' AND mail_address IS NOT NULL';
        $query .= ' AND language  = \'' . $this->conn->db_real_escape_string($languages) . '\'';

        return $this->conn->db_query($query);
    }

    public function updateUser(array $datas, int $user_id)
    {
        $this->conn->single_update(self::USERS_TABLE, $datas, ['id' => $user_id]);
    }

    public function addUser(array $datas, bool $auto_increment_for_table = true)
    {
        return $this->conn->single_insert(self::USERS_TABLE, $datas, $auto_increment_for_table);
    }

    public function massInserts(array $fields, array $datas)
    {
        $this->conn->mass_inserts(self::USERS_TABLE, $fields, $datas);
    }

    public function massUpdates(array $fields, array $datas)
    {
        $this->conn->mass_updates(self::USERS_TABLE, $fields, $datas);
    }

    public function deleteById(int $id)
    {
        $query = 'DELETE FROM ' . self::USERS_TABLE;
        $query .= ' WHERE id = ' . $id;
        $this->conn->db_query($query);
    }
}
