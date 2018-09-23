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

class ImageRepository extends BaseRepository
{
    public function findByField(string $field, array $ids)
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE ' . $field . $this->conn->in($ids);

        return $this->conn->db_query($query);
    }
}
