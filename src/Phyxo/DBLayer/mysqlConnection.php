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

namespace Phyxo\DBLayer;

class mysqlConnection extends DBLayer implements iDBLayer
{
    const REQUIRED_VERSION = '5.0.0';
    const REGEX_OPERATOR = 'REGEXP';
    const RANDOM_FUNCTION = 'RAND';

    protected $dblayer = 'mysql';
    protected $db_link = null;

    public function db_connect(string $host, string $user, string $password, string $database)
    {
        $port = null;
        $socket = null;

        if (strpos($host, '/') === 0) {
            $socket = $host;
            $host = null;
        } elseif (strpos($host, ':') !== false) {
            list($host, $port) = explode(':', $host);
        }

        $dbname = null;

        $this->db_link = @new \mysqli($host, $user, $password, $dbname, $port, $socket);
        if ($this->db_link->connect_errno) {
            throw new dbException("Can't connect to server");
        }

        if (!$this->db_link->select_db($database)) {
            throw new dbException('Connection to server succeed, but it was impossible to connect to database');
        }
        $this->db_post_connect();

        return $this->db_link;
    }

    public function db_query(string $query)
    {
        if (!empty($this->db_link)) {
            $start = microtime(true);
            $result = $this->db_link->query($query);
            $time = microtime(true) - $start;

            $this->db_show_query($query, $result, $time);

            if ($result === false) {
                $e = new dbException($this->db_last_error());
                $e->query = $query;
                throw $e;
            }

            return $result;
        }
    }

    public function db_version() : string
    {
        if (!empty($this->db_link)) {
            return $this->db_link->server_info;
        }
    }

    public function db_check_version() : void
    {
        $current_mysql = $this->db_version();
        if (version_compare($current_mysql, self::REQUIRED_VERSION, '<')) {
            throw new dbException(sprintf(
                'your MySQL version is too old, you have "%s" and you need at least "%s"',
                $current_mysql,
                self::REQUIRED_VERSION
            ));
        }
    }

    public function db_last_error() : string
    {
        if (!empty($this->db_link)) {
            return $this->db_link->error;
        }

        return false;
    }

    public function db_nextval(string $column, string $table) : int
    {
        $query = 'SELECT IF(MAX(' . $column . ')+1 IS NULL, 1, MAX(' . $column . ')+1) FROM ' . $table;
        list($next) = $this->db_fetch_row($this->db_query($query));

        return $next;
    }

    public function db_changes($result) : int
    {
        if (!empty($this->db_link)) {
            return $this->db_link->affected_rows;
        }
    }

    public function db_num_rows($result) : int
    {
        if (!empty($result)) {
            return $result->num_rows;
        }

        return 0;
    }

    public function db_fetch_assoc($result)
    {
        if (!empty($result)) {
            return $result->fetch_assoc();
        }
    }

    public function db_fetch_row($result)
    {
        if (!empty($result)) {
            return $result->fetch_row();
        }
    }

    public function db_free_result($result) : void
    {
        if (!empty($result)) {
            $result->free_result();
        }
    }

    public function db_real_escape_string($s)
    {
        if (!empty($this->db_link)) {
            return $this->db_link->real_escape_string($s);
        }
    }

    public function db_insert_id(string $table = null, string $column = 'id') : int
    {
        if (!empty($this->db_link)) {
            return $this->db_link->insert_id;
        }
    }

    public function db_close()
    {
        if (!empty($this->db_link)) {
            $this->db_link->close();
        }
    }

    // transaction functions
    public function db_start_transaction()
    {
        if (!empty($this->db_link)) {
            $this->db_query('BEGIN');
        }
    }

    public function db_commit()
    {
        if (!empty($this->db_link)) {
            $this->db_link->commit();
        }
    }

    public function db_rollback()
    {
        if (!empty($this->db_link)) {
            $this->db_link->rollback();
        }
    }

    public function db_write_lock(string $table)
    {
        if (!empty($this->db_link)) {
            $this->db_query('LOCK TABLES ' . $table . ' WRITE');
        }
    }

