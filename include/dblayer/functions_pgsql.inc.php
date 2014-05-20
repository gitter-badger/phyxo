<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire           http://phyxo.nikrou.net/ |
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

define('REQUIRED_PGSQL_VERSION', '8.0');
define('DB_ENGINE', 'PostgreSQL');

define('DB_REGEX_OPERATOR', '~');
define('DB_RANDOM_FUNCTION', 'RANDOM');

/**
 *
 * simple functions
 *
 */

function pwg_db_connect($host, $user, $password, $database) {
    global $pwg_db_link;

    $connection_string = '';
    if (strpos($host,':') !== false)  {
        list($host, $port) = explode(':', $host);
    }
    $connection_string = sprintf('host=%s', $host);
    if (!empty($port)) {
        $connection_string .= sprintf(' port=%d', $port);
    }
    $connection_string .= sprintf(' user=%s password=%s dbname=%s', $user, $password, $database);    
    $pwg_db_link = pg_connect($connection_string) or my_error('pg_connect', true);  
}

function pwg_db_check_charset() {
    return true;
}

function pwg_get_db_version()  {
    list($pg_version) = pwg_db_fetch_row(pwg_query('SHOW SERVER_VERSION;'));
    
    return $pg_version;
}

function pwg_db_check_version() {
    $current_pgsql = pwg_get_db_version();
    if (version_compare($current_pgsql, REQUIRED_PGSQL_VERSION, '<')) {
        fatal_error(
            sprintf(
                'your PostgreSQL version is too old, you have "%s" and you need at least "%s"',
                $current_pgsql,
                REQUIRED_PGSQL_VERSION
            )
        );
    }
}

function pwg_query($query) {
    global $conf,$page,$debug,$t2,$pwg_db_link;

    $replace_pattern = '`REPLACE INTO\s(\S*)\s*([^)]*\))\s*VALUES\(([^,]*),(.*)\)\s*`mi';  

    $start = microtime(true);    
    $result = pg_query($pwg_db_link, $query) or die($query."\n<br>".pg_last_error());
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
        if ( $result!=null and preg_match('/\s*SELECT\s+/i',$query)) {
            $output.= "\n".'(num rows        : ';
            $output.= pwg_db_num_rows($result).' )';
        } elseif ( $result!=null and preg_match('/\s*INSERT|UPDATE|REPLACE|DELETE\s+/i',$query)) {
            $output.= "\n".'(affected rows   : ';
            $output.= pwg_db_changes($result).' )';
        }
        $output.= "</pre>\n";
        
        $debug .= $output;
    }
    
    return $result;
}

