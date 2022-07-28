<?php
/**
 * Performance Lab database metrics computation module
 *
 * @package performance-lab
 * @group audit-database
 *
 * @since 1.4.0
 */

/**
 * Performance Lab database metrics class,
 *
 * @package performance-lab
 * @group audit-database
 *
 * @since 1.4.0
 */
class PerflabDbMetrics {
	const SERVER_RESPONSE_CACHE       = 'perflab_sitehealth_server_response';
	const PREVIOUS_TRACKING_VARIABLES = 'perflab_sitehealth_server_tracking';
	const SERVER_RESPONSE_TTL         = 5 * MINUTE_IN_SECONDS;

	/** First eligible db version, the advent of utfmb4 in WordPress databases.
	 *
	 * @var int
	 */
	public $first_compatible_db_version = 32814;
	/** Latest eligible db version.
	 * TODO: retest when new DB versions become available.
	 *
	 * @var int
	 */
	public $last_compatible_db_version = 53496;
	/** OK to gather metrics when $wp_db_version is greater than $last_compatible_db_version.
	 *
	 * @var bool false if we should error out on newer version.
	 */
	public $reject_newer_db_versions = false;
	/** This instances has high-resolution time.
	 *
	 * @var bool true The hrtime function is available.
	 */
	public $has_hr_time = false;

	/** Utility
	 *
	 * @var PerflabDbUtilities singleton instance
	 */
	private $util;

	/** Memoized table_info result set.
	 *
	 * @var array Table descriptions.
	 */
	private $table_info_cache = array();

	/** Memoized db_version result set.
	 *
	 * @var object Database version object.
	 */
	private $db_version;

	/** Cache for get_variable().
	 *
	 * @var array Associative array, in which keys are Status or Variable names
	 */
	private $variable_cache = array();
	/** List of MariaDB / MySQL system status variables we need.
	 *
	 * @var string[] Status and variable names. NOTE WELL. Status items start with a capital letter, and variables with lowercase.
	 */
	private $tracking_variables_we_need;

	/** Constructor.
	 */
	public function __construct() {
		try {
			$this->has_hr_time = function_exists( 'hrtime' );
		} catch ( Exception $ex ) {
			$this->has_hr_time = false;
		}
		$this->util = PerflabDbUtilities::get_instance();

		$this->tracking_variables_we_need = array(
			'innodb_buffer_pool_size',
			'Innodb_buffer_pool_read_requests',
			'Innodb_buffer_pool_reads',
			'Innodb_buffer_pool_wait_free',
			'key_buffer_size',
			'Key_read_requests',
			'Key_reads',
			'Uptime',
			'Questions',
			'Bytes_received',
			'Bytes_sent',
			'max_connections',
		);
	}

	/** Get current time (ref UNIX era) in microseconds.
	 *
	 * @return float
	 */
	public function now_microseconds() {
		try {
			// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.hrtimeFound
			return $this->has_hr_time ? ( hrtime( true ) * 0.001 ) : ( time() * 1000000. );
		} catch ( Exception $ex ) {
			return time() * 1000000.;
		}
	}

	/** Retrieve current values of tracking variables.
	 *
	 * Variable names beginning with uppercase are STATUS items.
	 *
	 * @return array Associative array: name => value
	 */
	public function get_tracking_variables() {
		$result               = array();
		$result ['timestamp'] = time();
		/* this will get replaced */
		$result ['delta_timestamp'] = time();
		foreach ( $this->tracking_variables_we_need as $name ) {
			$result[ $name ] = $this->get_variable( $name );
		}
		$result ['start_timestamp'] = time() - $result ['Uptime'];
		return $result;
	}

