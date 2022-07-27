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
		$label       = __( 'Database Performance One', 'performance-lab' );
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

		$tests['direct'][ 'database_performance' . $test_number ++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'myisam_pool_test' ),
		);

		$tests['direct'][ 'database_performance' . $test_number ++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'too_many_users_test' ),
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
				sprintf( __( 'Your SQL server (%s) is outdated', 'performance-lab' ), $this->name ),
				sprintf(
				/* translators: 1:  MySQL or MariaDB 2: actual version of database software */
					__( 'WordPress uses your %1$s server to store and retrieve all your site’s content and settings. The version you use (%2$s) does not offer the latest InnoDB Barracuda storage engine, the most efficient way to retrieve content. This affects you if you have many posts or users.', 'performance-lab' ),
					$this->name,
					$this->version->version
				),
				__( 'For best performance we recommend running MySQL version 5.7 or higher or MariaDB 10.3 or higher. Contact your hosting provider and ask them to upgrade their server.', 'performance-lab' ),
				'recommended'
			);
		}

		return $this->utilities->test_result(
		/* translators: 1:  MySQL or MariaDB */
			sprintf( __( 'Your %s SQL server is a recent version', 'performance-lab' ), $this->name ),
			sprintf(
			/* translators: 1:  MySQL or MariaDB 2: actual version string (e.g. '10.3.34-MariaDB-0ubuntu0.20.04.1' or '  of database software */
				__( 'Your %1$s SQL server is a required piece of software for WordPress\'s database. Your version (%2$s) offers the modern InnoDB Barracuda storage engine, an efficient way to retrieve your content and settings.', 'performance-lab' ),
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
		$bad                   = $this->metrics->get_wrong_format_tables( $stats, $target_storage_engine, $target_row_format );
		if ( count( $bad ) === 0 ) {
			return $this->utilities->test_result(
				__( 'Your WordPress tables use the modern storage format', 'performance-lab' ),
				sprintf(
				/* translators: 1 storage engine name, usually InnoDB  2: row format name, usually Dynamic */
					__( 'Your tables use the %1$s storage engine and the %2$s row format for good database performance.', 'performance-lab' ),
					$target_storage_engine,
					$target_row_format
				)
			);
		} else {
			$label = count( $bad ) === count( $stats )
				? __( 'Your WordPress tables use an obsolete storage format', 'performance-lab' )
				: __( 'Some WordPress tables use an obsolete storage format', 'performance-lab' );

			return $this->table_upgrade_instructions( $target_storage_engine, $target_row_format, $bad, $label );
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
				sprintf(
				/* translators: 1 storage engine name, usually InnoDB  2: row format name, usually Dynamic */
					__( 'Tables managed by your plugins use the %1$s storage engine and the %2$s row format for good database performance.', 'performance-lab' ),
					$target_storage_engine,
					$target_row_format
				)
			);
		} else {
			$label = count( $wrong_format_tables ) === count( $stats )
				? __( 'Your plugin tables use an obsolete storage format', 'performance-lab' )
				: __( 'Some plugin tables use an obsolete storage format', 'performance-lab' );

			return $this->table_upgrade_instructions( $target_storage_engine, $target_row_format, $wrong_format_tables, $label );
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
				__( 'The keys on your MyISAM tables (the obsolete storage engine) use %1$s, but %2$s\'s buffer size (\'Key_buffer_size\') is only %3$s. That may be too small.', 'performance-lab' ),
				$this->utilities->format_bytes( $myisam_size ),
				$this->name,
				$this->utilities->format_bytes( $myisam_pool_size )
			) . '</p>';
		} elseif ( 0 === $myisam_size ) {
			/* empty, intentionally. Don't pester the user about MyISAM here if they don't use it. */
		} else {
			$msgs[] = '<p>' . sprintf(
				/* translators: 1: memory size like 512MiB  2: server name like MySQL  3: memory size */
				__( 'The keys on your MyISAM tables (the obsolete storage engine) use %1$s and %2$s\'s key buffer size is %3$s. That is big enough in most cases.', 'performance-lab' ),
				$this->utilities->format_bytes( $myisam_size ),
				$this->name,
				$this->utilities->format_bytes( $myisam_pool_size )
			) . '</p>';
		}
		if ( $innodb_size > 0 && ( $innodb_pool_size / $fraction ) < $innodb_size ) {
			$too_small = true;
			$msgs[]    = '<p>' . sprintf(
				/* translators: 1: memory size like 512MiB  2: server name like MySQL  3: memory size */
				__( 'Your InnoDB tables (the modern storage engine) use %1$s, but %2$s\'s buffer pool size (\'Innodb_buffer_pool_size\') is only %3$s. That may be too small.', 'performance-lab' ),
				$this->utilities->format_bytes( $innodb_size ),
				$this->name,
				$this->utilities->format_bytes( $innodb_pool_size )
			) . '</p>';
		} else {
			$msgs[] = '<p>' . sprintf(
				/* translators: 1: memory size like 512MiB  2: server name like MySQL  3: memory size */
				__( 'Your InnoDB tables (the modern storage engine) use %1$s and %2$s\'s buffer pool size is %3$s. That is big enough in most cases.', 'performance-lab' ),
				$this->utilities->format_bytes( $innodb_size ),
				$this->name,
				$this->utilities->format_bytes( $innodb_pool_size )
			) . '</p>';
		}
		if ( $too_small ) {
			return $this->utilities->test_result(
				sprintf(
				/* translators: 1 server name like MariaDB */
					__( 'Your %1$s SQL server buffer pool is too small for your data', 'performance-lab' ),
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
					__( 'Your %1$s SQL server buffer pool is big enough for your data', 'performance-lab' ),
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

	/** Check data size against buffer pool size
	 *
	 * @return array
	 */
	public function myisam_pool_test() {
		if ( $this->skip_all_tests ) {
			return array();
		}
		$myisam_size = 0;
		foreach ( $this->table_stats as $stat ) {
			/* myisam only buffers indexes inside itself */
			if ( 'myisam' === strtolower( $stat->engine ) ) {
				$myisam_size += $stat->index_bytes;
			}
		}

		$myisam_size_display = $this->utilities->format_bytes( $myisam_size );

		$track                   = $this->metrics->tracking_variable_changes();
		$key_buffer_size         = $track ['key_buffer_size'];
		$cache_size              = $key_buffer_size;
		$cache_size_display      = $this->utilities->format_bytes( $cache_size );
		$cache_hit_rate_bad      = $this->utilities->get_threshold_value( 'cache_hit_rate_bad' );
		$target_hit_rate_display = number_format_i18n( $cache_hit_rate_bad * 100, 1 ) . '%';

		if ( $track ['Key_read_requests'] > 0 && $myisam_size > 0 ) {
			/* MyISAM is in play */
			list( $hit_rate_display, $terrible, $good ) = $this->figure_hit_rate( $track );

			if ( ! $good ) {
				$target_key_buffer_size = $this->utilities->power_of_two_round_up( $myisam_size );
				$target_key_buffer_size = $terrible ? $target_key_buffer_size * 2 : $target_key_buffer_size;
				if ( $target_key_buffer_size < $key_buffer_size ) {
					$target_key_buffer_size = $terrible ? $key_buffer_size * 4 : $key_buffer_size * 2;
					$target_key_buffer_size = $this->utilities->power_of_two_round_up( $target_key_buffer_size );
				}
				$target_key_buffer_size_display = $this->utilities->format_bytes( $target_key_buffer_size );

				/* translators: 1; size like 64MiB  2: size like 64Mib  3: percent like 81.2%  4: percent like 81.2% */
				$msg     = __( 'The keys on your MyISAM tables (the obsolete storage engine) use %1$s. Your MyISAM key buffer size is %2$s and its hit rate is too low at %3$s. An acceptable hit rate is %4$s. Your tables ', 'performance-lab' );
				$msg     = sprintf( $msg, $cache_size_display, $myisam_size_display, $hit_rate_display, $target_hit_rate_display );
				$action  = __( 'Consider asking your hosting provider to', 'performance-lab' ) . ' ';
				$action .= $this->get_buffer_recommendation( 'key_buffer_size', $key_buffer_size, $target_key_buffer_size );
				if ( $terrible ) {
					return $this->utilities->test_result(
						sprintf(
						/* translators: 1 server name like MariaDB */
							__( 'Your %1$s MyISAM key buffer size is far too small', 'performance-lab' ),
							$this->name
						),
						$msg,
						$action,
						'critical',
						'red'
					);
				}

				return $this->utilities->test_result(
					sprintf(
					/* translators: 1 server name like MariaDB */
						__( 'Your %1$s MyISAM key buffer size is too small', 'performance-lab' ),
						$this->name
					),
					$msg,
					$action,
					'recommended',
					'orange'
				);
			}
		} elseif ( $myisam_size * 2 < $cache_size && $cache_size > 65536 * 4 && 0 === $track ['Key_read_requests'] ) {
			$target_size = $this->utilities->power_of_two_round_up( $myisam_size );
			$target_size = max( $target_size, 65536 );
			if ( $myisam_size <= 0 ) {
				/* translators: 1: size like 64Mib  2: MySQL or MariaDB  */
				$msg = __( 'You do not use MyISAM (the obsolete storage engine) for any tables. But your MyISAM key buffer size is %1$s. That may be larger than you need, wasting RAM space in your %2$s server.', 'performance-lab' );
				$msg = sprintf( $msg, $cache_size_display, $this->name );
			} else {
				/* translators: 1; size like 64MiB  2: size like 64Mib 3: MySQL or MariaDB  */
				$msg = __( 'The keys on your MyISAM tables (the obsolete storage engine) use %1$s. But your MyISAM key buffer size is %2$s. That may be larger than you need, wasting RAM space in your %2$s server.', 'performance-lab' );
				$msg = sprintf( $msg, $myisam_size_display, $cache_size_display, $this->name );
			}
			$action  = __( 'To save RAM in your SQL server, consider asking your hosting provider to', 'performance-lab' ) . ' ';
			$action .= $this->get_buffer_recommendation( 'key_buffer_size', $cache_size, $target_size );

			return $this->utilities->test_result(
				sprintf(
				/* translators: 1 server name like MariaDB */
					__( 'Your %1$s MyISAM key buffer size may be too large', 'performance-lab' ),
					$this->name
				),
				$msg,
				$action,
				'recommended',
				'orange'
			);
		} elseif ( $myisam_size * 2 < $cache_size && $cache_size > 65536 * 4 && $track ['Key_read_requests'] > 0 ) {
			/* we don't use much, if any MyISAM, but check the server hit rate. */
			list( $hit_rate_display, $terrible, $good ) = $this->figure_hit_rate( $track );

			if ( ! $good ) {

				$target_key_buffer_size = $terrible ? $key_buffer_size * 4 : $key_buffer_size * 2;
				$target_key_buffer_size = $this->utilities->power_of_two_round_up( $target_key_buffer_size );

				/* translators: 1: MariaDB or MySQL 2; size like 64MiB  3: percent like 81.2%  4: percent like 81.2% 5: MySQL or MariaDB  */
				$msg    = __( 'Your %1$s server\'s MyISAM key buffer size is %2$s and its hit rate is too low at %3$s. An acceptable hit rate is %4$s.', 'performance-lab' );
				$msg    = sprintf( $msg, $this->name, $cache_size_display, $hit_rate_display, $target_hit_rate_display );
				$action = sprintf(
				/* translators: 1: MySQL or MariaDB  */
					__( 'This may be because your hosting provider shares your %1$s server with other customers. Consider asking your hosting provider to', 'performance-lab' ),
					$this->name
				);
				$action .= ' ';
				$action .= $this->get_buffer_recommendation( 'key_buffer_size', $key_buffer_size, $target_key_buffer_size );

				if ( $terrible ) {
					return $this->utilities->test_result(
						sprintf(
						/* translators: 1 server name like MariaDB */
							__( 'Your %1$s MyISAM key buffer is far too small', 'performance-lab' ),
							$this->name
						),
						$msg,
						$action,
						'critical',
						'red'
					);
				}

				return $this->utilities->test_result(
					sprintf(
					/* translators: 1 server name like MariaDB */
						__( 'Your %1$s MyISAM key buffer may be too small', 'performance-lab' ),
						$this->name
					),
					$msg,
					$action,
					'recommended',
					'orange'
				);
			}
		}

		/* the suggestion to upgrade to InnoDB is in another test */

		return array();
	}

	/** Check for a very large number of users.
	 *
	 * @return array
	 */
	public function too_many_users_test() {
		global $wpdb;
		$target_user_count = $this->utilities->get_threshold_value( 'target_user_count' );
		if ( isset( $this->table_stats[ $wpdb->users ] ) ) {
			$user_count = $this->table_stats[ $wpdb->users ]->row_count;
			if ( $user_count > $target_user_count ) {

				return $this->utilities->test_result(
					__( 'Your site has many registered users', 'performance-lab' ),
					sprintf(
					/* translators: 1 Number of registered users */
						__( 'Your site has %1$s registered users. This may cause your dashboard\'s Posts, Pages, and Users panels to load slowly, and may interfere with editing posts and pages.', 'performance-lab' ),
						number_format_i18n( $user_count )
					),
					__( 'Consider installing a plugin to help you manage many users.', 'performance-lab' ),
					'recommended',
					'orange'
				);
			}
		}

		return array();
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
		$results          = $this->metrics->server_response( $iterations, $response_timeout );
		/* results are in microseconds, and we report in milliseconds */
		$formatted_results   = number_format_i18n( $results * 0.001, 2 );
		$very_slow_response  = $this->utilities->get_threshold_value( 'server_response_very_slow' );
		$slow_response       = $this->utilities->get_threshold_value( 'server_response_slow' );
		$formatted_threshold = number_format_i18n( $slow_response * 0.001, 2 );
		if ( $results >= $very_slow_response ) {
			return $this->utilities->test_result(
			/* translators: 1:  MySQL or MariaDB */
				sprintf( __( 'Your %s SQL server connects and responds very slowly', 'performance-lab' ), $this->name ),
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
		} elseif ( $results >= $slow_response ) {
			return $this->utilities->test_result(
			/* translators: 1:  MySQL or MariaDB */
				sprintf( __( 'Your %s SQL server connects and responds slowly', 'performance-lab' ), $this->name ),
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
				sprintf( __( 'Your %s SQL server connects and responds promptly', 'performance-lab' ), $this->name ),
				sprintf(
				/* translators: 1:  number of milliseconds */
					__( 'Your SQL server connects and responds to simple requests from WordPress in %1$s milliseconds. This supports good site performance.', 'performance-lab' ),
					$formatted_results
				)
			);
		}
	}

	/** HTML for table upgrade instructions.
	 *
	 * @param string $target_storage_engine 'InnoDB'.
	 * @param string $target_row_format 'Dynamic'.
	 * @param array  $table_metrics Array containing metrics for tables needing upgrading.
	 * @param string $label The label to put on the health-check report.
	 *
	 * @return array
	 */
	private function table_upgrade_instructions( $target_storage_engine, $target_row_format, $table_metrics, $label ) {
		$explanation = sprintf(
		/* translators: 1 storage engine name, usually InnoDB  2: row format name, usually Dynamic  3: MySQL or MariaDB */
			__( '%3$s performance improves when your tables use the modern %1$s storage engine and the %2$s row format.', 'performance-lab' ),
			$target_storage_engine,
			$target_row_format,
			$this->name
		);
		$exhortation = __( 'Consider upgrading these tables.', 'performance-lab' );
		/* translators: header of column */
		$action_table_header_1 = __( 'Table Name', 'performance-lab' );
		/* translators: header of column */
		$action_table_header_2 = __( 'WP-CLI command to upgrade', 'performance-lab' );

		list( $description, $action ) = $this->utilities->instructions(
			array(
				$this,
				'format_upgrade_command',
			),
			$explanation,
			$exhortation,
			$action_table_header_1,
			$action_table_header_2,
			$table_metrics,
			$target_storage_engine,
			$target_row_format
		);

		return $this->utilities->test_result(
			$label,
			$description . $action,
			'',
			'recommended',
			'orange'
		);
	}

	/** Function to format the appropriate DDL statement for the table name.
	 *
	 * @param string $table_name The name of the table to put into the statement.
	 *
	 * @return string The statement.
	 */
	public function format_upgrade_command( $table_name ) {
		$target_storage_engine = $this->utilities->get_threshold_value( 'target_storage_engine' );
		$target_row_format     = $this->utilities->get_threshold_value( 'target_row_format' );

		return "wp db query \"ALTER TABLE $table_name ENGINE=$target_storage_engine ROW_FORMAT=$target_row_format\"";
	}

	/** Work out cache-hit-rate stuff.
	 *
	 * @param array $track Statistics block.
	 *
	 * @return array
	 */
	private function figure_hit_rate( array $track ) {
		$cache_hit_rate_terrible = $this->utilities->get_threshold_value( 'cache_hit_rate_terrible' );
		$cache_hit_rate_bad      = $this->utilities->get_threshold_value( 'cache_hit_rate_bad' );
		$hit_rate                = 1.0 - ( $track['Key_reads'] / $track ['Key_read_requests'] );
		$hit_rate_display        = number_format_i18n( $hit_rate * 100, 1 ) . '%';
		$terrible                = $hit_rate <= $cache_hit_rate_terrible;
		$bad                     = $hit_rate <= $cache_hit_rate_bad;
		$good                    = ! ( $terrible || $bad );

		return array( $hit_rate_display, $terrible, $good );
	}

	/** Get a string with a buffer-size change recommendation.
	 *
	 * It looks like
	 *
	 * increase your MySQL server's key_buffer_size from 16384 (16KiB) to 32768 (32Kib).
	 *
	 * @param string $variable_name The name of the variable, like innodb_buffer_pool_size.
	 * @param int    $buffer_size The present buffer size.
	 * @param int    $target_buffer_size The desired buffer size.
	 *
	 * @return string
	 */
	private function get_buffer_recommendation( $variable_name, $buffer_size, $target_buffer_size ) {
		if ( $buffer_size === $target_buffer_size ) {
			return '';
		}
		$buffer_size_display        = $this->utilities->format_bytes( $buffer_size );
		$target_buffer_size_display = $this->utilities->format_bytes( $target_buffer_size );
		$fmt                        = $target_buffer_size > $buffer_size_display
			/* translators: 1: MySQL or MariaDB 2: variable name like key_buffer_size 3: original size like 16384 4: original size like 16KiB 5: target size like 32765 6: target size like 32KiB */
			? __( 'increase your %1$s server\'s %2$s variable from %3$d (%4$s) to at least %5$d (%6$s). ', 'performance-lab' )
			/* translators: 1: MySQL or MariaDB 2: variable name like key_buffer_size 3: original size like 16384 4: original size like 16KiB 5: target size like 32765 6: target size like 32KiB */
			: __( 'decrease your %1$s server\'s %2$s variable from %3$d (%4$s) to %5$d (%6$s). ', 'performance-lab' );
		return sprintf( $fmt, $this->name, $variable_name, $buffer_size, $buffer_size_display, $target_buffer_size, $target_buffer_size_display );
	}

}
