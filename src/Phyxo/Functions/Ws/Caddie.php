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

namespace Phyxo\Functions\Ws;

class Caddie
{
    /**
     * API method
     * Adds images to the caddie
     * @param mixed[] $params
     *    @option int[] image_id
     */
    public static function add($params, &$service)
    {
        global $user, $conn;

        $query = 'SELECT id FROM ' . IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . CADDIE_TABLE . ' ON id=element_id AND user_id=' . $user['id'];
        $query .= ' WHERE id ' . $conn->in($params['image_id']) . ' AND element_id IS NULL';
        $result = $conn->query2array($query, null, 'id');

        $datas = [];
        foreach ($result as $id) {
            $datas[] = [
                'element_id' => $id,
                'user_id' => $user['id'],
            ];
        }
        if (count($datas)) {
            $conn->mass_inserts(
                CADDIE_TABLE,
                ['element_id', 'user_id'],
                $datas
            );
        }

        return count($datas);
    }
}
