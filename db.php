<?php
/*
 *  Incentive Sales DB Class 
 *
 */

define( 'OBJECT', 'OBJECT', true );
define( 'OBJECT_K', 'OBJECT_K' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'ARRAY_N', 'ARRAY_N' );

/* Database Access Abstraction Object */
class isdb {

	/* Amount of queries made */
	var $num_queries = 0;
	/* Count of rows returned by previous query */
	var $num_rows = 0;
	/* Count of affected rows by previous query */
	var $rows_affected = 0;
	/* The ID generated for an AUTO_INCREMENT column by the previous query */
	var $insert_id = 0;
	/* Saved result of the last query made, @access private, @var array */
	var $last_query;
	/* Results of the last query made, @access private, @var array|null */
	var $last_result;
	/* Saved info on the table column, @access private, @var array */
	var $col_info;
	/* Saved queries that were executed, @access private, @var array */
	var $queries;
	/* Whether the database queries are ready to start executing, @access private, @var bool */
	var $ready = false;
	/* List of tables, @access private, @var array */
	var $tables = array( 'incmedia_client', 'incmedia_staff', 'incmedia_product', 'incmedia_followups', 'incmedia_address',
		'incmedia_stateAus', 'incmedia_cusAddrRel', 'incmedia_contract' );

	/* Staff table, @access public, @var string */
	var $im_st = 'incmedia_staff';
	/* Products table, @access public, @var string */
	var $incmedia_product;
	/* Followups table, @access public, @var string */
	var $incmedia_followups;
	/* Address table, @access public, @var string */
	var $incmedia_address;
	/* Clients table, @access public, @var string */
	var $incmedia_client;
	/* States Australia table, @access public, @var string */
	var $incmedia_stateAus;
	/* Contracts table, @access public, @var string */
	var $incmedia_contract;
	/* Customer - Address table, @access public, @var string */
	var $incmedia_cusAddrRel;
	
	

	/* Format specifiers for DB columns. Columns not listed here default to %s. Initialized during WP load. Keys are column names, values are format types: 'ID' => '%d'
	  @see prepare(), @see insert(), @see update(), @see wp_set_wpdb_vars(), @access public, @var array */
	var $field_types = array();
	/* Whether to use mysql_real_escape_string, @access public, @var bool */
	var $real_escape = true;
	/* Database Username, @access private, @var string */
	var $dbuser;
	/* A textual description of the last query/get_row/get_var call, @access public, @var string */
	var $func_call;
	/* Whether MySQL is used as the database engine, @access public, @var bool */
	public $is_mysql = null;
	
	
	/* Connects to the database server and selects a database */
	function __construct( $dbuser, $dbpassword, $dbname, $dbhost ) {
		register_shutdown_function( array( &$this, '__destruct' ) );

		$this->dbuser = $dbuser;
		$this->dbpassword = $dbpassword;
		$this->dbname = $dbname;
		$this->dbhost = $dbhost;

		$this->db_connect();
	}
	
	/* destructor and will run when database object is destroyed, @see wpdb::__construct(), return bool true */
	
	function __destruct() {
		return true;
	}
		
	
	/* Selects a database using the current database connection. */
	
	function select( $db, $dbh = null ) {
		if ( is_null($dbh) )
			$dbh = $this->dbh;

		if ( !mysqli_select_db( $dbh, $db ) ) {  //@ en mysqli function
			$this->ready = false;
			echo "Can&#8217;t select database";
			
			return;
		}
	}
	
	/* Weak escape, using addslashes() */
	
	function _weak_escape( $string ) {
		return addslashes( $string );
	}
	
	/* Real escape, using mysql_real_escape_string() or addslashes() */
	
	function _real_escape( $string ) {
		if ( $this->dbh && $this->real_escape )
			return mysqli_real_escape_string( $this->dbh, $string );
		else
			return addslashes( $string );
	}
	
	
	/* Escapes content for insertion into the database using addslashes(), for security. */
	
	function escape( $data ) {
		if ( is_array( $data ) ) {
			foreach ( (array) $data as $k => $v ) {
				if ( is_array( $v ) )
					$data[$k] = $this->escape( $v );
				else
					$data[$k] = $this->_weak_escape( $v );
			}
		} else {
			$data = $this->_weak_escape( $data );
		}

		return $data;
	}
	
	/* Escapes content by reference for insertion into the database, for security */
	
	function escape_by_ref( &$string ) {
		$string = $this->_real_escape( $string );
	}
	
	
	/* Prepares a SQL query for safe execution. Uses sprintf()-like syntax. directives: %d (integer), %f (float), %s (string),  %% (literal percentage sign - no argument needed)*/
	
