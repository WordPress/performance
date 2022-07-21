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

	/** Array of index-name prefixes to ignore.
	 *
	 * @var array like [woo_,crp_,yarpp_].
	 */
	private $index_stop_list;
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
	/** Description of standard WordPress indexes.
	 *
	 * @var string[][]
	 */
	private $standard_indexes;

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
		$test_number = 100;
		$label       = __( 'Database Index One', 'performance-lab' );
		$tests['direct'][ 'database_performance' . $test_number++ ] = array(
			'label' => $label,
			'test'  => array( $this, 'index_test' ),
		);

		return $tests;
	}

	/** Test need for fast indexes.
	 *
	 * @throws InvalidArgumentException When something goes wrong with DDL generation.
	 */
	public function index_test() {
		if ( $this->skip_all_tests ) {
			return array();
		}
		$result = $this->get_rekeying( $this->version->unconstrained );
		return array(); // TODO stub.
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
		$eligible       = array_diff_key( $stats, $bad );

		return $eligible;
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
				if ( substr_compare( $index, $stop, null, true ) === 0 ) {
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

}




