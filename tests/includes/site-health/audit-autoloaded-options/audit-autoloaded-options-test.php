<?php
/**
 * Tests for audit-autoloaded-options check.
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

		if ( function_exists( 'wp_autoload_values_to_autoload' ) ) {
			$autoload_values = wp_autoload_values_to_autoload();
		} else {
			$autoload_values = array( 'yes' );
		}

		$autoloaded_options_size = $wpdb->get_var(
			$wpdb->prepare(
				sprintf(
					"SELECT SUM(LENGTH(option_value)) FROM $wpdb->options WHERE autoload IN (%s)",
					implode( ',', array_fill( 0, count( $autoload_values ), '%s' ) )
				),
				$autoload_values
			)
		);
		$this->assertEquals( $autoloaded_options_size, perflab_aao_autoloaded_options_size() );

		// Add autoload option.
		$test_option_string       = 'test';
		$test_option_string_bytes = mb_strlen( $test_option_string, '8bit' );
		self::set_autoloaded_option( $test_option_string_bytes );
		$this->assertSame( $autoloaded_options_size + $test_option_string_bytes, perflab_aao_autoloaded_options_size() );
	}

	public function test_perflab_aao_autoloaded_options_disable_revert_functionality() {

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Mock wp_redirect to avoid actual redirection.
		add_filter(
			'wp_redirect',
			static function () {
				return false;
			}
		);

		// Add an autoload option with small size length value for testing.
		$test_option_string       = 'test';
		$test_option_string_bytes = mb_strlen( $test_option_string, '8bit' );
		self::set_autoloaded_option( $test_option_string_bytes );

		// Test that the autoloaded option is not displayed in the table because value lower then it's threshold value.
		$table_html = perflab_aao_get_autoloaded_options_table();
		$this->assertStringNotContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );

		// Delete autoloaded option.
		self::delete_autoloaded_option();

		// Add an autoload option with bigger size length value for testing.
		self::set_autoloaded_option( self::WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES );

		$table_html = perflab_aao_get_autoloaded_options_table();
		$this->assertStringContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );

		// Check disable autoloaded option functionality.
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'perflab_aao_update_autoload' );
		$_GET['action']       = 'perflab_aao_update_autoload';
		$_GET['option_name']  = self::AUTOLOADED_OPTION_KEY;
		$_GET['autoload']     = 'false';

		perflab_aao_handle_update_autoload();

		// Test that the autoloaded option is not displayed in the perflab_aao_get_autoloaded_options_table() after disabling.
		$table_html = perflab_aao_get_autoloaded_options_table();
		$this->assertStringNotContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );

		// Test that the disabled autoloaded option is displayed in the disabled options perflab_aao_get_disabled_autoloaded_options_table().
		$table_html = perflab_aao_get_disabled_autoloaded_options_table();
		$this->assertStringContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );

		// Revert the disabled autoloaded option.
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'perflab_aao_update_autoload' );
		$_GET['action']       = 'perflab_aao_update_autoload';
		$_GET['option_name']  = self::AUTOLOADED_OPTION_KEY;
		$_GET['autoload']     = 'true';

		perflab_aao_handle_update_autoload();

		// Test that the disabled autoloaded option is not displayed in the disabled options perflab_aao_get_disabled_autoloaded_options_table() after reverting.
		$table_html = perflab_aao_get_disabled_autoloaded_options_table();
		$this->assertStringNotContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );

		// Test that the reverted autoloaded option is displayed in the autoloaded options perflab_aao_get_autoloaded_options_table().
		$table_html = perflab_aao_get_autoloaded_options_table();
		$this->assertStringContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );

		// Remove the mock filter.
		remove_filter(
			'wp_redirect',
			static function () {
				return false;
			}
		);
	}

	/**
	 * Sets a test autoloaded option.
	 *
	 * @param int $bytes bytes to load in options.
	 */
	public static function set_autoloaded_option( $bytes = 800000 ) {
		$heavy_option_string = wp_generate_password( $bytes );

		// Force autoloading so that WordPress core does not override it. See https://core.trac.wordpress.org/changeset/57920.
		add_option( self::AUTOLOADED_OPTION_KEY, $heavy_option_string, '', true );
	}

	/**
	 * Deletes test autoloaded option.
	 */
	public static function delete_autoloaded_option() {
		delete_option( self::AUTOLOADED_OPTION_KEY );
	}
}
