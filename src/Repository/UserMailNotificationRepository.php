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

class UserMailNotificationRepository extends BaseRepository
{
    public function findInfosForUsers(bool $no_mail_empty, bool $enabled, array $check_keys = [], string $order_by)
    {
        $query = 'SELECT user_id, check_key, enabled, last_send FROM ' . self::USER_MAIL_NOTIFICATION_TABLE . ' AS n';
        $query .= ' LEFT JOIN ' . self::USERS_TABLE . ' AS u';
        $query .= ' ON u.id = n.user_id';
        $query .= ' WHERE n.enabled = \'' . $conn->boolean_to_db($enabled) . '\'';

        if ($no_mail_empty) {
            $query .= ' AND n.enabled = \'' . $conn->boolean_to_db(true) . '\' AND u.mail_address IS NOT NULL';
        }

        if (count($check_keys) > 0) {
            $query .= ' AND check_key ' . $this->conn->in($check_keys);
        }

        $query .= ' ' . $order_by;

        return $this->conn->db_query($query);
    }

    public function getDistinctUser()
    {
        $query = 'SELECT DISTINCT user_id FROM ' . self::USER_MAIL_NOTIFICATION_TABLE;

        return $this->conn->db_query($query);
    }

    public function deleteByUserId(int $user_id)
    {
        $query = 'DELETE FROM ' . self::USER_MAIL_NOTIFICATION_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;
        $this->conn->db_query($query);
    }

    public function deleteByCheckKeys(array $check_keys)
    {
        $query = 'DELETE FROM ' . self::USER_MAIL_NOTIFICATION_TABLE;
        $query .= ' WHERE check_key  ' . $this->conn->in($check_keys);
        $this->conn->db_query($query);
    }

    public function deleteByUserIds(array $user_ids)
    {
        $query = 'DELETE FROM ' . self::USER_MAIL_NOTIFICATION_TABLE;
        $query .= ' WHERE user_id ' . $this->conn->in($user_ids);
        $this->conn->db_query($query);
    }

    public function massUpdates(array $fields, array $datas)
    {
        $this->conn->mass_updates(self::USER_MAIL_NOTIFICATION_TABLE, $fields, $datas);
    }

    public function massInserts(array $fields, array $datas)
    {
        $this->conn->mass_inserts(self::USER_MAIL_NOTIFICATION_TABLE, $fields, $datas);
    }
}
