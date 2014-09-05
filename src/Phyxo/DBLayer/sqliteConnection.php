<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire           http://phyxo.nikrou.net/ |
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

class sqliteConnection extends DBLayer implements iDBLayer
{
    Const REQUIRED_VERSION = '3.0.0';
    Const REGEX_OPERATOR = 'REGEXP';
    Const RANDOM_FUNCTION = 'RANDOM';

    protected $dblayer = 'sqlite';
    protected $db_link = null;

    public function db_connect($host, $user, $password, $database) {
        global $conf;

        $db_file = sprintf('sqlite:%s/%s%s.db', __DIR__.'/../../..', $conf['data_location'], $database);

        try {
            $this->db_link = new \PDO($db_file);
        } catch (\Exception $e) {
            throw new \Exception('Failed to open database '.$db_file . ':'.$e->getMessage());
        }

        $this->db_link->sqliteCreateFunction('now', array($this, '_now'), 0);
        $this->db_link->sqliteCreateFunction('unix_timestamp', array($this, '_unix_timestamp'), 0);
        $this->db_link->sqliteCreateFunction('md5', 'md5', 1);
        $this->db_link->sqliteCreateFunction('if', array($this, '_if'), 3);

        $this->db_link->sqliteCreateAggregate('std', array($this, '_std_step'), array($this, '_std_finalize'));
        $this->db_link->sqliteCreateFunction('regexp', array($this, '_regexp'), 2);

        return $this->db_link;
    }

    public function db_query($query) {
        $truncate_pattern = '`truncate(.*)`i';

        if (is_resource($this->db_link)) {
            if (preg_match($truncate_pattern, $query, $matches)) {
                $query = str_replace('TRUNCATE TABLE', 'DELETE FROM', $query);
                $result = $this->db_link->exec($query);
            } else {
                $result = $this->db_link->query($query);
            }
            if ($result === false) {
                $e = new \Exception($this->db_last_error($handle));
                $e->sql = $query;
                throw $e;
            }

            return $result;
        }
    }

    public function db_version()  {
        return $this->db_link->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }

    public function db_check_version() {
        $current_sqlite =$this->db_version();
        if (version_compare($current_sqlite, self::REQUIRED_VERSION, '<')) {
            throw new \Exception(sprintf(
                'your SQLite version is too old, you have "%s" and you need at least "%s"',
                $current_sqlite,
                self::REQUIRED_VERSION
            ));
        }
    }

    public function db_last_error() {
        if (is_resource($this->db_link)) {
            return $this->db_link->errorInfo();
        }

        return false;
    }

    public function db_nextval($column, $table) {
        $query = 'SELECT MAX('.$column.')+1 FROM '.$table;
        list($next) = $this->db_fetch_row($this->db_query($query));
        if (is_null($next)) {
            $next = 1;
        }

        return $next;
    }

    public function db_changes($result) {
        return $result->rowCount();
    }

    public function db_num_rows($result) {
        if (is_resource($result)) {
            return $result->columnCount();
        }

        return 0;
    }

    public function db_fetch_assoc($result) {
        if (is_resource($result)) {
            return $this->db_fetch_assoc($result);
        }
    }

    public function db_fetch_row($result) {
        if (is_resource($result)) {
            return $this->db_fetch_row($result);
        }
    }

    public function db_free_result($result) {
    }

    public function db_real_escape_string($s) {
        return trim($this->db_link->quote($s), '\'');
    }

    public function db_insert_id($table=null, $column='id') {
        return $this->db_link->lastInsertId();
    }

    public function db_close() {
        if (is_resource($this->db_link)) {
            $this->db_link = null;
        }
    }

    /* transaction functions */
    public function db_start_transaction() {
        $this->db_link->beginTransaction();
    }

    public function db_commit() {
        $this->db_link->commit();
    }

    public function db_rollback() {
        $this->db_link->rollback();
    }

    public function db_write_lock($table) {
        $this->db_query('BEGIN EXCLUSIVE TRANSACTION');
    }