	function prepare( $query = null ) { // ( $query, *$args )
		if ( is_null( $query ) )
			return;

		$args = func_get_args();
		array_shift( $args );
		// If args were passed as an array (as in vsprintf), move them up
		if ( isset( $args[0] ) && is_array($args[0]) )
			$args = $args[0];
		$query = str_replace( "'%s'", '%s', $query ); // in case someone mistakenly already singlequoted it
		$query = str_replace( '"%s"', '%s', $query ); // doublequote unquoting
		$query = preg_replace( '|(?<!%)%s|', "'%s'", $query ); // quote the strings, avoiding escaped strings like %%s
		array_walk( $args, array( &$this, 'escape_by_ref' ) );
		return @vsprintf( $query, $args );
	}
	
	
	
	/* Kill cached query results. */
	
	function flush() {
		$this->last_result = array();
		$this->col_info    = null;
		$this->last_query  = null;
	}
	
	/* Connect to and select database */
	
	function db_connect() {

		$this->is_mysql = true;

		$this->dbh = mysqli_connect( $this->dbhost, $this->dbuser, $this->dbpassword );  //@ en mysqli function

		if ( !$this->dbh ) {
		
			die('Could not connect: ' . mysql_error());

			return;
		}

		$this->ready = true;

		$this->select( $this->dbname, $this->dbh ); 	
	}
	
	
	/* Perform a MySQL database query, using current database connection. */
	
	function query( $query ) {
		if ( ! $this->ready )
			return false;

		$return_val = 0;
		$this->flush();

		// Keep track of the last query for debug..
		$this->last_query = $query;  

		$this->result = mysqli_query( $this->dbh, $query  );   //@ en mysqli function   
		$this->num_queries++;   

		if ( preg_match( '/^\s*(create|alter|truncate|drop) /i', $query ) ) {
			$return_val = $this->result;
		} elseif ( preg_match( '/^\s*(insert|delete|update|replace) /i', $query ) ) {
			$this->rows_affected = 	( $this->dbh ); 
			// Take note of the insert_id
			if ( preg_match( '/^\s*(insert|replace) /i', $query ) ) {
				$this->insert_id = mysqli_insert_id($this->dbh);
			}
			// Return number of rows affected
			$return_val = $this->rows_affected;
		} else {
			$i = 0;
			while ( $i < mysqli_field_count( $this->dbh ) ) {   //@ en mysqli function, the argument was changed from '$this->result' to '$this->dbh'
				$this->col_info[$i] = mysqli_fetch_field( $this->result );   //@ en mysqli function
				$i++;
			}
			$num_rows = 0;
			while ( $row = mysqli_fetch_object( $this->result ) ) {    //@ en mysqli function
				$this->last_result[$num_rows] = $row;
				$num_rows++;
			}

			mysqli_free_result( $this->result );   //@ en mysqli function

			// Log number of rows the query returned
			// and return number of rows selected
			$this->num_rows = $num_rows;
			$return_val     = $num_rows;
		}

		return $return_val;
	}
	
	/* Insert a row into a table. */
	
	function insert( $table, $data, $format = null ) {
		return $this->_insert_replace_helper( $table, $data, $format, 'INSERT' );
	}
	
	/* Replace a row into a table. */
	
	function replace( $table, $data, $format = null ) {
		return $this->_insert_replace_helper( $table, $data, $format, 'REPLACE' );
	}
	
	/* Helper function for insert and replace. */
	
	function _insert_replace_helper( $table, $data, $format = null, $type = 'INSERT' ) {
		if ( ! in_array( strtoupper( $type ), array( 'REPLACE', 'INSERT' ) ) )
			return false;
		$formats = $format = (array) $format;
		$fields = array_keys( $data );
		$formatted_fields = array();
		foreach ( $fields as $field ) {
			if ( !empty( $format ) )
				$form = ( $form = array_shift( $formats ) ) ? $form : $format[0];
			elseif ( isset( $this->field_types[$field] ) )
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$formatted_fields[] = $form; /* POSSIBLE ELIMINATION OF FORMATTED FIELDS */
		}
		$sql = "{$type} INTO `$table` (`" . implode( '`,`', $fields ) . "`) VALUES ('" . implode( "','", $formatted_fields ) . "')";
		return $this->query( $this->prepare( $sql, $data ) );
	}
	
	/* Update a row in the table */
	
