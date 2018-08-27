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

class Rate
{
    /**
     * API method
     * Deletes rates of an user
     * @param mixed[] $params
     *    @option int user_id
     *    @option string anonymous_id (optional)
     */
    function delete($params, &$service)
    {
        global $conn;

        $query = 'DELETE FROM ' . RATE_TABLE . ' WHERE user_id=' . $conn->db_real_escape_string($params['user_id']);

        if (!empty($params['anonymous_id'])) {
            $query .= ' AND anonymous_id=\'' . $conn->db_real_escape_string($params['anonymous_id']) . '\'';
        }
        if (!empty($params['image_id'])) {
            $query .= ' AND element_id=' . $conn->db_real_escape_string($params['image_id']);
        }

        $changes = $conn->db_changes($conn->db_query($query));
        if ($changes) {
            \Phyxo\Functions\Rate::update_rating_score();
        }

        return $changes;
    }
}