    public function db_unlock()
    {
        if (!empty($this->db_link)) {
            $this->db_query('UNLOCK TABLES');
        }
    }

    public function db_group_concat($field)
    {
        return sprintf('GROUP_CONCAT(%s)', $field);
    }

    public function db_full_text_search($fields, $values) : string
    {
        return sprintf(
            'MATCH(%s) AGAINST(\'%s\' IN BOOLEAN MODE)',
            implode(', ', $fields),
            $this->db_real_escape_string(implode(' ', $values))
        );
    }

    public function db_get_tables(string $prefix) : array
    {
        $tables = [];

        $query = 'SHOW TABLES;';
        $result = $this->db_query($query);

        while ($row = $this->db_fetch_row($result)) {
            if (preg_match('/^' . $prefix . '/', $row[0])) {
                $tables[] = $row[0];
            }
        }

        return $tables;
    }

    public function db_get_columns_of(array $tables) : array
    {
        $columns_of = [];

        foreach ($tables as $table) {
            $query = 'DESC ' . $table . ';';
            $result = $this->db_query($query);

            $columns_of[$table] = [];

            while ($row = $this->db_fetch_row($result)) {
                $columns_of[$table][] = $row[0];
            }
        }

        return $columns_of;
    }

    /**
     * returns an array containing the possible values of an enum field
     * TODO : find a better way to retrieved that stuff
     *
     * @param string tablename
     * @param string fieldname
     */
    public function get_enums(string $table, string $field) : array
    {
        // retrieving the properties of the table. Each line represents a field :
        // columns are 'Field', 'Type'
        $result = $this->db_query('desc ' . $table);
        while ($row = $this->db_fetch_assoc($result)) {
            // we are only interested in the the field given in parameter for the
            // function
            if ($row['Field'] == $field) {
                // retrieving possible values of the enum field
                // enum('blue','green','black')
                $options = explode(',', substr($row['Type'], 5, -1));
                foreach ($options as $i => $option) {
                    $options[$i] = str_replace("'", '', $option);
                }
            }
        }
        $this->db_free_result($result);
        return $options;
    }

    /**
     * return boolean true/false if $string (comming from database) can be converted to a real boolean
     */
    public function is_boolean(string $string) : bool
    {
        return ($string == 'true' || $string == 'false');
    }