    public function db_unlock() {
        $this->db_query('END');
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

        $query = "SELECT * FROM sqlite_master WHERE type = 'table'";
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

        $fmt_query = 'PRAGMA table_info(%s)';

        foreach ($tables as $table) {
            $query = sprintf($fmt_query, $this->db_real_escape_string($table));
            $result = $this->db_query($query);
            $columns_of[$table] = array();

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
    public function get_enums($table, $field) {
        global $prefixeTable;

        $list = array();

        $Enums = array();
        $Enums[$prefixeTable.'categories']['status'] = array('public', 'private');
        $Enums[$prefixeTable.'categories']['visible'] = array('true', 'false');
        $Enums[$prefixeTable.'categories']['commentable'] = array('true', 'false');
        $Enums[$prefixeTable.'comments']['validated'] = array('true', 'false');
        $Enums[$prefixeTable.'groups']['is_default'] = array('true', 'false');
        $Enums[$prefixeTable.'history']['section'] = array('categories','tags','search','list','favorites','most_visited','best_rated','recent_pics','recent_cats');
        $Enums[$prefixeTable.'history']['summarized'] = array('true', 'false');
        $Enums[$prefixeTable.'history']['image_type'] = array('picture', 'high', 'other');
        $Enums[$prefixeTable.'plugins']['state'] = array('inactive', 'active');
        $Enums[$prefixeTable.'user_cache']['need_update'] = array('true', 'false');
        $Enums[$prefixeTable.'user_cache']['image_access_type'] = array('NOT IN', 'IN');
        $Enums[$prefixeTable.'user_infos']['status'] = array('webmaster','admin','normal','generic','guest');
        $Enums[$prefixeTable.'user_infos']['expand'] = array('true', 'false');
        $Enums[$prefixeTable.'user_infos']['show_nb_comments'] = array('true', 'false');
        $Enums[$prefixeTable.'user_infos']['show_nb_hits'] = array('true', 'false');
        $Enums[$prefixeTable.'user_infos']['enabled_high'] = array('true', 'false');
        $Enums[$prefixeTable.'user_mail_notification']['enabled'] = array('true', 'false');

        if (!empty($Enums[$table][$field])) {
            $list = $Enums[$table][$field];
        }

        return $list;
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
    public function get_boolean($string) {
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
    public function boolean_to_string($var) {
        if (!empty($var) && ($var == 'true')) {
            return 'true';
        } else {
            return 'false';
        }
    }

    public function db_get_recent_period_expression($period, $date='CURRENT_DATE') {
        if ($date!='CURRENT_DATE') {
            $date = '\''.$date.'\'';
        }

        return 'date('.$date.',\''.-$period.' DAY\')';
    }

    public function db_get_recent_period($period, $date='CURRENT_DATE') {
        $query = 'SELECT '.$this->db_get_recent_period_expression($period, $date);
        list($d) = $this->db_fetch_row($this->db_query($query));

        return $d;
    }

    public function db_get_flood_period_expression($seconds) {
        return 'date('.$date.',\''.-$seconds.' SECOND\')';
    }

    public function db_date_to_ts($date) {
        return 'strftime(\'%s\', \''.$date.'\')';
    }

    public function db_get_date_YYYYMM($date) {
        return 'strftime(\'%Y%m\','.$date.')';
    }

    public function db_get_date_MMDD($date) {
        return 'strftime(\'%m%d\','.$date.')';
    }

    public function db_get_hour($date) {
        return 'strftime(\'%H\','.$date.')';
    }

    public function db_get_year($date) {
        return 'strftime(\'%Y\','.$date.')';
    }

    public function db_get_month($date) {
        return 'strftime(\'%m\','.$date.')';
    }

    public function db_get_week($date, $mode=null) {
        return 'strftime(\'%W\','.$date.')';
    }

    public function db_get_dayofmonth($date) {
        return 'strftime(\'%d\','.$date.')';
    }

    public function db_get_dayofweek($date) {
        return 'strftime(\'%w\','.$date.')';
    }

    public function db_get_weekday($date) {
        return 'strftime(\'%w\',date('.$date.',\'-1 DAY\'))';
    }

    public function db_concat($array) {
        return implode($array, ' || ');
    }

    public function db_concat_ws($array, $separator) {
        $glue = sprintf(' || \'%s\' || ', $separator);

        return implode($array, $glue);
    }

    public function db_cast_to_text($string) {
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
    public function mass_updates($tablename, $dbfields, $datas, $flags=0) {
        if (count($datas) == 0) {
            return;
        }

        $is_first = true;

        $query = 'UPDATE '.$tablename.' SET ';

        foreach ($datas as $key => $value) {
            $separator = $is_first ? '' : ",\n    ";

            if (isset($value) and $value !== '') {
                $query .= $separator.$key.' = \''.$value.'\'';
            } else {
                if ($flags & MASS_UPDATES_SKIP_EMPTY) {
                    continue; // next field
                }
                $query .= "$separator$key = NULL";
            }
            $is_first = false;
        }

        if (!$is_first) { // only if one field at least updated
            $is_first = true;

            $query .= ' WHERE ';

            foreach ($where as $key => $value) {
                if (!$is_first) {
                    $query .= ' AND ';
                }
                if (isset($value)) {
                    $query .= $key.' = \''.$value.'\'';
                } else {
                    $query .= $key.' IS NULL';
                }
                $is_first = false;
            }

            $this->db_query($query);
        }
    }

    /**
     * Do maintenance on all Phyxo tables
     *
     * @return none
     */
    function do_maintenance_all_tables() {
        global $prefixeTable;

        $all_tables = array();

        // List all tables
        $query = 'SELECT name FROM SQLITE_MASTER WHERE name LIKE \''.$prefixeTable.'%\'';

        $all_tables = $this->conn->query2array($query, null, 'name');
        foreach ($all_tables as $table_name) {
            $query = 'VACUUM '.$table_name.';';
            $result = $this->db_query($query);
        }

        return true;
    }

    // sqlite create functions
    public function _now() {
        return date('Y-m-d H:i:s');
    }

    public function _unix_timestamp() {
        return time();
    }

    public function _if($expression, $value1, $value2) {
        if ($expression) {
            return $value1;
        } else {
            return $value2;
        }
    }

    public function _regexp($pattern, $string) {
        $pattern = sprintf('`%s`', $pattern);

        return preg_match($pattern, $string);
    }

    public function _std_step(&$values, $rownumber, $value) {
        $values[] = $value;

        return $values;
    }

    public function _std_finalize(&$values, $rownumber) {
        if (count($values)<=1) {
            return 0;
        }

        $total = 0;
        $total_square = 0;
        foreach ($values as $value) {
            $total += $value;
            $total_square += pow($value, 2);
        }

        $mean = $total/count($values);
        $var = $total_square/count($values) - pow($mean, 2);

        return sqrt($var);
    }
}
