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
	public $last_compatible_db_version = 51917;


	/** Get version information from the database server.
	 *
	 * @return object
	 */
	public function get_db_version() {
		global $wpdb;
		global $wp_db_version;
		$results = $wpdb->get_results(
			"SELECT VERSION() version,
		        1 canreindex,
		        0 unconstrained,
	            CAST(SUBSTRING_INDEX(VERSION(), '.', 1) AS UNSIGNED) major,
	            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(), '.', 2), '.', -1) AS UNSIGNED) minor,
	            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(VERSION(), '-', '.'), '.', 3), '.', -1) AS UNSIGNED) build,
	            '' fork, '' distro"
		);
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
		if ( $wp_db_version < $this->first_compatible_db_version
			|| ( $this->last_compatible_db_version && $wp_db_version > $this->last_compatible_db_version ) ) {
			/* fail if we don't have an expected database version */
			$results->failure        = __( 'Your WordPress installation\'s $wp_db_version is outdated.', 'performance-lab' );
			$results->failure_action = __( 'Upgrade WordPress to use this Site Health feature.', 'performance-lab' );
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
		$prefix = $wpdb->get_results( "SHOW VARIABLES LIKE 'innodb_large_prefix'", ARRAY_N );
		if ( $prefix && is_array( $prefix ) ) {
			$prefix = $prefix[1];

			return ( ( 'ON' === $prefix[1] ) || ( '1' === $prefix[1] ) ) ? 1 : 0;
		}

		return 0;
	}

}
