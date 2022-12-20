<?php
/**
 * The main PDO extension class.
 *
 * @package performance-lab
 * @since 1.8.0
 */

/**
 * This class extends PDO class and does the real work.
 *
 * It accepts a request from wpdb class, initialize PDO instance,
 * execute SQL statement, and returns the results to WordPress.
 */
class Perflab_SQLite_PDO_Engine extends PDO { // phpcs:ignore

	/**
	 * The database version.
	 *
	 * This is used here to avoid PHP warnings in the health screen.
	 *
	 * @var string
	 */
	public $client_info = '';

	/**
	 * Class variable to check if there is an error.
	 *
	 * @var boolean
	 */
	public $is_error = false;

	/**
	 * Class variable which is used for CALC_FOUND_ROW query.
	 *
	 * @var unsigned integer
	 */
	public $found_rows_result = null;

	/**
	 * Class variable used for query with ORDER BY FIELD()
	 *
	 * @var array of the object
	 */
	public $pre_ordered_results = null;

	/**
	 * Class variable to store the rewritten queries.
	 *
	 * @access private
	 *
	 * @var array
	 */
	private $rewritten_query;

	/**
	 * Class variable to have what kind of query to execute.
	 *
	 * @access private
	 *
	 * @var string
	 */
	private $query_type;

	/**
	 * Class variable to store the result of the query.
	 *
	 * @access private
	 *
	 * @var array reference to the PHP object
	 */
	private $results = null;

	/**
	 * Class variable to store the results of the query.
	 *
	 * This is for the backward compatibility.
	 *
	 * @access private
	 *
	 * @var array reference to the PHP object
	 */
	private $_results = null;

	/**
	 * Class variable to reference to the PDO instance.
	 *
	 * @access private
	 *
	 * @var PDO object
	 */
	private $pdo;

	/**
	 * Class variable to store the query string prepared to execute.
	 *
	 * @access private
	 *
	 * @var string|array
	 */
	private $prepared_query;

	/**
	 * Class variable to store the values in the query string.
	 *
	 * @access private
	 *
	 * @var array
	 */
	private $extracted_variables = array();

	/**
	 * Class variable to store the error messages.
	 *
	 * @access private
	 *
	 * @var array
	 */
	private $error_messages = array();

	/**
	 * Class variable to store the file name and function to cause error.
	 *
	 * @access private
	 *
	 * @var array
	 */
	private $errors;

	/**
	 * Class variable to store the query strings.
	 *
	 * @var array
	 */
	public $queries = array();

	/**
	 * Class variable to store the affected row id.
	 *
	 * @var unsigned integer
	 * @access private
	 */
	private $last_insert_id;

	/**
	 * Class variable to store the number of rows affected.
	 *
	 * @var unsigned integer
	 */
	private $affected_rows;

	/**
	 * Class variable to store the queried column info.
	 *
	 * @var array
	 */
	private $column_data;

	/**
	 * Variable to emulate MySQL affected row.
	 *
	 * @var integer
	 */
	private $num_rows;

	/**
	 * Return value from query().
	 *
	 * Each query has its own return value.
	 *
	 * @var mixed
	 */
	private $return_value;

	/**
	 * Variable to determine which insert query to use.
	 *
	 * Whether VALUES clause in the INSERT query can take multiple values or not
	 * depends on the version of SQLite library. We check the version and set
	 * this variable to true or false.
	 *
	 * @var boolean
	 */
	private $can_insert_multiple_rows = false;

	/**
	 * The parameter number.
	 *
	 * @var integer
	 */
	private $param_num;

	/**
	 * Variable to check if there is an active transaction.
	 *
	 * @var boolean
	 * @access protected
	 */
	protected $has_active_transaction = false;

	/**
	 * Constructor
	 *
	 * Create PDO object, set user defined functions and initialize other settings.
	 * Don't use parent::__construct() because this class does not only returns
	 * PDO instance but many others jobs.
	 *
	 * Constructor definition is changed since version 1.7.1.
	 */
	function __construct() {

		// Fixes a warning in the site-health screen.
		$this->client_info = SQLite3::version()['versionString'];

		register_shutdown_function( array( $this, '__destruct' ) );
		if ( ! is_file( FQDB ) ) {
			$this->prepare_directory();
		}
		$dsn = 'sqlite:' . FQDB;
		if ( isset( $GLOBALS['@pdo'] ) ) {
			$this->pdo = $GLOBALS['@pdo'];
			$this->init();
			return;
		}

		$locked      = false;
		$status      = 0;
		$err_message = '';
		do {
			try {
				$this->pdo = new PDO( $dsn, null, null, array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ) ); // phpcs:ignore WordPress.DB.RestrictedClasses
				new Perflab_SQLite_PDO_User_Defined_Functions( $this->pdo );
				$GLOBALS['@pdo'] = $this->pdo;
			} catch ( PDOException $ex ) {
				$status = $ex->getCode();
				if ( 5 === $status || 6 === $status ) {
					$locked = true;
				} else {
					$err_message = $ex->getMessage();
				}
			}
		} while ( $locked );

