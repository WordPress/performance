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
	/** Thresholds instance.
	 *
	 * @var PerflabDbUtilities instance.
	 */
	private $utilities;
	/** Table Information.
	 *
	 * @var array associative array of table information.
	 */
	private $table_stats;

	/** Constructor for tests.
	 *
	 * @param PerflabDbMetrics $metrics Metrics-retrieving instance.
	 */
	public function __construct( $metrics ) {

		$this->metrics     = $metrics;
		$this->version     = $this->metrics->get_db_version();
		$this->name        = $this->version->server_name;
		$this->utilities   = PerflabDbUtilities::get_instance();
		$this->table_stats = $this->metrics->get_table_info();
	}

	/** Add all Site Health checks for database performance.
	 *
	 * @param array $tests Pre-existing tests.
	 *
	 * @return array Augmented tests.
	 */
	public function add_all_database_performance_checks( $tests ) {
		$test_number = 0;
		$label       = __( 'Database', 'performance-lab' );
		$tests['direct'][ 'database_performance' . $test_number ++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'server_version_test' ),
		);
		$tests['direct'][ 'database_performance' . $test_number ++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'minimal_server_response_test' ),
		);
		$tests['direct'][ 'database_performance' . $test_number ++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'core_tables_format_test' ),
		);

		$tests['direct'][ 'database_performance' . $test_number ++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'extra_tables_format_test' ),
		);

		$tests['direct'][ 'database_performance' . $test_number ++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'buffer_pool_size_test' ),
		);

		return $tests;
	}

	/** Check server version for Barracuda availability.
	 *
	 * @return array
	 */
	public function server_version_test() {
		if ( isset( $this->version->failure ) && is_string( $this->version->failure ) ) {
			$this->skip_all_tests = true;

			return $this->utilities->test_result(
				__( 'Upgrade your outdated WordPress installation', 'performance-lab' ),
				$this->version->failure,
				$this->version->failure_action,
				'critical'
			);
		}
		if ( 0 === $this->version->unconstrained ) {
			return $this->utilities->test_result(
			/* translators: 1:  MySQL or MariaDB */
				sprintf( __( 'Your %s server is outdated', 'performance-lab' ), $this->name ),
				sprintf(
				/* translators: 1:  MySQL or MariaDB 2: actual version of database software */
					__( 'WordPress uses your %1$s server to store and retrieve all your site’s content and settings. The version you use (%2$s) does not offer the latest InnoDB Barracuda storage engine, the most efficient way to retrieve content. This affects you if you have many posts or users.', 'performance-lab' ),
					$this->name,
					$this->version->version
				),
				__( 'For best performance we recommend running MySQL version 5.7 or higher or MariaDB version 10.3 or higher. Contact your hosting provider and ask them to upgrade their server.', 'performance-lab' ),
				'recommended'
			);
		}

		return $this->utilities->test_result(
		/* translators: 1:  MySQL or MariaDB */
			sprintf( __( 'Your %s server is a recent version', 'performance-lab' ), $this->name ),
			sprintf(
			/* translators: 1:  MySQL or MariaDB 2: actual version string (e.g. '10.3.34-MariaDB-0ubuntu0.20.04.1' or '  of database software */
				__( 'WordPress uses your %1$s SQL server to store your site’s content and settings. Your version (%2$s) offers the modern InnoDB Barracuda storage engine, an efficient way to retrieve your content and settings.', 'performance-lab' ),
				$this->name,
				$this->version->version
			)
		);
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
		/* Get stats for the core tables. */
		$stats                 = array_intersect_key( $this->table_stats, array_combine( $tables, $tables ) );
		$target_storage_engine = $this->utilities->get_threshold_value( 'target_storage_engine' );
		$target_row_format     = $this->utilities->get_threshold_value( 'target_row_format' );
		$wrong_format_tables   = $this->metrics->get_wrong_format_tables( $stats, $target_storage_engine, $target_row_format );
		if ( count( $wrong_format_tables ) === 0 ) {
			return $this->utilities->test_result(
				__( 'Your WordPress tables use the modern storage format', 'performance-lab' ),
				( 1 === $this->version->unconstrained )
					? sprintf(
						/* translators: 1 storage engine name, usually InnoDB  2: row format name, usually Dynamic */
						__( 'Your WordPress tables use the %1$s storage engine and the %2$s row format for good database performance.', 'performance-lab' ),
						$target_storage_engine,
						$target_row_format
					)
					: sprintf(
						/* translators: 1 storage engine name, usually InnoDB. */
						__( 'Your WordPress tables use the %1$s storage engine for good database performance.', 'performance-lab' ),
						$target_storage_engine,
						$target_row_format
					)
			);
		} else {
			$label = count( $wrong_format_tables ) === count( $stats )
				? __( 'Your WordPress tables use an obsolete storage format', 'performance-lab' )
				: __( 'Some WordPress tables use an obsolete storage format', 'performance-lab' );

			$explanation = sprintf(
				/* translators: 1 MySQL or MariaDB */
				__( '%1$s performance improves when your WordPress tables use the modern storage format.', 'performance-lab' ),
				$this->name
			);
			$actions = $this->list_wrong_format_tables( $wrong_format_tables, $target_storage_engine, $target_row_format );

			return $this->utilities->test_result(
				$label,
				$explanation,
				$actions,
				'recommended',
				'orange'
			);

		}
	}

	/** Check extra tables format
	 *
	 * @return array
	 */
	public function extra_tables_format_test() {
		if ( $this->skip_all_tests ) {
			return array();
		}
		global $wpdb;
		$tables = $wpdb->tables();
		/* Get stats for the non-core tables: the ones not mentioned in $wpdb->tables() */
		$stats = array_diff_key( $this->table_stats, array_combine( $tables, $tables ) );
		if ( 0 === count( $stats ) ) {
			/* don't pester the user when they have no plugin tables */
			return array();
		}
		$target_storage_engine = $this->utilities->get_threshold_value( 'target_storage_engine' );
		$target_row_format     = $this->utilities->get_threshold_value( 'target_row_format' );
		$wrong_format_tables   = $this->metrics->get_wrong_format_tables( $stats, $target_storage_engine, $target_row_format );
		if ( count( $wrong_format_tables ) === 0 ) {
			return $this->utilities->test_result(
				__( 'Your plugin tables use the modern storage format', 'performance-lab' ),
				( 1 === $this->version->unconstrained )
					? sprintf(
						/* translators: 1 storage engine name, usually InnoDB  2: row format name, usually Dynamic */
						__( 'Tables managed by your plugins use the %1$s storage engine and the %2$s row format for good database performance.', 'performance-lab' ),
						$target_storage_engine,
						$target_row_format
					)
					: sprintf(
						/* translators: 1 storage engine name, usually InnoDB   */
						__( 'Tables managed by your plugins use the %1$s storage engine for good database performance.', 'performance-lab' ),
						$target_storage_engine,
						$target_row_format
					)
			);
		} else {
			$label = count( $wrong_format_tables ) === count( $stats )
				? __( 'Your plugin tables use an obsolete storage format', 'performance-lab' )
				: __( 'Some plugin tables use an obsolete storage format', 'performance-lab' );

			$explanation = sprintf(
			/* translators: 1 MySQL or MariaDB */
				__( '%1$s performance improves when your plugins\' tables use the modern storage format.', 'performance-lab' ),
				$this->name
			);
			$actions = $this->list_wrong_format_tables( $wrong_format_tables, $target_storage_engine, $target_row_format );

			return $this->utilities->test_result(
				$label,
				$explanation,
				$actions,
				'recommended',
				'orange'
			);
		}
	}

	/** Check data size against buffer pool size
	 *
	 * @return array
	 */
	public function buffer_pool_size_test() {
		if ( $this->skip_all_tests ) {
			return array();
		}
		$innodb_size = 0;
		$myisam_size = 0;
		foreach ( $this->table_stats as $stat ) {
			switch ( strtolower( $stat->engine ) ) {
				case 'innodb':
					$innodb_size += $stat->total_bytes;
					break;
				case 'myisam':
					/* myisam only buffers indexes inside itself */
					$myisam_size += $stat->index_bytes;
					break;
			}
		}
		$fraction = $this->utilities->get_threshold_value( 'pool_size_fraction_min' );

		$myisam_pool_size = $this->metrics->get_buffer_pool_size( 'MyISAM' );
		$innodb_pool_size = $this->metrics->get_buffer_pool_size( 'InnoDB' );

		$too_small = false;
		$msgs      = array();

		if ( $myisam_size > 0 && ( $myisam_pool_size / $fraction ) < $myisam_size ) {
			$too_small = true;
			$msgs[]    = '<p>' . sprintf(
				/* translators: 1: memory size like 512MiB  2: server name like MySQL  3: memory size */
				__( 'The keys on your MyISAM tables (the obsolete storage engine) use %1$s, but %2$s\'s MyISAM key buffer size (the system variable named \'key_buffer_size\') is only %3$s. That may be too small.', 'performance-lab' ),
				$this->utilities->format_bytes( $myisam_size ),
				$this->name,
				$this->utilities->format_bytes( $myisam_pool_size )
			) . '</p>';
		} elseif ( 0 === $myisam_size ) {
			/* empty, intentionally. Don't pester the user about MyISAM here if they don't use it. */
		} else {
			$msgs[] = '<p>' . sprintf(
				/* translators: 1: memory size like 512MiB  2: server name like MySQL  3: memory size */
				__( 'The keys on your MyISAM tables (the obsolete storage engine) use %1$s and %2$s\'s MyISAM key buffer size is %3$s. That is big enough in most cases.', 'performance-lab' ),
				$this->utilities->format_bytes( $myisam_size ),
				$this->name,
				$this->utilities->format_bytes( $myisam_pool_size )
			) . '</p>';
		}
		if ( $innodb_size > 0 && ( $innodb_pool_size / $fraction ) < $innodb_size ) {
			$too_small = true;
			$msgs[]    = '<p>' . sprintf(
				/* translators: 1: memory size like 512MiB  2: server name like MySQL  3: memory size */
				__( 'Your InnoDB tables (the modern storage engine) use %1$s, but %2$s\'s InnoDb buffer pool size (the system variable named \'innodb_buffer_pool_size\') is only %3$s. That may be too small.', 'performance-lab' ),
				$this->utilities->format_bytes( $innodb_size ),
				$this->name,
				$this->utilities->format_bytes( $innodb_pool_size )
			) . '</p>';
		} else {
			$msgs[] = '<p>' . sprintf(
				/* translators: 1: memory size like 512MiB  2: server name like MySQL  3: memory size */
				__( 'Your InnoDB tables (the modern storage engine) use %1$s and %2$s\'s InnoDb buffer pool size is %3$s. That is big enough in most cases.', 'performance-lab' ),
				$this->utilities->format_bytes( $innodb_size ),
				$this->name,
				$this->utilities->format_bytes( $innodb_pool_size )
			) . '</p>';
		}
		if ( $too_small ) {
			return $this->utilities->test_result(
				sprintf(
				/* translators: 1 server name like MariaDB */
					__( 'Your %1$s server buffer pool is too small for your data', 'performance-lab' ),
					$this->name
				),
				sprintf(
				/* translators: 1 server name like MariaDB */
					'<p>' . __( 'Your %1$s buffer pool is too small. That makes it take longer to retrieve your content, especially when your site is busy.', 'performance-lab' ),
					$this->name
				) . '</p><p>' . implode( ' ', $msgs ) . '</p>',
				__( 'Consider asking your hosting provider to upgrade your SQL server\'s buffer pool size.', 'performance-lab' ),
				'recommended',
				'orange'
			);
		} else {
			return $this->utilities->test_result(
				sprintf(
				/* translators: 1 server name like MariaDB */
					__( 'Your %1$s server buffer pool is big enough for your data', 'performance-lab' ),
					$this->name
				),
				implode( '', $msgs ),
				sprintf(
				/* translators: 1 server name like MariaDB */
					__( 'Some hosting providers share their %1$s servers among multiple WordPress sites. In that case the buffer pool you share with those other sites may still be inadequate. Site Health cannot detect shared %1$s servers.', 'performance-lab' ),
					$this->name
				)
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
		$iterations       = $this->utilities->get_threshold_value( 'server_response_iterations' );
		$response_timeout = $this->utilities->get_threshold_value( 'server_response_timeout' );
		/* results are in microseconds, and we report in milliseconds */
		$results             = round( 0.001 * $this->metrics->server_response( $iterations, $response_timeout ), 1 );
		$formatted_results   = number_format_i18n( $results, 1 );
		$very_slow_response  = round( $this->utilities->get_threshold_value( 'server_response_very_slow' ), 1 );
		$slow_response       = round( $this->utilities->get_threshold_value( 'server_response_slow' ), 1 );
		$formatted_threshold = number_format_i18n( $slow_response, 1 );
		if ( $results > $very_slow_response ) {
			return $this->utilities->test_result(
			/* translators: 1:  MySQL or MariaDB */
				sprintf( __( 'Your %s server connects and responds very slowly', 'performance-lab' ), $this->name ),
				sprintf(
				/* translators: 1:  number of milliseconds 2: milliseconds */
					__( 'Your SQL server connects and responds to simple requests from WordPress in %1$s milliseconds. For best performance it should respond in under %2$s.', 'performance-lab' ),
					$formatted_results,
					$formatted_threshold
				),
				__( 'This usually means your SQL server is overburdened. Possibly many sites share it, or possibly it needs more RAM. Contact your web hosting company to correct this.', 'performance-lab' ),
				'critical',
				'red'
			);
		} elseif ( $results > $slow_response ) {
			return $this->utilities->test_result(
			/* translators: 1:  MySQL or MariaDB */
				sprintf( __( 'Your %s server connects and responds slowly', 'performance-lab' ), $this->name ),
				sprintf(
				/* translators: 1:  number of milliseconds  2: milliseconds */
					__( 'Your SQL server connects and responds to simple requests from WordPress in %1$s milliseconds. For best performance it should respond in under %2$s.', 'performance-lab' ),
					$formatted_results,
					$formatted_threshold
				),
				__( 'This usually means your SQL server is busy. Possibly it is shared among many sites, or possibly it needs more RAM. Contact your web hosting company to correct this.', 'performance-lab' ),
				'recommended',
				'orange'
			);
		} else {
			return $this->utilities->test_result(
			/* translators: 1:  MySQL or MariaDB */
				sprintf( __( 'Your %s server connects and responds promptly', 'performance-lab' ), $this->name ),
				sprintf(
				/* translators: 1:  number of milliseconds */
					__( 'Your SQL server connects and responds to simple requests from WordPress in %1$s milliseconds. This supports good site performance.', 'performance-lab' ),
					$formatted_results
				)
			);
		}
	}

	/** Generate HTML for a list of tables.
	 *
	 * @param array  $wrong_format_tables Associative array of tables with names to render.
	 * @param string $target_storage_engine Usually InnoDb.
	 * @param string $target_row_format Usually DYNAMIC.
	 *
	 * @return string
	 */
	private function list_wrong_format_tables( array $wrong_format_tables, $target_storage_engine, $target_row_format ) {
		$exhortation = 1 === $this->version->unconstrained
			? sprintf(
			/* translators: 1 storage engine name, usually InnoDB  2: row format name, usually Dynamic */
				__( 'Ask your hosting provider to upgrade these tables to use the %1$s storage engine and the %2$s row format.', 'performance-lab' ),
				$target_storage_engine,
				$target_row_format
			)
			: sprintf(
			/* translators: 1 storage engine name, usually InnoDB   */
				__( 'Ask your hosting provider to upgrade these tables to use the %1$s storage engine.', 'performance-lab' ),
				$target_storage_engine,
				$target_row_format
			);
		$actions    = array();
		$actions [] = $exhortation;
		$actions [] = '<ul class="tablelist">';
		foreach ( $wrong_format_tables as $name => $x ) {
			$actions [] = '<li>' . esc_html( $name ) . '</li>';
		}
		$actions [] = '</ul>';

		return implode( '', $actions );
	}

}
