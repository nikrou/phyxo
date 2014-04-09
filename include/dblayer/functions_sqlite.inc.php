<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire        http://www.nikrou.net/phyxo |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

define('REQUIRED_SQLITE_VERSION', '3.0.0');
define('DB_ENGINE', 'SQLite');

define('DB_REGEX_OPERATOR', 'REGEXP');
define('DB_RANDOM_FUNCTION', 'RANDOM');

function pwg_db_connect($host, $user, $password, $database) {
    global $conf, $pwg_db_link;

    $db_file = sprintf('sqlite:%s/%s/%s.db', dirname(__FILE__).'/../..',$conf['data_location'], $database);

    try {
        $pwg_db_link = new PDO($db_file);
    } catch (Exception $e) {
        my_error('Failed to open database '.$db_file . ':'.$e->getMessage(), true);
    }
    
    $pwg_db_link->sqliteCreateFunction('now', 'pwg_now', 0);
    $pwg_db_link->sqliteCreateFunction('unix_timestamp', 'pwg_unix_timestamp', 0);
    $pwg_db_link->sqliteCreateFunction('md5', 'md5', 1);
    $pwg_db_link->sqliteCreateFunction('if', 'pwg_if', 3);
    
    $pwg_db_link->sqliteCreateAggregate('std', 'pwg_std_step', 'pwg_std_finalize');
    $pwg_db_link->sqliteCreateFunction('regexp', 'pwg_regexp', 2);
}

function pwg_db_check_charset() {
  return true;
}

function pwg_get_db_version() {
    global $pwg_db_link;

    return $pwg_db_link->getAttribute(PDO::ATTR_SERVER_VERSION);
}

function pwg_db_check_version() {
    $current_sqlite = pwg_get_db_version();
    if (version_compare($current_sqlite, REQUIRED_SQLITE_VERSION, '<')) {
        fatal_error(
            sprintf(
                'your SQLite version is too old, you have "%s" and you need at least "%s"',
                $current_sqlite,
                REQUIRED_SQLITE_VERSION
            )
        );
    }
}

function pwg_query($query) {
    global $conf,$page,$debug,$t2,$pwg_db_link;

    $start = microtime(true);
    
    $truncate_pattern = '`truncate(.*)`i';
    $insert_pattern = '`(INSERT INTO [^)]*\)\s*VALUES)(\([^)]*\))\s*,\s*(.*)`mi';  
    
    if (preg_match($truncate_pattern, $query, $matches)) {
        $query = str_replace('TRUNCATE TABLE', 'DELETE FROM', $query);
        $truncate_query = true;
        ($result = $pwg_db_link->exec($query)) or die($query."\n<br>".print_r($pwg_db_link->errorInfo()));
    } else {
        ($result = $pwg_db_link->query($query)) 
            or die($query."\n<br>".print_r($pwg_db_link->errorInfo()));
    }

    $time = microtime(true) - $start;
    
    if (!isset($page['count_queries'])) {
        $page['count_queries'] = 0;
        $page['queries_time'] = 0;
    }

    $page['count_queries']++;
    $page['queries_time']+= $time;

    if ($conf['show_queries']) {
        $output = '';
        $output.= '<pre>['.$page['count_queries'].'] ';
        $output.= "\n".$query;
        $output.= "\n".'(this query time : ';
        $output.= '<b>'.number_format($time, 3, '.', ' ').' s)</b>';
        $output.= "\n".'(total SQL time  : ';
        $output.= number_format($page['queries_time'], 3, '.', ' ').' s)';
        $output.= "\n".'(total time      : ';
        $output.= number_format( ($time+$start-$t2), 3, '.', ' ').' s)';
        if ( $result!=null and preg_match('/\s*SELECT\s+/i',$query) ) {
            $output.= "\n".'(num rows        : ';
            $output.= pwg_db_num_rows($result).' )';
        } elseif ( $result!=null 
        and preg_match('/\s*INSERT|UPDATE|REPLACE|DELETE\s+/i',$query) 
        and !isset($truncate_query)) {
            $output.= "\n".'(affected rows   : ';
            $output.= pwg_db_changes($result).' )';
        }
        $output.= "</pre>\n";
        
        $debug .= $output;
    }

    return $result;
}

