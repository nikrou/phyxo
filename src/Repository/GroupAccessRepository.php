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

class GroupAccessRepository extends BaseRepository
{
    public function deleteByCatIds(array $ids, ? string $condition = null)
    {
        $query = 'DELETE FROM ' . self::GROUP_ACCESS_TABLE;
        $query .= ' WHERE cat_id ' . $this->conn->in($ids);

        if (!is_null($condition)) {
            $query .= ' AND ' . $condtion;
        }

        $this->conn->db_query($query);
    }

    public function findByCatId(int $cat_id, string $field)
    {
        $query = 'SELECT ' . $field . ' FROM ' . self::GROUP_ACCESS_TABLE;
        $query .= ' WHERE cat_id = ' . $this->conn->db_real_escape_string($cat_id);

        return $this->conn->db_query($query);
    }

    public function findCategoriesAuthorizedToUser(int $user_id)
    {
        $query = 'SELECT DISTINCT cat_id, c.uppercats, c.global_rank FROM ' . self::GROUP_ACCESS_TABLE . ' AS ga';
        $query .= ' LEFT JOIN ' . self::USER_GROUP_TABLE . ' AS ug ON ug.group_id = ga.group_id';
        $query .= ' LEFT JOIN ' . self::CATEGORIES_TABLE . ' AS c ON c.id = ga.cat_id';
        $query .= ' WHERE ug.user_id = ' . $user_id;

        return $this->conn->db_query($query);
    }

    public function insertGroupAccess(array $fields, array $datas)
    {
        $this->conn->mass_insert(self::GROUP_ACCESS_TABLE, $fields, $datas);
    }
}