		if ( $status > 0 ) {
			$message = sprintf(
				'<p>%s</p><p>%s</p><p>%s</p>',
				__( 'Database initialization error!', 'performance-lab' ),
				/* translators: %d: error code */
				sprintf( __( 'Code: %d', 'performance-lab' ), $status ),
				/* translators: %s: error message */
				sprintf( __( 'Error Message: %s', 'performance-lab' ), $err_message )
			);
			$this->set_error( __LINE__, __FILE__, $message );

			return false;
		}
		$this->init();
	}

	/**
	 * Destructor
	 *
	 * If SQLITE_MEM_DEBUG constant is defined, append information about
	 * memory usage into database/mem_debug.txt.
	 *
	 * This definition is changed since version 1.7.
	 *
	 * @return boolean
	 */
	function __destruct() {
		if ( defined( 'SQLITE_MEM_DEBUG' ) && SQLITE_MEM_DEBUG ) {
			$max = ini_get( 'memory_limit' );
			if ( is_null( $max ) ) {
				$message = sprintf(
					'[%s] Memory_limit is not set in php.ini file.',
					gmdate( 'Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] )
				);
				error_log( $message );
				return true;
			}
			if ( stripos( $max, 'M' ) !== false ) {
				$max = (int) $max * MB_IN_BYTES;
			}
			$peak = memory_get_peak_usage( true );
			$used = round( (int) $peak / (int) $max * 100, 2 );
			if ( $used > 90 ) {
				$message = sprintf(
					"[%s] Memory peak usage warning: %s %% used. (max: %sM, now: %sM)\n",
					gmdate( 'Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ),
					$used,
					$max,
					$peak
				);
				error_log( $message );
			}
		}

		return true;
	}

	/**
	 * Method to initialize database, executed in the constructor.
	 *
	 * It checks if WordPress is in the installing process and does the required
	 * jobs. SQLite library version specific settings are also in this function.
	 *
	 * Some developers use WP_INSTALLING constant for other purposes, if so, this
	 * function will do no harms.
	 */
	private function init() {
		if ( version_compare( SQLite3::version()['versionString'], '3.7.11', '>=' ) ) {
			$this->can_insert_multiple_rows = true;
		}
		$statement = $this->pdo->query( 'PRAGMA foreign_keys' );
		if ( $statement->fetchColumn( 0 ) == '0' ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			$this->pdo->query( 'PRAGMA foreign_keys = ON' );
		}
	}

	/**
	 * This method makes database directory and .htaccess file.
	 *
	 * It is executed only once when the installation begins.
	 */
	private function prepare_directory() {
		global $wpdb;
		$u = umask( 0000 );
		if ( ! is_dir( FQDBDIR ) ) {
			if ( ! @mkdir( FQDBDIR, 0704, true ) ) {
				umask( $u );
				$message = __( 'Unable to create the required directory! Please check your server settings.', 'performance-lab' );
				wp_die( $message, 'Error!' );
			}
		}
		if ( ! is_writable( FQDBDIR ) ) {
			umask( $u );
			$message = __( 'Unable to create a file in the directory! Please check your server settings.', 'performance-lab' );
			wp_die( $message, 'Error!' );
		}
		if ( ! is_file( FQDBDIR . '.htaccess' ) ) {
			$fh = fopen( FQDBDIR . '.htaccess', 'w' );
			if ( ! $fh ) {
				umask( $u );
				$message = __( 'Unable to create a file in the directory! Please check your server settings.', 'performance-lab' );
				echo $message;

				return false;
			}
			fwrite( $fh, 'DENY FROM ALL' );
			fclose( $fh );
		}
		if ( ! is_file( FQDBDIR . 'index.php' ) ) {
			$fh = fopen( FQDBDIR . 'index.php', 'w' );
			if ( ! $fh ) {
				umask( $u );
				$message = __( 'Unable to create a file in the directory! Please check your server settings.', 'performance-lab' );
				echo $message;

				return false;
			}
			fwrite( $fh, '<?php // Silence is gold. ?>' );
			fclose( $fh );
		}
		umask( $u );

		return true;
	}

	/**
	 * Method to execute query().
	 *
	 * Divide the query types into seven different ones. That is to say:
	 *
	 * 1. SELECT SQL_CALC_FOUND_ROWS
	 * 2. INSERT
	 * 3. CREATE TABLE(INDEX)
	 * 4. ALTER TABLE
	 * 5. SHOW VARIABLES
	 * 6. DROP INDEX
	 * 7. THE OTHERS
	 *
	 * #1 is just a tricky play. See the private function handle_sql_count() in query.class.php.
	 * From #2 through #5 call different functions respectively.
	 * #6 call the ALTER TABLE query.
	 * #7 is a normal process: sequentially call prepare_query() and execute_query().
	 *
	 * #1 process has been changed since version 1.5.1.
	 *
	 * @param string $statement          Full SQL statement string.
	 * @param int    $mode               Not used.
	 * @param array  ...$fetch_mode_args Not used.
	 *
	 * @return mixed according to the query type
	 * @see PDO::query()
	 */
	public function query( $statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, ...$fetch_mode_args ) { // phpcs:ignore WordPress.DB.RestrictedClasses
		$this->flush();

		$this->queries[] = "Raw query:\n$statement";
		$res             = $this->determine_query_type( $statement );
		if ( ! $res && defined( 'PDO_DEBUG' ) && PDO_DEBUG ) {
			$bailout_string = sprintf(
				/* translators: %s: SQL statement */
				'<h1>' . __( 'Unknown query type', 'performance-lab' ) . '</h1><p>' . __( 'Sorry, we cannot determine the type of query that is requested (%s).', 'performance-lab' ) . '</p>',
				$statement
			);
			$this->set_error( __LINE__, __FUNCTION__, $bailout_string );
		}
		switch ( strtolower( $this->query_type ) ) {
			case 'set':
				$this->return_value = false;
				break;

			case 'foundrows':
				$_column = array( 'FOUND_ROWS()' => '' );
				$column  = array();
				if ( ! is_null( $this->found_rows_result ) ) {
					$this->num_rows          = $this->found_rows_result;
					$_column['FOUND_ROWS()'] = $this->num_rows;
					$column[]                = new Perflab_SQLite_Object_Array( $_column );
					$this->results           = $column;
					$this->found_rows_result = null;
				}
				break;

			case 'insert':
				if ( $this->can_insert_multiple_rows ) {
					$this->execute_insert_query_new( $statement );
					break;
				}
				$this->execute_insert_query( $statement );
				break;

			case 'create':
				$this->return_value = $this->execute_create_query( $statement );
				break;

			case 'alter':
				$this->return_value = $this->execute_alter_query( $statement );
				break;

			case 'show_variables':
				$this->return_value = $this->show_variables_workaround( $statement );
				break;

			case 'showstatus':
				$this->return_value = $this->show_status_workaround( $statement );
				break;

			case 'drop_index':
				$this->return_value = false;
				if ( preg_match( '/^\\s*(DROP\\s*INDEX\\s*.*?)\\s*ON\\s*(.*)/im', $statement, $match ) ) {
					$this->query_type   = 'alter';
					$this->return_value = $this->execute_alter_query( 'ALTER TABLE ' . trim( $match[2] ) . ' ' . trim( $match[1] ) );
				}
				break;

			default:
				$engine                = $this->prepare_engine( $this->query_type );
				$this->rewritten_query = $engine->rewrite_query( $statement, $this->query_type );
				if ( ! is_null( $this->pre_ordered_results ) ) {
					$this->results             = $this->pre_ordered_results;
					$this->num_rows            = count( $this->results );
					$this->return_value        = $this->num_rows;
					$this->pre_ordered_results = null;
					break;
				}
				$this->queries[] = "Rewritten:\n$this->rewritten_query";
				$this->extract_variables();
				$prepared_query = $this->prepare_query();
				$this->execute_query( $prepared_query );
				if ( ! $this->is_error ) {
					$this->process_results( $engine );
				}
				break;
		}
		$debug_string = $this->get_debug_info();
		if ( defined( 'PDO_DEBUG' ) && PDO_DEBUG === true && $debug_string ) {
			error_log( $debug_string );
		}

		return $this->return_value;
	}

	/**
	 * Method to return inserted row id.
	 */
	public function get_insert_id() {
		return $this->last_insert_id;
	}

	/**
	 * Method to return the number of rows affected.
	 */
	public function get_affected_rows() {
		return $this->affected_rows;
	}

	/**
	 * Method to return the queried column names.
	 *
	 * These data are meaningless for SQLite. So they are dummy emulating
	 * MySQL columns data.
	 *
	 * @return array of the object
	 */
	public function get_columns() {
		if ( ! empty( $this->results ) ) {
			$primary_key = array(
				'meta_id',
				'comment_ID',
				'link_ID',
				'option_id',
				'blog_id',
				'option_name',
				'ID',
				'term_id',
				'object_id',
				'term_taxonomy_id',
				'umeta_id',
				'id',
			);
			$unique_key  = array( 'term_id', 'taxonomy', 'slug' );
			$data        = array(
				'name'         => '', // Column name.
				'table'        => '', // Table name.
				'max_length'   => 0,  // Max length of the column.
				'not_null'     => 1,  // 1 if not null.
				'primary_key'  => 0,  // 1 if column has primary key.
				'unique_key'   => 0,  // 1 if column has unique key.
				'multiple_key' => 0,  // 1 if column doesn't have unique key.
				'numeric'      => 0,  // 1 if column has numeric value.
				'blob'         => 0,  // 1 if column is blob.
				'type'         => '', // Type of the column.
				'unsigned'     => 0,  // 1 if column is unsigned integer.
				'zerofill'     => 0,  // 1 if column is zero-filled.
			);
			$table_name  = '';
			if ( preg_match( '/\s*FROM\s*(.*)?\s*/i', $this->rewritten_query, $match ) ) {
				$table_name = trim( $match[1] );
			}
			foreach ( $this->results[0] as $key => $value ) {
				$data['name']  = $key;
				$data['table'] = $table_name;
				if ( in_array( $key, $primary_key, true ) ) {
					$data['primary_key'] = 1;
				} elseif ( in_array( $key, $unique_key, true ) ) {
					$data['unique_key'] = 1;
				} else {
					$data['multiple_key'] = 1;
				}
				$this->column_data[] = new Perflab_SQLite_Object_Array( $data );

				// Reset data for next iteration.
				$data['name']         = '';
				$data['table']        = '';
				$data['primary_key']  = 0;
				$data['unique_key']   = 0;
				$data['multiple_key'] = 0;
			}

			return $this->column_data;
		}
		return null;
	}

	/**
	 * Method to return the queried result data.
	 *
	 * @return mixed
	 */
	public function get_query_results() {
		return $this->results;
	}

	/**
	 * Method to return the number of rows from the queried result.
	 */
	public function get_num_rows() {
		return $this->num_rows;
	}

	/**
	 * Method to return the queried results according to the query types.
	 *
	 * @return mixed
	 */
	public function get_return_value() {
		return $this->return_value;
	}

	/**
	 * Method to return error messages.
	 *
	 * @return string
	 */
	public function get_error_message() {
		if ( count( $this->error_messages ) === 0 ) {
			$this->is_error       = false;
			$this->error_messages = array();
			return '';
		}

		if ( false === $this->is_error ) {
			return '';
		}

		$output  = '<div style="clear:both">&nbsp;</div>';
		$output .= '<div class="queries" style="clear:both;margin_bottom:2px;border:red dotted thin;">';
		$output .= '<p>' . __( 'Queries made or created this session were:', 'performance-lab' ) . '</p>';
		$output .= '<ol>';
		foreach ( $this->queries as $q ) {
			$output .= '<li>' . $q . '</li>';
		}
		$output .= '</ol>';
		$output .= '</div>';
		foreach ( $this->error_messages as $num => $m ) {
			$output .= '<div style="clear:both;margin_bottom:2px;border:red dotted thin;" class="error_message" style="border-bottom:dotted blue thin;">';
			$output .= sprintf(
				/* translators: %1$d: line number, %2$s: function name, %3$s: error message */
				__( 'Error occurred at line %1$d in Function %2$s. Error message was: %3$s.', 'performance-lab' ),
				(int) $this->errors[ $num ]['line'],
				'<code>' . esc_html( $this->errors[ $num ]['function'] ) . '</code>',
				$m
			);
			$output .= '</div>';
		}

		ob_start();
		debug_print_backtrace();
		$output .= '<pre>' . ob_get_contents() . '</pre>';
		ob_end_clean();

		return $output;
	}

	/**
	 * Method to return information about query string for debugging.
	 *
	 * @return string
	 */
	private function get_debug_info() {
		$output = '';
		foreach ( $this->queries as $q ) {
			$output .= $q . "\n";
		}

		return $output;
	}

	/**
	 * Method to clear previous data.
	 */
	private function flush() {
		$this->rewritten_query     = '';
		$this->query_type          = '';
		$this->results             = null;
		$this->_results            = null;
		$this->last_insert_id      = null;
		$this->affected_rows       = null;
		$this->column_data         = array();
		$this->num_rows            = null;
		$this->return_value        = null;
		$this->extracted_variables = array();
		$this->error_messages      = array();
		$this->is_error            = false;
		$this->queries             = array();
		$this->param_num           = 0;
	}

	/**
	 * Method to include the apropreate class files.
	 *
	 * It is not a good habit to change the include files programmatically.
	 * Needs to be fixed some other way.
	 *
	 * @param string $query_type The query type (create, alter, etc).
	 *
	 * @return object reference to apropreate driver
	 */
	private function prepare_engine( $query_type = null ) {
		if ( stripos( $query_type, 'create' ) !== false ) {
			return new Perflab_SQLite_Create_Query();
		}
		if ( stripos( $query_type, 'alter' ) !== false ) {
			return new Perflab_SQLite_Alter_Query();
		}
		return new Perflab_SQLite_PDO_Driver();
	}

	/**
	 * Method to create a PDO statement object from the query string.
	 *
	 * @return PDOStatement
	 */
	private function prepare_query() {
		$this->queries[] = "Prepare:\n" . $this->prepared_query;
		$reason          = 0;
		$message         = '';
		$statement       = null;
		do {
			try {
				$statement = $this->pdo->prepare( $this->prepared_query );
			} catch ( PDOException $err ) {
				$reason  = $err->getCode();
				$message = $err->getMessage();
			}
		} while ( 5 === $reason || 6 === $reason );

		if ( $reason > 0 ) {
			$err_message = sprintf( 'Problem preparing the PDO SQL Statement.  Error was: %s', $message );
			$this->set_error( __LINE__, __FUNCTION__, $err_message );
		}

		return $statement;
	}

	/**
	 * Method to execute PDO statement object.
	 *
	 * This function executes query and sets the variables to give back to WordPress.
	 * The variables are class fields. So if success, no return value. If failure, it
	 * returns void and stops.
	 *
	 * @param object $statement The PDO statement.
	 *
	 * @return boolean
	 */
	private function execute_query( $statement ) {
		$reason  = 0;
		$message = '';
		if ( ! is_object( $statement ) ) {
			return false;
		}
		if ( count( $this->extracted_variables ) > 0 ) {
			$this->queries[] = "Executing:\n" . var_export( $this->extracted_variables, true );
			do {
				if ( 'update' === $this->query_type || 'replace' === $this->query_type ) {
					try {
						$this->beginTransaction();
						$statement->execute( $this->extracted_variables );
						$this->commit();
					} catch ( PDOException $err ) {
						$reason  = $err->getCode();
						$message = $err->getMessage();
						$this->rollBack();
					}
				} else {
					try {
						$statement->execute( $this->extracted_variables );
					} catch ( PDOException $err ) {
						$reason  = $err->getCode();
						$message = $err->getMessage();
					}
				}
			} while ( 5 == $reason || 6 == $reason ); // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		} else {
			$this->queries[] = 'Executing: (no parameters)';
			do {
				if ( 'update' === $this->query_type || 'replace' === $this->query_type ) {
					try {
						$this->beginTransaction();
						$statement->execute();
						$this->commit();
					} catch ( PDOException $err ) {
						$reason  = $err->getCode();
						$message = $err->getMessage();
						$this->rollBack();
					}
				} else {
					try {
						$statement->execute();
					} catch ( PDOException $err ) {
						$reason  = $err->getCode();
						$message = $err->getMessage();
					}
				}
			} while ( 5 == $reason || 6 == $reason ); // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		}
		if ( $reason > 0 ) {
			$err_message = sprintf( 'Error while executing query! Error message was: %s', $message );
			$this->set_error( __LINE__, __FUNCTION__, $err_message );
			return false;
		}
		$this->_results = $statement->fetchAll( PDO::FETCH_OBJ ); // phpcs:ignore WordPress.DB.RestrictedClasses

		// Generate the results that $wpdb will want to see.
		switch ( $this->query_type ) {
			case 'insert':
			case 'update':
			case 'replace':
				$this->last_insert_id = $this->pdo->lastInsertId();
				$this->affected_rows  = $statement->rowCount();
				$this->return_value   = $this->affected_rows;
				break;

			case 'select':
			case 'show':
			case 'showcolumns':
			case 'showindex':
			case 'describe':
			case 'desc':
			case 'check':
			case 'analyze':
				$this->num_rows     = count( $this->_results );
				$this->return_value = $this->num_rows;
				break;

			case 'delete':
				$this->affected_rows = $statement->rowCount();
				$this->return_value  = $this->affected_rows;
				break;

			case 'alter':
			case 'drop':
			case 'create':
			case 'optimize':
			case 'truncate':
				$this->return_value = true;
				if ( $this->is_error ) {
					$this->return_value = false;
				}
				break;
		}
	}

	/**
	 * Method to extract field data to an array and prepare the query statement.
	 *
	 * If original SQL statement is CREATE query, this function does nothing.
	 */
	private function extract_variables() {
		if ( 'create' === $this->query_type ) {
			$this->prepared_query = $this->rewritten_query;
			return;
		}

		// Long queries can really kill this.
		$pattern = '/(?<!\\\\)([\'"])(.*?)(?<!\\\\)\\1/imsx';
		$_limit  = ini_get( 'pcre.backtrack_limit' );
		$limit   = $_limit;
		// if user's setting is more than default * 10, make PHP do the job.
		if ( $limit > 10000000 ) {
			$query = preg_replace_callback(
				$pattern,
				array( $this, 'replace_variables_with_placeholders' ),
				$this->rewritten_query
			);
		} else {
			do {
				if ( $limit > 10000000 ) {
					$this->set_error( __LINE__, __FUNCTION__, 'The query is too big to parse properly' );
					break; // No point in continuing execution, would get into a loop.
				}
				ini_set( 'pcre.backtrack_limit', $limit );
				$query = preg_replace_callback(
					$pattern,
					array( $this, 'replace_variables_with_placeholders' ),
					$this->rewritten_query
				);
				$limit = $limit * 10;
			} while ( is_null( $query ) );

			// Reset the pcre.backtrack_limit.
			ini_set( 'pcre.backtrack_limit', $_limit );
		}

		if ( isset( $query ) ) {
			$this->queries[]      = "With Placeholders:\n" . $query;
			$this->prepared_query = $query;
		}
	}

	/**
	 * Call back function to replace field data with PDO parameter.
	 *
	 * @param string $matches Matched string.
	 *
	 * @return string
	 */
	private function replace_variables_with_placeholders( $matches ) {
		// Remove the WordPress escaping mechanism.
		$param = stripslashes( $matches[0] );

		// Remove trailing spaces.
		$param = trim( $param );

		// Remove the quotes at the end and the beginning.
		if ( in_array( $param[ strlen( $param ) - 1 ], array( "'", '"' ), true ) ) {
			$param = substr( $param, 0, -1 ); // End.
		}
		if ( in_array( $param[0], array( "'", '"' ), true ) ) {
			$param = substr( $param, 1 ); // Start.
		}
		$key                         = ':param_' . $this->param_num++;
		$this->extracted_variables[] = $param;
		// Return the placeholder.
		return ' ' . $key . ' ';
	}

	/**
	 * Method to determine which query type the argument is.
	 *
	 * It takes the query string ,determines the type and returns the type string.
	 * If the query is the type that SQLite Integration can't executes, returns false.
	 *
	 * @param string $query The query string.
	 *
	 * @return boolean
	 */
	private function determine_query_type( $query ) {
		$result = preg_match(
			'/^\\s*(SET|EXPLAIN|PRAGMA|SELECT\\s*FOUND_ROWS|SELECT|INSERT|UPDATE|REPLACE|DELETE|ALTER|CREATE|DROP\\s*INDEX|DROP|SHOW\\s*\\w+\\s*\\w+\\s*|DESCRIBE|DESC|TRUNCATE|OPTIMIZE|CHECK|ANALYZE)/i',
			$query,
			$match
		);

		if ( ! $result ) {
			return false;
		}
		$this->query_type = strtolower( $match[1] );
		if ( stripos( $this->query_type, 'found' ) !== false ) {
			$this->query_type = 'foundrows';
		}
		if ( stripos( $this->query_type, 'show' ) !== false ) {
			if ( stripos( $this->query_type, 'show table status' ) !== false ) {
				$this->query_type = 'showstatus';
			} elseif (
				stripos( $this->query_type, 'show tables' ) !== false ||
				stripos( $this->query_type, 'show full tables' ) !== false
			) {
				$this->query_type = 'show';
			} elseif (
				stripos( $this->query_type, 'show columns' ) !== false ||
				stripos( $this->query_type, 'show fields' ) !== false ||
				stripos( $this->query_type, 'show full columns' ) !== false
			) {
				$this->query_type = 'showcolumns';
			} elseif (
				stripos( $this->query_type, 'show index' ) !== false ||
				stripos( $this->query_type, 'show indexes' ) !== false ||
				stripos( $this->query_type, 'show keys' ) !== false
			) {
				$this->query_type = 'showindex';
			} elseif (
				stripos( $this->query_type, 'show variables' ) !== false ||
				stripos( $this->query_type, 'show global variables' ) !== false ||
				stripos( $this->query_type, 'show session variables' ) !== false
			) {
				$this->query_type = 'show_variables';
			} else {
				return false;
			}
		}
		if ( stripos( $this->query_type, 'drop index' ) !== false ) {
			$this->query_type = 'drop_index';
		}

		return true;
	}

	/**
	 * Method to execute INSERT query for SQLite version 3.7.11 or later.
	 *
	 * SQLite version 3.7.11 began to support multiple rows insert with values
	 * clause. This is for that version or later.
	 *
	 * @param string $query The query string.
	 */
	private function execute_insert_query_new( $query ) {
		$engine                = $this->prepare_engine( $this->query_type );
		$this->rewritten_query = $engine->rewrite_query( $query, $this->query_type );
		$this->queries[]       = "Rewritten:\n" . $this->rewritten_query;
		$this->extract_variables();
		$statement = $this->prepare_query();
		$this->execute_query( $statement );
	}

	/**
	 * Method to execute INSERT query for SQLite version 3.7.10 or lesser.
	 *
	 * It executes the INSERT query for SQLite version 3.7.10 or lesser. It is
	 * necessary to rewrite multiple row values.
	 *
	 * @param string $query The query string.
	 */
	private function execute_insert_query( $query ) {
		global $wpdb;
		$multi_insert = false;
		$statement    = null;
		$engine       = $this->prepare_engine( $this->query_type );
		if ( preg_match( '/(INSERT.*?VALUES\\s*)(\(.*\))/imsx', $query, $matched ) ) {
			$query_prefix = $matched[1];
			$values_data  = $matched[2];
			if ( stripos( $values_data, 'ON DUPLICATE KEY' ) !== false ) {
				$exploded_parts = $values_data;
			} elseif ( stripos( $query_prefix, "INSERT INTO $wpdb->comments" ) !== false ) {
				$exploded_parts = $values_data;
			} else {
				$exploded_parts = $this->parse_multiple_inserts( $values_data );
			}
			$count = count( $exploded_parts );
			if ( $count > 1 ) {
				$multi_insert = true;
			}
		}
		if ( $multi_insert ) {
			$first = true;
			foreach ( $exploded_parts as $value ) {
				$suffix = ( substr( $value, -1, 1 ) === ')' ) ? '' : ')';

				$query_string              = $query_prefix . ' ' . $value . $suffix;
				$this->rewritten_query     = $engine->rewrite_query( $query_string, $this->query_type );
				$this->queries[]           = "Rewritten:\n" . $this->rewritten_query;
				$this->extracted_variables = array();
				$this->extract_variables();
				if ( $first ) {
					$statement = $this->prepare_query();
					$this->execute_query( $statement );
					$first = false;
				} else {
					$this->execute_query( $statement );
				}
			}
		} else {
			$this->rewritten_query = $engine->rewrite_query( $query, $this->query_type );
			$this->queries[]       = "Rewritten:\n" . $this->rewritten_query;
			$this->extract_variables();
			$statement = $this->prepare_query();
			$this->execute_query( $statement );
		}
	}

	/**
	 * Method to help rewriting multiple row values insert query.
	 *
	 * It splits the values clause into an array to execute separately.
	 *
	 * @param string $values The values to be split.
	 *
	 * @return array
	 */
	private function parse_multiple_inserts( $values ) {
		$tokens         = preg_split( "/(''|(?<!\\\\)'|(?<!\()\),(?=\s*\())/s", $values, -1, PREG_SPLIT_DELIM_CAPTURE );
		$exploded_parts = array();
		$part           = '';
		$literal        = false;
		foreach ( $tokens as $token ) {
			switch ( $token ) {
				case '),':
					if ( ! $literal ) {
						$exploded_parts[] = $part;
						$part             = '';
					} else {
						$part .= $token;
					}
					break;

				case "'":
					$literal = ! $literal;
					$part   .= $token;
					break;

				default:
					$part .= $token;
					break;

			}
		}
		if ( ! empty( $part ) ) {
			$exploded_parts[] = $part;
		}

		return $exploded_parts;
	}

	/**
	 * Method to execute CREATE query.
	 *
	 * @param string $query The query to execute.
	 *
	 * @return boolean
	 */
	private function execute_create_query( $query ) {
		$engine          = $this->prepare_engine( $this->query_type );
		$rewritten_query = $engine->rewrite_query( $query );
		$reason          = 0;
		$message         = '';

		try {
			$this->beginTransaction();
			foreach ( (array) $rewritten_query as $single_query ) {
				$this->queries[] = "Executing:\n" . $single_query;
				$single_query    = trim( $single_query );
				if ( empty( $single_query ) ) {
					continue;
				}
				$this->pdo->exec( $single_query );
			}
			$this->commit();
		} catch ( PDOException $err ) {
			$reason  = $err->getCode();
			$message = $err->getMessage();
			if ( 5 == $reason || 6 == $reason ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				$this->commit();
			} else {
				$this->rollBack();
			}
		}
		if ( $reason > 0 ) {
			$err_message = sprintf( 'Problem in creating table or index. Error was: %s', $message );
			$this->set_error( __LINE__, __FUNCTION__, $err_message );

			return false;
		}

		return true;
	}

	/**
	 * Method to execute ALTER TABLE query.
	 *
	 * @param string $query The query to execute.
	 *
	 * @return boolean
	 */
	private function execute_alter_query( $query ) {
		$engine          = $this->prepare_engine( $this->query_type );
		$reason          = 0;
		$message         = '';
		$re_query        = '';
		$rewritten_query = $engine->rewrite_query( $query, $this->query_type );
		if ( is_array( $rewritten_query ) && array_key_exists( 'recursion', $rewritten_query ) ) {
			$re_query = $rewritten_query['recursion'];
			unset( $rewritten_query['recursion'] );
		}
		try {
			$this->beginTransaction();
			if ( is_array( $rewritten_query ) ) {
				foreach ( $rewritten_query as $single_query ) {
					$this->queries[] = "Executing:\n" . $single_query;
					$single_query    = trim( $single_query );
					if ( empty( $single_query ) ) {
						continue;
					}
					$this->pdo->exec( $single_query );
				}
			} else {
				$this->queries[] = "Executing:\n" . $rewritten_query;
				$rewritten_query = trim( $rewritten_query );
				$this->pdo->exec( $rewritten_query );
			}
			$this->commit();
		} catch ( PDOException $err ) {
			$reason  = $err->getCode();
			$message = $err->getMessage();
			if ( 5 == $reason || 6 == $reason ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				$this->commit();
				usleep( 10000 );
			} else {
				$this->rollBack();
			}
		}
		if ( '' !== $re_query ) {
			$this->query( $re_query );
		}
		if ( $reason > 0 ) {
			$err_message = sprintf( 'Problem in executing alter query. Error was: %s', $message );
			$this->set_error( __LINE__, __FUNCTION__, $err_message );

			return false;
		}

		return true;
	}

	/**
	 * Method to execute SHOW VARIABLES query
	 *
	 * This query is meaningless for SQLite. This function returns null data with some
	 * exceptions and only avoids the error message.
	 *
	 * @param string $query The query to execute.
	 *
	 * @return bool
	 */
	private function show_variables_workaround( $query ) {
		$dummy_data = array(
			'Variable_name' => '',
			'Value'         => null,
		);
		$pattern    = '/SHOW\\s*VARIABLES\\s*LIKE\\s*(.*)?$/im';
		if ( preg_match( $pattern, $query, $match ) ) {
			$value                       = str_replace( "'", '', $match[1] );
			$dummy_data['Variable_name'] = trim( $value );
			// This is set for Wordfence Security Plugin.
			$dummy_data['Value'] = '';
			if ( 'max_allowed_packet' === $value ) {
				$dummy_data['Value'] = 1047552;
			}
		}
		$_results[]         = new Perflab_SQLite_Object_Array( $dummy_data );
		$this->results      = $_results;
		$this->num_rows     = count( $this->results );
		$this->return_value = $this->num_rows;

		return true;
	}

	/**
	 * Method to execute SHOW TABLE STATUS query.
	 *
	 * This query is meaningless for SQLite. This function returns dummy data.
	 *
	 * @param string $query The query to execute.
	 *
	 * @return bool
	 */
	private function show_status_workaround( $query ) {
		$pattern    = '/^SHOW\\s*TABLE\\s*STATUS\\s*LIKE\\s*(.*?)$/im';
		$table_name = '';
		if ( preg_match( $pattern, $query, $match ) ) {
			$table_name = str_replace( "'", '', $match[1] );
		}
		$dummy_data         = array(
			'Name'            => $table_name,
			'Engine'          => '',
			'Version'         => '',
			'Row_format'      => '',
			'Rows'            => 0,
			'Avg_row_length'  => 0,
			'Data_length'     => 0,
			'Max_data_length' => 0,
			'Index_length'    => 0,
			'Data_free'       => 0,
			'Auto_increment'  => 0,
			'Create_time'     => '',
			'Update_time'     => '',
			'Check_time'      => '',
			'Collation'       => '',
			'Checksum'        => '',
			'Create_options'  => '',
			'Comment'         => '',
		);
		$_results[]         = new Perflab_SQLite_Object_Array( $dummy_data );
		$this->results      = $_results;
		$this->num_rows     = count( $this->results );
		$this->return_value = $this->num_rows;

		return true;
	}

	/**
	 * Method to format the queried data to that of MySQL.
	 *
	 * @param string $engine Not used.
	 */
	private function process_results( $engine ) {
		if ( in_array( $this->query_type, array( 'describe', 'desc', 'showcolumns' ), true ) ) {
			$this->convert_to_columns_object();
		} elseif ( 'showindex' === $this->query_type ) {
			$this->convert_to_index_object();
		} elseif ( in_array( $this->query_type, array( 'check', 'analyze' ), true ) ) {
			$this->convert_result_check_or_analyze();
		} else {
			$this->results = $this->_results;
		}
	}

	/**
	 * Method to format the error messages and put out to the file.
	 *
	 * When $wpdb::suppress_errors is set to true or $wpdb::show_errors is set to false,
	 * the error messages are ignored.
	 *
	 * @param string $line     Where the error occurred.
	 * @param string $function Indicate the function name where the error occurred.
	 * @param string $message  The message.
	 *
	 * @return boolean
	 */
	private function set_error( $line, $function, $message ) {
		global $wpdb;
		$this->errors[]         = array(
			'line'     => $line,
			'function' => $function,
		);
		$this->error_messages[] = $message;
		$this->is_error         = true;
		if ( $wpdb->suppress_errors || ! $wpdb->show_errors ) {
			return false;
		}
		error_log( "Line $line, Function: $function, Message: $message" );
	}

	/**
	 * Method to change the queried data to PHP object format.
	 *
	 * It takes the associative array of query results and creates a numeric
	 * array of anonymous objects
	 *
	 * @access private
	 */
	private function convert_to_object() {
		$_results = array();
		if ( count( $this->results ) === 0 ) {
			echo $this->get_error_message();
		} else {
			foreach ( $this->results as $row ) {
				$_results[] = new Perflab_SQLite_Object_Array( $row );
			}
		}
		$this->results = $_results;
	}

	/**
	 * Method to convert the SHOW COLUMNS query data to an object.
	 *
	 * It rewrites pragma results to mysql compatible array
	 * when query_type is describe, we use sqlite pragma function.
	 *
	 * @access private
	 */
	private function convert_to_columns_object() {
		$_results = array();
		$_columns = array( // Field names MySQL SHOW COLUMNS returns.
			'Field'   => '',
			'Type'    => '',
			'Null'    => '',
			'Key'     => '',
			'Default' => '',
			'Extra'   => '',
		);
		if ( empty( $this->_results ) ) {
			echo $this->get_error_message();
		} else {
			foreach ( $this->_results as $row ) {
				if ( ! is_object( $row ) ) {
					continue;
				}
				if ( property_exists( $row, 'name' ) ) {
					$_columns['Field'] = $row->name;
				}
				if ( property_exists( $row, 'type' ) ) {
					$_columns['Type'] = $row->type;
				}
				if ( property_exists( $row, 'notnull' ) ) {
					$_columns['Null'] = $row->notnull ? 'NO' : 'YES';
				}
				if ( property_exists( $row, 'pk' ) ) {
					$_columns['Key'] = $row->pk ? 'PRI' : '';
				}
				if ( property_exists( $row, 'dflt_value' ) ) {
					$_columns['Default'] = $row->dflt_value;
				}
				$_results[] = new Perflab_SQLite_Object_Array( $_columns );
			}
		}
		$this->results = $_results;
	}

	/**
	 * Method to convert SHOW INDEX query data to PHP object.
	 *
	 * It rewrites the result of SHOW INDEX to the Object compatible with MySQL
	 * added the WHERE clause manipulation (ver 1.3.1)
	 *
	 * @access private
	 */
	private function convert_to_index_object() {
		$_results = array();
		$_columns = array(
			'Table'        => '',
			'Non_unique'   => '', // Unique -> 0, not unique -> 1.
			'Key_name'     => '', // The name of the index.
			'Seq_in_index' => '', // Column sequence number in the index. begins at 1.
			'Column_name'  => '',
			'Collation'    => '', // A(scend) or NULL.
			'Cardinality'  => '',
			'Sub_part'     => '', // Set to NULL.
			'Packed'       => '', // How to pack key or else NULL.
			'Null'         => '', // If column contains null, YES. If not, NO.
			'Index_type'   => '', // BTREE, FULLTEXT, HASH, RTREE.
			'Comment'      => '',
		);
		if ( 0 === count( $this->_results ) ) {
			echo $this->get_error_message();
		} else {
			foreach ( $this->_results as $row ) {
				if ( 'table' === $row->type && ! stripos( $row->sql, 'primary' ) ) {
					continue;
				}
				if ( 'index' === $row->type && stripos( $row->name, 'sqlite_autoindex' ) !== false ) {
					continue;
				}
				switch ( $row->type ) {
					case 'table':
						$pattern1 = '/^\\s*PRIMARY.*\((.*)\)/im';
						$pattern2 = '/^\\s*(\\w+)?\\s*.*PRIMARY.*(?!\()/im';
						if ( preg_match( $pattern1, $row->sql, $match ) ) {
							$col_name                = trim( $match[1] );
							$_columns['Key_name']    = 'PRIMARY';
							$_columns['Non_unique']  = 0;
							$_columns['Column_name'] = $col_name;
						} elseif ( preg_match( $pattern2, $row->sql, $match ) ) {
							$col_name                = trim( $match[1] );
							$_columns['Key_name']    = 'PRIMARY';
							$_columns['Non_unique']  = 0;
							$_columns['Column_name'] = $col_name;
						}
						break;

					case 'index':
						$_columns['Non_unique'] = 1;
						if ( stripos( $row->sql, 'unique' ) !== false ) {
							$_columns['Non_unique'] = 0;
						}
						if ( preg_match( '/^.*\((.*)\)/i', $row->sql, $match ) ) {
							$col_name                = str_replace( "'", '', $match[1] );
							$_columns['Column_name'] = trim( $col_name );
						}
						$_columns['Key_name'] = $row->name;
						break;

				}
				$_columns['Table']       = $row->tbl_name;
				$_columns['Collation']   = null;
				$_columns['Cardinality'] = 0;
				$_columns['Sub_part']    = null;
				$_columns['Packed']      = null;
				$_columns['Null']        = 'NO';
				$_columns['Index_type']  = 'BTREE';
				$_columns['Comment']     = '';
				$_results[]              = new Perflab_SQLite_Object_Array( $_columns );
			}
			if ( stripos( $this->queries[0], 'WHERE' ) !== false ) {
				preg_match( '/WHERE\\s*(.*)$/im', $this->queries[0], $match );
				list($key, $value) = explode( '=', $match[1] );
				$key               = trim( $key );
				$value             = trim( preg_replace( "/[\';]/", '', $value ) );
				foreach ( $_results as $result ) {
					if ( ! empty( $result->$key ) && is_scalar( $result->$key ) && stripos( $value, $result->$key ) !== false ) {
						unset( $_results );
						$_results[] = $result;
						break;
					}
				}
			}
		}

		$this->results = $_results;
	}

	/**
	 * Method to the CHECK query data to an object.
	 *
	 * @access private
	 */
	private function convert_result_check_or_analyze() {
		$is_check      = 'check' === $this->query_type;
		$_results[]    = new Perflab_SQLite_Object_Array(
			array(
				'Table'    => '',
				'Op'       => $is_check ? 'check' : 'analyze',
				'Msg_type' => 'status',
				'Msg_text' => $is_check ? __( 'OK', 'performance-lab' ) : __( 'Table is already up to date', 'performance-lab' ),
			)
		);
		$this->results = $_results;
	}

	/**
	 * Method to check SQLite library version.
	 *
	 * This is used for checking if SQLite can execute multiple rows insert.
	 *
	 * @return version number string or 0
	 * @access private
	 */
	private function get_sqlite_version() {
		try {
			$statement = $this->pdo->prepare( 'SELECT sqlite_version()' );
			$statement->execute();
			$result = $statement->fetch( PDO::FETCH_NUM ); // phpcs:ignore WordPress.DB.RestrictedClasses

			return $result[0];
		} catch ( PDOException $err ) {
			return '0';
		}
	}

	/**
	 * Method to call PDO::beginTransaction().
	 *
	 * @see PDO::beginTransaction()
	 * @return boolean
	 */
	public function beginTransaction() {
		if ( $this->has_active_transaction ) {
			return false;
		}
		$this->has_active_transaction = $this->pdo->beginTransaction();
		return $this->has_active_transaction;
	}

	/**
	 * Method to call PDO::commit().
	 *
	 * @see PDO::commit()
	 */
	public function commit() {
		$this->pdo->commit();
		$this->has_active_transaction = false;
	}

	/**
	 * Method to call PDO::rollBack().
	 *
	 * @see PDO::rollBack()
	 */
	public function rollBack() {
		$this->pdo->rollBack();
		$this->has_active_transaction = false;
	}
}
