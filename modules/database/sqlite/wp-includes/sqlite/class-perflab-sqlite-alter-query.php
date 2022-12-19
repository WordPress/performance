<?php
/**
 * Rewrite the ALTER queries for SQLite to execute.
 *
 * @package performance-lab
 * @since 1.8.0
 */

/**
 * Class to handle the ALTER query.
 */
class Perflab_SQLite_Alter_Query {

	/**
	 * Variable to store the rewritten query string.
	 *
	 * @var string
	 */
	public $_query = null;

	/**
	 * Function to split the query string to the tokens and call appropriate functions.
	 *
	 * @param string $query      The query string.
	 * @param string $query_type The query type.
	 * @return bool|string
	 */
	public function rewrite_query( $query, $query_type ) {
		if ( stripos( $query, $query_type ) === false ) {
			return false;
		}
		$query   = str_replace( '`', '', $query );
		$pattern = '/^\\s*(ALTER\\s*TABLE)\\s*(\\w+)?\\s*/ims';
		$matched = preg_match( $pattern, $query, $match );

		if ( ! $matched ) {
			$this->_query = 'SELECT 1=1';
			return $this->_query;
		}

		$tmp_query                = array();
		$re_command               = '';
		$command                  = str_ireplace( $match[0], '', $query );
		$tmp_tokens['query_type'] = trim( $match[1] );
		$tmp_tokens['table_name'] = trim( $match[2] );
		$command_array            = explode( ',', $command );

		$single_command = array_shift( $command_array );
		if ( ! empty( $command_array ) ) {
			$re_command  = "ALTER TABLE {$tmp_tokens['table_name']} ";
			$re_command .= implode( ',', $command_array );
		}
		$command_tokens = $this->command_tokenizer( $single_command );
		if ( empty( $command_tokens ) ) {
			$this->_query = 'SELECT 1=1';
			return $this->_query;
		}
		$tokens       = array_merge( $tmp_tokens, $command_tokens );
		$command_name = strtolower( $tokens['command'] );
		switch ( $command_name ) {
			case 'add column':
			case 'rename to':
			case 'add index':
			case 'drop index':
				$tmp_query = $this->handle_single_command( $tokens );
				break;

			case 'add primary key':
				$tmp_query = $this->handle_add_primary_key( $tokens );
				break;

			case 'drop primary key':
				$tmp_query = $this->handle_drop_primary_key( $tokens );
				break;

			case 'modify column':
				$tmp_query = $this->handle_modify_command( $tokens );
				break;

			case 'change column':
				$tmp_query = $this->handle_change_command( $tokens );
				break;

			case 'alter column':
				$tmp_query = $this->handle_alter_command( $tokens );
				break;

			default:
				break;
		}
		if ( ! is_array( $tmp_query ) ) {
			$this->_query[] = $tmp_query;
		} else {
			$this->_query = $tmp_query;
		}
		if ( '' !== $re_command ) {
			$this->_query = array_merge( $this->_query, array( 'recursion' => $re_command ) );
		}

		return $this->_query;
	}

