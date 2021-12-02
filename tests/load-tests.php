<?php
/**
 * Tests for load.php
 *
 * @package performance-lab
 */

class Load_Tests extends WP_UnitTestCase {

	public function test_perflab_register_modules_setting() {
		global $new_allowed_options, $wp_registered_settings;

		// Reset relevant globals.
		$wp_registered_settings = array();
		// `$new_allowed_options` was only introduced in WordPress 5.5.
		if ( isset( $new_allowed_options ) ) {
			$new_allowed_options = array();
		}

		perflab_register_modules_setting();

		// Assert that the setting is correctly registered.
		$settings = get_registered_settings();
		$this->assertTrue( isset( $settings[ PERFLAB_MODULES_SETTING ] ) );
		// `$new_allowed_options` was only introduced in WordPress 5.5.
		if ( isset( $new_allowed_options ) ) {
			$this->assertTrue( isset( $new_allowed_options[ PERFLAB_MODULES_SCREEN ] ) );
		}

		// Assert that registered default works correctly.
		$this->assertSame( array(), get_option( PERFLAB_MODULES_SETTING ) );

		// Assert that most basic sanitization works correctly (an array is required).
		update_option( PERFLAB_MODULES_SETTING, 'invalid' );
		$this->assertSame( array(), get_option( PERFLAB_MODULES_SETTING ) );
	}

	public function test_perflab_sanitize_modules_setting() {
		// Assert that any non-array value gets sanitized to an empty array.
		$sanitized = perflab_sanitize_modules_setting( 'invalid' );
		$this->assertSame( array(), $sanitized );

		// Assert that any non-array value within the array gets stripped.
		$sanitized = perflab_sanitize_modules_setting(
			array(
				'valid-module'   => array( 'enabled' => true ),
				'invalid-module' => 'invalid',
			)
		);
		$this->assertSame( array( 'valid-module' => array( 'enabled' => true ) ), $sanitized );

		// Assert that every array value within the array has an 'enabled' key.
		$sanitized = perflab_sanitize_modules_setting(
			array( 'my-module' => array() )
		);
		$this->assertSame( array( 'my-module' => array( 'enabled' => false ) ), $sanitized );
	}

	public function test_perflab_get_module_settings() {
		// Assert that by default the settings are an empty array.
		$settings = perflab_get_module_settings();
		$this->assertSame( array(), $settings );

		// Assert that option updates are reflected in the settings correctly.
		$new_value = array( 'my-module' => array( 'enabled' => true ) );
		update_option( PERFLAB_MODULES_SETTING, $new_value );
		$settings = perflab_get_module_settings();
		$this->assertSame( $new_value, $settings );
	}

	public function test_perflab_get_active_modules() {
		// Assert that by default there are no active modules.
		$active_modules = perflab_get_active_modules();
		$this->assertSame( array(), $active_modules );

		// Assert that option updates affect the active modules correctly.
		$new_value = array(
			'inactive-module' => array( 'enabled' => false ),
			'active-module'   => array( 'enabled' => true ),
		);
		update_option( PERFLAB_MODULES_SETTING, $new_value );
		$active_modules = perflab_get_active_modules();
		$this->assertSame( array( 'active-module' ), $active_modules );
	}
}
