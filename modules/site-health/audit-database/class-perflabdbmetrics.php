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
	private $table_info;

	/** Memoized db_version result set.
	 *
	 * @var object Database version object.
	 */
	private $db_version;

	/** Constructor.
	 */
	public function __construct() {
		try {
			$this->has_hr_time = function_exists( 'hrtime' );
		} catch ( Exception $ex ) {
			$this->has_hr_time = false;
		}
		$this->util = PerflabDbUtilities::get_instance();
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

		/* waaay too old */
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

	/** Get a global variable from the DBMS.
	 *
	 * @param string $name Name of variable.
	 *
	 * @return mixed|string Value of variable. 0 if nothing found.
	 * @throws InvalidArgumentException Notice about variable lookup failure.
	 */
	public function get_variable( $name ) {
		global $wpdb;
		$var = $wpdb->get_results( $wpdb->prepare( 'SHOW VARIABLES LIKE %s', $name ), ARRAY_N );
		if ( $var && is_array( $var ) && 1 === count( $var ) ) {
			$var = $var[0];
			return $var[1];
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
		global $wpdb;
		$prefix = $this->get_variable( 'innodb_large_prefix' );
		return ( ( 'ON' === $prefix ) || ( '1' === $prefix ) || ( 1 === $prefix ) ) ? 1 : 0;
	}

	/** Get the characteristics of each table.
	 *
	 * @param array|null $tables list of tables. if omitted, all tables.
	 * @param bool       $exclude if true, exclude the tables in the list from the result set.
	 *
	 * @return array An associative array indexed by table name.
	 */
	public function get_table_info( $tables = null, $exclude = false ) {
		global $wpdb;
		if ( isset( $this->table_info ) ) {
			/* memoized result? */
			return $this->table_info;
		}
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

		if ( is_array( $tables ) ) {
			$list = array();
			foreach ( $tables as $table ) {
				$list[] = "'" . $table . "'";
			}
			$in_clause = $exclude ? 'NOT IN' : 'IN';

			$format_query .= ' AND t.TABLE_NAME ' . $in_clause . ' ( ' . implode( ',', $list ) . ')';
		}
		$format_query .= ' ORDER BY t.TABLE_NAME';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->table_info = $wpdb->get_results( $format_query, OBJECT_K );

		return $this->table_info;
	}

	/** Get 95th percentile of time taken for a connection and trivial query.
	 *
	 * This opens several wpdb connections and sends a trivial query to each one.
	 * It returns the 90th percentile of the time taken for multiple iterations.
	 *
	 * @param number $iterations how many times to run the operation.
	 * @param number $timeout milliseconds before we stop rerunning.
	 *
	 * @return float time taken in microseconds.
	 */
	public function server_response( $iterations, $timeout ) {
		$startall     = $this->now_microseconds();
		$microseconds = array();
		for ( $i = 0; $i < $iterations; $i++ ) {
			$start = $this->now_microseconds();
			/* don't let this run forever */
			if ( ( $start - $startall ) > ( 1000.0 * $timeout ) && count( $microseconds ) > $iterations * 0.25 ) {
				break;
			}
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$db    = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$query = 'SELECT ' . rand( 1, 1000 );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$db->get_results( $query );
			$db->close();
			unset( $db );
			$microseconds [] = $this->now_microseconds() - $start;
		}

		return $this->util->percentile( $microseconds, 0.90 );
	}

	/** DBMS buffer pool size.
	 *
	 * @param string $engine Storage engine.
	 *
	 * @return int|mixed|string MyISAM, InnoDB.
	 * @throws InvalidArgumentException For an unrecognized engine name.
	 */
	public function get_buffer_pool_size( $engine ) {
		$size = 0;
		$var  = '';
		switch ( strtolower( $engine ) ) {
			case 'innodb':
				$var = 'Innodb_buffer_pool_size';
				break;
			case 'myisam':
				$var = 'Key_buffer_size';
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
