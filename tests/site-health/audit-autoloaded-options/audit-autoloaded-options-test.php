<?php
/**
 * Tests for audit-autoloaded-options module.
 *
 * @package performance-lab
 */
class Audit_Autoloaded_Options_Tests extends WP_UnitTestCase {

	const WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES = 800000;

	/**
	 * Tests perflab_aao_autoloaded_options_count()
	 */
	public function test_perflab_aao_autoloaded_options_count() {
		$autoloaded_options_count = count( wp_load_alloptions() );
		$this->assertEquals( $autoloaded_options_count, perflab_aao_autoloaded_options_count() );

		// Add autoload option.
		add_option( 'test_option', 'test' );
		// We expect one more autoloaded option in the results.
		$this->assertEquals( $autoloaded_options_count + 1, perflab_aao_autoloaded_options_count() );
	}

	/**
	 * Tests perflab_aao_autoloaded_options_size()
	 */
	public function test_perflab_aao_autoloaded_options_size() {
		global $wpdb;
		$autoloaded_options_size = $wpdb->get_row( 'SELECT SUM(LENGTH(option_value)) FROM ' . $wpdb->prefix . 'options WHERE autoload = \'yes\'', ARRAY_N );
		$autoloaded_options_size = current( $autoloaded_options_size );
		$this->assertEquals( $autoloaded_options_size, perflab_aao_autoloaded_options_size() );

		// Add autoload option.
		$test_option_string       = 'test';
		$test_option_string_bytes = mb_strlen( $test_option_string, '8bit' );
		add_option( 'test_option', $test_option_string );
		$this->assertEquals( $autoloaded_options_size + $test_option_string_bytes, perflab_aao_autoloaded_options_size() );
	}

}