	/**
	 * Function to analyze ALTER TABLE command and sets the data to an array.
	 *
	 * @param string $command The command string.
	 *
	 * @return array
	 * @access private
	 */
	private function command_tokenizer( $command ) {
		$tokens  = array();
		$pattern = '/^(ADD|DROP|RENAME|MODIFY|CHANGE|ALTER)\\s*(\\w+)?\\s*(\\w+(\(.+\)|))?\\s*/ims';
		if ( preg_match( $pattern, $command, $match ) ) {
			$the_rest = str_ireplace( $match[0], '', $command );
			$match_1  = trim( $match[1] );
			$match_2  = trim( $match[2] );
			$match_3  = isset( $match[3] ) ? trim( $match[3] ) : '';
			switch ( strtolower( $match_1 ) ) {
				case 'add':
					if ( in_array( strtolower( $match_2 ), array( 'fulltext', 'constraint', 'foreign' ), true ) ) {
						break;
					}

					if ( stripos( 'column', $match_2 ) !== false ) {
						$tokens['command']     = $match_1 . ' ' . $match_2;
						$tokens['column_name'] = $match_3;
						$tokens['column_def']  = trim( $the_rest );
					} elseif ( stripos( 'primary', $match_2 ) !== false ) {
						$tokens['command']     = $match_1 . ' ' . $match_2 . ' ' . $match_3;
						$tokens['column_name'] = $the_rest;
					} elseif ( stripos( 'unique', $match_2 ) !== false ) {
						list($index_name, $col_name) = preg_split( '/[\(\)]/s', trim( $the_rest ), -1, PREG_SPLIT_DELIM_CAPTURE );
						$tokens['unique']            = true;
						$tokens['command']           = $match_1 . ' ' . $match_3;
						$tokens['index_name']        = trim( $index_name );
						$tokens['column_name']       = '(' . trim( $col_name ) . ')';
					} elseif ( in_array( strtolower( $match_2 ), array( 'index', 'key' ), true ) ) {
						$tokens['command']    = $match_1 . ' ' . $match_2;
						$tokens['index_name'] = $match_3;
						if ( '' === $match_3 ) {
							$tokens['index_name'] = str_replace( array( '(', ')' ), '', $the_rest );
						}
						$tokens['column_name'] = trim( $the_rest );
					} else {
						$tokens['command']     = $match_1 . ' COLUMN';
						$tokens['column_name'] = $match_2;
						$tokens['column_def']  = $match_3 . ' ' . $the_rest;
					}
					break;

				case 'drop':
					if ( stripos( 'column', $match_2 ) !== false ) {
						$tokens['command']     = $match_1 . ' ' . $match_2;
						$tokens['column_name'] = trim( $match_3 );
					} elseif ( stripos( 'primary', $match_2 ) !== false ) {
						$tokens['command'] = $match_1 . ' ' . $match_2 . ' ' . $match_3;
					} elseif ( in_array( strtolower( $match_2 ), array( 'index', 'key' ), true ) ) {
						$tokens['command']    = $match_1 . ' ' . $match_2;
						$tokens['index_name'] = $match_3;
					} elseif ( stripos( 'primary', $match_2 ) !== false ) {
						$tokens['command'] = $match_1 . ' ' . $match_2 . ' ' . $match_3;
					} else {
						$tokens['command']     = $match_1 . ' COLUMN';
						$tokens['column_name'] = $match_2;
					}
					break;

				case 'rename':
					if ( stripos( 'to', $match_2 ) !== false ) {
						$tokens['command']     = $match_1 . ' ' . $match_2;
						$tokens['column_name'] = $match_3;
					} else {
						$tokens['command']     = $match_1 . ' TO';
						$tokens['column_name'] = $match_2;
					}
					break;

				case 'modify':
					if ( stripos( 'column', $match_2 ) !== false ) {
						$tokens['command']     = $match_1 . ' ' . $match_2;
						$tokens['column_name'] = $match_3;
						$tokens['column_def']  = trim( $the_rest );
					} else {
						$tokens['command']     = $match_1 . ' COLUMN';
						$tokens['column_name'] = $match_2;
						$tokens['column_def']  = $match_3 . ' ' . trim( $the_rest );
					}
					break;

				case 'change':
					$the_rest = trim( $the_rest );
					if ( stripos( 'column', $match_2 ) !== false ) {
						$tokens['command']    = $match_1 . ' ' . $match_2;
						$tokens['old_column'] = $match_3;
						list($new_col)        = explode( ' ', $the_rest );
						$tmp_col              = preg_replace( '/\(.+?\)/im', '', $new_col );
						if ( array_key_exists( strtolower( $tmp_col ), $this->array_types ) ) {
							$tokens['column_def'] = $the_rest;
						} else {
							$tokens['new_column'] = $new_col;
							$col_def              = str_replace( $new_col, '', $the_rest );
							$tokens['column_def'] = trim( $col_def );
						}
					} else {
						$tokens['command']    = $match_1 . ' column';
						$tokens['old_column'] = $match_2;
						$tmp_col              = preg_replace( '/\(.+?\)/im', '', $match_3 );
						if ( array_key_exists( strtolower( $tmp_col ), $this->array_types ) ) {
							$tokens['column_def'] = $match_3 . ' ' . $the_rest;
						} else {
							$tokens['new_column'] = $match_3;
							$tokens['column_def'] = $the_rest;
						}
					}
					break;

				case 'alter':
					$tokens['default_command'] = 'DROP DEFAULT';
					if ( stripos( 'column', $match_2 ) !== false ) {
						$tokens['command']     = $match_1 . ' ' . $match_2;
						$tokens['column_name'] = $match_3;
						list($set_or_drop)     = explode( ' ', $the_rest );
						if ( stripos( 'set', $set_or_drop ) !== false ) {
							$tokens['default_command'] = 'SET DEFAULT';
							$default_value             = str_ireplace( 'set default', '', $the_rest );
							$tokens['default_value']   = trim( $default_value );
						}
					} else {
						$tokens['command']     = $match_1 . ' COLUMN';
						$tokens['column_name'] = $match_2;
						if ( stripos( 'set', $match_3 ) !== false ) {
							$tokens['default_command'] = 'SET DEFAULT';
							$default_value             = str_ireplace( 'default', '', $the_rest );
							$tokens['default_value']   = trim( $default_value );
						}
					}
					break;

				default:
					break;
			}
		}
		return $tokens;
	}

