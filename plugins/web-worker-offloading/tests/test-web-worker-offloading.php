<?php
/**
 * Test cases for Web Worker Offloading.
 *
 * @package web-worker-offloading
 */

class Test_Web_Worker_Offloading extends WP_UnitTestCase {

	/**
	 * @covers ::wwo_configuration
	 */
	public function test_wwo_configuration(): void {
		$wp_content_dir        = WP_CONTENT_DIR;
		$partytown_assets_path = 'web-worker-offloading/build/';
		$config                = wwo_configuration();

		$this->assertArrayHasKey( 'lib', $config );
		$this->assertArrayHasKey( 'forward', $config );
		$this->assertEmpty( $config['forward'] );
		$this->assertIsArray( $config['forward'] );
		$this->assertStringStartsWith( '/' . basename( $wp_content_dir ), $config['lib'] );
		$this->assertStringEndsWith( $partytown_assets_path, $config['lib'] );

		// Test `wwo_configuration` filter.
		add_filter(
			'wwo_configuration',
			static function ( $config ) {
				$config['forward'] = array( 'datalayer.push' );
				$config['debug']   = true;
				return $config;
			}
		);

		$config = wwo_configuration();

		$this->assertArrayHasKey( 'forward', $config );
		$this->assertArrayHasKey( 'debug', $config );
		$this->assertNotEmpty( $config['forward'] );
		$this->assertIsArray( $config['forward'] );
		$this->assertTrue( $config['debug'] );
		$this->assertContains( 'datalayer.push', $config['forward'] );

		remove_all_filters( 'wwo_configuration' );
	}

	/**
	 * @covers ::wwo_init
	 */
	public function test_wwo_init(): void {
		$this->assertEquals( 10, has_action( 'wp_enqueue_scripts', 'wwo_init' ) );

		// Register scripts.
		wwo_init();

		$wp_content_dir   = WP_CONTENT_DIR;
		$partytown_config = wwo_configuration();
		$partytown_lib    = dirname( $wp_content_dir ) . $partytown_config['lib'];
		$before_data      = wp_scripts()->get_inline_script_data( 'web-worker-offloader', 'before' );
		$after_data       = wp_scripts()->get_inline_script_data( 'web-worker-offloader', 'after' );

		$this->assertTrue( wp_script_is( 'web-worker-offloader', 'registered' ) );
		$this->assertNotEmpty( $before_data );
		$this->assertNotEmpty( $after_data );
		$this->assertEquals(
			sprintf( 'window.partytown = %s;', wp_json_encode( $partytown_config ) ),
			$before_data
		);
		$this->assertEquals( file_get_contents( $partytown_lib . 'partytown.js' ), $after_data );
		$this->assertTrue( wp_script_is( 'web-worker-offloader', 'registered' ) );

		// Ensure that Partytown is enqueued when a script depends on it.
		wp_enqueue_script( 'partytown-test', 'https://example.com/test.js', array( 'web-worker-offloader' ) );

		$this->assertTrue( wp_script_is( 'web-worker-offloader', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'partytown-test', 'enqueued' ) );

		// Reset the state.
		wp_scripts()->remove( 'web-worker-offloader' );
		wp_scripts()->remove( 'partytown-test' );
	}

	/**
	 * @covers ::wwo_get_web_worker_offloader_handles
	 */
	public function test_wwo_get_web_worker_offloader_handles(): void {
		$handles = wwo_get_web_worker_offloader_handles();

		$this->assertEmpty( $handles );

		// Enqueue a script that depends on Partytown.
		wp_enqueue_script( 'partytown-test', 'https://example.com/test.js', array( 'web-worker-offloader' ) );

		$handles = wwo_get_web_worker_offloader_handles();

		$this->assertNotEmpty( $handles );
		$this->assertContains( 'partytown-test', $handles );
		$this->assertTrue( wp_script_is( 'web-worker-offloader', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'partytown-test', 'enqueued' ) );

		// Reset the state.
		wp_scripts()->remove( 'web-worker-offloader' );
		wp_scripts()->remove( 'partytown-test' );
	}
}
