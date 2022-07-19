<?php
/**
 * Performance Lab database test module
 *
 * @package performance-lab
 * @group audit-database
 *
 * @since 1.4.0
 */

/**
 * Performance Lab database test class.
 *
 * This class declares and runs the database performance auditing tests.
 * It uses a passed-in PerflabDbMetrics instance to access the database.
 *
 * @package performance-lab
 * @group audit-database
 *
 * @since 1.4.0
 */
class PerflabDbTests {
	/** Metrics-retrieving instance.
	 *
	 * @var object Collection of functions.
	 */
	private $metrics;
	/** Database server semantic version and capability data.
	 *
	 * @var object Version descriptor.
	 */
	private $version;
	/** Database server name.
	 *
	 * @var string MySQL or MariaDB.
	 */
	private $name;
	/** Skip all tests.
	 *
	 * @var bool true if we should skip all tests
	 */
	private $skip_all_tests = false;
	/** Test sequence number.
	 *
	 * @var int test number.
	 */
	private $test_sequence_number = 0;
	/** Thresholds instance.
	 *
	 * @var PerflabDbThresholds instance.
	 */
	private $utilities;
	/** Table Information.
	 *
	 * @var array associative array of table information.
	 */
	private $table_stats;

	/** Constructor for tests.
	 *
	 * @param object $metrics Metrics-retrieving instance.
	 */
	public function __construct( $metrics ) {
		$this->metrics     = $metrics;
		$this->version     = $this->metrics->get_db_version();
		$this->name        = $this->version->server_name;
		$this->utilities   = PerflabDbUtilities::get_instance();
		$this->table_stats = $this->metrics->get_table_info();
	}

