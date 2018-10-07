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

class UpgradeRepository extends BaseRepository
{
    public function findAll()
    {
        $query = 'SELECT id, applied, description FROM ' . self::UPGRADE_TABLE;

        return $this->conn->db_query($query);
    }

    public function massInserts(array $fields, array $datas)
    {
        $this->conn->mass_inserts(self::UPGRADE_TABLE, $fields, $datas);
    }
}
