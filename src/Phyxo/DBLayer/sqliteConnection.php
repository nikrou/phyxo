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

class sqliteConnection extends DBLayer implements iDBLayer
{
    const REQUIRED_VERSION = '3.0.0';
    const REGEX_OPERATOR = 'REGEXP';
    const RANDOM_FUNCTION = 'RANDOM';

    protected $dblayer = 'sqlite';
    protected $db_link = null;

    public function db_connect(string $host, string $user, string $password, string $database)
    {
        $this->dsn = sprintf('sqlite:%s/db/%s.db', __DIR__ . '/../../..', $database);

        try {
            $this->db_link = new \PDO($this->dsn, null, null, [\PDO::ATTR_PERSISTENT => true]);
            $this->db_link->exec('PRAGMA journal_mode = wal;');
        } catch (\Exception $e) {
            throw new dbException('Failed to open database ' . $this->dsn . ':' . $e->getMessage());
        }

        $this->db_link->sqliteCreateFunction('now', [$this, '_now'], 0);
        $this->db_link->sqliteCreateFunction('unix_timestamp', [$this, '_unix_timestamp'], 0);
        $this->db_link->sqliteCreateFunction('md5', 'md5', 1);
        $this->db_link->sqliteCreateFunction('if', [$this, '_if'], 3);

        $this->db_link->sqliteCreateAggregate('std', [$this, '_std_step'], [$this, '_std_finalize']);
        $this->db_link->sqliteCreateFunction('regexp', [$this, '_regexp'], 2);

        return $this->db_link;
    }