function pwg_db_nextval($column, $table) {
    $query = '
SELECT nextval(\''.$table.'_'.$column.'_seq\')';
    list($next) = pwg_db_fetch_row(pwg_query($query));
    
    return $next;
}

/**
 *
 * complex functions
 *
 */

function pwg_db_changes($result) {
    return pg_affected_rows($result);
}

function pwg_db_num_rows($result) {
    return pg_num_rows($result);
}

function pwg_db_fetch_assoc($result) {
    return pg_fetch_assoc($result);
}

function pwg_db_fetch_array($result) {
    return pg_fetch_array($result);
}

function pwg_db_fetch_row($result) {
    return pg_fetch_row($result);
}

function pwg_db_fetch_object($result) {
    return pg_fetch_object($result);
}

function pwg_db_free_result($result) {
    return pg_free_result($result);
}

function pwg_db_real_escape_string($s) {
    return pg_escape_string($s);
}

function pwg_db_insert_id($table=null, $column='id') {
    $sequence = sprintf('%s_%s_seq', strtolower($table), $column);
    $query = 'SELECT CURRVAL(\''.$sequence.'\');';

    list($id) = pwg_db_fetch_row(pwg_query($query));
    
    return $id;
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
    if (count($datas) == 0)
        return;

    if (count($datas) < 10) {
        foreach ($datas as $data) {
            $query = '
UPDATE '.$tablename.'
  SET ';
            $is_first = true;
            foreach ($dbfields['update'] as $key) {
                $separator = $is_first ? '' : ",\n    ";
                
                if (isset($data[$key]) and $data[$key] != '') {
                    $query.= $separator.$key.' = \''.$data[$key].'\'';
                } else {
                    if ($flags & MASS_UPDATES_SKIP_EMPTY )
                        continue; // next field
                    $query.= "$separator$key = NULL";
                }
                $is_first = false;
            }
            if (!$is_first) {// only if one field at least updated
                $query.= '
  WHERE ';
                $is_first = true;
                foreach ($dbfields['primary'] as $key) {
                    if (!$is_first) {
                        $query.= ' AND ';
                    }
                    if (isset($data[$key])) {
                        $query.= $key.' = \''.$data[$key].'\'';
                    } else {
                        $query.= $key.' IS NULL';
                    }
                    $is_first = false;
                }
                pwg_query($query);
            }
        } // foreach update
    } else { // if mysql_ver or count<X
        $all_fields = array_merge($dbfields['primary'], $dbfields['update']);
        $temporary_tablename = $tablename.'_'.micro_seconds();
        $query = '
CREATE TABLE '.$temporary_tablename.' 
  AS SELECT * FROM '.$tablename.' WHERE 1=2';
        
        pwg_query($query);
        mass_inserts($temporary_tablename, $all_fields, $datas);
        if ( $flags & MASS_UPDATES_SKIP_EMPTY )
            $func_set = create_function('$s, $t', 'return "$s = IFNULL(t2.$s, '.$tablename.'.$s)";');
        else
            $func_set = create_function('$s', 'return "$s = t2.$s";');

        // update of images table by joining with temporary table
        $query = '
UPDATE '.$tablename.'
  SET '.
      implode(
        "\n    , ",
        array_map($func_set, $dbfields['update'])
        ).'
FROM '.$temporary_tablename.' AS t2
  WHERE '.
      implode(
        "\n    AND ",
        array_map(
          create_function('$s, $t', 'return "'.$tablename.'.$s = t2.$s";'),
          $dbfields['primary']
          )
        );
        pwg_query($query);
        $query = '
DROP TABLE '.$temporary_tablename;
        pwg_query($query);
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
                $query = '
INSERT INTO '.$table_name.'
  ('.implode(',', $dbfields).')
  VALUES';
                $first = false;
            } else {
                $query .= '
  , ';
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
    $query = 'SELECT tablename FROM pg_tables 
WHERE tablename like \''.$prefixeTable.'%\'';
    
    $all_tables = array_from_query($query, 'tablename');

    // Optimize all tables
    foreach ($all_tables as $table) {
        $query = 'VACUUM FULL '.$table;
        pwg_query($query);
    }
    $page['infos'][] = l10n('Optimizations completed');
}

function pwg_db_concat($array) {
    $string = implode($array, ',');

    return 'ARRAY_TO_STRING(ARRAY['.$string.'])';
}

function pwg_db_concat_ws($array, $separator) {
    $string = implode($array, ',');

    return 'ARRAY_TO_STRING(ARRAY['.$string.'],\''.$separator.'\')';
}

function pwg_db_cast_to_text($string) {
    return 'CAST('.$string.' AS TEXT)';
}

/**
 * returns an array containing the possible values of an enum field
 *
 * @param string tablename
 * @param string fieldname
 */
function get_enums($table, $field) {
    $typname = preg_replace('/'.$GLOBALS['prefixeTable'].'/', '', $table); 
    $typname .= '_' . $field;

    $query = 'SELECT 
enumlabel FROM pg_enum JOIN pg_type
  ON pg_enum.enumtypid=pg_type.oid 
  WHERE typname=\''.$typname.'\'
';
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        $options[] = $row['enumlabel'];
    }
    
    return $options;
}

// get_boolean transforms a string to a boolean value. If the string is
// "false" (case insensitive), then the boolean value false is returned. In
// any other case, true is returned.
function get_boolean($string) {
    $boolean = true;
    if ('f' == $string || 'false' == $string) {
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
    if (!empty($var) && ($var == 't')) {
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
        $date = '\''.$date.'\'::date';
    }

    return '('.$date.' - \''.$period.' DAY\'::interval)::date';
}

function pwg_db_get_recent_period($period, $date='CURRENT_DATE') {
    $query = 'select '.pwg_db_get_recent_period_expression($period, $date);
    list($d) = pwg_db_fetch_row(pwg_query($query));

    return $d;
}

function pwg_db_date_to_ts($date) {
    return 'EXTRACT(EPOCH FROM '.$date.')';
}

function pwg_db_get_date_YYYYMM($date) {
    return 'TO_CHAR('.$date.', \'YYYYMM\')';
}

function pwg_db_get_date_MMDD($date) {
    return 'TO_CHAR('.$date.', \'MMDD\')';
}

function pwg_db_get_hour($date) {
  return 'EXTRACT(HOUR FROM '.$date.')';
}

function pwg_db_get_year($date) {
    return 'EXTRACT(YEAR FROM '.$date.')';
}

function pwg_db_get_month($date) {
    return 'EXTRACT(MONTH FROM '.$date.')';
}

function pwg_db_get_week($date, $mode=null) {
    return 'EXTRACT(WEEK FROM '.$date.')';
}

function pwg_db_get_dayofmonth($date) {
    return 'EXTRACT(DAY FROM '.$date.')';
}

function pwg_db_get_dayofweek($date) {
    return 'EXTRACT(DOW FROM '.$date.')::INTEGER - 1';
}

function pwg_db_get_weekday($date) {
    return 'EXTRACT(ISODOW FROM '.$date.')::INTEGER - 1';
}

// my_error returns (or send to standard output) the message concerning the
// error occured for the last mysql query.
function my_error($header, $die) {
    $error = '[pgsql error]'.pg_last_error()."\n";
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
            while ($row = pg_fetch_assoc($result)) {
                $data[ $row[$key_name] ] = $row[$value_name];
            }
        } else {
            while ($row = pg_fetch_assoc($result)) {
                $data[ $row[$key_name] ] = $row;
            }
        }
    } else {
        if (isset($value_name)) {
            while ($row = pg_fetch_assoc($result)) {
                $data[] = $row[$value_name];
            }
        } else {
            while ($row = pg_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
    }

    return $data;
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

function pwg_db_close() {
    global $pwg_db_link;
    
    return pg_close($pwg_db_link);
}

/* transaction functions */
function pwg_db_start_transaction() {
    pwg_query('BEGIN');
}

function pwg_db_commit() {
    pwg_query('COMMIT');
}

function pwg_db_rollback() {
    pwg_query('ROLLBACK');
}

function pwg_db_write_lock($table) {
    pwg_query('BEGIN');
    pwg_query('LOCK TABLE '.$table.' IN EXCLUSIVE MODE');
}

function pwg_db_unlock() {
    pwg_query('END');
}

function pwg_db_group_concat($field) {
    return sprintf('ARRAY_TO_STRING(ARRAY_AGG(%s),\',\')', $field);
}

function pwg_db_full_text_search($fields, $values) {
    return sprintf(
        'to_tsvector(%s) @@ to_tsquery(\'%s\')', 
        implode(' || \' \' || ', $fields), 
        pwg_db_real_escape_string(implode(' | ', $values))
    );
}