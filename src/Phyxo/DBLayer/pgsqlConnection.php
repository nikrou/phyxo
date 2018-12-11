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

class pgsqlConnection extends DBLayer implements iDBLayer
{
    const REQUIRED_VERSION = '8.0';
    const REGEX_OPERATOR = '~';
    const RANDOM_FUNCTION = 'RANDOM';

    protected $dblayer = 'pgsql';
    protected $db_link = null;

    public function db_connect(string $host, string $user, string $password, string $database)
    {
        $connection_string = '';
        if (strpos($host, ':') !== false) {
            list($host, $port) = explode(':', $host);
        }
        $connection_string = sprintf('host=%s', $host);
        if (!empty($port)) {
            $connection_string .= sprintf(' port=%d', $port);
        }
        $connection_string .= sprintf(' user=%s password=%s dbname=%s', $user, $password, $database);

        if (($this->db_link = @pg_connect($connection_string)) === false) {
            throw new dbException('Unable to connect to database');
        }

        return $this->db_link;
    }

    public function db_query(string $query)
    {
        if (is_resource($this->db_link)) {
            $start = microtime(true);
            $result = pg_query($this->db_link, $query);
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
        list($pg_version) = $this->db_fetch_row($this->db_query('SHOW SERVER_VERSION;'));

        return $pg_version;
    }

    public function db_check_version() : void
    {
        $current_pgsql = $this->db_version();
        if (version_compare($current_pgsql, self::REQUIRED_VERSION, '<')) {
            throw new dbException(sprintf(
                'your PostgreSQL version is too old, you have "%s" and you need at least "%s"',
                $current_pgsql,
                self::REQUIRED_VERSION
            ));
        }
    }

    public function db_last_error() : string
    {
        if (is_resource($this->db_link)) {
            return pg_last_error($this->db_link);
        }

        return false;
    }

    public function db_nextval(string $column, string $table) : int
    {
        $query = 'SELECT nextval(\'' . $table . '_' . $column . '_seq\')';
        list($next) = $this->db_fetch_row($this->db_query($query));

        return $next;
    }

    public function db_changes($result) : int
    {
        return pg_affected_rows($result);
    }

    public function db_num_rows($result) : int
    {
        if (is_resource($result)) {
            return pg_num_rows($result);
        }

        return 0;
    }

    public function db_fetch_assoc($result)
    {
        if (is_resource($result)) {
            return pg_fetch_assoc($result);
        }
    }

    public function db_fetch_row($result)
    {
        if (is_resource($result)) {
            return pg_fetch_row($result);
        }
    }

    public function db_free_result($result) : void
    {
        if (is_resource($result)) {
            pg_free_result($result);
        }
    }

    public function db_real_escape_string($s)
    {
        return pg_escape_string($s);
    }

    public function db_insert_id(string $table = null, string $column = 'id') : int
    {
        $sequence = sprintf('%s_%s_seq', strtolower($table), $column);
        $query = 'SELECT CURRVAL(\'' . $sequence . '\')';
        $result = $this->db_query($query);
        list($id) = $this->db_fetch_row($result);

        return $id;
    }

    public function db_close()
    {
        if (is_resource($this->db_link)) {
            pg_close($this->db_link);
        }
    }

    // transaction functions
    public function db_start_transaction()
    {
        $this->db_query('BEGIN');
    }

    public function db_commit()
    {
        $this->db_query('COMMIT');
    }

    public function db_rollback()
    {
        $this->db_query('ROLLBACK');
    }

    public function db_write_lock(string $table)
    {
        $this->db_query('BEGIN');
        $this->db_query('LOCK TABLE ' . $table . ' IN EXCLUSIVE MODE');
    }

    public function db_unlock()
    {
        $this->db_query('END');
    }

    public function db_group_concat($field)
    {
        return sprintf('ARRAY_TO_STRING(ARRAY_AGG(%s),\',\')', $field);
    }

    public function db_full_text_search($fields, $values) : string
    {
        return sprintf(
            'to_tsvector(%s) @@ to_tsquery(\'%s\')',
            implode(' || \' \' || ', $fields),
            $this->db_real_escape_string(implode(' | ', $values))
        );
    }

    public function db_get_tables(string $prefix) : array
    {
        $tables = [];

        $query = 'SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema()';
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

        $fmt_query = 'SELECT column_name, udt_name, character_maximum_length,';
        $fmt_query .= ' is_nullable, column_default';
        $fmt_query .= ' FROM information_schema.columns';
        $fmt_query .= ' WHERE table_name = \'%s\'';

        foreach ($tables as $table) {
            $query = sprintf($fmt_query, $this->db_real_escape_string($table));
            $result = $this->db_query($query);
            $columns_of[$table] = [];

            while ($row = $this->db_fetch_row($result)) {
                $columns_of[$table][] = $row[0];
            }
        }

        return $columns_of;
    }

    public function get_enums(string $table, string $field) : array
    {
        $typname = preg_replace('/' . $GLOBALS['prefixeTable'] . '/', '', $table);
        $typname .= '_' . $field;

        $query = 'SELECT enumlabel FROM pg_enum JOIN pg_type ON pg_enum.enumtypid=pg_type.oid';
        $query .= ' WHERE typname=\'' . $typname . '\'';
        $result = $this->db_query($query);
        while ($row = $this->db_fetch_assoc($result)) {
            $options[] = $row['enumlabel'];
        }

        return $options;
    }

    /**
     * return boolean true/false if $string (comming from database) can be converted to a real boolean
     */
    public function is_boolean(string $string) : bool
    {
        return ($string == 'f' || $string == 't' || $string == 'false' || $string == 'true');
    }

    /**
     * get_boolean transforms a string to a boolean value. If the string is
     * "false" (case insensitive), then the boolean value false is returned. In
     * any other case, true is returned.
     */
    public function get_boolean(string $string) : bool
    {
        $boolean = true;
        if ('f' == $string || 'false' == $string) {
            $boolean = false;
        }

        return $boolean;
    }

    public function boolean_to_db(bool $var)
    {
        if ($var === true) {
            return 't';
        } else {
            return 'f';
        }
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
            if (!empty($var) && (($var == 't') || ($var == 'true'))) {
                return 'true';
            } else {
                return 'false';
            }
        } else {
            return $var;
        }
    }

