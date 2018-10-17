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

class CaddieRepository extends BaseRepository
{
    public function count(int $user_id) : int
    {
        $query = 'SELECT COUNT(1) FROM ' . self::CADDIE_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;
        list($nb_photos_in_caddie) = $this->conn->db_fetch_row($this->conn->db_query($query));

        return $nb_photos_in_caddie;
    }

    public function emptyCaddie(int $user_id)
    {
        $query = 'DELETE FROM ' . self::CADDIE_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;
        $this->conn->db_query($query);
    }

    public function fillCaddie(int $user_id, array $elements_id)
    {
        $result = $this->getElements($user_id);
        $in_caddie = $this->conn->result2array($result, null, 'element_id');

        $caddiables = array_diff($elements_id, $in_caddie);
        $datas = [];

        foreach ($caddiables as $caddiable) {
            $datas[] = [
                'element_id' => $caddiable,
                'user_id' => $user_id,
            ];
        }

        if (count($caddiables) > 0) {
            (new CaddieRepository($conn))->addElements(['element_id', 'user_id'], $datas);
        }
    }

    public function deleteElements(array $elements, ? int $user_id = null)
    {
        $query = 'DELETE FROM ' . self::CADDIE_TABLE;
        $query .= ' WHERE element_id ' . $this->conn->in($elements);

        if (!is_null($user_id)) {
            $query .= ' AND user_id = ' . $user_id;
        }
        $this->conn->db_query($query);
    }

    public function addElements(array $fields, array $datas)
    {
        $this->conn->mass_inserts(self::CADDIE_TABLE, $fields, $datas);
    }

    public function getElements(int $user_id)
    {
        $query = 'SELECT element_id FROM ' . self::CADDIE_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;

        return $this->conn->db_query($query);
    }
}
