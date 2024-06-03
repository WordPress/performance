<?php
/**
 * Tests for server-timing/load.php
 *
 * @package performance-lab
 */

/**
 * @group server-timing
 */
class Test_Server_Timing_Load extends WP_UnitTestCase {

	public function test_perflab_server_timing(): void {
		$this->assertSame( 10, has_action( 'wp_loaded', 'perflab_server_timing_init' ) );
		perflab_server_timing_init(); // Already called during bootstrap.
		$this->assertTrue( has_filter( 'template_include' ) );

		$server_timing = perflab_server_timing();
		$this->assertFalse( $server_timing->use_output_buffer() );
		$this->assertSame( PHP_INT_MAX, has_filter( 'template_include', array( $server_timing, 'on_template_include' ) ), 'template_include filter not added' );
		$this->assertFalse( has_action( 'template_redirect', array( $server_timing, 'start_output_buffer' ) ), 'template_redirect action added' );

		$server_timing2 = perflab_server_timing();
		$this->assertSame( $server_timing, $server_timing2, 'Different instance returned' );
	}

	/**
	 * @covers Perflab_Server_Timing::add_hooks
	 */
	public function test_perflab_server_timing_with_output_buffering(): void {
		remove_all_actions( 'template_redirect' );
		remove_all_filters( 'template_include' );

		$server_timing = perflab_server_timing();
		add_filter( 'perflab_server_timing_use_output_buffer', '__return_true' );
		$this->assertTrue( $server_timing->use_output_buffer() );
		$server_timing->add_hooks();
		$this->assertFalse( has_filter( 'template_include', array( $server_timing, 'on_template_include' ) ), 'template_include filter added' );
		$this->assertSame( PHP_INT_MIN, has_action( 'template_redirect', array( $server_timing, 'start_output_buffer' ) ), 'template_redirect action not added' );
	}

	public function test_perflab_server_timing_register_metric(): void {
		$this->assertFalse( perflab_server_timing()->has_registered_metric( 'test-metric' ) );

		perflab_server_timing_register_metric(
			'test-metric',
			array(
				'measure_callback' => static function ( $metric ): void {
					$metric->set_value( 100 );
				},
				'access_cap'       => 'exist',
			)
		);
		$this->assertTrue( perflab_server_timing()->has_registered_metric( 'test-metric' ) );
	}

	public function test_perflab_server_timing_use_output_buffer(): void {
		$this->assertFalse( perflab_server_timing_use_output_buffer() );

		add_filter( 'perflab_server_timing_use_output_buffer', '__return_true' );
		$this->assertTrue( perflab_server_timing_use_output_buffer() );
	}