	/**
	 * Function to handle single command.
	 *
	 * @access private
	 *
	 * @param array $queries An array of string queries.
	 *
	 * @return string
	 */
	private function handle_single_command( $queries ) {
		$tokenized_query = $queries;

		// Command is "add column".
		if ( stripos( $tokenized_query['command'], 'add column' ) !== false ) {
			$column_def = $this->convert_field_types( $tokenized_query['column_name'], $tokenized_query['column_def'] );
			return "ALTER TABLE {$tokenized_query['table_name']} ADD COLUMN {$tokenized_query['column_name']} $column_def";
		}

		// Command is "rename".
		if ( stripos( $tokenized_query['command'], 'rename' ) !== false ) {
			return "ALTER TABLE {$tokenized_query['table_name']} RENAME TO {$tokenized_query['column_name']}";
		}

		// Command is "add index".
		if ( stripos( $tokenized_query['command'], 'add index' ) !== false ) {
			$unique = isset( $tokenized_query['unique'] ) ? 'UNIQUE' : '';
			return "CREATE $unique INDEX IF NOT EXISTS {$tokenized_query['index_name']} ON {$tokenized_query['table_name']} {$tokenized_query['column_name']}";
		}

		// Command is "drop index".
		if ( stripos( $tokenized_query['command'], 'drop index' ) !== false ) {
			return "DROP INDEX IF EXISTS {$tokenized_query['index_name']}";
		}

		// Fallback.
		return 'SELECT 1=1';
	}

	/**
	 * Function to handle ADD PRIMARY KEY.
	 *
	 * @access private
	 *
	 * @param array $queries An array of string queries.
	 *
	 * @return array
	 */
	private function handle_add_primary_key( $queries ) {
		$tokenized_query = $queries;
		$tbl_name        = $tokenized_query['table_name'];
		$temp_table      = 'temp_' . $tokenized_query['table_name'];
		$query_obj       = ( new Perflab_SQLite_DB() )->get_results( "SELECT sql FROM sqlite_master WHERE tbl_name='$tbl_name'" );
		for ( $i = 0; $i < count( $query_obj ); $i++ ) {
			$index_queries[ $i ] = $query_obj[ $i ]->sql;
		}
		$table_query = ", PRIMARY KEY {$tokenized_query['column_name']}";
		$query       = array(
			$table_query,
			"INSERT INTO $temp_table SELECT * FROM {$tokenized_query['table_name']}",
			"DROP TABLE IF EXISTS {$tokenized_query['table_name']}",
			"ALTER TABLE $temp_table RENAME TO {$tokenized_query['table_name']}",
		);

		foreach ( $index_queries as $index ) {
			$query[] = $index;
		}

		return $query;
	}

