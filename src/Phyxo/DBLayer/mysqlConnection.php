<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire              http://www.phyxo.net/ |
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

namespace Phyxo\DBLayer;

class mysqlConnection extends DBLayer implements iDBLayer
{
    Const REQUIRED_VERSION = '5.0.0';
    Const REGEX_OPERATOR = 'REGEXP';
    Const RANDOM_FUNCTION = 'RAND';

    protected $dblayer = 'mysql';
    protected $db_link = null;

    public function db_connect($host, $user, $password, $database) {
        if (($this->db_link = @\mysql_connect($host, $user, $password)) === false) {
            throw new dbException('Can\'t connect to server');
        }

        if (@mysql_select_db($database, $this->db_link) === false) {
            throw new dbException('Connection to server succeed, but it was impossible to connect to database');
        }
        $this->db_post_connect();

        return $this->db_link;
    }

    public function db_query($query) {
        if (is_resource($this->db_link)) {
            $result = mysql_query($query, $this->db_link);
            if ($result === false) {
                $e = new dbException($this->db_last_error());
                $e->query = $query;
                throw $e;
            }

            return $result;
        }
    }

    public function db_version()  {
        return mysql_get_server_info();
    }

    public function db_check_version() {
        $current_mysql =$this->db_version();
        if (version_compare($current_mysql, self::REQUIRED_VERSION, '<')) {
            throw new dbException(sprintf(
                'your MySQL version is too old, you have "%s" and you need at least "%s"',
                $current_mysql,
                self::REQUIRED_VERSION
            ));
        }
    }

    public function db_last_error() {
        if (is_resource($this->db_link)) {
            return mysql_error();
        }

        return false;
    }

    public function db_nextval($column, $table) {
        $query = 'SELECT IF(MAX('.$column.')+1 IS NULL, 1, MAX('.$column.')+1) FROM '.$table;
        list($next) = $this->db_fetch_row($this->db_query($query));

        return $next;
    }

    public function db_changes($result) {
        return mysql_affected_rows();
    }

    public function db_num_rows($result) {
        if (is_resource($result)) {
            return mysql_num_rows($result);
        }

        return 0;
    }

    public function db_fetch_assoc($result) {
        if (is_resource($result)) {
            return mysql_fetch_assoc($result);
        }
    }

    public function db_fetch_row($result) {
        if (is_resource($result)) {
            return mysql_fetch_row($result);
        }
    }

    public function db_free_result($result) {
        if (is_resource($result)) {
            return mysql_free_result($result);
        }
    }

    public function db_real_escape_string($s) {
        return mysql_real_escape_string($s);
    }

    public function db_insert_id($table=null, $column='id') {
        return mysql_insert_id();
    }

    public function db_close() {
        if (is_resource($this->db_link)) {
            return mysql_close();
        }
    }

    /* transaction functions */
    public function db_start_transaction() {
        $this->db_query('BEGIN');
    }

    public function db_commit() {
        $this->db_query('COMMIT');
    }

    public function db_rollback() {
        $this->db_query('ROLLBACK');
    }

    public function db_write_lock($table) {
        $this->db_query('LOCK TABLES '.$table.' WRITE');
    }

    public function db_unlock() {
        $this->db_query('UNLOCK TABLES');
    }

    public function db_group_concat($field) {
        return sprintf('GROUP_CONCAT(%s)', $field);
    }

    public function db_full_text_search($fields, $values) {
        return sprintf(
            'MATCH(%s) AGAINST(\'%s\' IN BOOLEAN MODE)',
            implode(', ', $fields),
            $this->db_real_escape_string(implode(' ', $values))
        );
    }

    public function db_get_tables($prefix) {
        $tables = array();

        $query = 'SHOW TABLES;';
        $result = $this->db_query($query);

        while ($row = $this->db_fetch_row($result)) {
            if (preg_match('/^'.$prefix.'/', $row[0])) {
                $tables[] = $row[0];
            }
        }

        return $tables;
    }

    public function db_get_columns_of($tables) {
        $columns_of = array();

        foreach ($tables as $table) {
            $query = 'DESC '.$table.';';
            $result = $this->db_query($query);

            $columns_of[$table] = array();

            while ($row = $this->db_fetch_row($result)) {
                $columns_of[$table][] = $row[0];
            }
        }

        return $columns_of;
    }

    public function get_enums($table, $field) {
        // retrieving the properties of the table. Each line represents a field :
        // columns are 'Field', 'Type'
        $result = $this->db_query('desc '.$table);
        while ($row = $this->db_fetch_assoc($result)) {
            // we are only interested in the the field given in parameter for the
            // function
            if ($row['Field'] == $field) {
                // retrieving possible values of the enum field
                // enum('blue','green','black')
                $options = explode(',', substr($row['Type'], 5, -1));
                foreach ($options as $i => $option) {
                    $options[$i] = str_replace("'", '',$option);
                }
            }
        }
        $this->db_free_result($result);
        return $options;
    }

