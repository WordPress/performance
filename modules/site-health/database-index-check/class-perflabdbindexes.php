<?php
/**
 * Performance Lab Database Indexing.
 *
 * @package performance-lab
 * @group audit-database
 *
 * @since 1.4.0
 */

/**
 * Performance Lab Database Indexing class.
 *
 * Includes definitions of WordPress's standard indexes
 * and the high performance indexes, including some
 * compound primary keys.
 *
 * @package performance-lab
 * @group audit-database
 *
 * @since 1.4.0
 */
class PerflabDbIndexes {

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
	public function add_all_database_index_checks( $tests ) {
		$test_number = 100;
		$label       = __( 'Database', 'performance-lab' );
		$tests['direct'][ 'database_performance' . $test_number++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'table_reformat_instructions' ),
		);
		$tests['direct'][ 'database_performance' . $test_number++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'index_upgrade_test' ),
		);
		$tests['direct'][ 'database_performance' . $test_number++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'index_revert_test' ),
		);

		return $tests;
	}


	/** Check core tables format
	 *
	 * @return array
	 */
	public function table_reformat_instructions() {
		if ( $this->skip_all_tests ) {
			return array();
		}

		$target_storage_engine = $this->utilities->get_threshold_value( 'target_storage_engine' );
		$target_row_format     = $this->utilities->get_threshold_value( 'target_row_format' );
		$bad                   = $this->metrics->get_wrong_format_tables( $this->table_stats, $target_storage_engine, $target_row_format );
		if ( count( $bad ) === 0 ) {
			return array();
		} else {
			$label = __( 'Upgrade your tables to the modern storage format', 'performance-lab' );
			return $this->table_upgrade_instructions( $target_storage_engine, $target_row_format, $bad, $label );
		}
	}


	/** Test need for fast indexes.
	 */
	public function index_upgrade_test() {
		if ( $this->skip_all_tests ) {
			return array();
		}
		$action     = 'fast';
		$statements = $this->get_dml( $action );

		if ( 0 === count( $statements ) ) {
			return $this->utilities->test_index_result(
				__( 'Your WordPress tables have high-performance keys', 'performance-lab' ),
				sprintf(
				/* translators: 1 MySQL or MariaDB */
					__( 'High-performance keys improve performance of your %1$s server. You have already added them to your tables.', 'performance-lab' ),
					$this->name
				)
			);
		}

		global $wpdb;
		$label = count( $wpdb->tables() ) === count( $statements )
			? __( 'Your WordPress tables will perform better with high-performance keys', 'performance-lab' )
			: __( 'Some WordPress tables will perform better with high-performance keys', 'performance-lab' );

		$explanation = sprintf(
		/* translators: 1 MySQL or MariaDB */
			__( '%1$s retrieves your content more efficiently when you use high-performance keys.', 'performance-lab' ),
			$this->name
		);
		$exhortation = __( 'Consider adding them to these WordPress tables.', 'performance-lab' );
		/* translators: header of column */
		$action_table_header_1 = __( 'Table Name', 'performance-lab' );
		/* translators: header of column */
		$action_table_header_2 = __( 'WP-CLI command to add keys', 'performance-lab' );

		list( $description, $action ) = $this->utilities->instructions( array( $this, 'format_rekey_command' ), $explanation, $exhortation, $action_table_header_1, $action_table_header_2, $statements );

		return $this->utilities->test_index_result(
			$label,
			$description . $action,
			'',
			'recommended',
			'orange'
		);

	}

	/** Test need for fast indexes.
	 */
	public function index_revert_test() {
		if ( $this->skip_all_tests ) {
			return array();
		}
		$action     = 'standard';
		$statements = $this->get_dml( $action );

		if ( 0 === count( $statements ) ) {
			/* don't pester the user if they have standard keys */
			return array();
		}

		global $wpdb;
		$label = count( $wpdb->tables() ) === count( $statements )
			? __( 'Your WordPress tables have high performance keys to revert if necessary', 'performance-lab' )
			: __( 'Some WordPress tables have high performance keys to revert if necessary', 'performance-lab' );

		$explanation = sprintf(
		/* translators: 1 MySQL or MariaDB */
			__( 'You have added high-performance keys to help %1$s retrieve your content more efficiently. If you wish you can revert them to WordPress\'s standard keys.', 'performance-lab' ),
			$this->name
		);
		$exhortation = __( 'If you need to revert your high-performance keys use these commands. You don\'t usually need to do this.', 'performance-lab' );
		/* translators: header of column */
		$action_table_header_1 = __( 'Table Name', 'performance-lab' );
		/* translators: header of column */
		$action_table_header_2 = __( 'WP-CLI command to revert keys', 'performance-lab' );

		list( $description, $action ) = $this->utilities->instructions( array( $this, 'format_rekey_command' ), $explanation, $exhortation, $action_table_header_1, $action_table_header_2, $statements );

		return $this->utilities->test_index_result(
			$label,
			$description . $action
		);

	}
	/** Format the DDL for rekeying.
	 *
	 * @param string $name Name of the table.
	 * @param mixed  $info Associated information.
	 *
	 * @return string Formatted DML.
	 */
	public function format_rekey_command( $name, $info ) {
		return "wp db query \"{$info['ddl']}\"";
	}

	/** Figure out, based on current DDL and target DDL,
	 * what tables need to change, and how they could change.
	 *
	 * @param bool            $unconstrained 1 for Barracuda.
	 * @param string|string[] $actions 'fast', 'standard', 'all', ['fast', 'standard'] etc. default ['fast', 'standard'].
	 *
	 * @return array
	 */
	public function get_rekeying( $unconstrained, $actions = null ) {
		if ( null === $actions || 'all' === $actions ) {
			$actions = array( 'fast', 'standard' );
		} elseif ( is_string( $actions ) ) {
			$actions = array( $actions );
		} else {
			$actions = array( 'fast', 'standard' );
		}
		$result     = array();
		$table_list = $this->get_rekeyable_tables( $unconstrained );

		$eligible = $this->get_eligible_tables( $table_list );

		foreach ( $eligible as $name => $stat ) {
			$item = array();
			foreach ( $actions as $action ) {
				$item [ $action ] = $this->get_actions( $action, $table_list, $name, $unconstrained );
			}
			$item['row_count'] = $stat->row_count;
			$result [ $name ]  = $item;
		}

		return $result;
	}

	/** Tables in current instance that can be keyed
	 *
	 * @param bool $unconstrained 1 means Barracuda.
	 *
	 * @return string[] Table name relation, mapping for example wp_2_termmeta to termmeta
	 */
	public function get_rekeyable_tables( $unconstrained ) {
		global $wpdb;
		$tables           = $wpdb->tables();
		$indexable_tables = array();
		foreach ( $this->get_index_inventory( $unconstrained ) as $indexable_table ) {
			foreach ( $tables as $table ) {
				if ( str_ends_with( $table, $indexable_table ) ) {
					$indexable_tables [ $table ] = $indexable_table;
				}
			}
		}
		return $indexable_tables;

	}


	/** The list of tables we can rekey.
	 *
	 * @param bool $unconstrained True for Barracuda, false for Antelope.
	 *
	 * @return string[] List of tables.
	 */
	function get_index_inventory( $unconstrained ) {
		$tables = array();
		$x      = $this->get_standard_indexes( $unconstrained );
		foreach ( $x as $table => $indexes ) {
			$tables[ $table ] = 1;
		}
		$x = $this->get_high_performance_indexes( $unconstrained );
		foreach ( $x as $table => $indexes ) {
			$tables[ $table ] = 1;
		}
		$result = array();
		foreach ( $tables as $table => $z ) {
			$result[] = $table;
		}

		return $result;
	}

	/** Get WordPress's standard indexes.
	 *
	 * @param bool $unconstrained bool false if Antelope.
	 * @param int  $version WordPress database version.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	function get_standard_indexes( $unconstrained, $version = 53496 ) {
		/* these are WordPress's standard indexes for database version 53496 and before. */
		return array(
			'postmeta'    => array(
				'PRIMARY KEY' => 'ADD PRIMARY KEY (meta_id)',
				'post_id'     => 'ADD KEY post_id (post_id)',
				'meta_key'    => 'ADD KEY meta_key (meta_key(191))',
			),
			'usermeta'    => array(
				'PRIMARY KEY' => 'ADD PRIMARY KEY (umeta_id)',
				'user_id'     => 'ADD KEY user_id (user_id)',
				'meta_key'    => 'ADD KEY meta_key (meta_key(191))',
			),
			'termmeta'    => array(
				'PRIMARY KEY' => 'ADD PRIMARY KEY (meta_id)',
				'term_id'     => 'ADD KEY term_id (term_id)',
				'meta_key'    => 'ADD KEY meta_key (meta_key(191))',
			),
			'options'     => array(
				'PRIMARY KEY' => 'ADD PRIMARY KEY (option_id)',
				'option_name' => 'ADD UNIQUE KEY option_name (option_name)',
				'autoload'    => 'ADD KEY autoload (autoload)',
			),
			'posts'       => array(
				'PRIMARY KEY'      => 'ADD PRIMARY KEY (ID)',
				'post_name'        => 'ADD KEY post_name (post_name(191))',
				'post_parent'      => 'ADD KEY post_parent (post_parent)',
				'type_status_date' => 'ADD KEY type_status_date (post_type, post_status, post_date, ID)',
				'post_author'      => 'ADD KEY post_author (post_author)',
			),
			'comments'    => array(
				'PRIMARY KEY'               => 'ADD PRIMARY KEY (comment_ID)',
				'comment_post_ID'           => 'ADD KEY comment_post_ID (comment_post_ID)',
				'comment_approved_date_gmt' => 'ADD KEY comment_approved_date_gmt (comment_approved, comment_date_gmt)',
				'comment_date_gmt'          => 'ADD KEY comment_date_gmt (comment_date_gmt)',
				'comment_parent'            => 'ADD KEY comment_parent (comment_parent)',
				'comment_author_email'      => 'ADD KEY comment_author_email (comment_author_email(10))',
			),
			'commentmeta' => array(
				'PRIMARY KEY' => 'ADD PRIMARY KEY (meta_id)',
				'comment_id'  => 'ADD KEY comment_id (comment_id)',
				'meta_key'    => 'ADD KEY meta_key (meta_key(191))',
			),
			'users'       => array(
				'PRIMARY KEY'    => 'ADD PRIMARY KEY (ID)',
				'user_login_key' => 'ADD KEY user_login_key (user_login)',
				'user_nicename'  => 'ADD KEY user_nicename (user_nicename)',
				'user_email'     => 'ADD KEY user_email (user_email)',
			),
		);
	}

	/** Get description of high-performance indexes.
	 *
	 * @param bool $unconstrained 0: Antelope, prefix indexes   1:Barracuda, no prefix indexes.
	 *
	 * @return array
	 */
	function get_high_performance_indexes( $unconstrained ) {
		/*
		 * When changing a PRIMARY KEY,
		 * for example to a compound clustered index
		 * you also need to add a UNIQUE KEY
		 * to hold the autoincrementing column.
		 * Put that UNIQUE KEY first in the list of keys to add.
		 * That makes sure we always have some sort of
		 * no-duplicate constraint on the autoincrementing ID.
		 */

		/* these are the indexes not dependent on Antelope or Barracuda */
		$reindex_anyway = array(
			'options'  => array(
				'option_id'   => 'ADD UNIQUE KEY option_id (option_id)',
				'PRIMARY KEY' => 'ADD PRIMARY KEY (option_name)',
				'autoload'    => 'ADD KEY autoload (autoload)',
			),
			'comments' => array(
				'comment_ID'                   => 'ADD UNIQUE KEY comment_ID (comment_ID)',
				'PRIMARY KEY'                  => 'ADD PRIMARY KEY (comment_post_ID, comment_ID)',
				'comment_approved_date_gmt'    => 'ADD KEY comment_approved_date_gmt (comment_approved, comment_date_gmt, comment_ID)',
				'comment_date_gmt'             => 'ADD KEY comment_date_gmt (comment_date_gmt, comment_ID)',
				'comment_parent'               => 'ADD KEY comment_parent (comment_parent, comment_ID)',
				'comment_author_email'         => 'ADD KEY comment_author_email (comment_author_email, comment_post_ID, comment_ID)',
				'comment_post_parent_approved' => 'ADD KEY comment_post_parent_approved (comment_post_ID, comment_parent, comment_approved, comment_type, user_id, comment_date_gmt, comment_ID)',
			),
		);

		/*
		 * These are the Barracuda-dependent (unprefixed) indexes
		 * Notice that the indexed columns following prefix-indexed columns
		 * [for example post_id in (meta_key, meta_value(32), post_id) ]
		 * are intended for covering-index use, as if in an INCLUDE clause.
		 */
		$reindex_without_constraint = array(
			'postmeta'    => array(
				'meta_id'     => 'ADD UNIQUE KEY meta_id (meta_id)',
				'PRIMARY KEY' => 'ADD PRIMARY KEY (post_id, meta_key, meta_id)',
				'meta_key'    => 'ADD KEY meta_key (meta_key, meta_value(32), post_id, meta_id)',
				'meta_value'  => 'ADD KEY meta_value (meta_value(32), meta_id)',
			),

			'usermeta'    => array(
				'umeta_id'    => 'ADD UNIQUE KEY umeta_id (umeta_id)',
				'PRIMARY KEY' => 'ADD PRIMARY KEY (user_id, meta_key, umeta_id)',
				'meta_key'    => 'ADD KEY meta_key (meta_key, meta_value(32), user_id, umeta_id)',
				'meta_value'  => 'ADD KEY meta_value (meta_value(32), umeta_id)',
			),
			'termmeta'    => array(
				'meta_id'     => 'ADD UNIQUE KEY meta_id (meta_id)',
				'PRIMARY KEY' => 'ADD PRIMARY KEY (term_id, meta_key, meta_id)',
				'meta_key'    => 'ADD KEY meta_key (meta_key, meta_value(32), term_id, meta_id)',
				'meta_value'  => 'ADD KEY meta_value (meta_value(32), meta_id)',
			),
			'posts'       => array(
				'PRIMARY KEY'      => 'ADD PRIMARY KEY (ID)',
				'post_name'        => 'ADD KEY post_name (post_name)',
				'post_parent'      => 'ADD KEY post_parent (post_parent, post_type, post_status)',
				'type_status_date' => 'ADD KEY type_status_date (post_type, post_status, post_date, post_author)',
				'post_author'      => 'ADD KEY post_author (post_author, post_type, post_status, post_date)',
			),
			'commentmeta' => array(
				'meta_id'     => 'ADD UNIQUE KEY meta_id (meta_id)',
				'PRIMARY KEY' => 'ADD PRIMARY KEY (meta_key, comment_id, meta_id)',
				'comment_id'  => 'ADD KEY comment_id (comment_id, meta_key, meta_value(32))',
				'meta_value'  => 'ADD KEY meta_value (meta_value(32))',
			),
			'users'       => array(
				'PRIMARY KEY'    => 'ADD PRIMARY KEY (ID)',
				'user_login_key' => 'ADD KEY user_login_key (user_login)',
				'user_nicename'  => 'ADD KEY user_nicename (user_nicename)',
				'user_email'     => 'ADD KEY user_email (user_email)',
				'display_name'   => 'ADD KEY display_name (display_name)',
			),
		);

		/*
		 * these are the Antelope-dependent (prefixed) indexes.
		 * we can use shorter prefix indexes and still get almost all the value
		 */
		$reindex_with_antelope_constraint = array(
			'postmeta'    => array(
				'meta_id'     => 'ADD UNIQUE KEY meta_id (meta_id)',
				'PRIMARY KEY' => 'ADD PRIMARY KEY (post_id, meta_id)',
				'post_id'     => 'ADD KEY post_id (post_id, meta_key(32), meta_value(32), meta_id)',
				'meta_key'    => 'ADD KEY meta_key (meta_key(32), meta_value(32), meta_id)',
				'meta_value'  => 'ADD KEY meta_value (meta_value(32), meta_id)',
			),

			'usermeta'    => array(
				'umeta_id'    => 'ADD UNIQUE KEY umeta_id (umeta_id)',
				'PRIMARY KEY' => 'ADD PRIMARY KEY (user_id, umeta_id)',
				'user_id'     => 'ADD KEY user_id (user_id, meta_key(32), meta_value(32), umeta_id)',
				'meta_key'    => 'ADD KEY meta_key (meta_key(32), meta_value(32), umeta_id)',
				'meta_value'  => 'ADD KEY meta_value (meta_value(32), umeta_id)',
			),
			'termmeta'    => array(
				'meta_id'     => 'ADD UNIQUE KEY meta_id (meta_id)',
				'PRIMARY KEY' => 'ADD PRIMARY KEY (term_id, meta_id)',
				'term_id'     => 'ADD KEY term_id (term_id, meta_key(32), meta_value(32), meta_id)',
				'meta_key'    => 'ADD KEY meta_key (meta_key(32), meta_value(32), meta_id)',
				'meta_value'  => 'ADD KEY meta_value (meta_value(32), meta_id)',
			),
			'posts'       => array(
				'PRIMARY KEY'      => 'ADD PRIMARY KEY (ID)',
				'post_name'        => 'ADD KEY post_name (post_name(32))',
				'post_parent'      => 'ADD KEY post_parent (post_parent, post_type, post_status)',
				'type_status_date' => 'ADD KEY type_status_date (post_type, post_status, post_date, post_author)',
				'post_author'      => 'ADD KEY post_author (post_author, post_type, post_status, post_date)',
			),
			'commentmeta' => array(
				'meta_id'     => 'ADD UNIQUE KEY meta_id (meta_id)',
				'PRIMARY KEY' => 'ADD PRIMARY KEY (comment_id, meta_id)',
				'comment_id'  => 'ADD KEY comment_id (comment_id, meta_key(32))',
				'meta_key'    => 'ADD KEY meta_key (meta_key(32), meta_value(32))',
				'meta_value'  => 'ADD KEY meta_value (meta_value(32), meta_key(32))',
			),
			'users'       => array(
				'PRIMARY KEY'    => 'ADD PRIMARY KEY (ID)',
				'user_login_key' => 'ADD KEY user_login_key (user_login)',
				'user_nicename'  => 'ADD KEY user_nicename (user_nicename)',
				'user_email'     => 'ADD KEY user_email (user_email)',
				'display_name'   => 'ADD KEY display_name (display_name(32))',
			),

		);
		switch ( $unconstrained ) {
			case 1: /* Barracuda */
				$reindexes = array_merge( $reindex_without_constraint, $reindex_anyway );
				break;
			case 0: /* Antelope */
				$reindexes = array_merge( $reindex_with_antelope_constraint, $reindex_anyway );
				break;
			default:
				$reindexes = $reindex_anyway;
				break;
		}

		return $reindexes;
	}

	/** Get stats for tables that can be rekeyed, omitting old storage engines
	 *  and tables we don't know about.
	 *
	 * @param array $table_list Associative array named for tables.
	 *
	 * @return array
	 */
	public function get_eligible_tables( array $table_list ) {
		$stats          = array_intersect_key( $this->table_stats, $table_list );
		$storage_engine = $this->utilities->get_threshold_value( 'target_storage_engine' );
		$row_format     = $this->utilities->get_threshold_value( 'target_row_format' );
		$bad            = $this->metrics->get_wrong_format_tables( $stats, $storage_engine, $row_format );

		return array_diff_key( $stats, $bad );
	}

	/** Get the actions required to re-index a table.
	 *
	 * @param string   $which 'fast' or 'standard'.
	 * @param string[] $table_list A relation mapping, for example, wp_2_postmeta to postmeta.
	 * @param string   $name The table to reindex.
	 * @param bool     $unconstrained 1 means Barracuda, 0 Antelope.
	 *
	 * @return string[] Clauses for ALTER TABLE.
	 *
	 * @throws InvalidArgumentException Unexpected combination of indexes.
	 */
	public function get_actions( $which, $table_list, $name, $unconstrained ) {

		$target_indexes  = 'fast' === $which
			? $this->get_high_performance_indexes( $unconstrained )
			: $this->get_standard_indexes( $unconstrained );
		$unprefixed_name = $table_list[ $name ];
		$target_indexes  = array_key_exists( $unprefixed_name, $target_indexes ) ? $target_indexes[ $unprefixed_name ] : array();
		$current_indexes = $this->metrics->get_indexes( $name );
		/* build a list of all index names, target first and current second so UNIQUEs come first */
		$indexes = array();
		foreach ( $target_indexes as $key => $value ) {
			if ( ! isset( $indexes[ $key ] ) ) {
				$indexes[ $key ] = $key;
			}
		}
		foreach ( $current_indexes as $key => $value ) {
			if ( ! isset( $indexes[ $key ] ) ) {
				$indexes[ $key ] = $key;
			}
		}
		$stop_list = explode( '|', $this->utilities->get_threshold_value( 'index_stop_list' ) );
		foreach ( $stop_list as $stop ) {
			foreach ( $indexes as $index => $val ) {
				if ( strpos( $index, $stop ) === 0 ) {
					unset( $indexes[ $index ] );
				}
			}
		}

		$actions = array();
		foreach ( $indexes as $key => $value ) {
			if ( array_key_exists( $key, $current_indexes )
				&& array_key_exists( $key, $target_indexes )
				&& $current_indexes[ $key ]->add === $target_indexes[ $key ] ) {
				/* target and current keys match already, do nothing. (See array_filter() below.) */
				$actions[] = null;
			} elseif ( array_key_exists( $key, $current_indexes ) && array_key_exists( $key, $target_indexes ) && $current_indexes[ $key ]->add !== $target_indexes[ $key ] ) {
				/* target and current key names match, but content does not. DROP and then ADD. */
				$actions[] = $current_indexes[ $key ]->drop;
				$actions[] = $target_indexes[ $key ];
			} elseif ( array_key_exists( $key, $current_indexes ) && ! array_key_exists( $key, $target_indexes ) ) {
				/* target key name doesn't exist, but current does. DROP. */
				$actions[] = $current_indexes[ $key ]->drop;
			} elseif ( ! array_key_exists( $key, $current_indexes ) && array_key_exists( $key, $target_indexes ) ) {
				/* current key name doesn't exist, but target does.  ADD */
				$actions[] = $target_indexes[ $key ];
			} else {
				throw new InvalidArgumentException( 'weird key compare failure' );
			}
		}

		return array_filter( $actions );
	}

	/** Get the DML to do rekeying.
	 *
	 * @param string $action 'fast' or 'standard'.
	 *
	 * @return array[] Indexed by table name with [ddl, is_big, and row_count] items.
	 */
	private function get_dml( $action ) {
		$all_tables = $this->get_rekeying( $this->version->unconstrained, $action );

		$meta_size    = $this->utilities->get_threshold_value( 'meta_size' );
		$content_size = $this->utilities->get_threshold_value( 'content_size' );

		$statements = array();
		foreach ( $all_tables as $table => $info ) {
			/* note the larger tables */
			$is_big         = false;
			$size_threshold = str_ends_with( $table, 'meta' ) ? $meta_size : $content_size;
			if ( $info['row_count'] > $size_threshold ) {
				$is_big = true;
			}
			if ( count( $info[ $action ] ) > 0 ) {
				$ddl = array();
				foreach ( $info [ $action ] as $clause ) {
					$ddl[] = wordwrap( $clause, 64, PHP_EOL . '    ', false );
				}
				$separator             = ',' . PHP_EOL . '  ';
				$statements [ $table ] = array(
					'ddl'       => "ALTER TABLE $table" . PHP_EOL . '  ' . implode( $separator, $ddl ) . ';',
					'is_big'    => $is_big,
					'row_count' => $info ['row_count'],
				);
			}
		}

		return $statements;
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
		$explanation = ( 1 === $this->version->unconstrained )
			? sprintf(
			/* translators: 1 MySQL or MariaDB  2: storage engine name, usually InnoDB  3: row format name, usually Dynamic */
				__( '%1$s performance improves when your tables use the modern %2$s storage engine and the %3$s row format.', 'performance-lab' ),
				$this->name,
				$target_storage_engine,
				$target_row_format
			) : sprintf(
			/* translators: 1 MySQL or MariaDB  2: storage engine name, usually InnoDB  */
				__( '%1$s performance improves when your tables use the modern %2$s storage engine.', 'performance-lab' ),
				$this->name,
				$target_storage_engine
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
			$table_metrics
		);

		return $this->utilities->test_index_result(
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

		$result = ( 1 === $this->version->unconstrained )
			? "wp db query \"ALTER TABLE $table_name ENGINE=$target_storage_engine ROW_FORMAT=$target_row_format\""
			: "wp db query \"ALTER TABLE $table_name ENGINE=$target_storage_engine\"";

		return wordwrap( $result, 64, PHP_EOL . '    ', false );
	}


}




