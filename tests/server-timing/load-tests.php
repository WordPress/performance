<?php
/**
 * Tests for server-timing/load.php
 *
 * @package performance-lab
 */

/**
 * @group server-timing
 */
class Server_Timing_Load_Tests extends WP_UnitTestCase {

	public function test_perflab_server_timing() {
		$server_timing = perflab_server_timing();
		$this->assertInstanceOf( Perflab_Server_Timing::class, $server_timing );
		$this->assertSame( PHP_INT_MAX, has_filter( 'template_include', array( $server_timing, 'on_template_include' ) ), 'template_include filter not added' );

		$server_timing2 = perflab_server_timing();
		$this->assertSame( $server_timing, $server_timing2, 'Different instance returned' );
	}

	public function test_perflab_server_timing_register_metric() {
		$this->assertFalse( perflab_server_timing()->has_registered_metric( 'test-metric' ) );

		perflab_server_timing_register_metric(
			'test-metric',
			array(
				'measure_callback' => static function( $metric ) {
					$metric->set_value( 100 );
				},
				'access_cap'       => 'exist',
			)
		);
		$this->assertTrue( perflab_server_timing()->has_registered_metric( 'test-metric' ) );
	}

	public function test_perflab_server_timing_use_output_buffer() {
		$this->assertFalse( perflab_server_timing_use_output_buffer() );

		add_filter( 'perflab_server_timing_use_output_buffer', '__return_true' );
		$this->assertTrue( perflab_server_timing_use_output_buffer() );
	}

	public function test_perflab_wrap_server_timing() {
		$cb = static function() {
			return 123;
		};

		$wrapped = perflab_wrap_server_timing( $cb, 'wrapped-cb-without-capability', 'manage_options' );
		$this->assertSame( 123, $wrapped(), 'Wrapped callback without capability did not return expected value' );
		$this->assertTrue( perflab_server_timing()->has_registered_metric( 'wrapped-cb-without-capability' ), 'Wrapped callback metric should be registered despite lack of capability' );
		$this->assertStringNotContainsString( 'wrapped-cb-without-capability', perflab_server_timing()->get_header(), 'Wrapped callback was measured despite lack of capability' );

		$wrapped = perflab_wrap_server_timing( $cb, 'wrapped-cb-with-capability', 'exist' );
		$this->assertSame( 123, $wrapped(), 'Wrapped callback with capability did not return expected value' );
		$this->assertTrue( perflab_server_timing()->has_registered_metric( 'wrapped-cb-with-capability' ), 'Wrapped callback metric should be registered' );
		$this->assertStringContainsString( 'wrapped-cb-with-capability', perflab_server_timing()->get_header(), 'Wrapped callback was not measured despite having necessary capability' );

		$this->assertSame( 123, $wrapped(), 'Calling wrapped callback multiple times should not result in warning' );
	}

	public function test_perflab_register_server_timing_setting() {
		global $new_allowed_options, $wp_registered_settings;

		// Reset relevant globals.
		$wp_registered_settings = array();
		$new_allowed_options = array();

		perflab_register_server_timing_setting();

		// Assert that the setting is correctly registered.
		$settings = get_registered_settings();
		$this->assertTrue( isset( $settings[ PERFLAB_SERVER_TIMING_SETTING ] ) );

		// Assert that the setting is allowlisted for the relevant screen.
		$this->assertTrue( isset( $new_allowed_options[ PERFLAB_SERVER_TIMING_SCREEN ] ) );
		$this->assertSame( array( PERFLAB_SERVER_TIMING_SETTING ), $new_allowed_options[ PERFLAB_SERVER_TIMING_SCREEN ] );

		// Assert that registered default works correctly.
		$this->assertSame( array(), get_option( PERFLAB_SERVER_TIMING_SETTING ) );

		// Assert that most basic sanitization works correctly (an array is required).
		update_option( PERFLAB_SERVER_TIMING_SETTING, 'invalid' );
		$this->assertSame( array(), get_option( PERFLAB_SERVER_TIMING_SETTING ) );
	}

	/**
	 * @dataProvider data_perflab_sanitize_server_timing_setting
	 *
	 * @param mixed $unsanitized Unsanitized input.
	 * @param array $expected    Expected sanitized output.
	 */
	public function test_perflab_sanitize_server_timing_setting( $unsanitized, $expected ) {
		$sanitized = perflab_sanitize_server_timing_setting( $unsanitized );
		$this->assertSame( $expected, $sanitized );
	}

	public function data_perflab_sanitize_server_timing_setting() {
		return array(
			'invalid type'                                => array(
				'invalid',
				array(),
			),
			'empty list, array'                           => array(
				array( 'benchmarking_actions' => array() ),
				array( 'benchmarking_actions' => array() ),
			),
			'empty list, string'                          => array(
				array( 'benchmarking_actions' => '' ),
				array( 'benchmarking_actions' => array() ),
			),
			'empty list, string with whitespace'          => array(
				array( 'benchmarking_actions' => ' ' ),
				array( 'benchmarking_actions' => array() ),
			),
			'regular list, array'                         => array(
				array( 'benchmarking_actions' => array( 'after_setup_theme', 'init', 'wp_loaded' ) ),
				array( 'benchmarking_actions' => array( 'after_setup_theme', 'init', 'wp_loaded' ) ),
			),
			'regular list, string'                        => array(
				array( 'benchmarking_actions' => "after_setup_theme\ninit\nwp_loaded" ),
				array( 'benchmarking_actions' => array( 'after_setup_theme', 'init', 'wp_loaded' ) ),
			),
			'regular list, string with whitespace'        => array(
				array( 'benchmarking_actions' => "after_setup_  theme \ninit \n\nwp_loaded\n" ),
				array( 'benchmarking_actions' => array( 'after_setup_theme', 'init', 'wp_loaded' ) ),
			),
			'regular list, array with duplicates'         => array(
				array( 'benchmarking_actions' => array( 'after_setup_theme', 'init', 'wp_loaded', 'init' ) ),
				array( 'benchmarking_actions' => array( 'after_setup_theme', 'init', 'wp_loaded' ) ),
			),
			'regular list, array with special hook chars' => array(
				array( 'benchmarking_actions' => array( 'namespace/hookname', 'namespace.hookname' ) ),
				array( 'benchmarking_actions' => array( 'namespace/hookname', 'namespace.hookname' ) ),
			),
			'regular list, disallowed key'                => array(
				array( 'not_allowed' => array( 'after_setup_theme', 'init', 'wp_loaded' ) ),
				array(),
			),
		);
	}
}