	/** Retrieve differences between two sets of tracking variables.
	 *
	 * @param array $later The later-in-time set.
	 * @param array $earlier The earlier-in-time set.
	 *
	 * @return array
	 */
	public function tracking_variable_diffs( $later, $earlier ) {
		$result = array();

		/* compute time difference */
		$result ['previous_timestamp'] = $earlier ['timestamp'];
		$result ['delta_timestamp']    = $later ['timestamp'] - $earlier['timestamp'];
		$result ['timestamp']          = $later['timestamp'];

		/* compute differences in accumulating Status items */
		foreach ( $this->tracking_variables_we_need as $name ) {
			$first_letter = substr( $name, 0, 1 );
			if ( ctype_upper( $first_letter && is_numeric( $later [ $name ] ) ) ) {
				/* is Status, not Variable */
				$result [ $name ] = $later[ $name ] - $earlier [ $name ];
			} else {
				$result [ $name ] = $later[ $name ];
			}
		}
		return $result;
	}

	/** Get changes in tracking variables from previous sample or server boot.
	 *
	 * The idea is to give us the change in Status items like Questions, Key_Read_Requests, and
	 * so forth since some sample gathered in the past. If no sample has already been gathered
	 * on the site, we'll use the values since SQL server bootup.
	 *
	 * To get a rate. for example Questions / second, do
	 *
	 * $items ['Questions'] / $items ['Uptime']
	 *
	 * @return array Associative array: name => value.
	 */
	public function tracking_variable_changes() {
		$now                     = time();
		$current                 = $this->get_tracking_variables();
		$minimum_delta_timestamp = $this->util->get_threshold_value( 'minimum_delta_timestamp' );
		$earlier                 = get_option( self::PREVIOUS_TRACKING_VARIABLES );
		if ( ! $earlier || ( $now - $earlier ['timestamp'] ) < $minimum_delta_timestamp ) {
			/* nothing saved: we'll use whatever is on the server since it started */
			$diff            = $current;
			$diff ['source'] = 'server';
		} else {
			$diff            = $this->tracking_variable_diffs( $current, $earlier );
			$diff ['source'] = 'sample';
		}
		$maximum_delta_timestamp = $this->util->get_threshold_value( 'maximum_delta_timestamp' );
		if ( ! $earlier || ( $now - $earlier ['timestamp'] ) >= $maximum_delta_timestamp ) {
			update_option( self::PREVIOUS_TRACKING_VARIABLES, $current );
		}

		return $diff;
	}