function pwg_db_nextval($column, $table) {
    $query = 'SELECT MAX('.$column.')+1 FROM '.$table;
    list($next) = pwg_db_fetch_row(pwg_query($query));
    if (is_null($next)) {
        $next = 1;
    }

    return $next;
}

function pwg_db_changes(PDOStatement $result=null) {
    return $result->rowCount();
}

function pwg_db_num_rows(PDOStatement $result) { 
    return $result->columnCount();
}

function pwg_db_fetch_assoc($result) {
    return $result->fetch(PDO::FETCH_ASSOC);
}

function pwg_db_fetch_row($result) {
    return $result->fetch(PDO::FETCH_NUM);
}

function pwg_db_fetch_object($result) {
    return $result;
}

function pwg_db_free_result($result) {
}

function pwg_db_real_escape_string($s) {
    global $pwg_db_link;

    return trim($pwg_db_link->quote($s), "'");
}

function pwg_db_insert_id($table=null, $column='id') {
  global $pwg_db_link;

  return $pwg_db_link->lastInsertId();
}

/**
 *
 * complex functions
 *
 */


define('MASS_UPDATES_SKIP_EMPTY', 1);
/**
 * updates multiple lines in a table
 *
 * @param string table_name
 * @param array dbfields
 * @param array datas
 * @param int flags - if MASS_UPDATES_SKIP_EMPTY - empty values do not overwrite existing ones
 * @return void
 */
function mass_updates($tablename, $dbfields, $datas, $flags=0) {
    if (count($datas) == 0) {
        return;
    }

    foreach ($datas as $data) {
        $query = 'UPDATE '.$tablename.' SET ';
        $is_first = true;
        foreach ($dbfields['update'] as $key) {
            $separator = $is_first ? '' : ",\n    ";
            
            if (isset($data[$key]) and $data[$key] != '') {
                $query.= $separator.$key.' = \''.$data[$key].'\'';
            } else {
                if ($flags & MASS_UPDATES_SKIP_EMPTY ) {
                    continue; // next field
                }
                $query.= "$separator$key = NULL";
            }
            $is_first = false;
        }
        if (!$is_first) { // only if one field at least updated
            $query.= ' WHERE ';
            $is_first = true;
            foreach ($dbfields['primary'] as $key) {
                if (!$is_first) {
                    $query.= ' AND ';
                }
                if ( isset($data[$key]) ) {
                    $query.= $key.' = \''.$data[$key].'\'';
                } else {
                    $query.= $key.' IS NULL';
                }
                $is_first = false;
            }
            pwg_query($query);
        }
    }
}


/**
 * inserts multiple lines in a table
 *
 * @param string table_name
 * @param array dbfields
 * @param array inserts
 * @return void
 */
function mass_inserts($table_name, $dbfields, $datas) {
    if (count($datas) != 0) {
        $first = true;
        
        $packet_size = 16777216;
        $packet_size = $packet_size - 2000; // The last list of values MUST not exceed 2000 character*/
        $query = '';

        foreach ($datas as $insert) {
            if (strlen($query) >= $packet_size) {
                pwg_query($query);
                $first = true;
            }

            if ($first) {
                $query = 'INSERT INTO '.$table_name.' ('.implode(',', $dbfields).') VALUES';
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
                    $query .= "'".$insert[$dbfield]."'";
                }
            }
            $query .= ')';
        }

        pwg_query($query);
    }
}

/**
 * Do maintenance on all PWG tables
 *
 * @return none
 */