	/** Add all Site Health tests.
	 *
	 * @param array $tests Pre-existing tests.
	 *
	 * @return array Augmented tests.
	 */
	public function add_tests( $tests ) {
		$test_number = 0;
		$label       = __( 'Database Performance One', 'performance-lab' );
		$tests['direct'][ 'database_performance' . $test_number++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'server_version_test' ),
		);
		$tests['direct'][ 'database_performance' . $test_number++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'minimal_server_response_test' ),
		);
		$tests['direct'][ 'database_performance' . $test_number++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'core_tables_format_test' ),
		);

		return $tests;
	}

	/** Generate health-check result array.
	 *
	 * @param string $label Test label visible to user.
	 * @param string $description Test long description visible to user.
	 * @param string $actions Actions to take to correct the problem, visible to user, default ''.
	 * @param string $status 'critical', 'recommended', 'good', default 'good'.
	 * @param string $color a color specifier like 'blue' or 'red', default 'blue'.
	 *
	 * @return array
	 */
	private function test_result( $label, $description, $actions = '', $status = 'good', $color = 'blue' ) {
		$this->test_sequence_number ++;
		$result = array(
			'label'       => esc_html( $label ),
			'status'      => $status,
			'description' => $description,
			'badge'       => array(
				'label' => esc_html__( 'Database Performance', 'performance-lab' ),
				'color' => $color,
			),
			'actions'     => is_string( $actions ) ? $actions : '',
			'test'        => 'database_performance' . $this->test_sequence_number,
		);

		/**
		 * Filter database performance troubleshooting results.
		 *
		 * @since 1.4.0
		 *
		 * @param array $value The result set.
		 */

		return apply_filters( 'perflab_db_test_result', $result );

	}

	/** Check server version for Barracuda availability.
	 *
	 * @return array
	 */
	public function server_version_test() {
		if ( isset( $this->version->failure ) && is_string( $this->version->failure ) ) {
			$this->skip_all_tests = true;
			return $this->test_result(
				__( 'Upgrade your outdated WordPress installation', 'performance-lab' ),
				$this->version->failure,
				$this->version->failure_action,
				'critical'
			);
		}
		if ( 0 === $this->version->unconstrained ) {
			return $this->test_result(
			/* translators: 1:  MySQL or MariaDB */
				sprintf( __( 'Your SQL server (%s) is outdated', 'performance-lab' ), $this->name ),
				sprintf(
				/* translators: 1:  MySQL or MariaDB 2: actual version of database software */
					__( 'Your %1$s SQL server is a required piece of software for WordPress\'s database. WordPress uses it to store and retrieve all your site’s content and settings. The version you use (%2$s) does not offer the fastest way to retrieve content. This affects you if you have many posts or users.', 'performance-lab' ),
					$this->name,
					$this->version->version
				),
				__( 'For best performance we recommend running MySQL version 5.7 or higher or MariaDB 10.3 or higher. Contact your web hosting company to correct this.', 'performance-lab' ),
				'recommended'
			);
		}

		return $this->test_result(
		/* translators: 1:  MySQL or MariaDB */
			sprintf( __( 'Your %s SQL server is a recent version', 'performance-lab' ), $this->name ),
			sprintf(
			/* translators: 1:  MySQL or MariaDB 2: actual version string (e.g. '10.3.34-MariaDB-0ubuntu0.20.04.1' or '  of database software */
				__( 'Your %1$s SQL server is a required piece of software for WordPress\'s database. The version you use (%2$s) offers an efficient way to retrieve your content and settings.', 'performance-lab' ),
				$this->name,
				$this->version->version
			)
		);
	}

	/** Inspect a set of tables to ensure they have the correct storage engine and row format.
	 *
	 * @param array $stats Result set from get_table_info.
	 *
	 * @return array The tables with the wrong format.
	 */
	private function get_wrong_format_tables( $stats ) {
		$result                = array();
		$target_storage_engine = $this->utilities->get( 'target_storage_engine' );
		$target_row_format     = $this->utilities->get( 'target_row_format' );
		foreach ( $stats as $table => $stat ) {
			$hit = strtolower( $stat->engine ) !== strtolower( $target_storage_engine );
			$hit = $hit | strtolower( $stat->row_format ) !== strtolower( $target_row_format );
			if ( $hit ) {
				$result[ $table ] = $stat;
			}
		}
		return $result;
	}

	/** Check core tables format
	 *
	 * @return array
	 */
	public function core_tables_format_test() {
		if ( $this->skip_all_tests ) {
			return array();
		}
		global $wpdb;
		$tables = $wpdb->tables();
		$stats  = array_intersect_key( $this->table_stats, array_combine( $tables, $tables ) );
		$bad    = $this->get_wrong_format_tables( $stats );
		if ( count( $bad ) === 0 ) {
			return $this->test_result(
				__( 'Your WordPress tables use the modern format', 'performance-lab' ),
				__( 'Your WordPress tables use the appropriate storage engine and row format for good database performance.', 'performance-lab' )
			);
		} elseif ( count( $bad ) === count( $stats ) ) {
			return $this->test_result(
			/* translators: 1:  MySQL or MariaDB */
				sprintf( __( 'All your %s SQL server WordPress tables formats suck', 'performance-lab' ), $this->name ),
				__( 'All your WordPress tables use an obsolete storage engine and/or row format.', 'performance-lab' ),
				'Fix them KTNKSBAI',
				'recommended',
				'orange'
			);
		} else {
			$desc = '<div class="description">' . __( 'These WordPress tables use an obsolete storage engine and/or row format.', 'performance-lab' ) . '</div>';
			$ts   = array();
			foreach ( $bad as $table => $data ) {
				$ts [] = '<li class="tablename">' . $table . '</li>';
			}
			$desc .= '<ul class="tables">' . implode( PHP_EOL, $ts ) . '</ul>';
			return $this->test_result(
			/* translators: 1:  MySQL or MariaDB */
				sprintf( __( 'Some of your %s SQL server WordPress tables formats suck', 'performance-lab' ), $this->name ),
				$desc,
				'Fix them KTNKSBAI',
				'recommended',
				'orange'
			);

		}

	}
	/** Check server connection response time.
	 *
	 * @return array
	 */
	public function minimal_server_response_test() {
		if ( $this->skip_all_tests ) {
			return array();
		}
		$results   = $this->metrics->server_response(
			$this->utilities->get( 'server_response_iterations' ),
			$this->utilities->get( 'server_response_timeout' )
		);
		$formatted = number_format_i18n( $results, 2 );
		if ( $results >= $this->utilities->get( 'server_response_very_slow' ) ) {
			return $this->test_result(
			/* translators: 1:  MySQL or MariaDB */
				sprintf( __( 'Your %s SQL server connects and responds very slowly', 'performance-lab' ), $this->name ),
				sprintf(
				/* translators: 1:  number of milliseconds */
					__( 'Your SQL server connects and responds to simple requests from WordPress in %1$s milliseconds. For best performance it should respond in under 1 millisecond.', 'performance-lab' ),
					$formatted
				),
				__( 'This usually means your SQL server is overburdened. Possibly many sites share it, or possibly it needs more RAM. Contact your web hosting company to correct this.', 'performance-lab' ),
				'critical',
				'red'
			);

		} elseif ( $results >= $this->utilities->get( 'server_response_slow' ) ) {
			return $this->test_result(
			/* translators: 1:  MySQL or MariaDB */
				sprintf( __( 'Your %s SQL server connects and responds slowly', 'performance-lab' ), $this->name ),
				sprintf(
				/* translators: 1:  number of milliseconds */
					__( 'Your SQL server connects and responds to simple requests from WordPress in %1$s milliseconds. For best performance it should respond in under 1 millisecond.', 'performance-lab' ),
					$formatted
				),
				__( 'This usually means your SQL server is busy. Possibly it is shared among many sites, or possibly it needs more RAM. Contact your web hosting company to correct this.', 'performance-lab' ),
				'recommended',
				'orange'
			);

		} else {
			return $this->test_result(
			/* translators: 1:  MySQL or MariaDB */
				sprintf( __( 'Your %s SQL server connects and responds promptly', 'performance-lab' ), $this->name ),
				sprintf(
				/* translators: 1:  number of milliseconds */
					__( 'Your SQL server connects and responds to simple requests from WordPress in %1$s milliseconds. This allows good performance.', 'performance-lab' ),
					$formatted
				)
			);

		}
	}

}