    public function db_get_recent_period_expression($period, $date = 'CURRENT_DATE')
    {
        if ($date != 'CURRENT_DATE') {
            $date = '\'' . $date . '\'::date';
        }

        return '(' . $date . ' - \'' . $period . ' DAY\'::interval)::timestamp';
    }

    public function db_get_recent_period($period, $date = 'CURRENT_DATE')
    {
        $query = 'select ' . $this->db_get_recent_period_expression($period, $date);
        list($d) = $this->db_fetch_row($this->db_query($query));

        return $d;
    }

    function db_get_flood_period_expression($seconds)
    {
        return '(now() - \'' . $seconds . ' SECOND\'::interval)::timestamp';
    }

    public function db_date_to_ts($date)
    {
        return 'EXTRACT(EPOCH FROM ' . $date . ')';
    }

    public function db_get_date_YYYYMM($date)
    {
        return 'TO_CHAR(' . $date . ', \'YYYYMM\')';
    }

    public function db_get_date_MMDD($date)
    {
        return 'TO_CHAR(' . $date . ', \'MMDD\')';
    }

    public function db_get_hour($date)
    {
        return 'EXTRACT(HOUR FROM ' . $date . ')';
    }

    public function db_get_year($date)
    {
        return 'EXTRACT(YEAR FROM ' . $date . ')';
    }

    public function db_get_month($date)
    {
        return 'EXTRACT(MONTH FROM ' . $date . ')';
    }

    public function db_get_week($date, $mode = null)
    {
        return 'EXTRACT(WEEK FROM ' . $date . ')';
    }

    public function db_get_dayofmonth($date)
    {
        return 'EXTRACT(DAY FROM ' . $date . ')';
    }

    public function db_get_dayofweek($date)
    {
        return 'EXTRACT(DOW FROM ' . $date . ')::INTEGER - 1';
    }

    public function db_get_weekday($date)
    {
        return 'EXTRACT(ISODOW FROM ' . $date . ')::INTEGER - 1';
    }

    public function db_concat(array $array) : string
    {
        $string = implode($array, ',');

        return 'ARRAY_TO_STRING(ARRAY[' . $string . '])';
    }

