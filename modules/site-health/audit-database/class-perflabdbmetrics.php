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

	/** Constructor.
	 */
	public function __construct() {
		try {
			$this->has_hr_time = function_exists( 'hrtime' );
		} catch ( Exception $ex ) {
			$this->has_hr_time = false;
		}
	}

	/** Get current time (ref UNIX era) in microseconds.
	 *
	 * @return float
	 */
	private function now_microseconds() {
		try {
			 // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.hrtimeFound
			return $this->has_hr_time ? ( hrtime( true ) * 0.000001 ) : ( time() * 1000000. );
		} catch ( Exception $ex ) {
			return time() * 1000000.;
		}
	}
	/** Get version information from the database server.
	 *
	 * @return object
	 */
	public function get_db_version() {
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

			return $this->make_numeric( $results );
		}
		if ( $this->reject_newer_db_versions && $wp_db_version > $this->last_compatible_db_version ) {
			/* fail if we don't have an expected database version */
			$results->failure        = __( 'Your WordPress installation\'s $wp_db_version is too new.', 'performance-lab' );
			$results->failure_action = __( 'Upgrade the Performance Lab plugin to this Site Health feature.', 'performance-lab' );
			$results->canreindex     = 0;

			return $this->make_numeric( $results );
		}

		$is_maria = ! ! stripos( $results->version, 'mariadb' );
		/* Work out whether we have Antelope or Barracuda InnoDB format : mysql 8+ */
		if ( ! $is_maria && $results->major >= 8 ) {
			$results->unconstrained = 1;

			return $this->make_numeric( $results );
		}
		/* work out whether we have Antelope or Barracuda InnoDB format  mariadb 10.3 + */
		if ( $is_maria && $results->major >= 10 && $results->minor >= 3 ) {
			$results->unconstrained = 1;

			return $this->make_numeric( $results );
		}
		/* mariadb 10.2 ar before */
		if ( $is_maria && $results->major >= 10 ) {

			$results->unconstrained = $this->has_large_prefix();

			return $this->make_numeric( $results );
		}

		/* waaay too old */
		if ( $results->major < 5 ) {
			$results->canreindex = 0;

			return $this->make_numeric( $results );
		}
		/* before 5.5 */
		if ( 5 === $results->major && $results->minor < 5 ) {
			$results->canreindex = 0;

			return $this->make_numeric( $results );
		}
		/* older 5.5 */
		if ( 5 === $results->major && 5 === $results->minor && $results->build < 62 ) {
			$results->canreindex = 0;

			return $this->make_numeric( $results );
		}
		/* older 5.6 */
		if ( 5 === $results->major && 6 === $results->minor && $results->build < 4 ) {
			$results->canreindex = 0;

			return $this->make_numeric( $results );
		}
		$results->unconstrained = $this->has_large_prefix();

		return $this->make_numeric( $results );
	}

	/** Convert an array to an object, and the numeric properties therein to numbers rather than strings.
	 *
	 * @param array $ob associative array.
	 *
	 * @return object
	 */
	private function make_numeric( $ob ) {
		$result = array();
		foreach ( $ob as $key => $val ) {
			if ( is_numeric( $val ) ) {
				$val = $val + 0;
			}
			$result[ $key ] = $val;
		}

		return (object) $result;
	}

	/** Determine whether the MariaDB / MySQL instance supports InnoDB / Barracuda.
	 *
	 * @return int 1 means Barracuda (3072-byte index columns), 0 if Antelope (768-byte).
	 */
	public function has_large_prefix() {
		global $wpdb;
		/* Beware: this innodb_large_prefix variable is missing in MySQL 8+ and MySQL 5.5- */
		$query = "SHOW VARIABLES LIKE 'innodb_large_prefix'";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prefix = $wpdb->get_results( $query, ARRAY_N );
		if ( $prefix && is_array( $prefix ) ) {
			$prefix = $prefix[1];

			return ( ( 'ON' === $prefix[1] ) || ( '1' === $prefix[1] ) ) ? 1 : 0;
		}

		return 0;
	}

	/** Get the characteristics of each table.
	 *
	 * @param array|null $tables list of tables. if omitted, all tables.
	 * @param bool       $exclude if true, exclude the tables in the list from the result set.
	 *
	 * @return object with object properties the names of tables.
	 */
	public function get_table_info( $tables = null, $exclude = false ) {
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
		return $wpdb->get_results( $format_query, OBJECT_K );

	}

	/** Get 95th percentile of time taken for a connection and trivial query.
	 *
	 * This opens several wpdb connections and sends a trivial query to each one.
	 * It returns the 95th percentile of the time taken for multiple iterations.
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

		return $this->percentile( $microseconds, 0.95 );
	}

	/** Arithmetic mean
	 *
	 * @param array $a dataset.
	 *
	 * @return number
	 */
	private function mean( array $a ) {
		$n = count( $a );
		if ( ! $n ) {
			return null;
		}
		if ( 1 === $n ) {
			return $a[0];
		}
		$acc = 0;
		foreach ( $a as $v ) {
			$acc += $v;
		}

		return $acc / $n;
	}

	/** Mean absolute deviation.
	 *
	 * @param array $a dataset.
	 *
	 * @return float|int|null
	 */
	private function mad( array $a ) {
		$n = count( $a );
		if ( ! $n ) {
			return null;
		}
		if ( 1 === $n ) {
			return 0.0;
		}
		$acc = 0;
		foreach ( $a as $v ) {
			$acc += $v;
		}
		$mean = $acc / $n;
		$acc  = 0;
		foreach ( $a as $v ) {
			$acc += abs( $v - $mean );
		}

		return $acc / $n;
	}

	/** Percentile.
	 *
	 * @param array  $a dataset.
	 * @param number $p percentile as fraction 0-1.
	 *
	 * @return float
	 */
	public function percentile( array $a, $p ) {
		$n = count( $a );
		sort( $a );
		$i = floor( $n * $p );
		if ( $i >= $n ) {
			$i = $n - 1;
		}

		return $a[ $i ];
	}

}
