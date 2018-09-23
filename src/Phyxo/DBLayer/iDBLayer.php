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
    public function db_connect(string $host, string $user, string $password, string $database);

    /**
     * Database query
     *
     * This method should run an SQL query and return a resource result.
     *
     * @param resource   $handle		Resource link
     * @param string	    $query		SQL query string
     * @return resource
     */
    public function db_query(string $query);

    /**
     * Database version
     *
     * This method should return database version number.
     *
     * @return string
     */
    public function db_version() : string;

    /**
     * Check database version against required one.
     *
     * This method throws an execption if database version is lesser than required one.
     *
     * @return void
     */
    public function db_check_version() : void;

    /**
     * Next value for integer field in a table
     *
     * @param string $column     Column name.
     * @param string $table      Table name.
     * @return integer
     */
    public function db_nextval(string $column, string $table) : int;

    /**
     * Affected rows
     *
     * This method should return number of rows affected by INSERT, UPDATE or
     * DELETE queries.
     *
     * @param resource $result    Resource result
     * @return integer
     */
    public function db_changes($result) : int;

    /**
     * Last error
     *
     * This method should return the last error string for the current connection.
     *
     * @return string
     */
    public function db_last_error() : string;

    /**
     * Result rows count
     *
     * This method should return the number of rows in a result.
     *
     * @param resource	$result			Resource result
     * @return integer
     */
    public function db_num_rows($result) : int;

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
    public function db_free_result($result) : void;

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
    public function db_insert_id(string $table = null, string $column = 'id') : int;

    /**
     * Close connection
     *
     * This method should close resource link.
     */
    public function db_close();

    // transaction methods

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
    public function db_write_lock(string $table);

    /**
     * Release lock
     *
     * This method should releases an acquiered lock.
     */
    public function db_unlock();

    // strings methods

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
    public function db_full_text_search($fields, $values) : string;

    /**
     * Convert Array to String
     *
     * @param array	$array		Array to convert.
     * @return string
     */
    public function db_concat(array $array) : string;

    /**
     * Convert Array to String with separator
     *
     * @param array	    $array		Array to convert.
     * @param string	$separator	Separator between parts.
     * @return string
     */
    public function db_concat_ws(array $array, string $separator) : string;

    /**
     * Cast string to text
     *
     * @param string	$string	    String to cast.
     * @return string
     */
    public function db_cast_to_text(string $string) : string;

    // others methods

    /**
     * Get all tables in current database
     *
     * @param string $prefix    Prefix to identify tables.
     * @return array
     */
    public function db_get_tables(string $prefix) : array;

    /**
     * Get columns of tables
     *
     * @param array $tables    Array of tables name.
     * @return array indexed by table name.
     */
    public function db_get_columns_of(array $tables) : array;

    /**
     * Get enums for a field in a table
     *
     * @param string $table    Table name.
     * @param string $field    Field name.
     * @return array
     */
    public function get_enums(string $table, string $field) : array;

    /**
     * is_boolean test if a string is a boolean.
     * The string comes from database so it depends of database engine
     */
    public function is_boolean(string $string) : bool;

    /**
     * get_boolean transforms a string to a boolean value. If the string is
     * "false" (case insensitive), then the boolean value false is returned. In
     * any other case, true is returned.
     */
    public function get_boolean(string $string) : bool;

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
    public function boolean_to_db(bool $var);

    /**
     * return an IN clause where @params are escaped
     */
    public function in(array $params);

    // day & time methods
    public function db_get_recent_period($period, $date = 'CURRENT_DATE');

    public function db_get_recent_period_expression($period, $date = 'CURRENT_DATE');

    public function db_get_flood_period_expression($seconds);

    public function db_date_to_ts($date);

    public function db_get_date_YYYYMM($date);

    public function db_get_date_MMDD($date);

    public function db_get_hour($date);

    public function db_get_year($date);

    public function db_get_month($date);

    public function db_get_week($date, $mode = null);

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
    public function query2array(string $query, string $key_name = null, string $value_name = null) : array;

    public function result2array($result, string $key_name = null, string $value_name = null) : array;

    /**
     * Updates one line in a table.
     *
     * @param string $tablename
     * @param array $datas
     * @param array $where
     * @param int $flags - if MASS_UPDATES_SKIP_EMPTY, empty values do not overwrite existing ones
     */
    public function single_update(string $tablename, array $datas, array $where, int $flags = 0);

    /**
     * Inserts one line in a table.
     *
     * @param string $table_name
     * @param array $data
     */
    public function single_insert(string $table_name, array $data);

    /**
     * updates multiple lines in a table
     *
     * @param string table_name
     * @param array dbfields
     * @param array datas
     * @param int flags - if MASS_UPDATES_SKIP_EMPTY - empty values do not overwrite existing ones
     * @return void
     */
    public function mass_updates(string $tablename, array $dbfields, array $datas, int $flags = 0);

    /**
     * inserts multiple lines in a table
     *
     * @param string table_name
     * @param array dbfields
     * @param array inserts
     * @return void
     */
    public function mass_inserts(string $table_name, array $dbfields, array $datas);

    /**
     * deletes multiple lines in a table
     *
     * @param string table_name
     * @param array dbfields
     * @param array datas
     * @return void
     */
    public function mass_deletes(string $tablename, array $dbfields, array $datas);

    /**
     * Do maintenance on all Phyxo tables
     *
     * @return true on success false on failure
     */
    function do_maintenance_all_tables();
}