	public function test_perflab_wrap_server_timing(): void {
		$cb = static function () {
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

	/**
	 * @covers ::perflab_get_server_timing_setting_default_value
	 * @covers ::perflab_register_server_timing_setting
	 * @covers ::perflab_sanitize_server_timing_setting
	 */
	public function test_perflab_register_server_timing_setting(): void {
		global $new_allowed_options, $wp_registered_settings;

		// Reset relevant globals.
		$wp_registered_settings = array();
		$new_allowed_options    = array();

		perflab_register_server_timing_setting();

		// Assert that the setting is correctly registered.
		$settings = get_registered_settings();
		$this->assertTrue( isset( $settings[ PERFLAB_SERVER_TIMING_SETTING ] ) );

		// Assert that the setting is allowlisted for the relevant screen.
		$this->assertArrayHasKey( PERFLAB_SERVER_TIMING_SCREEN, $new_allowed_options );
		$this->assertSame( array( PERFLAB_SERVER_TIMING_SETTING ), $new_allowed_options[ PERFLAB_SERVER_TIMING_SCREEN ] );

		$expected_default = array(
			'benchmarking_actions' => array(),
			'benchmarking_filters' => array(),
			'output_buffering'     => false,
		);
		$this->assertSame( $expected_default, perflab_get_server_timing_setting_default_value() );

		// Assert that registered default works correctly.
		$this->assertSame( $expected_default, get_option( PERFLAB_SERVER_TIMING_SETTING ) );

		// Assert that most basic sanitization works correctly (an array is required).
		update_option( PERFLAB_SERVER_TIMING_SETTING, 'invalid' );
		$this->assertSame( $expected_default, get_option( PERFLAB_SERVER_TIMING_SETTING ) );
	}

	/**
	 * @dataProvider data_perflab_sanitize_server_timing_setting
	 *
	 * @param mixed                $unsanitized Unsanitized input.
	 * @param array<string, mixed> $expected    Expected sanitized output.
	 */
	public function test_perflab_sanitize_server_timing_setting( $unsanitized, array $expected ): void {
		$sanitized = perflab_sanitize_server_timing_setting( $unsanitized );
		$this->assertSame( $expected, $sanitized );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function data_perflab_sanitize_server_timing_setting(): array {
		$default = array(
			'benchmarking_actions' => array(),
			'benchmarking_filters' => array(),
			'output_buffering'     => false,
		);

		return array(
			'invalid type'                                => array(
				'invalid',
				$default,
			),
			'empty list, array'                           => array(
				array( 'benchmarking_actions' => array() ),
				array_merge(
					$default,
					array(
						'benchmarking_actions' => array(),
						'output_buffering'     => false,
					)
				),
			),
			'empty list, string'                          => array(
				array( 'benchmarking_actions' => '' ),
				array_merge(
					$default,
					array(
						'benchmarking_actions' => array(),
						'output_buffering'     => false,
					)
				),
			),
			'empty list, string with whitespace'          => array(
				array( 'benchmarking_actions' => ' ' ),
				array_merge(
					$default,
					array(
						'benchmarking_actions' => array(),
						'output_buffering'     => false,
					)
				),
			),
			'regular list, array'                         => array(
				array( 'benchmarking_actions' => array( 'after_setup_theme', 'init', 'wp_loaded' ) ),
				array_merge(
					$default,
					array(
						'benchmarking_actions' => array( 'after_setup_theme', 'init', 'wp_loaded' ),
						'output_buffering'     => false,
					)
				),
			),
			'regular list, string'                        => array(
				array( 'benchmarking_actions' => "after_setup_theme\ninit\nwp_loaded" ),
				array_merge(
					$default,
					array(
						'benchmarking_actions' => array( 'after_setup_theme', 'init', 'wp_loaded' ),
						'output_buffering'     => false,
					)
				),
			),
			'regular list, string with whitespace'        => array(
				array( 'benchmarking_actions' => "after_setup_  theme \ninit \n\nwp_loaded\n" ),
				array_merge(
					$default,
					array(
						'benchmarking_actions' => array( 'after_setup_theme', 'init', 'wp_loaded' ),
						'output_buffering'     => false,
					)
				),
			),
			'regular list, array with duplicates'         => array(
				array( 'benchmarking_actions' => array( 'after_setup_theme', 'init', 'wp_loaded', 'init' ) ),
				array_merge(
					$default,
					array(
						'benchmarking_actions' => array( 'after_setup_theme', 'init', 'wp_loaded' ),
						'output_buffering'     => false,
					)
				),
			),
			'regular list, array with special hook chars' => array(
				array( 'benchmarking_actions' => array( 'namespace/hookname', 'namespace.hookname' ) ),
				array_merge(
					$default,
					array(
						'benchmarking_actions' => array( 'namespace/hookname', 'namespace.hookname' ),
						'output_buffering'     => false,
					)
				),
			),
			'output buffering enabled'                    => array(
				array( 'output_buffering' => 'on' ),
				array_merge( $default, array( 'output_buffering' => true ) ),
			),
			'regular list, disallowed key'                => array(
				array( 'not_allowed' => array( 'after_setup_theme', 'init', 'wp_loaded' ) ),
				array_merge( $default, array( 'output_buffering' => false ) ),
			),
		);
	}
}
