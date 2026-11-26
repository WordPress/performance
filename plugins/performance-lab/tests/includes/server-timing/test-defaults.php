<?php
/**
 * Tests for server-timing/defaults.php
 *
 * @package performance-lab
 */

/**
 * @group server-timing
 */
class Test_Server_Timing_Defaults extends WP_UnitTestCase {

	/**
	 * Backup of the registered metrics on the shared Perflab_Server_Timing instance.
	 *
	 * @var array<string, mixed>
	 */
	private $metrics_backup = array();

	public function set_up(): void {
		parent::set_up();

		// Start each test with no metrics registered, since the shared instance persists across tests.
		foreach ( array( 'registered_metrics', 'registered_metrics_data' ) as $property ) {
			$this->metrics_backup[ $property ] = $this->get_server_timing_property( $property );
			$this->set_server_timing_property( $property, array() );
		}

		// Only the hooks added by the functions under test should run.
		remove_all_actions( 'admin_init' );
	}

	public function tear_down(): void {
		foreach ( $this->metrics_backup as $property => $value ) {
			$this->set_server_timing_property( $property, $value );
		}
		parent::tear_down();
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ doing_ajax: bool, output_buffer: bool, expected: bool }>
	 */
	public function data_admin_init_metrics(): array {
		return array(
			'ajax-with-output-buffer'        => array(
				'doing_ajax'    => true,
				'output_buffer' => true,
				'expected'      => true,
			),
			'ajax-without-output-buffer'     => array(
				'doing_ajax'    => true,
				'output_buffer' => false,
				'expected'      => false,
			),
			'non-ajax-with-output-buffer'    => array(
				'doing_ajax'    => false,
				'output_buffer' => true,
				'expected'      => false,
			),
			'non-ajax-without-output-buffer' => array(
				'doing_ajax'    => false,
				'output_buffer' => false,
				'expected'      => false,
			),
		);
	}

	/**
	 * @covers ::perflab_register_default_server_timing_before_template_metrics
	 * @covers ::perflab_register_default_server_timing_template_metrics
	 *
	 * @dataProvider data_admin_init_metrics
	 */
	public function test_admin_init_registers_metrics_for_ajax_with_output_buffer( bool $doing_ajax, bool $output_buffer, bool $expected ): void {
		add_filter( 'wp_doing_ajax', $doing_ajax ? '__return_true' : '__return_false' );
		add_filter( 'perflab_server_timing_use_output_buffer', $output_buffer ? '__return_true' : '__return_false' );

		perflab_register_default_server_timing_before_template_metrics();
		perflab_register_default_server_timing_template_metrics();

		do_action( 'admin_init' );

		$server_timing = perflab_server_timing();
		$this->assertSame( $expected, $server_timing->has_registered_metric( 'before-template' ), 'Unexpected before-template metric registration' );
		$this->assertSame( $expected, $server_timing->has_registered_metric( 'template' ), 'Unexpected template metric registration' );
	}

	/**
	 * @covers ::perflab_register_default_server_timing_template_metrics
	 */
	public function test_ajax_with_output_buffer_header_includes_handler_metrics(): void {
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'perflab_server_timing_use_output_buffer', '__return_true' );
		remove_all_actions( 'perflab_server_timing_send_header' );

		perflab_register_default_server_timing_before_template_metrics();
		perflab_register_default_server_timing_template_metrics();

		do_action( 'admin_init' );
		do_action( 'perflab_server_timing_send_header' );

		$header = perflab_server_timing()->get_header();
		$this->assertMatchesRegularExpression( '/(^|, )wp-before-template;dur=[0-9.]+(,|$)/', $header );
		$this->assertMatchesRegularExpression( '/(^|, )wp-template;dur=[0-9.]+(,|$)/', $header );
		$this->assertMatchesRegularExpression( '/(^|, )wp-total;dur=[0-9.]+(,|$)/', $header );
	}

	/**
	 * Gets a private property of the shared Perflab_Server_Timing instance.
	 *
	 * @param string $property Property name.
	 * @return mixed Property value.
	 */
	private function get_server_timing_property( string $property ) {
		$reflection = new ReflectionProperty( Perflab_Server_Timing::class, $property );
		$reflection->setAccessible( true );
		return $reflection->getValue( perflab_server_timing() );
	}

	/**
	 * Sets a private property of the shared Perflab_Server_Timing instance.
	 *
	 * @param string $property Property name.
	 * @param mixed  $value    Property value.
	 */
	private function set_server_timing_property( string $property, $value ): void {
		$reflection = new ReflectionProperty( Perflab_Server_Timing::class, $property );
		$reflection->setAccessible( true );
		$reflection->setValue( perflab_server_timing(), $value );
	}
}
