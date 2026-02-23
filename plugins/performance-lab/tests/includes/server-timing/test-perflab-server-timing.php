<?php
/**
 * Tests for server-timing/class-perflab-server-timing.php
 *
 * @package performance-lab
 */

/**
 * @group server-timing
 * @phpstan-import-type MetricArguments from Perflab_Server_Timing
 */
class Test_Perflab_Server_Timing extends WP_UnitTestCase {

	/**
	 * @var Perflab_Server_Timing
	 */
	private $server_timing;

	/** @var int */
	private static $admin_id;

	/** @var array<string, mixed> */
	private static $dummy_args;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$dummy_args = array(
			'measure_callback' => '__return_null',
			'access_cap'       => 'exist',
		);

		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public static function wpTearDownAfterClass(): void {
		self::delete_user( self::$admin_id );
	}

	public function set_up(): void {
		parent::set_up();
		$this->server_timing = new Perflab_Server_Timing();
	}

	public function test_register_metric_stores_metrics_and_runs_measure_callback(): void {
		$called = false;
		$this->server_timing->register_metric(
			'test-metric',
			array(
				'measure_callback' => static function ( Perflab_Server_Timing_Metric $metric ) use ( &$called ): void {
					unset( $metric );
					$called = true;
				},
				'access_cap'       => 'exist',
			)
		);

		$this->assertTrue( $this->server_timing->has_registered_metric( 'test-metric' ), 'Metric not registered' );
		$this->assertTrue( $called, 'Measure callback not run' );
	}

	public function test_register_metric_runs_measure_callback_based_on_access_cap(): void {
		$called     = false;
		$access_cap = 'manage_options'; // Admin capability.
		$this->server_timing->register_metric(
			'test-metric',
			array(
				'measure_callback' => static function ( Perflab_Server_Timing_Metric $metric ) use ( &$called ): void {
					unset( $metric );
					$called = true;
				},
				'access_cap'       => $access_cap,
			)
		);

		$this->assertTrue( $this->server_timing->has_registered_metric( 'test-metric' ), 'Metric without cap should still be registered' );
		$this->assertFalse( $called, 'Measure callback without cap must not be run' );

		wp_set_current_user( self::$admin_id );
		$this->server_timing->register_metric(
			'test-metric-2',
			array(
				'measure_callback' => static function ( Perflab_Server_Timing_Metric $metric ) use ( &$called ): void {
					unset( $metric );
					$called = true;
				},
				'access_cap'       => $access_cap,
			)
		);

		$this->assertTrue( $this->server_timing->has_registered_metric( 'test-metric-2' ), 'Metric with cap should be registered' );
		$this->assertTrue( $called, 'Measure callback with cap should be run' );
	}

	public function test_register_metric_prevents_duplicates(): void {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing::class . '::register_metric' );

		$this->server_timing->register_metric( 'duplicate-metric', self::$dummy_args );
		$this->server_timing->register_metric( 'duplicate-metric', self::$dummy_args );

