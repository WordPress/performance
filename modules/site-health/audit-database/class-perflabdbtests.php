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
	 * @param object $metrics Metrics-retrieving instance.
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

		$tests['direct'][ 'database_performance' . $test_number++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'extra_tables_format_test' ),
		);

		$tests['direct'][ 'database_performance' . $test_number++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'buffer_pool_size_test' ),
		);

		$tests['direct'][ 'database_performance' . $test_number++ ] = array(
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
					__( 'Your %1$s SQL server is a required piece of software for WordPress\'s database. WordPress uses it to store and retrieve all your site’s content and settings. The version you use (%2$s) does not offer the fastest way to retrieve content. This affects you if you have many posts or users.', 'performance-lab' ),
					$this->name,
					$this->version->version
				),
				__( 'For best performance we recommend running MySQL version 5.7 or higher or MariaDB 10.3 or higher. Contact your web hosting company to correct this.', 'performance-lab' ),
				'recommended'
			);
		}

		return $this->utilities->test_result(
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
	 * @param array  $stats Result set from get_table_info.
	 * @param string $target_storage_engine Usually InnoDB.
	 * @param string $target_row_format Usually Dynamic.
	 *
	 * @return array The tables with the wrong format.
	 */
	private function get_wrong_format_tables( $stats, $target_storage_engine, $target_row_format ) {
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

	/** Check core tables format
	 *
	 * @return array
	 */
	public function core_tables_format_test() {
		if ( $this->skip_all_tests ) {
			return array();
		}
		global $wpdb;
		$tables                = $wpdb->tables();
		$stats                 = array_intersect_key( $this->table_stats, array_combine( $tables, $tables ) );
		$target_storage_engine = $this->utilities->get_threshold_value( 'target_storage_engine' );
		$target_row_format     = $this->utilities->get_threshold_value( 'target_row_format' );
		$bad                   = $this->get_wrong_format_tables( $stats, $target_storage_engine, $target_row_format );
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
		$tables                = $wpdb->tables();
		$stats                 = array_diff_key( $this->table_stats, array_combine( $tables, $tables ) );
		$target_storage_engine = $this->utilities->get_threshold_value( 'target_storage_engine' );
		$target_row_format     = $this->utilities->get_threshold_value( 'target_row_format' );
		$bad                   = $this->get_wrong_format_tables( $stats, $target_storage_engine, $target_row_format );
		if ( count( $bad ) === 0 ) {
			return $this->utilities->test_result(
				__( 'Your plugin tables use the modern storage format', 'performance-lab' ),
				sprintf(
				/* translators: 1 storage engine name, usually InnoDB  2: row format name, usually Dynamic */
					__( 'Your tables use the %1$s storage engine and the %2$s row format for good database performance.', 'performance-lab' ),
					$target_storage_engine,
					$target_row_format
				)
			);
		} else {
			$label = count( $bad ) === count( $stats )
				? __( 'Your plugin tables use an obsolete storage format', 'performance-lab' )
				: __( 'Some plugin tables use an obsolete storage format', 'performance-lab' );

			return $this->table_upgrade_instructions( $target_storage_engine, $target_row_format, $bad, $label );
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
				__( 'The keys on your MyISAM tables (the obsolete storage engine) use %1$s, but %2$s\'s buffer size (\'Key_buffer_size\') is only %3$s. That may be inadequate.', 'performance-lab' ),
				$this->utilities->format_bytes( $myisam_size ),
				$this->name,
				$this->utilities->format_bytes( $myisam_pool_size )
			) . '</p>';
		} else {
			$msgs[] = '<p>' . sprintf(
			/* translators: 1: memory size like 512MiB  2: server name like MySQL  3: memory size */
				__( 'The keys on your MyISAM tables (the obsolete storage engine) use %1$s and %2$s\'s buffer size is %3$s. That is adequate in most cases.', 'performance-lab' ),
				$this->utilities->format_bytes( $myisam_size ),
				$this->name,
				$this->utilities->format_bytes( $myisam_pool_size )
			) . '</p>';
		}
		if ( $innodb_size > 0 && ( $innodb_pool_size / $fraction ) < $innodb_size ) {
			$too_small = true;
			$msgs[]    = '<p>' . sprintf(
			/* translators: 1: memory size like 512MiB  2: server name like MySQL  3: memory size */
				__( 'Your InnoDB tables (the modern storage engine) use %1$s, but %2$s\'s buffer pool size (\'Innodb_buffer_pool_size\') is only %3$s. That may be inadequate.', 'performance-lab' ),
				$this->utilities->format_bytes( $innodb_size ),
				$this->name,
				$this->utilities->format_bytes( $innodb_pool_size )
			) . '</p>';
		} else {
			$msgs[] = '<p>' . sprintf(
			/* translators: 1: memory size like 512MiB  2: server name like MySQL  3: memory size */
				__( 'Your InnoDB tables (the modern storage engine) use %1$s and %2$s\'s buffer pool size is %3$s. That is adequate in most cases.', 'performance-lab' ),
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
					'<p>' . __( 'Your %1$s buffer pool is probably too small. That makes it take longer to retrieve your content, especially when your site is busy.', 'performance-lab' ),
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
					__( 'Your %1$s SQL server buffer pool appears to be adequate for your data', 'performance-lab' ),
					$this->name
				),
				implode( '', $msgs ),
				sprintf(
				/* translators: 1 server name like MariaDB */
					__( 'Some hosts share their %1$s SQL servers among multiple customers. In that case the shared buffer pool may still be inadequate. Site Health cannot detect shared SQL servers.', 'performance-lab' ),
					$this->name
				)
			);
		}
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
		$iterations          = $this->utilities->get_threshold_value( 'server_response_iterations' );
		$response_timeout    = $this->utilities->get_threshold_value( 'server_response_timeout' );
		$results             = $this->metrics->server_response( $iterations, $response_timeout );
		$formatted_results   = number_format_i18n( $results, 2 );
		$very_slow_response  = $this->utilities->get_threshold_value( 'server_response_very_slow' );
		$slow_response       = $this->utilities->get_threshold_value( 'server_response_slow' );
		$formatted_threshold = number_format_i18n( $slow_response, 2 );
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
	 * @param array  $bad Array containing metrics for tables needing upgrading.
	 * @param string $label The label to put on the health-check report.
	 *
	 * @return array
	 */
	private function table_upgrade_instructions( $target_storage_engine, $target_row_format, $bad, $label ) {
		$clip     = plugin_dir_url( __FILE__ ) . 'assets/clip.svg';
		$copy_txt = esc_attr__( 'Copy to clipboard', 'performance-lab' );
		$desc     = array();
		$desc[]   = '<p class="description">';
		$desc[]   = sprintf(
		/* translators: 1 storage engine name, usually InnoDB  2: row format name, usually Dynamic  3: MySQL or MariaDB */
			__( '%3$s performance improves when your tables use the modern %1$s storage engine and the %2$s row format.', 'performance-lab' ),
			$target_storage_engine,
			$target_row_format,
			$this->name
		);
		$desc[] = '</p>';
		$desc[] = '<p class="description">';
		$desc[] = __( 'Consider upgrading these tables.', 'performance-lab' );
		$desc[] = '</p>';
		$acts   = array();
		$acts[] = '<table class="upgrades">';
		$acts[] = '<thead><tr><th scope="col" class=\"table\">Table Name</th><th scope="col" class=\"cmd\">WP-CLI command to upgrade</th><th></th></tr></thead>';
		$acts[] = '<tbody>';
		foreach ( $bad as $table => $data ) {
			$acts[]  = '<tr>';
			$acts[]  = "<td class=\"table\">$table</td>";
			$acts[]  = '<td class="cmd">';
			$acts[]  = "<img src=\"$clip\" alt=\"$copy_txt\" title=\"$copy_txt\" >";
			$acts[]  = '<span class="cmd">';
			$acts[]  = "wp db query \"ALTER TABLE $table ENGINE=$target_storage_engine ROW_FORMAT=$target_row_format\"";
			$acts [] = '</span>';
			$acts[]  = '</td>';
			$acts[]  = '</tr>';
		}
		$acts [] = '</tbody></table>';

		return $this->utilities->test_result(
			$label,
			implode( '', $desc ) . implode( '', $acts ),
			'',
			'recommended',
			'orange'
		);
	}

}