    public function db_concat_ws(array $array, string $separator) : string
    {
        $string = implode($array, ',');

        return 'ARRAY_TO_STRING(ARRAY[' . $string . '],\'' . $separator . '\')';
    }

    public function db_cast_to_text(string $string) : string
    {
        return 'CAST(' . $string . ' AS TEXT)';
    }

    /**
     * inserts multiple lines in a table
     *
     * @param string table_name
     * @param array dbfields
     * @param array inserts
     * @return void
     */
    public function mass_inserts(string $tablename, array $dbfields, array $datas, array $options = [])
    {
        if (count($datas) != 0) {
            foreach ($datas as $insert) {
                $query = 'INSERT INTO ' . $tablename . ' (' . implode(',', $dbfields) . ')';
                $query .= ' SELECT ';
                foreach ($dbfields as $field_id => $dbfield) {
                    if ($field_id > 0) {
                        $query .= ',';
                    }

                    if (isset($insert[$dbfield]) && is_bool($insert[$dbfield])) {
                        $query .= '\'' . $this->boolean_to_db($insert[$dbfield]) . '\'';
                    } elseif (!isset($insert[$dbfield]) or $insert[$dbfield] === '') {
                        $query .= 'NULL';
                    } else {
                        $query .= '\'' . $this->db_real_escape_string($insert[$dbfield]) . '\'';
                    }
                }
                $query .= ' WHERE NOT EXISTS(';
                $query .= ' SELECT 1 FROM ' . $tablename;
                $query .= ' WHERE ';
                $parts = [];
                foreach ($dbfields as $dbfield) {
                    if (isset($insert[$dbfield]) && is_bool($insert[$dbfield])) {
                        $parts[] = $dbfield . ' = \'' . $this->boolean_to_db($insert[$dbfield]) . '\'';
                    } elseif (!isset($insert[$dbfield]) or $insert[$dbfield] === '') {
                        $parts[] = $dbfield . ' = NULL';
                    } else {
                        $parts[] = $dbfield . ' = \'' . $this->db_real_escape_string($insert[$dbfield]) . '\'';
                    }
                }
                $query .= implode(' AND ', $parts);
                $query .= ')';
                $this->db_query($query);
            }
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
        if (count($datas) === 0) {
            return;
        }

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
                        $query .= "$separator$key = NULL";
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
            }
        } else {
            $all_fields = array_merge($dbfields['primary'], $dbfields['update']);
            $temporary_tablename = $this->getTemporaryTable($tablename);
            $query = 'CREATE TABLE ' . $temporary_tablename . ' AS SELECT * FROM ' . $tablename . ' WHERE 1=2';

            $this->db_query($query);
            $this->mass_inserts($temporary_tablename, $all_fields, $datas);
            if ($flags & MASS_UPDATES_SKIP_EMPTY) {
                $func_set = function ($s) use ($tablename, $temporary_tablename) {
                    return sprintf(
                        '%1$s = IFNULL(%3$s.%1$s, %2$s.%1$s)',
                        $s,
                        $tablename,
                        $temporary_tablename
                    );
                };
            } else {
                $func_set = function ($s) use ($temporary_tablename) {
                    return sprintf(
                        '%1$s = %2$s.%1$s',
                        $s,
                        $temporary_tablename
                    );
                };
            }

            // update of images table by joining with temporary table
            $query = 'UPDATE ' . $tablename . ' SET ';
            $query .= implode(', ', array_map($func_set, $dbfields['update']));
            $query .= ' FROM ' . $temporary_tablename;
            $query .= ' WHERE ';
            $query .= implode(' AND ', array_map(
                function ($s) use ($tablename, $temporary_tablename) {
                    return sprintf(
                        '%2$s.%1$s = %3$s.%1$s',
                        $s,
                        $tablename,
                        $temporary_tablename
                    );
                },
                $dbfields['primary']
            ));
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
        $query = 'SELECT tablename FROM pg_tables';
        $query .= ' WHERE tablename like \'' . $prefixeTable . '%\'';

        $all_tables = $this->query2array($query, null, 'tablename');
        // Optimize all tables
        foreach ($all_tables as $table) {
            $query = 'VACUUM ' . $table;
            $this->db_query($query);
        }

        return true;
    }
}