	/** Get version information from the database server.
	 *
	 * @return object
	 */
	public function get_db_version() {
		/* memoized result ? */
		if ( isset( $this->db_version ) ) {
			return $this->db_version;
		}
		global $wpdb;
		global $wp_db_version;
		$query = "SELECT VERSION() version,
		        1 canreindex,
		        0 unconstrained,
	            CAST(SUBSTRING_INDEX(VERSION(), '.', 1) AS UNSIGNED) major,
	            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(), '.', 2), '.', -1) AS UNSIGNED) minor,
	            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(VERSION(), '-', '.'), '.', 3), '.', -1) AS UNSIGNED) build,
	            '' fork, '' distro";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query );
		$results = $results[0];

		$results->db_host = DB_HOST;
		$ver              = explode( '-', $results->version, 3 );
		if ( count( $ver ) >= 2 ) {
			$results->fork = $ver[1];
		}
		if ( count( $ver ) >= 3 ) {
			$results->distro = $ver[2];
		}
		$results->server_name = ( 0 === strpos( strtolower( $results->fork ), 'maria' ) ) ? 'MariaDB' : 'MySQL';

		/* check db version */
		if ( $wp_db_version < $this->first_compatible_db_version ) {
			/* fail if we have an outdated db version. */
			$results->failure        = __( 'Your WordPress installation\'s $wp_db_version is outdated.', 'performance-lab' );
			$results->failure_action = __( 'Upgrade WordPress to use this Site Health feature.', 'performance-lab' );
			$results->canreindex     = 0;
			$this->db_version        = $this->util->make_numeric( $results );

			return $this->db_version;
		}
		if ( $this->reject_newer_db_versions && $wp_db_version > $this->last_compatible_db_version ) {
			/* fail if we don't have an expected database version */
			$results->failure        = __( 'Your WordPress installation\'s $wp_db_version is too new.', 'performance-lab' );
			$results->failure_action = __( 'Upgrade the Performance Lab plugin to this Site Health feature.', 'performance-lab' );
			$results->canreindex     = 0;
			$this->db_version        = $this->util->make_numeric( $results );

			return $this->db_version;
		}

		$is_maria = ! ! stripos( $results->version, 'mariadb' );
		/* Work out whether we have Antelope or Barracuda InnoDB format : mysql 8+ */
		if ( ! $is_maria && $results->major >= 8 ) {
			$results->unconstrained = 1;
			$this->db_version       = $this->util->make_numeric( $results );

			return $this->db_version;
		}
		/* work out whether we have Antelope or Barracuda InnoDB format  mariadb 10.3 + */
		if ( $is_maria && $results->major >= 10 && $results->minor >= 3 ) {
			$results->unconstrained = 1;
			$this->db_version       = $this->util->make_numeric( $results );

			return $this->db_version;
		}
		/* mariadb 10.2 ar before */
		if ( $is_maria && $results->major >= 10 ) {

			$results->unconstrained = $this->has_large_prefix();
			$this->db_version       = $this->util->make_numeric( $results );

			return $this->db_version;
		}

		/* way too old */
		if ( $results->major < 5 ) {
			$results->canreindex = 0;
			$this->db_version    = $this->util->make_numeric( $results );

			return $this->db_version;
		}
		/* before 5.5 */
		if ( 5 === $results->major && $results->minor < 5 ) {
			$results->canreindex = 0;
			$this->db_version    = $this->util->make_numeric( $results );

			return $this->db_version;
		}
		/* older 5.5 */
		if ( 5 === $results->major && 5 === $results->minor && $results->build < 62 ) {
			$results->canreindex = 0;
			$this->db_version    = $this->util->make_numeric( $results );

			return $this->db_version;
		}
		/* older 5.6 */
		if ( 5 === $results->major && 6 === $results->minor && $results->build < 4 ) {
			$results->canreindex = 0;
			$this->db_version    = $this->util->make_numeric( $results );

			return $this->db_version;
		}
		$results->unconstrained = $this->has_large_prefix();
		$this->db_version       = $this->util->make_numeric( $results );

		return $this->db_version;
	}

	/** Get a global server variable or status from the DBMS.
	 *
	 * @param string $name Name of variable or status. Status names start with a capital letter.
	 *
	 * @return mixed|string Value of variable. 0 if nothing found.
	 * @throws InvalidArgumentException Notice about variable lookup failure.
	 */
	public function get_variable( $name ) {
		global $wpdb;
		if ( isset( $this->variable_cache [ $name ] ) ) {
			return $this->variable_cache [ $name ];
		}
		$first_letter = substr( $name, 0, 1 );
		$query        = ctype_upper( $first_letter )
			? 'SHOW STATUS LIKE %s'
			: 'SHOW VARIABLES LIKE %s';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$var = $wpdb->get_results( $wpdb->prepare( $query, $name ), ARRAY_N );
		if ( $var && is_array( $var ) && 1 === count( $var ) ) {
			/* dive into the result set and get the value we want */
			$var = $var[0];
			$var = $var[1];

			if ( is_numeric( $var ) ) {
				$var = 0 + $var;
			}
			$this->variable_cache [ $name ] = $var;
			return $var;
		} elseif ( $var && is_array( $var ) && 0 === count( $var ) ) {
			throw new InvalidArgumentException( $name . ': no such MySQL variable' );
		} elseif ( $var && is_array( $var ) && count( $var ) > 1 ) {
			throw new InvalidArgumentException( $name . ': returns multiple MySQL variables' );
		} else {
			throw new InvalidArgumentException( $name . ': something went wrong looking up MySQL variable' );
		}
	}

	/** Determine whether the MariaDB / MySQL instance supports InnoDB / Barracuda.
	 *
	 * @return int 1 means Barracuda (3072-byte index columns), 0 if Antelope (768-byte).
	 */
	private function has_large_prefix() {
		$prefix = $this->get_variable( 'innodb_large_prefix' );

		return ( ( 'ON' === $prefix ) || ( '1' === $prefix ) || ( 1 === $prefix ) ) ? 1 : 0;
	}

	/** Get the characteristics of each table.
	 *
	 * Notice that the row_count, data_bytes, index_bytes, and total_bytes values
	 * are approximate. We don't want to do COUNT(*) on big tables because it is
	 * absurdly slow in InnoDB.
	 *
	 * @param array|null $tables list of tables. if omitted, all tables.
	 * @param bool       $exclude if true, exclude the tables in the list from the result set.
	 *
	 * @return array An associative array indexed by table name.
	 */
	public function get_table_info( $tables = null, $exclude = false ) {
		/* All the tables, or just some? Compute a memoization hash. */
		$in_clause = $exclude ? 'NOT IN' : 'IN';
		$list      = array();
		$hash      = array();
		if ( is_array( $tables ) ) {
			foreach ( $tables as $table ) {
				$hash[] = $table;
				$list[] = "'" . $table . "'";
			}
		}
		sort( $hash );
		$memo_hash = $in_clause . '|' . implode( '|', $hash );
		if ( array_key_exists( $memo_hash, $this->table_info_cache ) ) {
			/* memoized result? */
			return $this->table_info_cache[ $memo_hash ];
		}

		global $wpdb;
		$format_query = "SELECT
    			t.TABLE_NAME AS table_name,
                t.ENGINE AS engine,
                t.ROW_FORMAT AS row_format,
                t.TABLE_COLLATION AS collation,
                t.TABLE_ROWS AS row_count,
    			t.DATA_LENGTH AS data_bytes,
    			t.INDEX_LENGTH AS index_bytes,
    			t.DATA_LENGTH + t.INDEX_LENGTH AS total_bytes
        	FROM information_schema.TABLES t
            WHERE t.TABLE_SCHEMA = DATABASE()
        	AND t.TABLE_TYPE = 'BASE TABLE'
            AND t.ENGINE IS NOT NULL";

		if ( count( $list ) > 0 ) {
			$format_query .= ' AND t.TABLE_NAME ' . $in_clause . ' ( ' . implode( ',', $list ) . ')';
		}
		$format_query .= ' ORDER BY t.TABLE_NAME';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_results( $format_query, OBJECT_K );
		/* memoize the result */
		$this->table_info_cache [ $memo_hash ] = $result;

		return $result;
	}

	/** Get 95th percentile of time taken for a connection and trivial query.
	 *
	 * This opens several wpdb connections and sends a trivial query to each one.
	 * It returns the 90th percentile of the time taken for multiple iterations.
	 *
	 * @param number $iterations how many times to run the operation.
	 * @param number $timeout milliseconds before we stop rerunning.
	 * @param number $percentile Fraction for percentile reporting, default 0.90.
	 *
	 * @return float Time taken in microseconds.
	 */
	public function server_response( $iterations, $timeout, $percentile = 0.90 ) {
		global $wpdb;
		/* this test is heavy, so we'll cache its result for 5 min */
		$result = get_transient( self::SERVER_RESPONSE_CACHE );
		if ( is_numeric( $result ) ) {
			return $result;
		}
		$start_all    = $this->now_microseconds();
		$microseconds = array();
		$table_info   = $this->get_table_info();
		$max_post_id  = $table_info[ $wpdb->posts ]->row_count;
		for ( $i = 0; $i < $iterations; $i ++ ) {
			$start = $this->now_microseconds();
			/* don't let this run forever */
			if ( ( $start - $start_all ) > ( 1000.0 * $timeout ) && count( $microseconds ) > $iterations * 0.25 ) {
				break;
			}
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$db     = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$random = rand( 1, $max_post_id );
			$query  = "SELECT p.ID, p.post_name, p.post_title, p.guid, m.meta_key, m.meta_value FROM $wpdb->posts p JOIN $wpdb->postmeta m ON p.ID = m.post_id WHERE p.ID = %d ";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$db->get_results( $db->prepare( $query, $random ) );

			$db->close();
			unset( $db );
			$microseconds [] = $this->now_microseconds() - $start;
		}

		$result = $this->util->percentile( $microseconds, $percentile );
		/* stash this for a while; it's a bit expensive to run. */
		set_transient( self::SERVER_RESPONSE_CACHE, $result, self::SERVER_RESPONSE_TTL );

		return $result;
	}

	/** DBMS buffer pool size.
	 *
	 * @param string $engine Storage engine.
	 *
	 * @return int|mixed|string MyISAM, InnoDB.
	 * @throws InvalidArgumentException For an unrecognized engine name.
	 */
	public function get_buffer_pool_size( $engine ) {
		switch ( strtolower( $engine ) ) {
			case 'innodb':
				$var = 'innodb_buffer_pool_size';
				break;
			case 'myisam':
				$var = 'key_buffer_size';
				break;
			default:
				throw new InvalidArgumentException( 'bogus engine name' );
		}

		return 0 + $this->get_variable( $var );
	}

	/** Inspect a set of tables to ensure they have the correct storage engine and row format.
	 *
	 * @param array  $stats Result set from get_table_info.
	 * @param string $target_storage_engine Usually InnoDB.
	 * @param string $target_row_format Usually Dynamic.
	 *
	 * @return array The tables with the wrong format.
	 */
	public function get_wrong_format_tables( $stats, $target_storage_engine, $target_row_format ) {
		$result = array();
		foreach ( $stats as $table => $stat ) {
			$hit = strtolower( $stat->engine ) !== strtolower( $target_storage_engine );
			$hit = $hit | strtolower( $stat->row_format ) !== strtolower( $target_row_format );
			if ( $hit ) {
				$result[ $table ] = $stat;
			}
		}

		return $result;
	}

	/** Get the indexes on a table, excluding fulltext indexes.
	 *
	 * @param string $table_name The subject table.
	 *
	 * @return array|object|stdClass[]|null One row per index.
	 */
	public function get_indexes( $table_name ) {
		global $wpdb;
		$query = "SELECT key_name, `add`, `drop`
         FROM (
          SELECT
            IF(tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY', tc.CONSTRAINT_TYPE, CONCAT (s.INDEX_NAME)) key_name,
            IF(tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY', 1, 0) is_primary,
            CASE WHEN tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY' THEN 1
                WHEN tc.CONSTRAINT_TYPE LIKE 'UNIQUE' THEN 1
                ELSE 0 END is_unique,
            CONCAT ( 'ADD ',
                CASE WHEN tc.CONSTRAINT_TYPE = 'UNIQUE' THEN CONCAT ('UNIQUE KEY ', s.INDEX_NAME)
                     WHEN tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY' THEN tc.CONSTRAINT_TYPE
                                         ELSE CONCAT ('KEY', ' ', s.INDEX_NAME) END,
                ' (',
                GROUP_CONCAT(
                  IF(s.SUB_PART IS NULL, s.COLUMN_NAME, CONCAT(s.COLUMN_NAME,'(',s.SUB_PART,')'))
                  ORDER BY s.SEQ_IN_INDEX
                  SEPARATOR ', '),
                ')'
                ) `add`,
            CONCAT ( 'DROP ',
                IF(tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY', tc.CONSTRAINT_TYPE, CONCAT ('KEY', ' ', s.INDEX_NAME))
                ) `drop`
          FROM information_schema.STATISTICS s
          LEFT JOIN information_schema.TABLE_CONSTRAINTS tc
                  ON s.TABLE_NAME = tc.TABLE_NAME
                 AND s.TABLE_SCHEMA = tc.TABLE_SCHEMA
                 AND s.TABLE_CATALOG = tc.CONSTRAINT_CATALOG
                 AND s.INDEX_NAME = tc.CONSTRAINT_NAME
         WHERE s.TABLE_SCHEMA = DATABASE()
           AND s.TABLE_NAME = %s
           /* #37 don't do anything with FULLTEXT indexes */
           AND s.INDEX_TYPE <> 'FULLTEXT'
         GROUP BY s.INDEX_NAME
        ) q
        ORDER BY is_primary DESC, is_unique DESC, key_name";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $query, $table_name ), OBJECT_K );
	}
}
