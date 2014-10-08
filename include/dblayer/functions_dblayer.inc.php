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

/**
 * @deprecated Deprecated in 1.2.0, to be removed in 1.3.0. Use DBLayer class instead.
 */

function pwg_query($query) {
    global $conn;

    return $conn->db_query($query);
}

function pwg_get_db_version()  {
    global $conn;

    return $conn->db_version();
}

function pwg_db_check_version() {
    global $conn;

    return $conn->db_check_version();
}

function pwg_db_nextval($column, $table) {
    global $conn;

    return $conn->db_nextval($column, $table);
}

function pwg_db_changes($result) {
    global $conn;

    return $conn->db_changes($result);
}

function pwg_db_error() {
    global $conn;

    return $conn->db_last_error();
}

function pwg_db_num_rows($result) {
    global $conn;

    return $conn->db_num_rows($result);
}

function pwg_db_fetch_assoc($result) {
    global $conn;

    return $conn->db_fetch_assoc($result);
}

function pwg_db_fetch_row($result) {
    global $conn;

    return $conn->db_fetch_row($result);
}

function pwg_db_free_result($result) {
    global $conn;

    return $conn->db_free_result($result);
}

function pwg_db_real_escape_string($s) {
    global $conn;

    return $conn->db_real_escape_string($s);
}

function pwg_db_insert_id($table=null, $column='id') {
    global $conn;

    return $conn->db_insert_id($table, $column);
}

function pwg_db_close() {
    global $conn;

    $conn->db_close();
}

/* transaction functions */
function pwg_db_start_transaction() {
    global $conn;

    $conn->db_start_transaction();
}

function pwg_db_commit() {
    global $conn;

    $conn->db_commit();
}

function pwg_db_rollback() {
    global $conn;

    $conn->db_rollback();
}

function pwg_db_write_lock($table) {
    global $conn;

    $conn->db_write_lock($table);
}

function pwg_db_unlock() {
    global $conn;

    $conn->db_unlock();
}

function pwg_db_group_concat($field) {
    global $conn;

    return $conn->db_group_concat($field);
}

function pwg_db_full_text_search($fields, $values) {
    global $conn;

    return $conn->db_full_text_search($fields, $values);
}

function pwg_db_concat($array) {
    global $conn;

    return $conn->db_concat($array);
}

function pwg_db_concat_ws($array, $separator) {
    global $conn;

    return $conn->db_concat_ws($array, $separator);
}

function pwg_db_cast_to_text($string) {
    global $conn;

    return $conn->db_cast_to_text($string);
}

function pwg_db_get_tables($prefix) {
    global $conn;

    return $conn->db_get_tables($prefix);
}

function pwg_db_get_columns_of($tables) {
    global $conn;

    return $conn->db_get_columns_of($tables);
}

function get_enums($table, $field) {
    global $conn;

    return $conn->get_enums($table, $field);
}

function is_boolean($string) {
    global $conn;

    return $conn->is_boolean($string);
}

function get_boolean($string) {
    global $conn;

    return $conn->get_boolean($string);
}

function boolean_to_string($var) {
    global $conn;

    return $conn->boolean_to_string($var);
}

function boolean_to_db($var) {
    global $conn;

    return $conn->boolean_to_db($var);
}

function pwg_db_get_recent_period_expression($period, $date='CURRENT_DATE') {
    global $conn;

    return $conn->db_get_recent_period_expression($period, $date);
}

function pwg_db_get_recent_period($period, $date='CURRENT_DATE') {
    global $conn;

    return $conn->db_get_recent_period($period, $date);
}

function pwg_db_date_to_ts($date) {
    global $conn;

    return $conn->db_date_to_ts($date);
}

function pwg_db_get_date_YYYYMM($date) {
    global $conn;

    return $conn->db_get_date_YYYYMM($date);
}

function pwg_db_get_date_MMDD($date) {
    global $conn;

    return $conn->db_get_date_MMDD($date);
}

function pwg_db_get_hour($date) {
    global $conn;

    return $conn->db_get_hour($date);
}

function pwg_db_get_year($date) {
    global $conn;

    return $conn->db_get_year($date);
}

function pwg_db_get_month($date) {
    global $conn;

    return $conn->db_get_month($date);
}

function pwg_db_get_week($date, $mode=null) {
    global $conn;

    return $conn->db_get_week($date, $mode);
}

function pwg_db_get_dayofmonth($date) {
    global $conn;

    return $conn->db_get_dayofmonth($date);
}

function pwg_db_get_dayofweek($date) {
    global $conn;

    return $conn->db_get_dayofweek($date);
}

function pwg_db_get_weekday($date) {
    global $conn;

    return $conn->db_get_weekday($date);
}

function single_update($table_name, $datas, $where, $flags=0) {
    global $conn;

    return $conn->single_update($table_name, $datas, $where, $flags);
}

function single_insert($table_name, $data) {
    global $conn;

    $conn->single_insert($table_name, $data);
}

function mass_updates($tablename, $dbfields, $datas, $flags=0) {
    global $conn;

    $conn->mass_updates($tablename, $dbfields, $datas, $flags);
}

function mass_inserts($table_name, $dbfields, $datas, $options=array()) {
    global $conn;

    $conn->mass_inserts($table_name, $dbfields, $datas);
}

function query2array($query, $key_name=null, $value_name=null) {
    global $conn;

    return $conn->query2array($query, $key_name, $value_name);
}

function do_maintenance_all_tables() {
    global $conn;

    $conn->do_maintenance_all_tables();
}

function simple_hash_from_query($query, $keyname, $valuename) {
    global $conn;

    return $conn->simple_hash_from_query($query, $keyname, $valuename);
}

function hash_from_query($query, $keyname) {
    global $conn;

    return $conn->hash_from_query($query, $keyname);
}

function array_from_query($query, $fieldname=false) {
    global $conn;

    return $conn->array_from_query($query, $fieldname);
}

function pwg_db_get_flood_period_expression($seconds) {
    global $conn;

    return $conn->db_get_flood_period_expression($seconds);
}