    public function db_query(string $query)
    {
        $truncate_pattern = '`truncate(.*)`i';

        if ($this->db_link) {
            if (preg_match($truncate_pattern, $query, $matches)) {
                $start = microtime(true);
                $query = str_replace('TRUNCATE TABLE', 'DELETE FROM', $query);
                $result = $this->db_link->exec($query);
                $time = microtime(true) - $start;

                $this->db_show_query($query, $result, $time);
            } else {
                $start = microtime(true);
                $result = $this->db_link->query($query);
                $time = microtime(true) - $start;

                $this->db_show_query($query, $result, $time);
            }
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
        return $this->db_link->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }

    public function db_check_version() : void
    {
        $current_sqlite = $this->db_version();
        if (version_compare($current_sqlite, self::REQUIRED_VERSION, '<')) {
            throw new dbException(sprintf(
                'your SQLite version is too old, you have "%s" and you need at least "%s"',
                $current_sqlite,
                self::REQUIRED_VERSION
            ));
        }
    }

    public function db_last_error() : string
    {
        if ($this->db_link) {
            $err = $this->db_link->errorInfo();
            return $err[2] . ' (' . $err[1] . ')';
        }

        return false;
    }

    public function db_nextval(string $column, string $table) : int
    {
        $query = 'SELECT MAX(' . $column . ')+1 FROM ' . $table;
        list($next) = $this->db_fetch_row($this->db_query($query));
        if (is_null($next)) {
            $next = 1;
        }

        return $next;
    }

    public function db_changes($result) : int
    {
        return $result->rowCount();
    }

    public function db_num_rows($result) : int
    {
        if (!empty($result)) {
            return $result->columnCount();
        }

        return 0;
    }

    public function db_fetch_assoc($result)
    {
        if (!empty($result)) {
            return $result->fetch(\PDO::FETCH_ASSOC);
        }
    }

    public function db_fetch_row($result)
    {
        if (!empty($result)) {
            return $result->fetch(\PDO::FETCH_NUM);
        }
    }

    public function db_free_result($result) : void
    {
    }

    public function db_real_escape_string($s)
    {
        return trim($this->db_link->quote($s), '\'');
    }

    public function db_insert_id(string $table = null, string $column = 'id') : int
    {
        return $this->db_link->lastInsertId();
    }

    public function db_close()
    {
        if ($this->db_link) {
            $this->db_link = null;
        }
    }

    // transaction functions
    public function db_start_transaction()
    {
        $this->db_link->beginTransaction();
    }

    public function db_commit()
    {
        $this->db_link->commit();
    }

    public function db_rollback()
    {
        $this->db_link->rollback();
    }

    public function db_write_lock(string $table)
    {
        // $this->db_query('BEGIN EXCLUSIVE TRANSACTION');
    }

    public function db_unlock()
    {
        // $this->db_query('END');
    }

    public function db_group_concat($field)
    {
        return sprintf('GROUP_CONCAT(%s)', $field);
    }

    public function db_full_text_search(array $fields, array $values) : string
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

        $query = "SELECT * FROM sqlite_master WHERE type = 'table'";
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

        $fmt_query = 'PRAGMA table_info(%s)';

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

    /**
     * returns an array containing the possible values of an enum field
     * TODO : find a better way to retrieved that stuff
     *
     * @param string tablename
     * @param string fieldname
     */
    public function get_enums(string $table, string $field) : array
    {
        $list = [];

        $Enums = [];
        $Enums[$this->prefix . 'categories']['status'] = ['public', 'private'];
        $Enums[$this->prefix . 'categories']['visible'] = ['true', 'false'];
        $Enums[$this->prefix . 'categories']['commentable'] = ['true', 'false'];
        $Enums[$this->prefix . 'comments']['validated'] = ['true', 'false'];
        $Enums[$this->prefix . 'groups']['is_default'] = ['true', 'false'];
        $Enums[$this->prefix . 'history']['section'] = ['categories', 'tags', 'search', 'list', 'favorites', 'most_visited', 'best_rated', 'recent_pics', 'recent_cats'];
        $Enums[$this->prefix . 'history']['summarized'] = ['true', 'false'];
        $Enums[$this->prefix . 'history']['image_type'] = ['picture', 'high', 'other'];
        $Enums[$this->prefix . 'plugins']['state'] = ['inactive', 'active'];
        $Enums[$this->prefix . 'user_cache']['need_update'] = ['true', 'false'];
        $Enums[$this->prefix . 'user_cache']['image_access_type'] = ['NOT IN', 'IN'];
        $Enums[$this->prefix . 'user_infos']['status'] = ['webmaster', 'admin', 'normal', 'generic', 'guest'];
        $Enums[$this->prefix . 'user_infos']['expand'] = ['true', 'false'];
        $Enums[$this->prefix . 'user_infos']['show_nb_comments'] = ['true', 'false'];
        $Enums[$this->prefix . 'user_infos']['show_nb_hits'] = ['true', 'false'];
        $Enums[$this->prefix . 'user_infos']['enabled_high'] = ['true', 'false'];
        $Enums[$this->prefix . 'user_mail_notification']['enabled'] = ['true', 'false'];

        if (!empty($Enums[$table][$field])) {
            $list = $Enums[$table][$field];
        }

        return $list;
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
    public function get_boolean(string $string) : bool
    {
        $boolean = true;
        if ('f' === $string || 'false' === $string) {
            $boolean = false;
        }

        return $boolean;
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
            if (!empty($var) && ($var == 'true')) {
                return 'true';
            } else {
                return 'false';
            }
        } else {
            return $var;
        }
    }

    public function boolean_to_db(bool $var)
    {
        if ($var === true) {
            return 1;
        } else {
            return 0;
        }
    }

    public function db_get_recent_period_expression($period, $date = 'CURRENT_DATE')
    {
        if ($date != 'CURRENT_DATE') {
            $date = '\'' . $date . '\'';
        }

        return 'date(' . $date . ',\'' . -$period . ' DAY\')';
    }

    public function db_get_recent_period($period, $date = 'CURRENT_DATE')
    {
        if (empty($this->cache[md5($period . $date)])) {
            $query = 'SELECT ' . $this->db_get_recent_period_expression($period, $date);
            list($d) = $this->db_fetch_row($this->db_query($query));

            $this->cache[md5($period . $date)] = $d;
        }

        return $this->cache[md5($period . $date)];
    }

    public function db_get_flood_period_expression($seconds): string
    {
        return 'date(' . $seconds . ',\'' . -$seconds . ' SECOND\')';
    }

    public function db_date_to_ts($date)
    {
        return 'strftime(\'%s\', \'' . $date . '\')';
    }

    public function db_get_date_YYYYMM($date)
    {
        return 'strftime(\'%Y%m\',' . $date . ')';
    }

    public function db_get_date_MMDD($date)
    {
        return 'strftime(\'%m%d\',' . $date . ')';
    }

    public function db_get_hour($date)
    {
        return 'strftime(\'%H\',' . $date . ')';
    }

    public function db_get_year($date)
    {
        return 'strftime(\'%Y\',' . $date . ')';
    }

    public function db_get_month($date)
    {
        return 'strftime(\'%m\',' . $date . ')';
    }

    public function db_get_week($date, $mode = null)
    {
        return 'strftime(\'%W\',' . $date . ')';
    }

    public function db_get_dayofmonth($date)
    {
        return 'strftime(\'%d\',' . $date . ')';
    }

    public function db_get_dayofweek($date)
    {
        return 'strftime(\'%w\',' . $date . ')';
    }

    public function db_get_weekday($date)
    {
        return 'strftime(\'%w\',date(' . $date . ',\'-1 DAY\'))';
    }

    public function db_concat(array $array) : string
    {
        return implode(' || ', $array);
    }

    public function db_concat_ws(array $array, string $separator) : string
    {
        $glue = sprintf(' || \'%s\' || ', $separator);

        return implode($glue, $array);
    }

    public function db_cast_to_text(string $string) : string
    {
        return $string;
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
                        if ($flags & self::MASS_UPDATES_SKIP_EMPTY) {
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
            if ($flags & self::MASS_UPDATES_SKIP_EMPTY) {
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
        $all_tables = [];

        // List all tables
        $query = 'SELECT name FROM SQLITE_MASTER WHERE name LIKE \'' . $this->prefix . '%\'';

        $all_tables = $this->query2array($query, null, 'name');
        foreach ($all_tables as $table_name) {
            $query = 'VACUUM ' . $table_name . ';';
            $result = $this->db_query($query);
        }

        return true;
    }

    /**
     * inserts multiple lines in a table
     *
     * @param string table_name
     * @param array dbfields
     * @param array inserts
     * @return void
     */
    public function mass_inserts(string $table_name, array $dbfields, array $datas, array $options = [])
    {
        $ignore = '';
        if (isset($options['ignore']) and $options['ignore']) {
            $ignore = 'OR IGNORE';
        }

        if (count($datas) != 0) {
            $query = '';

            foreach ($datas as $insert) {
                $query = 'INSERT ' . $ignore . ' INTO ' . $table_name . ' (' . implode(',', $dbfields) . ') VALUES';

                $query .= '(';
                foreach ($dbfields as $field_id => $dbfield) {
                    if ($field_id > 0) {
                        $query .= ',';
                    }

                    if (!isset($insert[$dbfield]) or $insert[$dbfield] === '') {
                        $query .= 'NULL';
                    } else {
                        $query .= '\'' . $this->db_real_escape_string($insert[$dbfield]) . '\'';
                    }
                }
                $query .= ')';
                $this->db_query($query);
            }
        }
    }

    // sqlite create functions
    public function _now()
    {
        return date('Y-m-d H:i:s');
    }

    public function _unix_timestamp()
    {
        return time();
    }

    public function _if($expression, $value1, $value2)
    {
        if ($expression) {
            return $value1;
        } else {
            return $value2;
        }
    }

    public function _regexp($pattern, $string)
    {
        $pattern = sprintf('`%s`', $pattern);

        return preg_match($pattern, $string);
    }

    public function _std_step(&$values, $rownumber, $value)
    {
        $values[] = $value;

        return $values;
    }

    public function _std_finalize(&$values, $rownumber)
    {
        if (count($values) <= 1) {
            return 0;
        }

        $total = 0;
        $total_square = 0;
        foreach ($values as $value) {
            $total += $value;
            $total_square += pow($value, 2);
        }

        $mean = $total / count($values);
        $var = $total_square / count($values) - pow($mean, 2);

        return sqrt($var);
    }
}
