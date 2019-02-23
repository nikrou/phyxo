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

namespace App\Utils;

use Phyxo\DBLayer\DBLayer;

class DataTransformer
{
    public function __construct(DBLayer $conn)
    {
        $this->conn = $conn;
    }

    public function map(array $data = []) : array
    {
        $mapped_data = [];

        foreach ($data as $key => $value) {
            if (is_null($value)) {
                $mapped_data[$key] = null;
            } elseif ($this->conn->is_boolean($value)) {
                $mapped_data[$key] = $this->conn->get_boolean($value);
            } else {
                $mapped_data[$key] = $value;
            }
        }

        return $mapped_data;
    }
}
