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

class UserCacheRepository extends BaseRepository
{
    public function invalidateUserCache(string $field)
    {
        $query = 'UPDATE ' . self::USER_CACHE_TABLE;
        $query .= ' SET ' . $field . ' = NULL';
        $this->conn->db_query($query);
    }
}
