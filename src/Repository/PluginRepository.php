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

class PluginRepository extends BaseRepository
{
    public function findAll(? string $state = null)
    {
        $query = 'SELECT id, state, version FROM ' . self::PLUGINS_TABLE;
        if (!is_null($state)) {
            $query .= ' WHERE state = \'' . $this->conn->db_real_escape_string($state) . '\'';
        }

        return $this->conn->db_query($query);
    }

    public function findByStateAndExcludeIds(string $status, array $plugin_ids)
    {
        $query = 'SELECT id, state, version FROM ' . self::PLUGINS_TABLE;
        $query .= ' WHERE state = \'active\'';
        $query .= ' AND id NOT ' . $this->conn->in($plugin_ids);

        return $this->conn->db_query($query);
    }

    public function addPlugin(string $id, string $version, string $state = 'inactive')
    {
        return $this->conn->single_insert(self::PLUGINS_TABLE, ['id' => $id, 'version' => $version, 'state' => $state], false);
    }

    public function updatePlugin(array $datas, array $where)
    {
        $this->conn->single_update(self::PLUGINS_TABLE, $datas, $where);
    }

    public function deactivateIds(array $plugin_ids)
    {
        $query = 'UPDATE ' . self::PLUGINS_TABLE;
        $query .= ' SET state=\'inactive\'';
        $query .= ' WHERE id ' . $this->conn->in($plugin_ids);
        $this->conn->db_query($query);
    }

    public function deletePlugin(string $plugin_id)
    {
        $query = 'DELETE FROM ' . self::PLUGINS_TABLE;
        $query .= ' WHERE id=\'' . $this->conn->db_real_escape_string($plugin_id) . '\'';
        $this->conn->db_query($query);
    }
}