    /**
     * get_boolean transforms a string to a boolean value. If the string is
     * "false" (case insensitive), then the boolean value false is returned. In
     * any other case, true is returned.
     */
    public function get_boolean(string $input) : bool
    {
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
    public function boolean_to_string($var)
    {
        if (is_bool($var)) {
            return $var ? 'true' : 'false';
        } else {
            return $var;
        }
    }

    public function boolean_to_db(bool $var)
    {
        if ($var === true) {
            return 'true';
        } else {
            return 'false';
        }
    }

    public function db_get_recent_period_expression($period, $date = 'CURRENT_DATE')
    {
        if ($date != 'CURRENT_DATE') {
            $date = '\'' . $date . '\'';
        }

        return 'SUBDATE(' . $date . ',INTERVAL ' . $period . ' DAY)';
    }

    public function db_get_recent_period($period, $date = 'CURRENT_DATE')
    {
        $query = 'SELECT ' . $this->db_get_recent_period_expression($period);
        list($d) = $this->db_fetch_row($this->db_query($query));

        return $d;
    }

    public function db_get_flood_period_expression($seconds)
    {
        return 'SUBDATE(NOW(), INTERVAL ' . $seconds . ' SECOND)';
    }

    public function db_date_to_ts($date)
    {
        return 'UNIX_TIMESTAMP(' . $date . ')';
    }

    public function db_get_date_YYYYMM($date)
    {
        return 'DATE_FORMAT(' . $date . ', \'%Y%m\')';
    }

    public function db_get_date_MMDD($date)
    {
        return 'DATE_FORMAT(' . $date . ', \'%m%d\')';
    }

    public function db_get_hour($date)
    {
        return 'HOUR(' . $date . ')';
    }

    public function db_get_year($date)
    {
        return 'YEAR(' . $date . ')';
    }

    public function db_get_month($date)
    {
        return 'MONTH(' . $date . ')';
    }

    public function db_get_week($date, $mode = null)
    {
        if ($mode) {
            return 'WEEK(' . $date . ', ' . $mode . ')';
        } else {
            return 'WEEK(' . $date . ')';
        }
    }

    public function db_get_dayofmonth($date)
    {
        return 'DAYOFMONTH(' . $date . ')';
    }

    public function db_get_dayofweek($date)
    {
        return 'DAYOFWEEK(' . $date . ')';
    }

    public function db_get_weekday($date)
    {
        return 'WEEKDAY(' . $date . ')';
    }

    public function db_concat(array $array) : string
    {
        $string = implode($array, ',');

        return 'CONCAT(' . $string . ')';
    }

    public function db_concat_ws(array $array, string $separator) : string
    {
        $string = implode($array, ',');

        return 'CONCAT_WS(\'' . $separator . '\',' . $string . ')';
    }

    public function db_cast_to_text(string $string) : string
    {
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
    function mass_inserts(string $table_name, array $dbfields, array $datas, array $options = [])
    {
        $ignore = '';
        if (isset($options['ignore']) and $options['ignore']) {
            $ignore = 'IGNORE';
        }

        if (count($datas) != 0) {
            $first = true;

            $query = 'SHOW VARIABLES LIKE \'max_allowed_packet\'';
            list(, $packet_size) = $this->db_fetch_row($this->db_query($query));
            $packet_size = $packet_size - 2000; // The last list of values MUST not exceed 2000 character*/
            $query = '';

            foreach ($datas as $insert) {
                if (strlen($query) >= $packet_size) {
                    $this->db_query($query);
                    $first = true;
                }

                if ($first) {
                    $query = 'INSERT ' . $ignore . ' INTO ' . $table_name . ' (' . implode(',', $dbfields) . ') VALUES';
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
                        $query .= "'" . $this->db_real_escape_string($insert[$dbfield]) . "'";
                    }
                }
                $query .= ')';
            }
            $this->db_query($query);
        }
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
    public function mass_updates(string $tablename, array $dbfields, array $datas, int $flags = 0)
    {
        if (count($datas) == 0) {
            return;
        }

        // depending on the MySQL version, we use the multi table update or N update queries
        if (count($datas) < 10) {
            foreach ($datas as $data) {
                $query = 'UPDATE ' . $tablename . ' SET ';
                $is_first = true;

                foreach ($dbfields['update'] as $key) {
                    $separator = $is_first ? '' : ', ';

                    if (isset($data[$key]) && is_bool($data[$key])) {
                        $query .= $separator . $key . ' = \'' . $this->boolean_to_db($data[$key]) . '\'';
                    } elseif (isset($data[$key])) {
                        $query .= $separator . $key . ' = \'' . $this->db_real_escape_string($data[$key]) . '\'';
                    } else {
                        if ($flags & MASS_UPDATES_SKIP_EMPTY) {
                            continue; // next field
                        }
                        $query .= " $separator $key = null ";
                    }
                    $is_first = false;
                }
                if (!$is_first) { // only if one field at least updated
                    $query .= ' WHERE ';
                    $is_first = true;
                    foreach ($dbfields['primary'] as $key) {
                        if (!$is_first) {
                            $query .= ' AND ';
                        }
                        if (isset($data[$key]) && is_bool($data[$key])) {
                            $query .= $key . ' = \'' . $this->boolean_to_db($data[$key]) . '\'';
                        } elseif (!isset($data[$key]) || $data[$key] === '') {
                            $query .= $key . ' IS NULL';
                        } else {
                            $query .= $key . ' = \'' . $this->db_real_escape_string($data[$key]) . '\'';
                        }
                        $is_first = false;
                    }
                    $this->db_query($query);
                }
            } // foreach update
        } else { // if mysql_ver or count<X
            // creation of the temporary table
            $query = 'SHOW FULL COLUMNS FROM ' . $tablename;
            $result = $this->db_query($query);
            $columns = [];
            $all_fields = array_merge($dbfields['primary'], $dbfields['update']);
            while ($row = $this->db_fetch_assoc($result)) {
                if (in_array($row['Field'], $all_fields)) {
                    $column = $row['Field'];
                    $column .= ' ' . $row['Type'];

                    $nullable = true;
                    if (!isset($row['Null']) or $row['Null'] == '' or $row['Null'] == 'NO') {
                        $column .= ' NOT NULL';
                        $nullable = false;
                    }
                    if (isset($row['Default'])) {
                        $column .= " default '" . $row['Default'] . "' ";
                    } elseif ($nullable) {
                        $column .= " default null ";
                    }
                    if (isset($row['Collation']) and $row['Collation'] != 'NULL') {
                        $column .= " collate '" . $row['Collation'] . "' ";
                    }
                    $columns[] = $column;
                }
            }

            $temporary_tablename = $this->getTemporaryTable($tablename);

            $query = 'CREATE TABLE ' . $temporary_tablename . '(';
            $query .= implode(', ', $columns) . ',';
            $query .= ' UNIQUE KEY the_key (' . implode(',', $dbfields['primary']) . '))';

            $this->db_query($query);
            $this->mass_inserts($temporary_tablename, $all_fields, $datas);
            if ($flags & MASS_UPDATES_SKIP_EMPTY) {
                $func_set = function ($s) {
                    return " t1 . $s = IFNULL(t2 . $s, t1 . $s) ";
                };
            } else {
                $func_set = function ($s) {
                    return " t1 . $s = t2 . $s ";
                };
            }

            // update of images table by joining with temporary table
            $query = 'UPDATE ' . $tablename . ' AS t1, ' . $temporary_tablename . ' AS t2';
            $query .= ' SET ';
            $query .= implode(', ', array_map($func_set, $dbfields['update']));
            $query .= ' WHERE ' . implode(
                ' AND ',
                array_map(
                    function ($s) {
                        return " t1 . $s = t2 . $s ";
                    },
                    $dbfields['primary']
                )
            );
            $this->db_query($query);
            $query = 'DROP TABLE ' . $temporary_tablename;
            $this->db_query($query);
        }
    }

    /**
     * Do maintenance on all Phyxo tables
     */
    public function do_maintenance_all_tables() : bool
    {
        global $prefixeTable;

        $all_tables = [];

        // List all tables
        $query = 'SHOW TABLES LIKE \'' . $prefixeTable . '%\'';
        $result = $this->db_query($query);
        while ($row = $this->db_fetch_row($result)) {
            $all_tables[] = $row[0];
        }

        // Repair all tables
        $query = 'REPAIR TABLE ' . implode(', ', $all_tables);
        $mysql_rc = $this->db_query($query);

        // Re-Order all tables
        foreach ($all_tables as $table_name) {
            $all_primary_key = [];

            $query = 'DESC ' . $table_name . ';';
            $result = $this->db_query($query);
            while ($row = $this->db_fetch_assoc($result)) {
                if ($row['Key'] == 'PRI') {
                    $all_primary_key[] = $row['Field'];
                }
            }

            if (count($all_primary_key) != 0) {
                $query = 'ALTER TABLE ' . $table_name . ' ORDER BY ' . implode(', ', $all_primary_key) . ';';
                $mysql_rc = $mysql_rc && $this->db_query($query);
            }
        }

        // Optimize all tables
        $query = 'OPTIMIZE TABLE ' . implode(', ', $all_tables);
        return ($mysql_rc && $this->db_query($query));
    }

    /**
     */
    private function db_post_connect()
    {
        $this->db_query('SET NAMES utf8');
    }
}