	/**
	 * Function to handle DROP PRIMARY KEY.
	 *
	 * @access private
	 *
	 * @param array $queries An of string $queries.
	 *
	 * @return array
	 */
	private function handle_drop_primary_key( $queries ) {
		$tokenized_query = $queries;
		$temp_table      = 'temp_' . $tokenized_query['table_name'];
		$query_obj       = ( new Perflab_SQLite_DB() )->get_results( "SELECT sql FROM sqlite_master WHERE tbl_name='{$tokenized_query['table_name']}'" );
		for ( $i = 0; $i < count( $query_obj ); $i++ ) {
			$index_queries[ $i ] = $query_obj[ $i ]->sql;
		}
		$table_query = array_shift( $index_queries );
		$pattern1    = '/^\\s*PRIMARY\\s*KEY\\s*\(.*\)/im';
		$pattern2    = '/^\\s*.*(PRIMARY\\s*KEY\\s*(:?AUTOINCREMENT|))\\s*(?!\()/im';
		if ( preg_match( $pattern1, $table_query, $match ) ) {
			$table_query = str_replace( $match[0], '', $table_query );
		} elseif ( preg_match( $pattern2, $table_query, $match ) ) {
			$table_query = str_replace( $match[1], '', $table_query );
		}
		$table_query = str_replace( $tokenized_query['table_name'], $temp_table, $table_query );
		$query       = array(
			$table_query,
			"INSERT INTO $temp_table SELECT * FROM {$tokenized_query['table_name']}",
			"DROP TABLE IF EXISTS {$tokenized_query['table_name']}",
			"ALTER TABLE $temp_table RENAME TO {$tokenized_query['table_name']}",
		);
		foreach ( $index_queries as $index ) {
			$query[] = $index;
		}

		return $query;
	}

	/**
	 * Function to handle MODIFY COLUMN.
	 *
	 * @access private
	 *
	 * @param array $queries An array of string queries.
	 *
	 * @return string|array
	 */
	private function handle_modify_command( $queries ) {
		$tokenized_query = $queries;
		$temp_table      = 'temp_' . $tokenized_query['table_name'];
		$column_def      = $this->convert_field_types( $tokenized_query['column_name'], $tokenized_query['column_def'] );
		$query_obj       = ( new Perflab_SQLite_DB() )->get_results( "SELECT sql FROM sqlite_master WHERE tbl_name='{$tokenized_query['table_name']}'" );
		for ( $i = 0; $i < count( $query_obj ); $i++ ) {
			$index_queries[ $i ] = $query_obj[ $i ]->sql;
		}
		$create_query = array_shift( $index_queries );
		if ( stripos( $create_query, $tokenized_query['column_name'] ) === false ) {
			return 'SELECT 1=1';
		}
		$pattern = "/{$tokenized_query['column_name']}\\s*{$column_def}\\s*[,)]/i";
		if ( preg_match( $pattern, $create_query ) ) {
			return 'SELECT 1=1';
		}
		$pattern_1 = "/{$tokenized_query['table_name']}/i";
		$pattern_2 = "/\\b{$tokenized_query['column_name']}\\s*.*(?=,)/ims";
		$pattern_3 = "/\\b{$tokenized_query['column_name']}\\s*.*(?=\))/ims";

		$create_query = preg_replace( $pattern_1, $temp_table, $create_query );
		if ( preg_match( $pattern_2, $create_query ) ) {
			$create_query = preg_replace( $pattern_2, "{$tokenized_query['column_name']} {$column_def}", $create_query );
		} elseif ( preg_match( $pattern_3, $create_query ) ) {
			$create_query = preg_replace( $pattern_3, "{$tokenized_query['column_name']} {$column_def}", $create_query );
		}
		$query = array(
			$create_query,
			"INSERT INTO $temp_table SELECT * FROM {$tokenized_query['table_name']}",
			"DROP TABLE IF EXISTS {$tokenized_query['table_name']}",
			"ALTER TABLE $temp_table RENAME TO {$tokenized_query['table_name']}",
		);
		foreach ( $index_queries as $index ) {
			$query[] = $index;
		}

		return $query;
	}