function do_maintenance_all_tables() {
    global $prefixeTable, $page;

    $all_tables = array();

    // List all tables
    $query = 'SELECT name FROM SQLITE_MASTER WHERE name LIKE \''.$prefixeTable.'%\'';
    
    $all_tables = array_from_query($query, 'name');
    foreach ($all_tables as $table_name) {
        $query = 'VACUUM '.$table_name.';';
        $result = pwg_query($query);
    }
  
    array_push($page['infos'], l10n('Optimizations completed'));
}

function pwg_db_concat($array) {
    return implode($array, ' || ');
}

function pwg_db_concat_ws($array, $separator) {
    $glue = sprintf(' || \'%s\' || ', $separator);

    return implode($array, $glue);
}

function pwg_db_cast_to_text($string) {
    return $string;
}

/**
 * returns an array containing the possible values of an enum field
 * TODO : find a better way to retrieved that stuff
 *
 * @param string tablename
 * @param string fieldname
 */
function get_enums($table, $field) {
    $list = array();

    $Enums = array();
    $Enums['piwigo_categories']['status'] = array('public', 'private');
    $Enums['piwigo_categories']['visible'] = array('true', 'false');
    $Enums['piwigo_categories']['commentable'] = array('true', 'false');
    $Enums['piwigo_comments']['validated'] = array('true', 'false');
    $Enums['piwigo_groups']['is_default'] = array('true', 'false');
    $Enums['piwigo_history']['section'] = array('categories','tags','search','list','favorites','most_visited','best_rated','recent_pics','recent_cats');
    $Enums['piwigo_history']['summarized'] = array('true', 'false');
    $Enums['piwigo_history']['image_type'] = array('picture', 'high', 'other');
    $Enums['piwigo_plugins']['state'] = array('inactive', 'active');
    $Enums['piwigo_user_cache']['need_update'] = array('true', 'false');
    $Enums['piwigo_user_cache']['image_access_type'] = array('NOT IN', 'IN');
    $Enums['piwigo_user_infos']['status'] = array('webmaster','admin','normal','generic','guest');
    $Enums['piwigo_user_infos']['expand'] = array('true', 'false');
    $Enums['piwigo_user_infos']['show_nb_comments'] = array('true', 'false');
    $Enums['piwigo_user_infos']['show_nb_hits'] = array('true', 'false');
    $Enums['piwigo_user_infos']['enabled_high'] = array('true', 'false');
    $Enums['piwigo_user_mail_notification']['enabled'] = array('true', 'false');

    if (!empty($Enums[$table][$field])) {
        $list = $Enums[$table][$field];
    }
    
    return $list;
}

// get_boolean transforms a string to a boolean value. If the string is
// "false" (case insensitive), then the boolean value false is returned. In
// any other case, true is returned.
function get_boolean( $string ) {
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
function boolean_to_string($var) {
    if (!empty($var) && ($var == 'true')) {
        return 'true';
    } else {
        return 'false';
    }
}

/**
 *
 * interval and date functions 
 *
 */
function pwg_db_get_recent_period_expression($period, $date='CURRENT_DATE') {
    if ($date!='CURRENT_DATE') {
        $date = '\''.$date.'\'';
    }
    
    return 'date('.$date.',\''.-$period.' DAY\')';
}

function pwg_db_get_recent_period($period, $date='CURRENT_DATE') {
    $query = 'select '.pwg_db_get_recent_period_expression($period, $date);
    list($d) = pwg_db_fetch_row(pwg_query($query));

    return $d;
}

function pwg_db_get_date_YYYYMM($date) {
    return 'strftime(\'%Y%m\','.$date.')';
}

function pwg_db_get_date_MMDD($date) {
    return 'strftime(\'%m%d\','.$date.')';
}

function pwg_db_get_year($date) {
    return 'strftime(\'%Y\','.$date.')';
}

function pwg_db_get_month($date) {
    return 'strftime(\'%m\','.$date.')';
}

function pwg_db_get_week($date, $mode=null) {
    return 'strftime(\'%W\','.$date.')';
}

function pwg_db_get_dayofmonth($date) {
    return 'strftime(\'%d\','.$date.')';
}

function pwg_db_get_dayofweek($date) {
    return 'strftime(\'%w\','.$date.')';
}

function pwg_db_get_weekday($date) {
    return 'strftime(\'%w\',date('.$date.',\'-1 DAY\'))';
}

// my_error returns (or send to standard output) the message concerning the
// error occured for the last mysql query.
function my_error($header, $die) {
    global $pwg_db_link;

    $error = '';
    if (isset($pwg_db_link)) {
        $error .= '[sqlite error]'.print_r($pwg_db_link->errorInfo())."\n";
    }

    $error .= $header;

    if ($die) {
        fatal_error($error);
    }
    echo("<pre>");
    trigger_error($error, E_USER_WARNING);
    echo("</pre>");
}

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
function query2array($query, $key_name=null, $value_name=null) {
    $result = pwg_query($query);
    $data = array();

    if (isset($key_name)) {
        if (isset($value_name)) {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $data[ $row[$key_name] ] = $row[$value_name];
            }
        } else {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $data[ $row[$key_name] ] = $row;
            }
        }
    } else {
        if (isset($value_name)) {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $data[] = $row[$value_name];
            }
        } else {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $data[] = $row;
            }
        }
    }

    return $data;
}


