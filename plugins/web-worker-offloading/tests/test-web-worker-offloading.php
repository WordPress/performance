<?php
/**
 * Test cases for Web Worker Offloading.
 *
 * @package web-worker-offloading
 */

class Test_Web_Worker_Offloading extends WP_UnitTestCase {

	/**
	 * Set up the test case.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->reset_wp_dependencies();
		add_theme_support( 'html5', array( 'script' ) );
	}

	/**
	 * Tear down the test case.
	 */
	public function tear_down(): void {
		parent::tear_down();
		$this->reset_wp_dependencies();
	}

	/**
	 * @covers ::wwo_get_configuration
	 */
	public function test_wwo_get_configuration(): void {
		$wp_content_dir        = WP_CONTENT_DIR;
		$partytown_assets_path = 'web-worker-offloading/build/';
		$config                = wwo_get_configuration();

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

		$config = wwo_get_configuration();

		$this->assertArrayHasKey( 'forward', $config );
		$this->assertArrayHasKey( 'debug', $config );
		$this->assertNotEmpty( $config['forward'] );
		$this->assertIsArray( $config['forward'] );
		$this->assertTrue( $config['debug'] );
		$this->assertContains( 'datalayer.push', $config['forward'] );
	}

	/**
	 * @covers ::wwo_init
	 */
	public function test_wwo_init(): void {
		$this->assertEquals( 10, has_action( 'wp_enqueue_scripts', 'wwo_init' ) );

		// Register scripts.
		wwo_init();

		$wp_content_dir   = WP_CONTENT_DIR;
		$partytown_config = wwo_get_configuration();
		$partytown_lib    = dirname( $wp_content_dir ) . $partytown_config['lib'];
		$before_data      = wp_scripts()->get_inline_script_data( 'web-worker-offloading', 'before' );
		$after_data       = wp_scripts()->get_inline_script_data( 'web-worker-offloading', 'after' );

		$this->assertTrue( wp_script_is( 'web-worker-offloading', 'registered' ) );
		$this->assertNotEmpty( $before_data );
		$this->assertNotEmpty( $after_data );
		$this->assertEquals(
			sprintf( 'window.partytown = %s;', wp_json_encode( $partytown_config ) ),
			$before_data
		);
		$this->assertEquals( file_get_contents( $partytown_lib . 'partytown.js' ), $after_data );
		$this->assertTrue( wp_script_is( 'web-worker-offloading', 'registered' ) );

		// Ensure that Partytown is enqueued when a script depends on it.
		wp_enqueue_script( 'partytown-test', 'https://example.com/test.js', array( 'web-worker-offloading' ) );

		$this->assertTrue( wp_script_is( 'web-worker-offloading', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'partytown-test', 'enqueued' ) );
	}

	/**
	 * Data provider for testing `wwo_update_script_type`.
	 *
	 * @return array<string, mixed> Data.
	 */
	public static function data_update_script_types(): array {
		return array(
			'add-script'                           => array(
				'set_up'         => static function (): void {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), '1.0.0', true );
				},
				'expected'       => '<script src="https://example.com/foo.js?ver=1.0.0" id="foo-js"></script>',
				'doing_it_wrong' => false,
			),
			'add-script-for-web-worker-offloading' => array(
				'set_up'         => static function (): void {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array( 'web-worker-offloading' ), '1.0.0', true );
				},
				'expected'       => '{{ wwo_config }}{{ wwo_inline_script }}<script type="text/partytown" src="https://example.com/foo.js?ver=1.0.0" id="foo-js"  ></script>',
				'doing_it_wrong' => false,
			),
			'add-script-for-web-worker-offloading-with-before-data' => array(
				'set_up'         => static function (): void {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array( 'web-worker-offloading' ), '1.0.0', true );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'before' );
				},
				'expected'       => '{{ wwo_config }}{{ wwo_inline_script }}<script id="foo-js-before">console.log("Hello, World!");</script><script type="text/partytown" src="https://example.com/foo.js?ver=1.0.0" id="foo-js"  ></script>',
				'doing_it_wrong' => false,
			),
			'add-script-for-web-worker-offloading-with-after-data' => array(
				'set_up'         => static function (): void {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array( 'web-worker-offloading' ), '1.0.0', true );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'after' );
				},
				'expected'       => '{{ wwo_config }}{{ wwo_inline_script }}<script src="https://example.com/foo.js?ver=1.0.0" id="foo-js" data-wp-strategy="async"></script><script id="foo-js-after">console.log("Hello, World!");</script>',
				'doing_it_wrong' => true,
			),
			'add-script-for-web-worker-offloading-with-before-and-after-data' => array(
				'set_up'         => static function (): void {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array( 'web-worker-offloading' ), '1.0.0', true );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'before' );
					wp_add_inline_script( 'foo', 'console.log("Hello, World!");', 'after' );
				},
				'expected'       => '{{ wwo_config }}{{ wwo_inline_script }}<script id="foo-js-before">console.log("Hello, World!");</script><script src="https://example.com/foo.js?ver=1.0.0" id="foo-js" data-wp-strategy="async"></script><script id="foo-js-after">console.log("Hello, World!");</script>',
				'doing_it_wrong' => true,
			),
		);
	}

	/**
	 * Test `wwo_update_script_type`.
	 *
	 * @covers ::wwo_update_script_type
	 * @covers ::wwo_update_script_strategy
	 *
	 * @dataProvider data_update_script_types
	 *
	 * @param Closure $set_up         Closure to set up the test.
	 * @param string  $expected       Expected output.
	 * @param bool    $doing_it_wrong Whether to expect a `_doing_it_wrong` notice.
	 */
	public function test_update_script_types( Closure $set_up, string $expected, bool $doing_it_wrong ): void {
		// Setup.
		wwo_init();

		$wwo_config_data        = wp_scripts()->get_inline_script_data( 'web-worker-offloading', 'before' );
		$wwo_inline_script_data = wp_scripts()->get_inline_script_data( 'web-worker-offloading', 'after' );

		$expected = str_replace(
			'{{ wwo_config }}',
			wp_get_inline_script_tag( $wwo_config_data, array( 'id' => 'web-worker-offloading-js-before' ) ),
			$expected
		);
		$expected = str_replace(
			'{{ wwo_inline_script }}',
			wp_get_inline_script_tag( $wwo_inline_script_data, array( 'id' => 'web-worker-offloading-js-after' ) ),
			$expected
		);

		if ( $doing_it_wrong ) {
			$this->setExpectedIncorrectUsage( 'wwo_update_script_type' );
		}

		$set_up();

		// Normalize the output.
		$actual   = preg_replace( '/\r|\n/', '', get_echo( 'wp_print_scripts' ) );
		$expected = preg_replace( '/\r|\n/', '', $expected );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Reset WP_Scripts and WP_Styles.
	 */
	private function reset_wp_dependencies(): void {
		$GLOBALS['wp_scripts'] = null;
	}
}
