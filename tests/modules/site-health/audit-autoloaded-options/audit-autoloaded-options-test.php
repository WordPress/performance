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
	 * @dataProvider provider_added_test_info_site_health
	 *
	 * Tests perflab_aao_add_autoloaded_options_test()
	 */
	public function test_perflab_aao_add_autoloaded_options_test( $provider_added_test_info_site_health ) {
		$this->assertEqualSets( $provider_added_test_info_site_health, perflab_aao_add_autoloaded_options_test( array() ) );
	}

	/**
	 * @dataProvider provider_autoloaded_options_less_than_limit
	 *
	 * Tests perflab_aao_autoloaded_options_test() when autoloaded options less than warning size.
	 */
	public function test_perflab_aao_autoloaded_options_test_no_warning( $provider_autoloaded_options_less_than_limit ) {
		$this->assertEqualSets(
			perflab_aao_autoloaded_options_test(),
			$provider_autoloaded_options_less_than_limit
		);
	}

	/**
	 * @dataProvider provider_autoloaded_options_bigger_than_limit
	 *
	 * Tests perflab_aao_autoloaded_options_test() when autoloaded options more than warning size.
	 */
	public function test_perflab_aao_autoloaded_options_test_warning( $provider_autoloaded_options_bigger_than_limit ) {
		self::set_autoloaded_option( self::WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES );
		$this->assertEqualSets(
			perflab_aao_autoloaded_options_test(),
			$provider_autoloaded_options_bigger_than_limit
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
	 * Sets a test autoloaded option.
	 *
	 * @param int $bytes bytes to load in options.
	 */
	public static function set_autoloaded_option( $bytes = 800000 ) {
		$heavy_option_string = self::random_string_generator( $bytes );
		add_option( self::AUTOLOADED_OPTION_KEY, $heavy_option_string );
	}

	/**
	 * Deletes test autoloaded option.
	 */
	public static function delete_autoloaded_option() {
		delete_option( self::AUTOLOADED_OPTION_KEY );
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

	/**
	 * This is the information we are adding into site_status_tests hook.
	 *
	 * @return array
	 */
	public function provider_added_test_info_site_health() {
		$added_tests                                 = array();
		$added_tests['direct']['autoloaded_options'] = array(
			'label' => esc_html__( 'Autoloaded options', 'performance-lab' ),
			'test'  => 'perflab_aao_autoloaded_options_test',
		);
		return array( array( $added_tests ) );
	}

	/**
	 * Data provider for perflab_aao_autoloaded_options_test if autoloaded options are less than the limit.
	 *
	 * @return array
	 */
	public function provider_autoloaded_options_less_than_limit() {
		$autoloaded_options_size  = perflab_aao_autoloaded_options_size();
		$autoloaded_options_count = count( wp_load_alloptions() );

		$result = array(
			'label'       => esc_html__( 'Autoloaded options', 'performance-lab' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => esc_html__( 'Performance', 'performance-lab' ),
				'color' => 'blue',
			),
			'description' => sprintf(
			/* translators: 1: Number of autoloaded options. 2.Autoloaded options size. */
				'<p>' . esc_html__( 'The amount of %1$s autoloaded options (size: %2$s) in options table is acceptable.', 'performance-lab' ) . '</p>',
				$autoloaded_options_count,
				size_format( $autoloaded_options_size )
			),
			'actions'     => '',
			'test'        => 'autoloaded_options',
		);
		return array( array( $result ) );
	}

	/**
	 * Data provider for perflab_aao_autoloaded_options_test if autoloaded options are more than the limit.
	 *
	 * @return array
	 */
	public function provider_autoloaded_options_bigger_than_limit() {
		$result = perflab_aao_autoloaded_options_test();

		self::set_autoloaded_option( self::WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES );
		$autoloaded_options_size  = perflab_aao_autoloaded_options_size();
		$autoloaded_options_count = count( wp_load_alloptions() );
		self::delete_autoloaded_option();

		$result['status']         = 'critical';
		$result['badge']['color'] = 'red';
		$result['description']    = sprintf(
		/* translators: 1: Number of autoloaded options. 2.Autoloaded options size. */
			'<p>' . esc_html__( 'Your website uses %1$s autoloaded options (size: %2$s). Try to reduce the number of autoloaded options or performance will be affected.', 'performance-lab' ) . '</p>',
			$autoloaded_options_count,
			size_format( $autoloaded_options_size )
		);

		$result['actions'] = sprintf(
		/* translators: 1: HelpHub URL. 2: Link description. */
			'<p><a target="_blank" href="%1$s">%2$s</a></p>',
			esc_url( __( 'https://wordpress.org/support/article/optimization/', 'performance-lab' ) ),
			esc_html__( 'More info about performance optimization', 'performance-lab' )
		);

		return array( array( $result ) );
	}


}

