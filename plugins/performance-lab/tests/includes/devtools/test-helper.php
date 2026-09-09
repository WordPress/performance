<?php
/**
 * Tests for devtools/helper.php
 *
 * @package performance-lab
 */

/**
 * @group devtools
 */
class Test_DevTools_Helper extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public function tear_down(): void {
		wp_dequeue_script_module( 'perflab-devtools-discovery' );
		wp_deregister_script_module( 'perflab-devtools-discovery' );
		parent::tear_down();
	}

	public function test_hooks_added(): void {
		$this->assertSame( 10, has_action( 'wp_enqueue_scripts', 'perflab_devtools_enqueue_script_module' ) );
		$this->assertSame( PHP_INT_MAX, has_action( 'wp_footer', 'perflab_devtools_print_data' ) );
	}

	/**
	 * @covers ::perflab_devtools_get_capability
	 */
	public function test_perflab_devtools_get_capability_default(): void {
		$this->assertSame( 'manage_options', perflab_devtools_get_capability() );
	}

	/**
	 * @covers ::perflab_devtools_get_capability
	 */
	public function test_perflab_devtools_get_capability_filtered(): void {
		add_filter(
			'perflab_devtools_capability',
			static function (): string {
				return 'edit_posts';
			}
		);
		$this->assertSame( 'edit_posts', perflab_devtools_get_capability() );
	}

	/**
	 * @covers ::perflab_devtools_get_capability
	 */
	public function test_perflab_devtools_get_capability_invalid_filter_value_falls_back_to_default(): void {
		add_filter( 'perflab_devtools_capability', '__return_empty_string' );
		$this->assertSame( 'manage_options', perflab_devtools_get_capability() );

		remove_filter( 'perflab_devtools_capability', '__return_empty_string' );
		add_filter( 'perflab_devtools_capability', '__return_false' );
		$this->assertSame( 'manage_options', perflab_devtools_get_capability() );
	}

	/**
	 * @covers ::perflab_devtools_get_asset_path
	 */
	public function test_perflab_devtools_get_asset_path(): void {
		$path = perflab_devtools_get_asset_path();
		$this->assertStringStartsWith( 'includes/devtools/devtools-discovery', $path );
		$this->assertStringEndsWith( '.js', $path );
		if ( 'includes/devtools/devtools-discovery.min.js' === $path ) {
			$this->assertFileExists( PERFLAB_PLUGIN_DIR_PATH . $path );
		}
	}

	/**
	 * @covers ::perflab_devtools_enqueue_script_module
	 */
	public function test_perflab_devtools_enqueue_script_module_for_unauthorized_user(): void {
		wp_set_current_user( self::$subscriber_id );
		perflab_devtools_enqueue_script_module();
		$this->assertStringNotContainsString( 'devtools-discovery', get_echo( array( wp_script_modules(), 'print_enqueued_script_modules' ) ) );
	}

	/**
	 * @covers ::perflab_devtools_enqueue_script_module
	 */
	public function test_perflab_devtools_enqueue_script_module_for_admin(): void {
		wp_set_current_user( self::$admin_id );
		perflab_devtools_enqueue_script_module();
		$output = get_echo( array( wp_script_modules(), 'print_enqueued_script_modules' ) );
		$this->assertStringContainsString( 'devtools-discovery', $output );
		$this->assertStringContainsString( 'type="module"', $output );
	}

	/**
	 * @covers ::perflab_devtools_print_data
	 */
	public function test_perflab_devtools_print_data_for_unauthorized_user(): void {
		wp_set_current_user( self::$subscriber_id );
		$this->assertSame( '', get_echo( 'perflab_devtools_print_data' ) );

		wp_set_current_user( 0 );
		$this->assertSame( '', get_echo( 'perflab_devtools_print_data' ) );
	}

	/**
	 * @covers ::perflab_devtools_print_data
	 */
	public function test_perflab_devtools_print_data_for_admin(): void {
		wp_set_current_user( self::$admin_id );
		$output = get_echo( 'perflab_devtools_print_data' );
		$this->assertStringContainsString( 'id="perflab-devtools-data"', $output );
		$this->assertStringContainsString( 'type="application/json"', $output );

		$data = $this->get_printed_data( $output );
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'environment', $data );
		$this->assertArrayHasKey( 'dbQueries', $data );
	}

	/**
	 * @covers ::perflab_devtools_print_data
	 */
	public function test_perflab_devtools_print_data_with_filtered_capability(): void {
		wp_set_current_user( self::$subscriber_id );
		add_filter(
			'perflab_devtools_capability',
			static function (): string {
				return 'read';
			}
		);
		$output = get_echo( 'perflab_devtools_print_data' );
		$this->assertStringContainsString( 'id="perflab-devtools-data"', $output );
	}

	/**
	 * @covers ::perflab_devtools_get_environment_info
	 */
	public function test_perflab_devtools_get_environment_info(): void {
		$info = perflab_devtools_get_environment_info();
		$this->assertSame( get_bloginfo( 'version' ), $info['wpVersion'] );
		$this->assertSame( phpversion(), $info['phpVersion'] );
		$this->assertSame( get_stylesheet(), $info['theme']['stylesheet'] );
		$this->assertIsBool( $info['usingExternalObjectCache'] );
		$this->assertIsBool( $info['wpDebug'] );
		$this->assertIsBool( $info['saveQueries'] );
		$this->assertSame( is_multisite(), $info['isMultisite'] );
		$this->assertIsArray( $info['activePlugins'] );
	}

	/**
	 * @covers ::perflab_devtools_get_database_queries
	 */
	public function test_perflab_devtools_get_database_queries(): void {
		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
			$queries = perflab_devtools_get_database_queries();
			$this->assertIsArray( $queries );
			$this->assertArrayHasKey( 'count', $queries );
			$this->assertArrayHasKey( 'totalTimeMs', $queries );
			$this->assertArrayHasKey( 'queries', $queries );
		} else {
			$this->assertNull( perflab_devtools_get_database_queries() );
		}
	}

	/**
	 * @covers ::perflab_devtools_map_database_queries
	 */
	public function test_perflab_devtools_map_database_queries(): void {
		$long_sql = 'SELECT ' . str_repeat( 'option_name, ', 500 ) . 'option_value FROM wp_options';

		$mapped = perflab_devtools_map_database_queries(
			array(
				array( ' SELECT * FROM wp_posts ', 0.0123, 'require, wp, WP_Query->get_posts' ),
				array( $long_sql, 0.5, 'require, get_option' ),
				'not-an-array',
				array( 'missing time and caller' ),
			)
		);

		$this->assertSame( 2, $mapped['count'] );
		$this->assertSame( 512.3, $mapped['totalTimeMs'] );
		$this->assertCount( 2, $mapped['queries'] );

		$this->assertSame( 'SELECT * FROM wp_posts', $mapped['queries'][0]['sql'] );
		$this->assertSame( 12.3, $mapped['queries'][0]['timeMs'] );
		$this->assertSame( 'require, wp, WP_Query->get_posts', $mapped['queries'][0]['caller'] );

		$this->assertSame( 2000 + strlen( '…' ), strlen( $mapped['queries'][1]['sql'] ) );
		$this->assertStringEndsWith( '…', $mapped['queries'][1]['sql'] );
	}

	/**
	 * Extracts and decodes the JSON payload from the printed script tag.
	 *
	 * @param string $output Output of perflab_devtools_print_data().
	 * @return mixed Decoded data.
	 */
	private function get_printed_data( string $output ) {
		$this->assertSame( 1, preg_match( '#<script[^>]*>(.+)</script>#s', $output, $matches ) );
		return json_decode( $matches[1], true );
	}
}