		$this->assertTrue( $this->server_timing->has_registered_metric( 'duplicate-metric' ) );
	}

	public function test_register_metric_prevents_late_registration(): void {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing::class . '::register_metric' );

		$this->server_timing->register_metric( 'registered-in-time', self::$dummy_args );
		do_action( 'perflab_server_timing_send_header' );
		$this->server_timing->register_metric( 'registered-too-late', self::$dummy_args );

		$this->assertTrue( $this->server_timing->has_registered_metric( 'registered-in-time' ), 'Metric registered in time should be stored' );
		$this->assertFalse( $this->server_timing->has_registered_metric( 'registered-too-late' ), 'Metric registered too late should not be stored' );
	}

	public function test_register_metric_requires_measure_callback(): void {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing::class . '::register_metric' );

		$this->server_timing->register_metric(
			'metric-without-measure-callback',
			array( 'access_cap' => 'exist' )
		);

		$this->assertFalse( $this->server_timing->has_registered_metric( 'metric-without-measure-callback' ) );
	}

	public function test_register_metric_requires_access_cap(): void {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing::class . '::register_metric' );

		$this->server_timing->register_metric(
			'metric-without-access-cap',
			array( 'measure_callback' => '__return_null' )
		);

		$this->assertFalse( $this->server_timing->has_registered_metric( 'metric-without-access-cap' ) );
	}

	public function test_has_registered_metric(): void {
		$this->assertFalse( $this->server_timing->has_registered_metric( 'metric-to-check-for' ), 'Metric should not be available before registration' );

		$this->server_timing->register_metric( 'metric-to-check-for', self::$dummy_args );
		$this->assertTrue( $this->server_timing->has_registered_metric( 'metric-to-check-for' ), 'Metric should be available after registration' );
	}

	public function test_register_metric_replaces_slashes(): void {
		$this->server_timing->register_metric(
			'foo/bar/baz',
			array(
				'measure_callback' => static function ( Perflab_Server_Timing_Metric $metric ): void {
					$metric->set_value( 123 );
				},
				'access_cap'       => 'exist',
			)
		);
		$this->assertSame( 'wp-foo-bar-baz;dur=123', $this->server_timing->get_header() );
	}

	/**
	 * @dataProvider data_get_header
	 *
	 * @param string                         $expected The expected header value.
	 * @param array<string, MetricArguments> $metrics  The metric configurations.
	 */
	public function test_get_header( string $expected, array $metrics ): void {
		foreach ( $metrics as $metric_slug => $args ) {
			$this->server_timing->register_metric( $metric_slug, $args );
		}
		$this->assertSame( $expected, $this->server_timing->get_header() );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function data_get_header(): array {
		$measure_42         = static function ( Perflab_Server_Timing_Metric $metric ): void {
			$metric->set_value( 42 );
		};
		$measure_300        = static function ( Perflab_Server_Timing_Metric $metric ): void {
			$metric->set_value( 300 );
		};
		$measure_12point345 = static function ( Perflab_Server_Timing_Metric $metric ): void {
			$metric->set_value( 12.345 );
		};

		return array(
			'single metric'                      => array(
				'wp-integer;dur=300',
				array(
					'integer' => array(
						'measure_callback' => $measure_300,
						'access_cap'       => 'exist',
					),
				),
			),
			'multiple metrics'                   => array(
				'wp-integer;dur=300, wp-float;dur=12.35, wp-bttf;dur=42, wp-bttf2;dur=42',
				array(
					'integer' => array(
						'measure_callback' => $measure_300,
						'access_cap'       => 'exist',
					),
					'float'   => array(
						'measure_callback' => $measure_12point345,
						'access_cap'       => 'exist',
					),
					'bttf'    => array(
						'measure_callback' => $measure_42,
						'access_cap'       => 'exist',
					),
					'bttf2'   => array(
						'measure_callback' => $measure_42,
						'access_cap'       => 'exist',
					),
				),
			),
			'metrics with partially missing cap' => array(
				'wp-with-cap;dur=42',
				array(
					'without-cap' => array(
						'measure_callback' => $measure_42,
						'access_cap'       => 'cap_that_nobody_has',
					),
					'with-cap'    => array(
						'measure_callback' => $measure_42,
						'access_cap'       => 'exist',
					),
				),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_data_to_test_use_output_buffer(): array {
		$enable_option  = static function (): void {
			$option                     = (array) get_option( PERFLAB_SERVER_TIMING_SETTING );
			$option['output_buffering'] = true;
			update_option( PERFLAB_SERVER_TIMING_SETTING, $option );
		};
		$disable_option = static function (): void {
			$option                     = (array) get_option( PERFLAB_SERVER_TIMING_SETTING );
			$option['output_buffering'] = false;
			update_option( PERFLAB_SERVER_TIMING_SETTING, $option );
		};

		return array(
			'default'         => array(
				'set_up'   => static function (): void {},
				'expected' => false,
			),
			'option-enabled'  => array(
				'set_up'   => $enable_option,
				'expected' => true,
			),
			'option-disabled' => array(
				'set_up'   => $disable_option,
				'expected' => false,
			),
			'filter-enabled'  => array(
				'set_up'   => static function () use ( $disable_option ): void {
					$disable_option();
					add_filter( 'perflab_server_timing_use_output_buffer', '__return_true' );
				},
				'expected' => true,
			),
			'filter-disabled' => array(
				'set_up'   => static function () use ( $enable_option ): void {
					$enable_option();
					add_filter( 'perflab_server_timing_use_output_buffer', '__return_false' );
				},
				'expected' => false,
			),
		);
	}

	/**
	 * @covers Perflab_Server_Timing::use_output_buffer
	 *
	 * @dataProvider get_data_to_test_use_output_buffer
	 *
	 * @param callable $set_up   Set up.
	 * @param bool     $expected Expected value.
	 */
	public function test_use_output_buffer( callable $set_up, bool $expected ): void {
		$set_up();
		$this->assertSame( $expected, $this->server_timing->use_output_buffer() );
	}

	/**
	 * @dataProvider data_get_header_with_description
	 *
	 * @param string                         $expected The expected header value.
	 * @param array<string, MetricArguments> $metrics  The metric configurations.
	 */
	public function test_get_header_with_description( string $expected, array $metrics ): void {
		foreach ( $metrics as $metric_slug => $args ) {
			$this->server_timing->register_metric( $metric_slug, $args );
		}
		$this->assertSame( $expected, $this->server_timing->get_header() );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function data_get_header_with_description(): array {
		$measure_with_description = static function ( Perflab_Server_Timing_Metric $metric ): void {
			$metric->set_value( 100 );
			$metric->set_description( 'Database queries' );
		};
		$measure_description_only = static function ( Perflab_Server_Timing_Metric $metric ): void {
			$metric->set_description( 'Cache operations' );
		};
		$measure_duration_only    = static function ( Perflab_Server_Timing_Metric $metric ): void {
			$metric->set_value( 50 );
		};

		return array(
			'metric with duration and description' => array(
				'wp-db-query;dur=100;desc="Database queries"',
				array(
					'db-query' => array(
						'measure_callback' => $measure_with_description,
						'access_cap'       => 'exist',
					),
				),
			),
			'metric with description only'         => array(
				'wp-cache-ops;desc="Cache operations"',
				array(
					'cache-ops' => array(
						'measure_callback' => $measure_description_only,
						'access_cap'       => 'exist',
					),
				),
			),
			'metric with duration only'            => array(
				'wp-duration-only;dur=50',
				array(
					'duration-only' => array(
						'measure_callback' => $measure_duration_only,
						'access_cap'       => 'exist',
					),
				),
			),
			'mixed metrics'                        => array(
				'wp-with-both;dur=100;desc="Database queries", wp-desc-only;desc="Cache operations", wp-dur-only;dur=50',
				array(
					'with-both' => array(
						'measure_callback' => $measure_with_description,
						'access_cap'       => 'exist',
					),
					'desc-only' => array(
						'measure_callback' => $measure_description_only,
						'access_cap'       => 'exist',
					),
					'dur-only'  => array(
						'measure_callback' => $measure_duration_only,
						'access_cap'       => 'exist',
					),
				),
			),
		);
	}

	/**
	 * @dataProvider data_get_header_with_description_edge_cases
	 *
	 * @param string                         $expected The expected header value.
	 * @param array<string, MetricArguments> $metrics  The metric configurations.
	 */
	public function test_get_header_with_description_edge_cases( string $expected, array $metrics ): void {
		foreach ( $metrics as $metric_slug => $args ) {
			$this->server_timing->register_metric( $metric_slug, $args );
		}
		$this->assertSame( $expected, $this->server_timing->get_header() );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function data_get_header_with_description_edge_cases(): array {
		return array(
			'description with double quote'    => array(
				'wp-quoted;desc="Say \\"hello\\""',
				array(
					'quoted' => array(
						'measure_callback' => static function ( Perflab_Server_Timing_Metric $metric ): void {
							$metric->set_description( 'Say "hello"' );
						},
						'access_cap'       => 'exist',
					),
				),
			),
			'description with backslash'       => array(
				'wp-backslash;desc="path\\\\to\\\\file"',
				array(
					'backslash' => array(
						'measure_callback' => static function ( Perflab_Server_Timing_Metric $metric ): void {
							$metric->set_description( 'path\\to\\file' );
						},
						'access_cap'       => 'exist',
					),
				),
			),
			'description with newline'         => array(
				'wp-newline;desc="Line 1Line 2"',
				array(
					'newline' => array(
						'measure_callback' => static function ( Perflab_Server_Timing_Metric $metric ): void {
							$metric->set_description( "Line 1\nLine 2" );
						},
						'access_cap'       => 'exist',
					),
				),
			),
			'description with carriage return' => array(
				'wp-cr;desc="BeforeAfter"',
				array(
					'cr' => array(
						'measure_callback' => static function ( Perflab_Server_Timing_Metric $metric ): void {
							$metric->set_description( "Before\rAfter" );
						},
						'access_cap'       => 'exist',
					),
				),
			),
		);
	}
}