    /**
     * return boolean true/false if $string (comming from database) can be converted to a real boolean
     */
    public function is_boolean($string) {
        return ($string=='true' || $string=='false');
    }

    /**
     * get_boolean transforms a string to a boolean value. If the string is
     * "false" (case insensitive), then the boolean value false is returned. In
     * any other case, true is returned.
     */
    public function get_boolean($input) {
        if ('false' === strtolower($input)) {
            return false;
        }

        return (bool)$input;
    }

    /**
     * returns boolean string 'true' or 'false' if the given var is boolean
     *
     * @param mixed $var
     * @return mixed
     */
    public function boolean_to_string($var) {
        if (is_bool($var)) {
            return $var ? 'true' : 'false';
        } else {
            return $var;
        }
    }

    public function boolean_to_db($var) {
        if ($var===true) {
            return 'true';
        } else {
            return 'false';
        }
    }

    public function db_get_recent_period_expression($period, $date='CURRENT_DATE') {
        if ($date!='CURRENT_DATE') {
            $date = '\''.$date.'\'';
        }

        return 'SUBDATE('.$date.',INTERVAL '.$period.' DAY)';
    }

    public function db_get_recent_period($period, $date='CURRENT_DATE') {
        $query = 'SELECT '.$this->db_get_recent_period_expression($period);
        list($d) = $this->db_fetch_row($this->db_query($query));

        return $d;
    }

    public function db_get_flood_period_expression($seconds) {
        return 'SUBDATE(now(), INTERVAL '.$seconds.' SECOND)';
    }

    public function db_date_to_ts($date) {
        return 'UNIX_TIMESTAMP('.$date.')';
    }

    public function db_get_date_YYYYMM($date) {
        return 'DATE_FORMAT('.$date.', \'%Y%m\')';
    }

    public function db_get_date_MMDD($date) {
        return 'DATE_FORMAT('.$date.', \'%m%d\')';
    }

    public function db_get_hour($date) {
        return 'hour('.$date.')';
    }

    public function db_get_year($date) {
        return 'YEAR('.$date.')';
    }

    public function db_get_month($date) {
        return 'MONTH('.$date.')';
    }

    public function db_get_week($date, $mode=null) {
        if ($mode){
            return 'WEEK('.$date.', '.$mode.')';
        } else {
            return 'WEEK('.$date.')';
        }
    }

    public function db_get_dayofmonth($date) {
        return 'DAYOFMONTH('.$date.')';
    }

    public function db_get_dayofweek($date) {
        return 'DAYOFWEEK('.$date.')';
    }

    public function db_get_weekday($date) {
        return 'WEEKDAY('.$date.')';
    }

    public function db_concat($array) {
        $string = implode($array, ',');

        return 'CONCAT('. $string.')';
    }

    public function db_concat_ws($array, $separator) {
        $string = implode($array, ',');

        return 'CONCAT_WS(\''.$separator.'\','. $string.')';
    }

    public function db_cast_to_text($string) {
        return $string;
    }

    /**
     * inserts multiple lines in a table
     *
     * @param string table_name
     * @param array dbfields
     * @param array inserts
     * @return void
     */
    function mass_inserts($table_name, $dbfields, $datas, $options=array()) {
        $ignore = '';
        if (isset($options['ignore']) and $options['ignore']) {
            $ignore = 'IGNORE';
        }

        if (count($datas) != 0) {
            $first = true;

            $packet_size = $this->getMaxAllowedPacket();
            $packet_size = $packet_size - 2000; // The last list of values MUST not exceed 2000 character*/
            $query = '';

            foreach ($datas as $insert) {
                if (strlen($query) >= $packet_size) {
                    $this->db_query($query);
                    $first = true;
                }

                if ($first) {
                    $query = 'INSERT '.$ignore.' INTO '.$table_name.' ('.implode(',', $dbfields).') VALUES';
                    $first = false;
                } else {
                    $query .= ', ';
                }

                $query .= '(';
                foreach ($dbfields as $field_id => $dbfield) {
                    if ($field_id > 0) {
                        $query .= ',';
                    }

                    if (!isset($insert[$dbfield]) or $insert[$dbfield] === '') {
                        $query .= 'NULL';
                    } else {
                        $query .= "'".$this->db_real_escape_string($insert[$dbfield])."'";
                    }
                }
                $query .= ')';
            }
            $this->db_query($query);
        }
    }

    public function getMaxAllowedPacket() {
        $query = 'SHOW VARIABLES LIKE \'max_allowed_packet\'';
        list(, $packet_size) = $this->db_fetch_row($this->db_query($query));
    }

