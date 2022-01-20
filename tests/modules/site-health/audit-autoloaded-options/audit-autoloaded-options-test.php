<?php
/**
 * Tests for audit-autoloaded-options module.
 *
 * @package performance-lab
 */
class Audit_Autoloaded_Options_Tests extends WP_UnitTestCase {

	const WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES = 800000;

	/**
	 * Tests perflab_aao_add_autoloaded_options_test()
	 */
	public function test_perflab_aao_add_autoloaded_options_test() {
		$this->assertEqualSets( Autoloaded_Options_Mock_Responses::return_added_test_info_site_health(), perflab_aao_add_autoloaded_options_test( array() ) );
	}

	/**
	 * Tests perflab_aao_autoloaded_options_test() when autoloaded options less than warning size.
	 */
	public function test_perflab_aao_autoloaded_options_test_no_warning() {
		$autoloaded_options_size  = perflab_aao_autoloaded_options_size();
		$autoloaded_options_count = perflab_aao_autoloaded_options_count();

		if ( $autoloaded_options_size < self::WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES ) {
			$this->assertEqualSets(
				perflab_aao_autoloaded_options_test(),
				Autoloaded_Options_Mock_Responses::return_perflab_aao_autoloaded_options_test_less_than_limit(
					$autoloaded_options_size,
					$autoloaded_options_count
				)
			);
		}
	}

	/**
	 * Tests perflab_aao_autoloaded_options_test() when autoloaded options more than warning size.
	 */
	public function test_perflab_aao_autoloaded_options_test_warning() {
		Autoloaded_Options_Set::set_autoloaded_option( self::WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES );
		$autoloaded_options_size  = perflab_aao_autoloaded_options_size();
		$autoloaded_options_count = perflab_aao_autoloaded_options_count();

		if ( $autoloaded_options_size > self::WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES ) {
			$this->assertEqualSets(
				perflab_aao_autoloaded_options_test(),
				Autoloaded_Options_Mock_Responses::return_perflab_aao_autoloaded_options_test_bigger_than_limit(
					$autoloaded_options_size,
					$autoloaded_options_count
				)
			);
		}
	}

	/**
	 * Tests perflab_aao_autoloaded_options_count()
	 */
	public function test_perflab_aao_autoloaded_options_count() {
		$autoloaded_options_count = count( wp_load_alloptions() );
		$this->assertEquals( $autoloaded_options_count, perflab_aao_autoloaded_options_count() );

		// Add autoload option.
		Autoloaded_Options_Set::set_autoloaded_option( 5 );
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
		Autoloaded_Options_Set::set_autoloaded_option( $test_option_string_bytes );
		$this->assertEquals( $autoloaded_options_size + $test_option_string_bytes, perflab_aao_autoloaded_options_size() );
	}

}