	function update( $table, $data, $where, $format = null, $where_format = null ) {
		if ( ! is_array( $data ) || ! is_array( $where ) )
			return false;

		$formats = $format = (array) $format;
		$bits = $wheres = array();
		foreach ( (array) array_keys( $data ) as $field ) {
			if ( !empty( $format ) )
				$form = ( $form = array_shift( $formats ) ) ? $form : $format[0];
			elseif ( isset($this->field_types[$field]) )
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$bits[] = "`$field` = {$form}";
		}

		$where_formats = $where_format = (array) $where_format;
		foreach ( (array) array_keys( $where ) as $field ) {
			if ( !empty( $where_format ) )
				$form = ( $form = array_shift( $where_formats ) ) ? $form : $where_format[0];
			elseif ( isset( $this->field_types[$field] ) )
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$wheres[] = "`$field` = {$form}";
		}

		$sql = "UPDATE `$table` SET " . implode( ', ', $bits ) . ' WHERE ' . implode( ' AND ', $wheres );
		return $this->query( $this->prepare( $sql, array_merge( array_values( $data ), array_values( $where ) ) ) );
	}
	
	/* Retrieve one variable from the database. */
	
	function get_var( $query = null, $x = 0, $y = 0 ) {
		$this->func_call = "\$db->get_var(\"$query\", $x, $y)";
		if ( $query )
			$this->query( $query );

		// Extract var out of cached results based x,y vals
		if ( !empty( $this->last_result[$y] ) ) {
			$values = array_values( get_object_vars( $this->last_result[$y] ) );
		}

		// If there is a value return it else return null
		return ( isset( $values[$x] ) && $values[$x] !== '' ) ? $values[$x] : null;
	}
	
	/* Retrieve one row from the database. */
	
	function get_row( $query = null, $output = OBJECT, $y = 0 ) {
		$this->func_call = "\$db->get_row(\"$query\",$output,$y)";
		if ( $query )
			$this->query( $query );
		else
			return null;

		if ( !isset( $this->last_result[$y] ) )
			return null;

		if ( $output == OBJECT ) {
			return $this->last_result[$y] ? $this->last_result[$y] : null;
		} elseif ( $output == ARRAY_A ) {
			return $this->last_result[$y] ? get_object_vars( $this->last_result[$y] ) : null;
		} elseif ( $output == ARRAY_N ) {
			return $this->last_result[$y] ? array_values( get_object_vars( $this->last_result[$y] ) ) : null;
		} else {
			echo "\$db->get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N";
		}
	}
	
	/* Retrieve one column from the database. */
	
	function get_col( $query = null , $x = 0 ) {
		if ( $query )
			$this->query( $query );

		$new_array = array();
		// Extract the column values
		for ( $i = 0, $j = count( $this->last_result ); $i < $j; $i++ ) {
			$new_array[$i] = $this->get_var( null, $x, $i );
		}
		return $new_array;
	}
	
	/* Retrieve an entire SQL result set from the database (i.e., many rows) */
	
	function get_results( $query = null, $output = OBJECT ) {
		$this->func_call = "\$db->get_results(\"$query\", $output)";

		if ( $query )
			$this->query( $query );
		else
			return null;

		$new_array = array();
		if ( $output == OBJECT ) {
			// Return an integer-keyed array of row objects
			return $this->last_result;
		} elseif ( $output == OBJECT_K ) {
			// Return an array of row objects with keys from column 1
			// (Duplicates are discarded)
			foreach ( $this->last_result as $row ) {
				$var_by_ref = get_object_vars( $row );
				$key = array_shift( $var_by_ref );
				if ( ! isset( $new_array[ $key ] ) )
					$new_array[ $key ] = $row;
			}
			return $new_array;
		} elseif ( $output == ARRAY_A || $output == ARRAY_N ) {
			// Return an integer-keyed array of...
			if ( $this->last_result ) {
				foreach( (array) $this->last_result as $row ) {
					if ( $output == ARRAY_N ) {
						// ...integer-keyed row arrays
						$new_array[] = array_values( get_object_vars( $row ) );
					} else {
						// ...column name-keyed row arrays
						$new_array[] = get_object_vars( $row );
					}
				}
			}
			return $new_array;
		}
		return null;
	}
	
	/* Retrieve column metadata from the last query. $info_type: name, table, def, max_length, not_null, primary_key, multiple_key, unique_key, numeric, blob, type, unsigned, zerofill
		$col_offset can be 0: col name. 1: which table the col's in. 2: col's max length. 3: if the col is numeric. 4: col's type */
	
	function get_col_info( $info_type = 'name', $col_offset = -1 ) {
		if ( $this->col_info ) {
			if ( $col_offset == -1 ) {
				$i = 0;
				$new_array = array();
				foreach( (array) $this->col_info as $col ) {
					$new_array[$i] = $col->{$info_type};
					$i++;
				}
				return $new_array;
			} else {
				return $this->col_info[$col_offset]->{$info_type};
			}
		}
	}
	
}
?>
