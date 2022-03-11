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
		$autoloaded_options_count = count( wp_load_alloptions() );

		$this->assertEqualSets(
			perflab_aao_autoloaded_options_test(),
			Autoloaded_Options_Mock_Responses::return_perflab_aao_autoloaded_options_test_less_than_limit(
				$autoloaded_options_size,
				$autoloaded_options_count
			)
		);
	}

	/**
	 * Tests perflab_aao_autoloaded_options_test() when autoloaded options more than warning size.
	 */
	public function test_perflab_aao_autoloaded_options_test_warning() {
		self::set_autoloaded_option( self::WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES );
		$autoloaded_options_size  = perflab_aao_autoloaded_options_size();
		$autoloaded_options_count = count( wp_load_alloptions() );

		$this->assertEqualSets(
			perflab_aao_autoloaded_options_test(),
			Autoloaded_Options_Mock_Responses::return_perflab_aao_autoloaded_options_test_bigger_than_limit(
				$autoloaded_options_size,
				$autoloaded_options_count
			)
		);
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
	 * Sets an autoloaded option.
	 *
	 * @param int $bytes bytes to load in options.
	 */
	public static function set_autoloaded_option( $bytes = 800000 ) {
		$heavy_option_string = self::random_string_generator( $bytes );
		add_option( 'test_set_autoloaded_option', $heavy_option_string );
	}

	/**
	 * Generate random string with certain $length.
	 *
	 * @param int $length Length ( in bytes ) of string to create.
	 * @return string
	 */
	protected static function random_string_generator( $length ) {
		$seed        = 'abcd123';
		$length_seed = strlen( $seed );
		$string      = '';
		for ( $x = 0; $x < $length; $x++ ) {
			$string .= $seed[ rand( 0, $length_seed - 1 ) ];
		}
		return $string;
	}

}

