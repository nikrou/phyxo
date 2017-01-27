<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2017 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

namespace Phyxo\Session;

class SessionDbHandler implements \SessionHandlerInterface
{
    private $conn, $session_length;

    public function __construct(\Phyxo\DBLayer\DBLayer $conn) {
        $this->conn = $conn;
    }

    public function open($save_path, $session_name) {
        return true;
    }

    public function close() {
        return true;
    }

    public function read($id) {
        $data = '';

        $query = 'SELECT data FROM '.SESSIONS_TABLE;
        $query .= ' WHERE id = \''.$this->conn->db_real_escape_string($id).'\';';
        $result = $this->conn->db_query($query);
        if ($result) {
            $row = $this->conn->db_fetch_assoc($result);
            if (!empty($row['data'])) {
                $data = $row['data'];
            }
        }

        return $data;
    }

    public function write($id, $data) {
        $query = 'SELECT count(1) FROM '.SESSIONS_TABLE;
        $query .= ' WHERE id = \''.$this->conn->db_real_escape_string($id).'\'';

        list($counter) = $this->conn->db_fetch_row($this->conn->db_query($query));
        if ($counter==1) {
            $query = 'UPDATE '.SESSIONS_TABLE.' SET data = \''.$this->conn->db_real_escape_string($data).'\', expiration=now()';
            $query .= ' WHERE id = \''.$this->conn->db_real_escape_string($id).'\'';
            $this->conn->db_query($query);
        } else {
            $query = 'INSERT INTO '.SESSIONS_TABLE.' (id,data,expiration)';
            $query .= ' VALUES(\''.$this->conn->db_real_escape_string($id).'\',\''.$this->conn->db_real_escape_string($data).'\',now())';
            $this->conn->db_query($query);
        }

        return true;
    }

    public function destroy($id) {
        $this->conn->db_query('DELETE FROM '.SESSIONS_TABLE.' WHERE id = \''.$this->conn->db_real_escape_string($id).'\'');

        return true;
    }

    public function gc($maxlifetime) {
        $query = 'DELETE FROM '.SESSIONS_TABLE;
        $query .= ' WHERE '.$this->conn->db_date_to_ts('NOW()').' - '.$this->conn->db_date_to_ts('expiration').' > '.$maxlifetime;
        $this->conn->db_query($query);

        return true;
    }
}