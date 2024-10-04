<?php
/**
 * Tests for server-timing/class-perflab-server-timing-metric.php
 *
 * @package performance-lab
 */

/**
 * @group server-timing
 */
class Test_Perflab_Server_Timing_Metric extends WP_UnitTestCase {

	/** @var Perflab_Server_Timing_Metric */
	private $metric;

	public function set_up(): void {
		parent::set_up();
		$this->metric = new Perflab_Server_Timing_Metric( 'test-metric' );
	}

	public function test_get_slug(): void {
		$this->assertSame( 'test-metric', $this->metric->get_slug() );
	}

	public function test_set_value_with_integer(): void {
		$this->metric->set_value( 123 );
		$this->assertSame( 123, $this->metric->get_value() );
	}

	public function test_set_value_with_float(): void {
		$this->metric->set_value( 123.4567 );
		$this->assertSame( 123.4567, $this->metric->get_value() );
	}

	public function test_set_value_with_numeric_string(): void {
		$this->metric->set_value( '123.4567' );
		$this->assertSame( 123.4567, $this->metric->get_value() );
	}

	public function test_set_value_requires_integer_or_float_or_numeric_string(): void {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing_Metric::class . '::set_value' );

		$this->metric->set_value( 'not-a-number' );
		$this->assertNull( $this->metric->get_value() );
	}

	public function test_set_value_prevents_late_measurement(): void {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing_Metric::class . '::set_value' );

		$this->metric->set_value( 2 );
		do_action( 'perflab_server_timing_send_header' );
		$this->metric->set_value( 3 );

		$this->assertSame( 2, $this->metric->get_value() );
	}

	public function test_set_start_value_with_integer(): void {
		$this->metric->set_start_value( 123 );
		$this->assertSame( 123, $this->metric->get_start_value() );
	}

	public function test_set_start_value_with_float(): void {
		$this->metric->set_start_value( 123.4567 );
		$this->assertSame( 123.4567, $this->metric->get_start_value() );
	}

	public function test_set_start_value_with_numeric_string(): void {
		$this->metric->set_start_value( '123.4567' );
		$this->assertSame( 123.4567, $this->metric->get_start_value() );
	}

	public function test_set_start_value_requires_integer_or_float_or_numeric_string(): void {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing_Metric::class . '::set_start_value' );

		$this->metric->set_start_value( 'not-a-number' );
		$this->assertNull( $this->metric->get_start_value() );
	}

	public function test_set_start_value_prevents_late_measurement(): void {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing_Metric::class . '::set_start_value' );

		$this->metric->set_start_value( 2 );
		do_action( 'perflab_server_timing_send_header' );
		$this->metric->set_start_value( 3 );

		$this->assertSame( 2, $this->metric->get_start_value() );
	}

	public function test_get_value(): void {
		$this->metric->set_value( 86.42 );
		$this->assertSame( 86.42, $this->metric->get_value() );
	}

	public function test_get_start_value(): void {
		$t = microtime( true ) * 1000.0;
		$this->metric->set_start_value( $t );
		$this->assertSame( $t, $this->metric->get_start_value() );
	}

	public function test_measure_start_and_end_correctly(): void {
		$this->metric->measure_start();
		sleep( 1 );
		$this->metric->measure_end();

		// Loose float comparison with 100ms delta, since measurement won't be exactly 1000ms.
		$this->assertEqualsWithDelta( 1000.0, $this->metric->get_value(), 100.0 );
	}

	public function test_measure_end_without_start(): void {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing_Metric::class . '::measure_end' );

		$this->metric->measure_end();

		$this->assertNull( $this->metric->get_value() );
	}

	public function test_measure_custom_start_and_end_correctly(): void {
		$this->metric->set_start_value( microtime( true ) * 1000.0 );
		sleep( 1 );
		$this->metric->measure_end();

		// Loose float comparison with 100ms delta, since measurement won't be exactly 1000ms.
		$this->assertEqualsWithDelta( 1000.0, $this->metric->get_value(), 100.0 );
	}
}