	/**
	 * Function to handle CHANGE COLUMN.
	 *
	 * @access private
	 *
	 * @param array $queries An array of string queries.
	 *
	 * @return string|array
	 */
	private function handle_change_command( $queries ) {
		$col_check       = false;
		$old_fields      = '';
		$tokenized_query = $queries;
		$temp_table      = 'temp_' . $tokenized_query['table_name'];
		$column_name     = $tokenized_query['old_column'];
		if ( isset( $tokenized_query['new_column'] ) ) {
			$column_name = $tokenized_query['new_column'];
		}
		$column_def = $this->convert_field_types( $column_name, $tokenized_query['column_def'] );
		$col_obj    = ( new Perflab_SQLite_DB() )->get_results( "SHOW COLUMNS FROM {$tokenized_query['table_name']}" );
		foreach ( $col_obj as $col ) {
			if ( stripos( $col->Field, $tokenized_query['old_column'] ) !== false ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$col_check = true;
			}
			$old_fields .= $col->Field . ','; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
		if ( false === $col_check ) {
			return 'SELECT 1=1';
		}
		$old_fields = rtrim( $old_fields, ',' );
		$new_fields = str_ireplace( $tokenized_query['old_column'], $column_name, $old_fields );
		$query_obj  = ( new Perflab_SQLite_DB() )->get_results( "SELECT sql FROM sqlite_master WHERE tbl_name='{$tokenized_query['table_name']}'" );
		for ( $i = 0; $i < count( $query_obj ); $i++ ) {
			$index_queries[ $i ] = $query_obj[ $i ]->sql;
		}
		$create_query = array_shift( $index_queries );
		$create_query = preg_replace( "/{$tokenized_query['table_name']}/i", $temp_table, $create_query );

		$pattern_1 = "/\\b{$tokenized_query['old_column']}\\s*(.+?)(?=,)/ims";
		$pattern_2 = "/\\b{$tokenized_query['old_column']}\\s*(.+?)(?=\))/ims";
		if ( preg_match( $pattern_1, $create_query, $match ) ) {
			if ( stripos( trim( $match[1] ), $column_def ) !== false ) {
				return 'SELECT 1=1';
			}
			$pattern      = "/\\b{$tokenized_query['old_column']}\\s*.+?(?=,)/ims";
			$create_query = preg_replace( $pattern, "{$column_name} {$column_def}", $create_query, 1 );
		} elseif ( preg_match( $pattern_2, $create_query, $match ) ) {
			if ( stripos( trim( $match[1] ), $column_def ) !== false ) {
				return 'SELECT 1=1';
			}
			$pattern      = "/\\b{$tokenized_query['old_column']}\\s*.*(?=\))/ims";
			$create_query = preg_replace( $pattern, "{$column_name} {$column_def}", $create_query, 1 );
		}
		$query = array(
			$create_query,
			"INSERT INTO $temp_table ($new_fields) SELECT $old_fields FROM {$tokenized_query['table_name']}",
			"DROP TABLE IF EXISTS {$tokenized_query['table_name']}",
			"ALTER TABLE $temp_table RENAME TO {$tokenized_query['table_name']}",
		);
		foreach ( $index_queries as $index ) {
			$query[] = $index;
		}

		return $query;
	}

	/**
	 * Function to handle ALTER COLUMN.
	 *
	 * @access private
	 *
	 * @param array $queries An array of string queries.
	 *
	 * @return string|array
	 */
	private function handle_alter_command( $queries ) {
		$tokenized_query = $queries;
		$temp_table      = 'temp_' . $tokenized_query['table_name'];
		$def_value       = null;
		if ( isset( $tokenized_query['default_value'] ) ) {
			$def_value = $this->convert_field_types( $tokenized_query['column_name'], $tokenized_query['default_value'] );
			$def_value = 'DEFAULT ' . $def_value;
		}
		$query_obj = ( new Perflab_SQLite_DB() )->get_results( "SELECT sql FROM sqlite_master WHERE tbl_name='{$tokenized_query['table_name']}'" );
		for ( $i = 0; $i < count( $query_obj ); $i++ ) {
			$index_queries[ $i ] = $query_obj[ $i ]->sql;
		}
		$create_query = array_shift( $index_queries );
		if ( stripos( $create_query, $tokenized_query['column_name'] ) === false ) {
			return 'SELECT 1=1';
		}
		$pattern_1 = "/\\s*({$tokenized_query['column_name']})\\s*(.*)?(DEFAULT\\s*.*)[,)]/im";
		$pattern_2 = "/\\s*({$tokenized_query['column_name']})\\s*(.*)?[,)]/im";
		if ( preg_match( $pattern_1, $create_query, $match ) ) {
			$col_name        = trim( $match[1] );
			$col_def         = trim( $match[2] );
			$col_def_esc     = str_replace( array( '(', ')' ), array( '\(', '\)' ), $col_def );
			$checked_col_def = $this->convert_field_types( $col_name, $col_def );
			$old_default     = trim( $match[3] );
			$replacement     = $col_name . ' ' . $checked_col_def;
			if ( ! is_null( $def_value ) ) {
				$replacement .= ' ' . $def_value;
			}
			$pattern      = "/$col_name\\s*$col_def_esc\\s*$old_default/im";
			$create_query = preg_replace( $pattern, $replacement, $create_query );
			$create_query = str_ireplace( $tokenized_query['table_name'], $temp_table, $create_query );
		} elseif ( preg_match( $pattern_2, $create_query, $match ) ) {
			$col_name        = trim( $match[1] );
			$col_def         = trim( $match[2] );
			$col_def_esc     = str_replace( array( '(', ')' ), array( '\(', '\)' ), $col_def );
			$checked_col_def = $this->convert_field_types( $col_name, $col_def );
			$replacement     = $col_name . ' ' . $checked_col_def;
			if ( ! is_null( $def_value ) ) {
				$replacement .= ' ' . $def_value;
			}
			$pattern      = "/$col_name\\s*$col_def_esc/im";
			$create_query = preg_replace( $pattern, $replacement, $create_query );
			$create_query = str_ireplace( $tokenized_query['table_name'], $temp_table, $create_query );
		} else {
			return 'SELECT 1=1';
		}
		$query = array(
			$create_query,
			"INSERT INTO $temp_table SELECT * FROM {$tokenized_query['table_name']}",
			"DROP TABLE IF EXISTS {$tokenized_query['table_name']}",
			"ALTER TABLE $temp_table RENAME TO {$tokenized_query['table_name']}",
		);
		foreach ( $index_queries as $index ) {
			$query[] = $index;
		}

		return $query;
	}

	/**
	 * Function to change the field definition to SQLite compatible data type.
	 *
	 * @access private
	 *
	 * @param string $col_name Column name.
	 * @param string $col_def  Column definition.
	 *
	 * @return string
	 */
	private function convert_field_types( $col_name, $col_def ) {
		$array_curtime = array( 'current_timestamp', 'current_time', 'current_date' );
		$array_reptime = array( "'0000-00-00 00:00:00'", "'0000-00-00 00:00:00'", "'0000-00-00'" );
		$def_string    = str_replace( '`', '', $col_def );
		foreach ( $this->array_types as $o => $r ) {
			$pattern = "/\\b$o\\s*(\([^\)]*\)*)?\\s*/ims";
			if ( preg_match( $pattern, $def_string ) ) {
				$def_string = preg_replace( $pattern, "$r ", $def_string );
				break;
			}
		}
		$def_string = preg_replace( '/unsigned/im', '', $def_string );
		$def_string = preg_replace( '/auto_increment/im', 'PRIMARY KEY AUTOINCREMENT', $def_string );
		// When you use ALTER TABLE ADD, you can't use current_*. so we replace.
		$def_string = str_ireplace( $array_curtime, $array_reptime, $def_string );
		// This is enum.
		$pattern_enum = '/enum\((.*?)\)([^,\)]*)/ims';
		if ( preg_match( $pattern_enum, $col_def, $matches ) ) {
			$def_string = 'TEXT' . $matches[2] . ' CHECK (' . $col_name . ' IN (' . $matches[1] . '))';
		}

		return $def_string;
	}

	/**
	 * Variable to store the data definition table.
	 *
	 * @access private
	 * @var associative array
	 */
	private $array_types = array(
		'bit'        => 'INTEGER',
		'bool'       => 'INTEGER',
		'boolean'    => 'INTEGER',
		'tinyint'    => 'INTEGER',
		'smallint'   => 'INTEGER',
		'mediumint'  => 'INTEGER',
		'bigint'     => 'INTEGER',
		'integer'    => 'INTEGER',
		'int'        => 'INTEGER',
		'float'      => 'REAL',
		'double'     => 'REAL',
		'decimal'    => 'REAL',
		'dec'        => 'REAL',
		'numeric'    => 'REAL',
		'fixed'      => 'REAL',
		'datetime'   => 'TEXT',
		'date'       => 'TEXT',
		'timestamp'  => 'TEXT',
		'time'       => 'TEXT',
		'year'       => 'TEXT',
		'varchar'    => 'TEXT',
		'char'       => 'TEXT',
		'varbinary'  => 'BLOB',
		'binary'     => 'BLOB',
		'tinyblob'   => 'BLOB',
		'mediumblob' => 'BLOB',
		'longblob'   => 'BLOB',
		'blob'       => 'BLOB',
		'tinytext'   => 'TEXT',
		'mediumtext' => 'TEXT',
		'longtext'   => 'TEXT',
		'text'       => 'TEXT',
	);
}
