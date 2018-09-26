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

class LanguageRepository extends BaseRepository
{
    public function findAll()
    {
        $query = 'SELECT id, name FROM ' . self::LANGUAGES_TABLE;
        $query .= ' ORDER BY name ASC';

        return $this->conn->db_query($query);
    }
}
