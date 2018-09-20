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

class ImageTagRepository extends BaseRepository
{
    public function findBy(string $field, string $value)
    {
        $query = 'SELECT id FROM ' . self::IMAGE_TAG_TABLE;
        $query .= sprintf(' WHERE %s = \'%s\'', $key, $this->conn->db_real_escape_string($value));

        return $this->conn->db_query($query);
    }

    public function deleteBy(string $field, array $values)
    {
        $query = 'DELETE  FROM ' . self::IMAGE_TAG_TABLE;
        $query .= ' WHERE ' . $field . ' ' . $this->conn->in($values);
        $this->conn->db_query($query);
    }
}
