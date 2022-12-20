<?php
/**
 * Tests for server-timing/class-perflab-server-timing.php
 *
 * @package performance-lab
 */

/**
 * @group server-timing
 */
class Perflab_Server_Timing_Tests extends WP_UnitTestCase {

	private $server_timing;

	private static $admin_id;
	private static $dummy_args;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$dummy_args = array(
			'measure_callback' => function() {},
			'access_cap'       => 'exist',
		);

		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
	}

	public function set_up() {
		parent::set_up();
		$this->server_timing = new Perflab_Server_Timing();
	}

	public function test_register_metric_stores_metrics_and_runs_measure_callback() {
		$called = false;
		$this->server_timing->register_metric(
			'test-metric',
			array(
				'measure_callback' => function() use ( &$called ) {
					$called = true;
				},
				'access_cap'       => 'exist',
			)
		);

		$this->assertTrue( $this->server_timing->has_registered_metric( 'test-metric' ), 'Metric not registered' );
		$this->assertTrue( $called, 'Measure callback not run' );
	}

	public function test_register_metric_runs_measure_callback_based_on_access_cap() {
		$called = false;
		$args   = array(
			'measure_callback' => function() use ( &$called ) {
				$called = true;
			},
			'access_cap'       => 'manage_options', // Admin capability.
		);

		$this->server_timing->register_metric( 'test-metric', $args );

		$this->assertTrue( $this->server_timing->has_registered_metric( 'test-metric' ), 'Metric without cap should still be registered' );
		$this->assertFalse( $called, 'Measure callback without cap must not be run' );

		wp_set_current_user( self::$admin_id );
		$this->server_timing->register_metric( 'test-metric-2', $args );

		$this->assertTrue( $this->server_timing->has_registered_metric( 'test-metric-2' ), 'Metric with cap should be registered' );
		$this->assertTrue( $called, 'Measure callback with cap should be run' );
	}

	public function test_register_metric_prevents_duplicates() {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing::class . '::register_metric' );

		$this->server_timing->register_metric( 'duplicate-metric', self::$dummy_args );
		$this->server_timing->register_metric( 'duplicate-metric', self::$dummy_args );

		$this->assertTrue( $this->server_timing->has_registered_metric( 'duplicate-metric' ) );
	}

	public function test_register_metric_prevents_late_registration() {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing::class . '::register_metric' );

		$this->server_timing->register_metric( 'registered-in-time', self::$dummy_args );
		do_action( 'perflab_server_timing_send_header' );
		$this->server_timing->register_metric( 'registered-too-late', self::$dummy_args );

		$this->assertTrue( $this->server_timing->has_registered_metric( 'registered-in-time' ), 'Metric registered in time should be stored' );
		$this->assertFalse( $this->server_timing->has_registered_metric( 'registered-too-late' ), 'Metric registered too late should not be stored' );
	}

	public function test_register_metric_requires_measure_callback() {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing::class . '::register_metric' );

		$this->server_timing->register_metric(
			'metric-without-measure-callback',
			array( 'access_cap' => 'exist' )
		);

		$this->assertFalse( $this->server_timing->has_registered_metric( 'metric-without-measure-callback' ) );
	}

	public function test_register_metric_requires_access_cap() {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing::class . '::register_metric' );

		$this->server_timing->register_metric(
			'metric-without-access-cap',
			array( 'measure_callback' => function() {} )
		);

		$this->assertFalse( $this->server_timing->has_registered_metric( 'metric-without-access-cap' ) );
	}

	public function test_has_registered_metric() {
		$this->assertFalse( $this->server_timing->has_registered_metric( 'metric-to-check-for' ), 'Metric should not be available before registration' );

		$this->server_timing->register_metric( 'metric-to-check-for', self::$dummy_args );
		$this->assertTrue( $this->server_timing->has_registered_metric( 'metric-to-check-for' ), 'Metric should be available after registration' );
	}

	/**
	 * @dataProvider data_get_header
	 */
	public function test_get_header( $expected, $metrics ) {
		foreach ( $metrics as $metric_slug => $args ) {
			$this->server_timing->register_metric( $metric_slug, $args );
		}
		$this->assertSame( $expected, $this->server_timing->get_header() );
	}

	public function data_get_header() {
		$measure_42         = function( $metric ) {
			$metric->set_value( 42 );
		};
		$measure_300        = function( $metric ) {
			$metric->set_value( 300 );
		};
		$measure_12point345 = function( $metric ) {
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

	public function test_use_output_buffer() {
		$this->assertFalse( $this->server_timing->use_output_buffer() );

		add_filter( 'perflab_server_timing_use_output_buffer', '__return_true' );
		$this->assertTrue( $this->server_timing->use_output_buffer() );
	}
}
