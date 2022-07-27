<?php
/**
 * Tests for audit-database module.
 *
 * @package performance-lab
 * @group audit-database-test
 */

class Audit_Database_Tests extends WP_UnitTestCase {

	public function __construct( $name = null, array $data = array(), $data_name = '' ) {
		$dir = '/../../../../modules/site-health/audit-database';
		require_once __DIR__ . $dir . '/class-perflabdbutilities.php';
		require_once __DIR__ . $dir . '/class-perflabdbmetrics.php';
		require_once __DIR__ . $dir . '/class-perflabdbtests.php';
		require_once __DIR__ . $dir . '/class-perflabdbindexes.php';
		parent::__construct( $name, $data, $data_name );
	}

	public function test_initialize() {

		$pdm   = new PerflabDbMetrics();
		$pdp   = new PerflabDbTests( $pdm );
		$pdi   = new PerflabDbIndexes( $pdm );
		$utils = PerflabDbUtilities::get_instance();

		$this->assertIsObject( $pdm, 'Failed to instantiate PerflabDbMetrics' );
		$this->assertIsObject( $pdp, 'Failed to instantiate PerflabDbTests' );
		$this->assertIsObject( $pdi, 'Failed to instantiate PerflabDbIndexes' );
		$this->assertIsObject( $utils, 'Failed to instantiate PerflabDbUtilities' );
		$this->assertEquals( 32814, $pdm->first_compatible_db_version, 'Wrong first_compatible_db_version' );
	}

	public function test_get_threshold() {
		$utils = PerflabDbUtilities::get_instance();
		$val   = $utils->get_threshold_value( 'target_storage_engine' );
		$this->assertEquals( 'InnoDB', $utils->get_threshold_value( 'target_storage_engine' ) );

		add_filter( 'perflab_db_threshold', array( $this, 'filter_threshold_1' ), 10, 2 );
		$this->assertEquals( 'InnoDBtest', $utils->get_threshold_value( 'target_storage_engine' ) );

		remove_filter( 'perflab_db_threshold', array( $this, 'filter_threshold_1' ) );
		$this->assertEquals( 'InnoDB', $utils->get_threshold_value( 'target_storage_engine' ) );

		try {
			$val = $utils->get_threshold_value( 'BOGUS' );
		} catch ( InvalidArgumentException $exception ) {
			$this->assertInstanceOf( InvalidArgumentException::class, $exception, 'Wrong Exception was thrown' );
		}
		add_filter( 'perflab_db_threshold', array( $this, 'filter_threshold_2' ), 10, 2 );
		$val = $utils->get_threshold_value( 'BOGUS' );
		$this->assertEquals( 'Yeah, bogus indeed. but filtered', $val );
		remove_filter( 'perflab_db_threshold', array( $this, 'filter_threshold_2' ) );
	}

	public function filter_threshold_1( $value, $name ) {
		if ( 'target_storage_engine' === $name ) {
			return $value . 'test';
		}
		return $value;
	}

	public function filter_threshold_2( $value, $name ) {
		if ( 'BOGUS' === $name ) {
			return 'Yeah, bogus indeed. but filtered';
		}
		return $value;
	}

	public function test_database_get_version() {

		$pdm = new PerflabDbMetrics();

		$version = $pdm->get_db_version();

		$this->assertIsObject( $version );
		$name = $version->server_name;
		$this->assertIsString( $name );
		$name_is_as_expected = 'MariaDB' === $name || 'MySQL' === $name;
		$this->assertIsNumeric( $version->major );
		$this->assertIsNumeric( $version->minor );
		$this->assertIsNumeric( $version->build );
		$this->assertIsNumeric( $version->canreindex );
		$this->assertIsNumeric( $version->unconstrained );
		$this->assertIsString( $version->version );
		$this->assertIsString( $version->db_host );
	}

	public function test_server_response() {

		$pdm = new PerflabDbMetrics();

		$response_time = $pdm->server_response( 10, 100 );

		$this->assertIsNumeric( $response_time );
		$this->assertEqualsWithDelta( 5, $response_time, 2000000 );
	}
	public function test_buffer_pool_size() {

		$pdm = new PerflabDbMetrics();

		$this->AssertIsNumeric( $pdm->get_buffer_pool_size( 'InnoDB' ) );
		$this->assertGreaterThan( 2048, $pdm->get_buffer_pool_size( 'InnoDB' ) );
		$this->AssertIsNumeric( $pdm->get_buffer_pool_size( 'MyISAM' ) );
		$this->assertGreaterThan( 2048, $pdm->get_buffer_pool_size( 'MyISAM' ) );

		try {
			$pdm->get_buffer_pool_size( 'XYZZY' );
		} catch ( InvalidArgumentException $exception ) {
			$this->assertInstanceOf( InvalidArgumentException::class, $exception, 'Wrong Exception was thrown' );
			return;
		}

		$this->fail( 'Bogus engine name not caught' );
	}

