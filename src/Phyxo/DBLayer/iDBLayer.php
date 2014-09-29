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

interface iDBLayer
{
    /**
     * Open connection
     *
     * This method should open a database connection and return a new resource link.
     *
     * @param string	$host		Database server host
     * @param string	$user		Database user name
     * @param string	$password	Database password
     * @param string	$database	Database name
     * @return resource
     */
    public function db_connect($host, $user, $password, $database);

	/**
     * Database query
     *
     * This method should run an SQL query and return a resource result.
     *
     * @param resource   $handle		Resource link
     * @param string	    $query		SQL query string
     * @return resource
     */
    public function db_query($query);

    /**
     * Database version
     *
     * This method should return database version number.
     *
     * @return string
     */
    public function db_version();

    /**
     * Check database version against required one.
     *
     * This method throws an execption if database version is lesser than required one.
     *
     * @return void
     */
    public function db_check_version();

    /**
     * Next value for integer field in a table
     *
     * @param string $column     Column name.
     * @param string $table      Table name.
     * @return integer
     */
    public function db_nextval($column, $table);

	/**
	* Affected rows
	*
	* This method should return number of rows affected by INSERT, UPDATE or
	* DELETE queries.
	*
    * @param resource $result    Resource result
	* @return integer
	*/
    public function db_changes($result);

    /**
     * Last error
     *
     * This method should return the last error string for the current connection.
     *
     * @return string
     */
    public function db_last_error();

    /**
     * Result rows count
     *
     * This method should return the number of rows in a result.
     *
     * @param resource	$result			Resource result
     * @return integer
     */
    public function db_num_rows($result);

    /**
     * Fetch result
     *
     * This method should fetch one line of result and return an associative array
     * with field name as key and field value as value.
     *
     * @param resource	$result			Resource result
     * @return array
     */
    public function db_fetch_assoc($result);

    /**
     * Fetch result
     *
     * This method should fetch one line of result and return an array
     * indexed from 0, containing all fields value.
     *
     * @param resource	$result			Resource result
     * @return array
     */
    public function db_fetch_row($result);

    /**
     * Free result memory
     *
     * This method should free the memory and data associated with the specified query result resource.
     * return void
     */
    public function db_free_result($result);

    /**
     * Escape string
     *
     * This method should return an escaped string for the current connection.
     *
     * @param string	$string			String to escape
     * @return string
     */
    public function db_real_escape_string($string);

    /**
     * Last insert id
     *
     * This method should return the generated ID from the last query
     *
     * @param string	$table			Table in which the query was made.
     * @param string	$column			Column auto-incremented.
     * @return integer
     */
    public function db_insert_id($table=null, $column='id');

	/**
     * Close connection
     *
     * This method should close resource link.
     */
    public function db_close();

    /* transaction methods */
    /**
     * Starts a transaction
     */
    public function db_start_transaction();

	/**
     * Commits transaction
     */
    public function db_commit();

	/**
     * Rollback transaction
     */
    public function db_rollback();

	/**
     * Acquiere Write lock
     *
     * This method should lock the given table in write access.
     *
     * @param string	$table		Table name
     */
    public function db_write_lock($table);

    /**
     * Release lock
     *
     * This method should releases an acquiered lock.
     */
    public function db_unlock();

    /* strings methods */
    /**
     *  GROUP_CONCAT for mysql
     */
    public function db_group_concat($field);

    /**
     * Full text search
     *
     * @param string	$fields		Fields in which to operate.
     * @param string	$values		Values to search.
     * @return string
     */
    public function db_full_text_search($fields, $values);

    /**
     * Convert Array to String
     *
     * @param array	$array		Array to convert.
     * @return string
     */
    public function db_concat($array);

    /**
     * Convert Array to String with separator
     *
     * @param array	    $array		Array to convert.
     * @param string	$separator	Separator between parts.
     * @return string
     */
    public function db_concat_ws($array, $separator);

    /**
     * Cast string to text
     *
     * @param string	$string	    String to cast.
     * @return string
     */
    public function db_cast_to_text($string);

    /* others methods */
    /**
     * Get all tables in current database
     *
     * @param string $prefix    Prefix to identify tables.
     * @return array
     */
    public function db_get_tables($prefix);

    /**
     * Get columns of tables
     *
     * @param array $tables    Array of tables name.
     * @return array indexed by table name.
     */
    public function db_get_columns_of($tables);

    /**
     * Get enums for a field in a table
     *
     * @param string $table    Table name.
     * @param string $field    Field name.
     * @return array
     */
    public function get_enums($table, $field);