    /**
     * updates multiple lines in a table
     *
     * @param string table_name
     * @param array dbfields
     * @param array datas
     * @param int flags - if MASS_UPDATES_SKIP_EMPTY - empty values do not overwrite existing ones
     * @return void
     */
    public function mass_updates($tablename, $dbfields, $datas, $flags=0) {
        if (count($datas) == 0) {
            return;
        }

        // depending on the MySQL version, we use the multi table update or N update queries
        if (count($datas) < 10) {
            foreach ($datas as $data) {
                $query = 'UPDATE '.$tablename.' SET ';
                $is_first = true;
                foreach ($dbfields['update'] as $key) {
                    $separator = $is_first ? '' : ",\n    ";

                    if (isset($data[$key]) and $data[$key] != '') {
                        $query .= $separator.$key.' = \''.$this->db_real_escape_string($data[$key]).'\'';
                    } else {
                        if ($flags & MASS_UPDATES_SKIP_EMPTY) {
                            continue; // next field
                        }
                        $query .= "$separator$key = NULL";
                    }
                    $is_first = false;
                }
                if (!$is_first) { // only if one field at least updated
                    $query.= ' WHERE ';
                    $is_first = true;
                    foreach ($dbfields['primary'] as $key) {
                        if (!$is_first) {
                            $query .= ' AND ';
                        }
                        if (isset($data[$key])) {
                            $query .= $key.' = \''.$this->db_real_escape_string($data[$key]).'\'';
                        } else {
                            $query .= $key.' IS NULL';
                        }
                        $is_first = false;
                    }
                    $this->db_query($query);
                }
            } // foreach update
        } else { // if mysql_ver or count<X
            // creation of the temporary table
            $query = 'SHOW FULL COLUMNS FROM '.$tablename;
            $result = $this->db_query($query);
            $columns = array();
            $all_fields = array_merge($dbfields['primary'], $dbfields['update']);
            while ($row = $this->db_fetch_assoc($result)) {
                if (in_array($row['Field'], $all_fields)) {
                    $column = $row['Field'];
                    $column .= ' '.$row['Type'];

                    $nullable = true;
                    if (!isset($row['Null']) or $row['Null'] == '' or $row['Null']=='NO') {
                        $column .= ' NOT NULL';
                        $nullable = false;
                    }
                    if (isset($row['Default'])) {
                        $column .= " default '".$row['Default']."'";
                    } elseif ($nullable) {
                        $column .= " default NULL";
                    }
                    if (isset($row['Collation']) and $row['Collation'] != 'NULL') {
                        $column .= " collate '".$row['Collation']."'";
                    }
                    $columns[] = $column;
                }
            }

            $temporary_tablename = $tablename.'_'.micro_seconds();

            $query = 'CREATE TABLE '.$temporary_tablename.'(';
            $query .= implode(', ', $columns).',';
            $query .= ' UNIQUE KEY the_key ('.implode(',', $dbfields['primary']).'))';

            $this->db_query($query);
            $this->mass_inserts($temporary_tablename, $all_fields, $datas);
            if ($flags & MASS_UPDATES_SKIP_EMPTY) {
                $func_set = function($s) {
                    return "t1.$s = IFNULL(t2.$s, t1.$s)";
                };
            } else {
                $func_set = function($s) {
                    return "t1.$s = t2.$s";
                };
            }

            // update of images table by joining with temporary table
            $query = 'UPDATE '.$tablename.' AS t1, '.$temporary_tablename.' AS t2';
            $query .= ' SET ';
            $query .= implode(', ', array_map($func_set, $dbfields['update']));
            $query .= ' WHERE '.implode(
                ' AND ',
                array_map(
                    function($s) {
                        return "t1.$s = t2.$s";
                    },
                    $dbfields['primary']
                )
            );
            $this->db_query($query);
            $query = 'DROP TABLE '.$temporary_tablename;
            $this->db_query($query);
        }
    }

    /**
     * Do maintenance on all Phyxo tables
     *
     * @return none
     */
    public function do_maintenance_all_tables() {
        global $prefixeTable;

        $all_tables = array();

        // List all tables
        $query = 'SHOW TABLES LIKE \''.$prefixeTable.'%\'';
        $result = $this->db_query($query);
        while ($row = $this->db_fetch_row($result)) {
            $all_tables[] = $row[0];
        }

        // Repair all tables
        $query = 'REPAIR TABLE '.implode(', ', $all_tables);
        $mysql_rc = $this->db_query($query);

        // Re-Order all tables
        foreach ($all_tables as $table_name) {
            $all_primary_key = array();

            $query = 'DESC '.$table_name.';';
            $result = $this->db_query($query);
            while ($row = $this->db_fetch_assoc($result)) {
                if ($row['Key'] == 'PRI') {
                    $all_primary_key[] = $row['Field'];
                }
            }

            if (count($all_primary_key) != 0) {
                $query = 'ALTER TABLE '.$table_name.' ORDER BY '.implode(', ', $all_primary_key).';';
                $mysql_rc = $mysql_rc && $this->db_query($query);
            }
        }

        // Optimize all tables
        $query = 'OPTIMIZE TABLE '.implode(', ', $all_tables);
        return ($mysql_rc && $this->db_query($query));
    }

    /**
     */
    private function db_post_connect() {
        $this->db_query('SET NAMES utf8');
    }
}
