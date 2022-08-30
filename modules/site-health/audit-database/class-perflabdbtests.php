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

	/** Results accumulator.
	 *
	 * @var [string]
	 */
	private $results = array();

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
		$tests['direct']['database_performance'] = array(
			'label' => __( 'Database', 'performance-lab' ),
			'test'  => array( $this, 'alltests' ),
		);
		return $tests;
	}

	/** Accumulate results, tracking status.
	 *
	 * @param array $result Test result from individual test.
	 *
	 * @return void
	 */
	private function add_result( $result ) {
		$status = $result['status'];
		if ( ! array_key_exists( $status, $this->results ) ) {
			$this->results[ $status ] = array();
		}
		$this->results[ $status ] [] = $result;
	}

	/** Run all detailed tests.
	 *
	 * @return array
	 */
	public function alltests() {
		$this->add_result( $this->server_version_test() );
		$this->add_result( $this->tables_format_test() );
		$this->add_result( $this->buffer_pool_size_test() );
		$this->add_result( $this->minimal_server_response_test() );

		$description = array();

		$result_status = 'good';
		$result_status = array_key_exists( 'recommended', $this->results ) ? 'recommended' : $result_status;
		$result_status = array_key_exists( 'critical', $this->results ) ? 'critical' : $result_status;

		$status_display = 'good' === $result_status
			? array( 'critical', 'recommended', 'good' )
			: array( 'critical', 'recommended' );
		foreach ( $status_display as $status ) {
			if ( array_key_exists( $status, $this->results ) ) {
				foreach ( $this->results[ $status ] as $result ) {
					$description [] = '<div class="stanza">';
					$description [] = '<div>' . $result['description'] . '</div>';
					if ( array_key_exists( 'actions', $result ) && is_string( $result['actions'] ) && strlen( $result['actions'] ) > 0 ) {
						$description [] = '<div class="actions">' . $result['actions'] . '</div>';
					}
					$description [] = '</div>';
				}
			}
		}

		$description = implode( PHP_EOL, $description );
		switch ( $result_status ) {
			case 'critical':
			case 'recommended':
				$label = sprintf(
					/* translators: 1:  MySQL or MariaDB */
					__( 'Configure your %1$s database server for improved performance', 'performance-lab' ),
					$this->name
				);
				break;
			case 'good':
			default:
				$label = sprintf(
				/* translators: 1:  MySQL or MariaDB */
					__( 'Your %1$s server appears to be configured for good performance', 'performance-lab' ),
					$this->name
				);
		}
		$action = ( 'good' !== $result_status )
			? sprintf(
				/* translators: 1: MySQL or MariaDB */
				__( 'Contact your server administrator or web hosting company for help configuring your %1$s server.', 'performance-lab' ),
				$this->name
			)
			: null;
		return $this->utilities->test_result( $label, $description, $action, $result_status );

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
					__( 'WordPress uses your %1$s server to store and retrieve all your site’s content and settings. The version you use (%2$s) does not offer the most modern and efficient way to store your content and settings. This affects you if you have many posts or users.', 'performance-lab' ),
					$this->name,
					$this->version->version
				),
				__( 'For optimal performance we recommend running MySQL version 5.7 or higher or MariaDB version 10.3 or higher. Contact your web hosting company to correct this.', 'performance-lab' ),
				'recommended'
			);
		}

		return $this->utilities->test_result(
		/* translators: 1:  MySQL or MariaDB */
			sprintf( __( 'Your %s server is a recent version', 'performance-lab' ), $this->name ),
			sprintf(
			/* translators: 1:  MySQL or MariaDB  */
				__( 'WordPress uses your %1$s SQL server to store your site’s content and settings. Your version offers the modern and efficient way to store your content and settings.', 'performance-lab' ),
				$this->name
			)
		);
	}

	/** Check core tables format
	 *
	 * @return array
	 */
	public function tables_format_test() {
		if ( $this->skip_all_tests ) {
			return array();
		}
		global $wpdb;
		/* Get stats for the core tables. */
		$stats                 = $this->table_stats;
		$target_storage_engine = $this->utilities->get_threshold_value( 'target_storage_engine' );
		$target_row_format     = $this->utilities->get_threshold_value( 'target_row_format' );
		$wrong_format_tables   = $this->metrics->get_wrong_format_tables( $stats, $target_storage_engine, $target_row_format );
		if ( count( $wrong_format_tables ) === 0 ) {
			return $this->utilities->test_result(
				__( 'Your tables use the modern storage engine', 'performance-lab' ),
				__( 'Your tables use the latest storage engine for good database performance.', 'performance-lab' )
			);
		} else {
			$label = count( $wrong_format_tables ) === count( $stats )
				? __( 'Your tables all use an obsolete storage format', 'performance-lab' )
				: __( 'Some tables use an obsolete storage format', 'performance-lab' );

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
			$msgs[]    = sprintf(
				/* translators: 1: memory size like 512MiB  2: server name like MySQL  3: memory size */
				__( 'The keys on your MyISAM tables (the obsolete storage engine) use %1$s, but %2$s\'s MyISAM key buffer size (the system variable named \'key_buffer_size\') is only %3$s. That may be too small.', 'performance-lab' ),
				$this->utilities->format_bytes( $myisam_size ),
				$this->name,
				$this->utilities->format_bytes( $myisam_pool_size )
			);
		} elseif ( 0 === $myisam_size ) {
			/* empty, intentionally. Don't pester the user about MyISAM here if they don't use it. */
		} else {
			$msgs[] = sprintf(
				/* translators: 1: memory size like 512MiB  2: server name like MySQL  3: memory size */
				__( 'The keys on your MyISAM tables (the obsolete storage engine) use %1$s and %2$s\'s MyISAM key buffer size is %3$s. That is big enough in most cases.', 'performance-lab' ),
				$this->utilities->format_bytes( $myisam_size ),
				$this->name,
				$this->utilities->format_bytes( $myisam_pool_size )
			);
		}
		if ( $innodb_size > 0 && ( $innodb_pool_size / $fraction ) < $innodb_size ) {
			$too_small = true;
			$msgs[]    = sprintf(
				/* translators: 1: memory size like 512MiB  2: server name like MySQL  3: memory size */
				__( 'Your InnoDB tables (the modern storage engine) use %1$s, but %2$s\'s InnoDb buffer pool size (the system variable named \'innodb_buffer_pool_size\') is only %3$s. That may be too small.', 'performance-lab' ),
				$this->utilities->format_bytes( $innodb_size ),
				$this->name,
				$this->utilities->format_bytes( $innodb_pool_size )
			);
		} else {
			$msgs[] = sprintf(
				/* translators: 1: memory size like 512MiB  2: server name like MySQL  3: memory size */
				__( 'Your InnoDB tables (the modern storage engine) use %1$s and %2$s\'s InnoDb buffer pool size is %3$s. That is big enough in most cases.', 'performance-lab' ),
				$this->utilities->format_bytes( $innodb_size ),
				$this->name,
				$this->utilities->format_bytes( $innodb_pool_size )
			);
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
					__( 'Your %1$s buffer pool is too small. That makes it take longer to retrieve your content, especially when your site is busy.', 'performance-lab' ),
					$this->name
				) . implode( '<br/>', $msgs ),
				__( 'Consider asking your server administrator to upgrade your SQL server\'s buffer pool size.', 'performance-lab' ),
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
				implode( '<br/>', $msgs ),
				sprintf(
				/* translators: 1 server name like MariaDB */
					__( 'Some web hosting companies share their %1$s servers among multiple WordPress sites. In that case the buffer pool you share with those other sites may still be inadequate. Site Health cannot detect shared %1$s servers.', 'performance-lab' ),
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
					__( 'Your SQL server connects and responds to simple requests from WordPress very slowly, in %1$s milliseconds. For best performance it should respond in under %2$s.', 'performance-lab' ),
					$formatted_results,
					$formatted_threshold
				),
				__( 'This usually means your SQL server is overburdened. Too many sites may share it, or it may need more RAM. Contact your web hosting company to correct this.', 'performance-lab' ),
				'critical',
				'red'
			);
		} elseif ( $results > $slow_response ) {
			return $this->utilities->test_result(
			/* translators: 1:  MySQL or MariaDB */
				sprintf( __( 'Your %s server connects and responds slowly', 'performance-lab' ), $this->name ),
				sprintf(
				/* translators: 1:  number of milliseconds  2: milliseconds */
					__( 'Your SQL server connects and responds to simple requests from WordPress slowly, in %1$s milliseconds. For best performance it should respond in under %2$s.', 'performance-lab' ),
					$formatted_results,
					$formatted_threshold
				),
				__( 'This usually means your SQL server is busy. Too many sites may share it, or it may need more RAM. Contact your web hosting company to correct this.', 'performance-lab' ),
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
				__( 'Ask your server administrator to upgrade these tables to use the %1$s storage engine and the %2$s row format.', 'performance-lab' ),
				$target_storage_engine,
				$target_row_format
			)
			: sprintf(
			/* translators: 1 storage engine name, usually InnoDB   */
				__( 'Ask your server administrator to upgrade these tables to use the %1$s storage engine.', 'performance-lab' ),
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