    /**
     * is_boolean test if a string is a boolean.
     * The string comes from database so it depends of database engine
     */
    public function is_boolean($string);

    /**
     * get_boolean transforms a string to a boolean value. If the string is
     * "false" (case insensitive), then the boolean value false is returned. In
     * any other case, true is returned.
     */
    public function get_boolean($string);

    /**
     * returns boolean string 'true' or 'false' if the given var is boolean
     *
     * @param mixed $var
     * @return mixed
     */
    public function boolean_to_string($var);

    /**
     * boolean_to_db transforms boolean to a compatible field in database
     */
    public function boolean_to_db($var);

    /**
     * return an IN clause where @params are escaped
     */
    public function in($params);

    /* day & time methods */
    public function db_get_recent_period($period, $date='CURRENT_DATE');

    public function db_get_recent_period_expression($period, $date='CURRENT_DATE');

    public function db_get_flood_period_expression($seconds);

    public function db_date_to_ts($date);

    public function db_get_date_YYYYMM($date);

    public function db_get_date_MMDD($date);

    public function db_get_hour($date);

    public function db_get_year($date);

    public function db_get_month($date);

    public function db_get_week($date, $mode=null);

    public function db_get_dayofmonth($date);

    public function db_get_dayofweek($date);

    public function db_get_weekday($date);

    /**
     * Builds an data array from a SQL query.
     * Depending on $key_name and $value_name it can return :
     *
     *    - an array of arrays of all fields (key=null, value=null)
     *        array(
     *          array('id'=>1, 'name'=>'DSC8956', ...),
     *          array('id'=>2, 'name'=>'DSC8957', ...),
     *          ...
     *          )
     *
     *    - an array of a single field (key=null, value='...')
     *        array('DSC8956', 'DSC8957', ...)
     *
     *    - an associative array of array of all fields (key='...', value=null)
     *        array(
     *          'DSC8956' => array('id'=>1, 'name'=>'DSC8956', ...),
     *          'DSC8957' => array('id'=>2, 'name'=>'DSC8957', ...),
     *          ...
     *          )
     *
     *    - an associative array of a single field (key='...', value='...')
     *        array(
     *          'DSC8956' => 1,
     *          'DSC8957' => 2,
     *          ...
     *          )
     *
     * @since 2.6
     *
     * @param string $query
     * @param string $key_name
     * @param string $value_name
     * @return array
     */
    public function query2array($query, $key_name=null, $value_name=null);

    /**
     * Updates one line in a table.
     *
     * @param string $tablename
     * @param array $datas
     * @param array $where
     * @param int $flags - if MASS_UPDATES_SKIP_EMPTY, empty values do not overwrite existing ones
     */
    public function single_update($tablename, $datas, $where, $flags=0);

    /**
     * Inserts one line in a table.
     *
     * @param string $table_name
     * @param array $data
     */
    public function single_insert($table_name, $data);

    /**
     * updates multiple lines in a table
     *
     * @param string table_name
     * @param array dbfields
     * @param array datas
     * @param int flags - if MASS_UPDATES_SKIP_EMPTY - empty values do not overwrite existing ones
     * @return void
     */
    public function mass_updates($tablename, $dbfields, $datas, $flags=0);

    /**
     * inserts multiple lines in a table
     *
     * @param string table_name
     * @param array dbfields
     * @param array inserts
     * @return void
     */
    public function mass_inserts($table_name, $dbfields, $datas);

    /**
     * Do maintenance on all Phyxo tables
     *
     * @return true on success false on failure
     */
    function do_maintenance_all_tables();

    /**
     * creates an simple hashmap based on a SQL query.
     * choose one to be the key, another one to be the value.
     * @deprecated Deprecated in 1.2, to be removed in 1.3
     *
     * @param string $query
     * @param string $keyname
     * @param string $valuename
     * @return array
     */
    public function simple_hash_from_query($query, $keyname, $valuename);

    /**
     * creates an associative array based on a SQL query.
     * choose one to be the key
     * @deprecated Deprecated in 1.2, to be removed in 1.3
     *
     * @param string $query
     * @param string $keyname
     * @return array
     */
    public function hash_from_query($query, $keyname);

    /**
     * creates a numeric array based on a SQL query.
     * if _$fieldname_ is empty the returned value will be an array of arrays
     * if _$fieldname_ is provided the returned value will be a one dimension array
     * @deprecated Deprecated in 1.2, to be removed in 1.3
     *
     * @param string $query
     * @param string $fieldname
     * @return array
     */
    public function array_from_query($query, $fieldname=false);
}