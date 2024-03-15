<?php
/**
 * Tests for audit-autoloaded-options module.
 *
 * @package performance-lab
 */
class Audit_Autoloaded_Options_Tests extends WP_UnitTestCase {

	const WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES = 800000;
	const AUTOLOADED_OPTION_KEY                  = 'test_set_autoloaded_option';

	/**
	 * Tests perflab_aao_add_autoloaded_options_test()
	 */
	public function test_perflab_aao_add_autoloaded_options_test() {
		$expected_test = array(
			'label' => esc_html__( 'Autoloaded options', 'performance-lab' ),
			'test'  => 'perflab_aao_autoloaded_options_test',
		);

		$this->assertEquals(
			array(
				'direct' => array(
					'autoloaded_options' => $expected_test,
				),
			),
			perflab_aao_add_autoloaded_options_test( array() )
		);
	}

	/**
	 * Tests perflab_aao_autoloaded_options_test() when autoloaded options less than warning size.
	 */
	public function test_perflab_aao_autoloaded_options_test_no_warning() {
		$expected_label  = esc_html__( 'Autoloaded options are acceptable', 'performance-lab' );
		$expected_status = 'good';

		$result = perflab_aao_autoloaded_options_test();
		$this->assertSame( $expected_label, $result['label'] );
		$this->assertSame( $expected_status, $result['status'] );
	}

	/**
	 * Tests perflab_aao_autoloaded_options_test() when autoloaded options more than warning size.
	 */
	public function test_perflab_aao_autoloaded_options_test_warning() {
		self::set_autoloaded_option( self::WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES );

		$expected_label  = esc_html__( 'Autoloaded options could affect performance', 'performance-lab' );
		$expected_status = 'critical';

		$result = perflab_aao_autoloaded_options_test();
		$this->assertSame( $expected_label, $result['label'] );
		$this->assertSame( $expected_status, $result['status'] );
	}

	/**
	 * Tests perflab_aao_autoloaded_options_size()
	 */
	public function test_perflab_aao_autoloaded_options_size() {
		global $wpdb;
		$autoloaded_options_size = $wpdb->get_var( 'SELECT SUM(LENGTH(option_value)) FROM ' . $wpdb->prefix . 'options WHERE autoload = \'yes\'' );
		$this->assertEquals( $autoloaded_options_size, perflab_aao_autoloaded_options_size() );

		// Add autoload option.
		$test_option_string       = 'test';
		$test_option_string_bytes = mb_strlen( $test_option_string, '8bit' );
		self::set_autoloaded_option( $test_option_string_bytes );
		$this->assertSame( $autoloaded_options_size + $test_option_string_bytes, perflab_aao_autoloaded_options_size() );
	}

	/**
	 * Sets a test autoloaded option.
	 *
	 * @param int $bytes bytes to load in options.
	 */
	public static function set_autoloaded_option( $bytes = 800000 ) {
		$heavy_option_string = wp_generate_password( $bytes );
		add_option( self::AUTOLOADED_OPTION_KEY, $heavy_option_string );
	}

	/**
	 * Deletes test autoloaded option.
	 */
	public static function delete_autoloaded_option() {
		delete_option( self::AUTOLOADED_OPTION_KEY );
	}
}
