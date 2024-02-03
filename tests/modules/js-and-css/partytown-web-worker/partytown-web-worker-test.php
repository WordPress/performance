<?php
/**
 * Test cases for Partytown Web Worker module.
 *
 * @package performance-lab
 */
class Partytown_Web_Worker_Tests extends WP_UnitTestCase {

	/**
	 * Cache original $wp_scripts global.
	 *
	 * @var WP_Scripts
	 */
	private static $wp_scripts_cache;

	/**
	 * Setup before class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		global $wp_scripts;

		// Cache the original $wp_scripts global.
		self::$wp_scripts_cache = $wp_scripts;
	}

	/**
	 * Tear down after class.
	 */
	public static function tear_down_after_class() {
		global $wp_scripts;

		// Restore the original $wp_scripts global.
		$wp_scripts = self::$wp_scripts_cache;

		parent::tear_down_after_class();
	}


	/**
	 * @covers ::perflab_partytown_web_worker_configuration
	 */
	public function test_perflab_partytown_web_worker_configuration() {
		$wp_content_dir        = WP_CONTENT_DIR;
		$partytown_assets_path = 'modules/js-and-css/partytown-web-worker/assets/js/partytown/';
		$config                = perflab_partytown_web_worker_configuration();

		$this->assertArrayHasKey( 'lib', $config );
		$this->assertArrayHasKey( 'forward', $config );
		$this->assertEmpty( $config['forward'] );
		$this->assertIsArray( $config['forward'] );
		$this->assertStringStartsWith( '/' . basename( $wp_content_dir ), $config['lib'] );
		$this->assertStringEndsWith( $partytown_assets_path, $config['lib'] );

		// Test `perflab_partytown_configuration` filter.
		add_filter(
			'perflab_partytown_configuration',
			static function ( $config ) {
				$config['forward'] = array( 'datalayer.push' );
				$config['debug']   = true;
				return $config;
			}
		);

		$config = perflab_partytown_web_worker_configuration();

		$this->assertArrayHasKey( 'forward', $config );
		$this->assertArrayHasKey( 'debug', $config );
		$this->assertNotEmpty( $config['forward'] );
		$this->assertIsArray( $config['forward'] );
		$this->assertTrue( $config['debug'] );
		$this->assertContains( 'datalayer.push', $config['forward'] );

		remove_all_filters( 'perflab_partytown_configuration' );
	}

	/**
	 * @covers ::perflab_partytown_web_worker_init
	 */
	public function test_perflab_partytown_web_worker_init() {
		global $wp_scripts;

		$this->assertEquals( 10, has_action( 'wp_enqueue_scripts', 'perflab_partytown_web_worker_init' ) );

		$wp_content_dir   = WP_CONTENT_DIR;
		$partytown_config = perflab_partytown_web_worker_configuration();
		$partytown_lib    = dirname( $wp_content_dir ) . $partytown_config['lib'];
		$before_data      = $wp_scripts->get_inline_script_data( 'partytown', 'before' );
		$after_data       = $wp_scripts->get_inline_script_data( 'partytown', 'after' );

		$this->assertTrue( wp_script_is( 'partytown', 'registered' ) );
		$this->assertNotEmpty( $before_data );
		$this->assertNotEmpty( $after_data );
		$this->assertEquals(
			sprintf( 'window.partytown = %s;', wp_json_encode( $partytown_config ) ),
			$before_data
		);
		$this->assertEquals( file_get_contents( $partytown_lib . 'partytown.js' ), $after_data );
		$this->assertTrue( wp_script_is( 'partytown', 'registered' ) );

		// Ensure that Partytown is enqueued when a script depends on it.
		wp_enqueue_script( 'partytown-test', 'https://example.com/test.js', array( 'partytown' ) );

		$this->assertTrue( wp_script_is( 'partytown', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'partytown-test', 'enqueued' ) );

		// Reset the state.
		$wp_scripts->remove( 'partytown' );
		$wp_scripts->remove( 'partytown-test' );
	}

	/**
	 * @covers ::perflab_get_partytown_handles
	 */
	public function test_perflab_get_partytown_handles() {
		global $wp_scripts;

		$handles = perflab_get_partytown_handles();

		$this->assertEmpty( $handles );
		$this->assertIsArray( $handles );

		// Enqueue a script that depends on Partytown.
		wp_enqueue_script( 'partytown-test', 'https://example.com/test.js', array( 'partytown' ) );

		$handles = perflab_get_partytown_handles();

		$this->assertIsArray( $handles );
		$this->assertNotEmpty( $handles );
		$this->assertContains( 'partytown-test', $handles );
		$this->assertTrue( wp_script_is( 'partytown', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'partytown-test', 'enqueued' ) );

		// Reset the state.
		$wp_scripts->remove( 'partytown' );
		$wp_scripts->remove( 'partytown-test' );
	}
}
