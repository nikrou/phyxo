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

namespace Phyxo\Session;

class SessionDbHandler implements \SessionHandlerInterface
{
    private $conn, $session_length;

    public function __construct(\Phyxo\DBLayer\DBLayer $conn)
    {
        $this->conn = $conn;
    }

    public function open($save_path, $session_name)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        $data = '';

        $query = 'SELECT data FROM ' . \App\Repository\BaseRepository::SESSIONS_TABLE;
        $query .= ' WHERE id = \'' . $this->conn->db_real_escape_string($id) . '\';';
        $result = $this->conn->db_query($query);
        if ($result) {
            $row = $this->conn->db_fetch_assoc($result);
            if (!empty($row['data'])) {
                $data = $row['data'];
            }
        }

        return $data;
    }

    public function write($id, $data)
    {
        $query = 'SELECT count(1) FROM ' . \App\Repository\BaseRepository::SESSIONS_TABLE;
        $query .= ' WHERE id = \'' . $this->conn->db_real_escape_string($id) . '\'';

        list($counter) = $this->conn->db_fetch_row($this->conn->db_query($query));
        if ($counter == 1) {
            $query = 'UPDATE ' . \App\Repository\BaseRepository::SESSIONS_TABLE . ' SET data = \'' . $this->conn->db_real_escape_string($data) . '\', expiration=now()';
            $query .= ' WHERE id = \'' . $this->conn->db_real_escape_string($id) . '\'';
            $this->conn->db_query($query);
        } else {
            $query = 'INSERT INTO ' . \App\Repository\BaseRepository::SESSIONS_TABLE . ' (id,data,expiration)';
            $query .= ' VALUES(\'' . $this->conn->db_real_escape_string($id) . '\',\'' . $this->conn->db_real_escape_string($data) . '\',now())';
            $this->conn->db_query($query);
        }

        return true;
    }

    public function destroy($id)
    {
        $this->conn->db_query('DELETE FROM ' . \App\Repository\BaseRepository::SESSIONS_TABLE . ' WHERE id = \'' . $this->conn->db_real_escape_string($id) . '\'');

        return true;
    }

    public function gc($maxlifetime)
    {
        $query = 'DELETE FROM ' . \App\Repository\BaseRepository::SESSIONS_TABLE;
        $query .= ' WHERE ' . $this->conn->db_date_to_ts('NOW()') . ' - ' . $this->conn->db_date_to_ts('expiration') . ' > ' . $maxlifetime;
        $this->conn->db_query($query);

        return true;
    }
}