// sqlite create functions
function pwg_now() {
    return date('Y-m-d H:i:s');
}

function pwg_unix_timestamp() {
    return time();
}

function pwg_if($expression, $value1, $value2) {
    if ($expression) {
        return $value1;
    } else {
        return $value2;
    }
} 

function pwg_regexp($pattern, $string) {
    $pattern = sprintf('`%s`', $pattern);

    return preg_match($pattern, $string);
}

function pwg_std_step(&$values, $rownumber, $value) {
    $values[] = $value;
    
    return $values;
}

function pwg_std_finalize(&$values, $rownumber) {
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

/**
 * Inserts one line in a table.
 *
 * @param string $table_name
 * @param array $data
 */
function single_insert($table_name, $data) {
  if (count($data) != 0) {
    $query = '
INSERT INTO '.$table_name.'
  ('.implode(',', array_keys($data)).')
  VALUES';

    $query .= '(';
    $is_first = true;
    foreach ($data as $key => $value)
    {
      if (!$is_first)
      {
        $query .= ',';
      }
      else
      {
        $is_first = false;
      }
      
      if ($value === '')
      {
        $query .= 'NULL';
      }
      else
      {
        $query .= "'".$value."'";
      }
    }
    $query .= ')';
    
    pwg_query($query);
  }
}

/**
 * Updates one line in a table.
 *
 * @param string $tablename
 * @param array $datas
 * @param array $where
 * @param int $flags - if MASS_UPDATES_SKIP_EMPTY, empty values do not overwrite existing ones
 */
function single_update($tablename, $datas, $where, $flags=0)
{
  if (count($datas) == 0)
  {
    return;
  }

  $is_first = true;

  $query = '
UPDATE '.$tablename.'
  SET ';

  foreach ($datas as $key => $value)
  {
    $separator = $is_first ? '' : ",\n    ";

    if (isset($value) and $value !== '')
    {
      $query.= $separator.$key.' = \''.$value.'\'';
    }
    else
    {
      if ($flags & MASS_UPDATES_SKIP_EMPTY)
      {
        continue; // next field
      }
      $query.= "$separator$key = NULL";
    }
    $is_first = false;
  }

  if (!$is_first)
  {// only if one field at least updated
    $is_first = true;

    $query.= '
  WHERE ';

    foreach ($where as $key => $value)
    {
      if (!$is_first)
      {
        $query.= ' AND ';
      }
      if (isset($value))
      {
        $query.= $key.' = \''.$value.'\'';
      }
      else
      {
        $query.= $key.' IS NULL';
      }
      $is_first = false;
    }

    pwg_query($query);
  }
}
