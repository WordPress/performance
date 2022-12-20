<?php
/**
 * Tests for server-timing/class-perflab-server-timing-metric.php
 *
 * @package performance-lab
 */

/**
 * @group server-timing
 */
class Perflab_Server_Timing_Metric_Tests extends WP_UnitTestCase {

	private $metric;

	public function set_up() {
		parent::set_up();
		$this->metric = new Perflab_Server_Timing_Metric( 'test-metric' );
	}

	public function test_get_slug() {
		$this->assertSame( 'test-metric', $this->metric->get_slug() );
	}

	public function test_set_value_with_integer() {
		$this->metric->set_value( 123 );
		$this->assertSame( 123, $this->metric->get_value() );
	}

	public function test_set_value_with_float() {
		$this->metric->set_value( 123.4567 );
		$this->assertSame( 123.4567, $this->metric->get_value() );
	}

	public function test_set_value_with_numeric_string() {
		$this->metric->set_value( '123.4567' );
		$this->assertSame( 123.4567, $this->metric->get_value() );
	}

	public function test_set_value_requires_integer_or_float_or_numeric_string() {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing_Metric::class . '::set_value' );

		$this->metric->set_value( 'not-a-number' );
		$this->assertNull( $this->metric->get_value() );
	}

	public function test_set_value_prevents_late_measurement() {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing_Metric::class . '::set_value' );

		$this->metric->set_value( 2 );
		do_action( 'perflab_server_timing_send_header' );
		$this->metric->set_value( 3 );

		$this->assertSame( 2, $this->metric->get_value() );
	}

	public function test_get_value() {
		$this->metric->set_value( 86.42 );
		$this->assertSame( 86.42, $this->metric->get_value() );
	}

	public function test_measure_before_and_after_correctly() {
		$this->metric->measure_before();
		sleep( 1 );
		$this->metric->measure_after();

		// Loose float comparison with 100ms delta, since measurement won't be exactly 1000ms.
		$this->assertEqualsWithDelta( 1000.0, $this->metric->get_value(), 100.0 );
	}

	public function test_measure_after_without_before() {
		$this->setExpectedIncorrectUsage( Perflab_Server_Timing_Metric::class . '::measure_after' );

		$this->metric->measure_after();

		$this->assertNull( $this->metric->get_value() );
	}
}