	public function test_get_table_info() {

		global $wpdb;
		$pdm        = new PerflabDbMetrics();
		$table_info = $pdm->get_table_info();
		$this->assertIsArray( $table_info, '$table_info should be an array.' );
		foreach ( $wpdb->tables() as $table ) {
			$this->AssertArrayHasKey( $table, $table_info, '$table_info should contain ' . $table );
		}

		foreach ( $table_info as $table => $info ) {
			$this->assertIsString( $info->table_name );
			$this->assertIsString( $info->engine );
			$this->assertIsString( $info->row_format );
			$this->assertIsNumeric( $info->row_count );
			$this->assertIsNumeric( $info->data_bytes );
			$this->assertIsNumeric( $info->index_bytes );
			$this->assertIsNumeric( $info->total_bytes );
		}

		$table_info = $pdm->get_table_info( $wpdb->tables() );
		$this->assertIsArray( $table_info );
		foreach ( $wpdb->tables() as $table ) {
			$this->AssertArrayHasKey( $table, $table_info );
		}
		$this->assertEquals( count( $wpdb->tables() ), count( $table_info ) );

	}
	public function test_get_indexes() {

		$pdm        = new PerflabDbMetrics();
		$table_info = $pdm->get_table_info();
		foreach ( (array) $table_info as $table => $info ) {
			$indexes = $pdm->get_indexes( $table );
			$this->assertIsArray( $indexes );
		}
		global $wpdb;
		$table_info = $pdm->get_table_info( $wpdb->tables() );
		foreach ( (array) $table_info as $table => $info ) {
			$indexes = $pdm->get_indexes( $table );
			$this->assertIsArray( $indexes );
			$this->assertArrayHasKey( 'PRIMARY KEY', $indexes, 'WordPress core tables always have PKs' );
		}
	}

	function test_now_microseconds() {
		$pdm      = new PerflabDbMetrics();
		$thenusec = $pdm->now_microseconds();
		sleep( 1 );
		$nowusec = $pdm->now_microseconds();
		$this->assertGreaterThan( $thenusec, $nowusec, 'Time keeps on slippin\', slippin\' into the future.' );
		$this->assertEqualsWithDelta( $thenusec, $nowusec, 1500000, 'nondeterministic, possibly fails on heavily loaded test system.' );
	}

	function test_sampling() {
		$pdm = new PerflabDbMetrics();

		$diffs = $pdm->tracking_variable_changes();
		$this->assertGreaterThan( 0, $diffs['delta_timestamp'] );
		$this->assertEqualsWithDelta( time(), $diffs ['timestamp'], 2 );
		$this->assertIsNumeric( $diffs ['Uptime'] );
		$this->assertIsNumeric( $diffs ['innodb_buffer_pool_size'] );
		$this->assertIsNumeric( $diffs ['key_buffer_size'] );
	}

	function test_arithmetic() {
		$pdu = PerflabDbUtilities::get_instance();
		$this->assertEquals( 65536, $pdu->power_of_two_round_up( 65535 ) );
		$this->assertEquals( 65536, $pdu->power_of_two_round_up( 65536 ) );
		$this->assertEquals( 65536, $pdu->power_of_two_round_up( 32769 ) );
		$this->assertEquals( 1024 * 1024 * 1024, $pdu->power_of_two_round_up( 1024 * 1024 * 513 ) );
		$this->assertEquals( 1024 * 1024 * 1024, $pdu->power_of_two_round_up( 1024 * 1024 * 1024 ) );
		$this->assertEquals( 1024 * 1024 * 1024 * 1024, $pdu->power_of_two_round_up( 1024 * 1024 * 1024 * 1023 ) );
		$this->assertEquals( 1024 * 1024 * 1024 * 1024, $pdu->power_of_two_round_up( 1024 * 1024 * 1024 * 1024 ) );
		$this->assertEquals( 1, $pdu->power_of_two_round_up( 1 ) );
		$this->assertEquals( 4, $pdu->power_of_two_round_up( 3 ) );
		$this->assertEquals( 1, $pdu->power_of_two_round_up( 0 ) );
		$this->assertEquals( 1, $pdu->power_of_two_round_up( -10 ) );

	}
}

