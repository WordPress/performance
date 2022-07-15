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
		$this->assertSame( perflab_get_modules_setting_default(), get_option( PERFLAB_MODULES_SETTING ) );

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

	public function test_perflab_get_modules_setting_default() {
		$default_enabled_modules = require PERFLAB_MAIN_DIR_PATH . 'default-enabled-modules.php';
		$expected                = array();
		foreach ( $default_enabled_modules as $default_enabled_module ) {
			$expected[ $default_enabled_module ] = array( 'enabled' => true );
		}

		$this->assertSame( $expected, perflab_get_modules_setting_default() );
	}

	public function test_perflab_get_module_settings() {
		// Assert that by default the settings are using the same value as the registered default.
		$settings = perflab_get_module_settings();
		$this->assertSame( perflab_get_modules_setting_default(), $settings );

		// More specifically though, assert that the default is also passed through to the
		// get_option() call, to support scenarios where the function is called before 'init'.
		// Unhook the registered default logic to verify the default comes from the passed value.
		remove_all_filters( 'default_option_' . PERFLAB_MODULES_SETTING );
		$has_passed_default = false;
		add_filter(
			'default_option_' . PERFLAB_MODULES_SETTING,
			function( $default, $option, $passed_default ) use ( &$has_passed_default ) {
				// This callback just records whether there is a default value being passed.
				$has_passed_default = $passed_default;
				return $default;
			},
			10,
			3
		);
		$settings = perflab_get_module_settings();
		$this->assertTrue( $has_passed_default );
		$this->assertSame( perflab_get_modules_setting_default(), $settings );

		// Assert that option updates are reflected in the settings correctly.
		$new_value = array( 'my-module' => array( 'enabled' => true ) );
		update_option( PERFLAB_MODULES_SETTING, $new_value );
		$settings = perflab_get_module_settings();
		$this->assertSame( $new_value, $settings );
	}

	public function test_perflab_get_active_modules() {
		// Assert that by default there are no active modules.
		$active_modules          = perflab_get_active_modules();
		$expected_active_modules = array_keys(
			array_filter(
				perflab_get_modules_setting_default(),
				function( $module_settings ) {
					return $module_settings['enabled'];
				}
			)
		);
		$this->assertSame( $expected_active_modules, $active_modules );

		// Assert that option updates affect the active modules correctly.
		$new_value = array(
			'inactive-module' => array( 'enabled' => false ),
			'active-module'   => array( 'enabled' => true ),
		);
		update_option( PERFLAB_MODULES_SETTING, $new_value );
		$active_modules = perflab_get_active_modules();
		$this->assertSame( array( 'active-module' ), $active_modules );
	}

	public function test_perflab_get_generator_content() {
		// Assert that it returns the current version and active modules.
		// For this test, set the active modules to all defaults but the last one.
		$active_modules = require PERFLAB_MAIN_DIR_PATH . 'default-enabled-modules.php';
		array_pop( $active_modules );
		add_filter(
			'perflab_active_modules',
			function() use ( $active_modules ) {
				return $active_modules;
			}
		);
		$active_modules = array_filter( perflab_get_active_modules(), 'perflab_is_valid_module' );
		$expected       = 'Performance Lab ' . PERFLAB_VERSION . '; modules: ' . implode( ', ', $active_modules );
		$content        = perflab_get_generator_content();
		$this->assertSame( $expected, $content );
	}

	public function test_perflab_render_generator() {
		// Assert generator tag is rendered. Content does not matter, so just use no modules active.
		add_filter( 'perflab_active_modules', '__return_empty_array' );
		$expected = '<meta name="generator" content="Performance Lab ' . PERFLAB_VERSION . '; modules: ">' . "\n";
		$output   = get_echo( 'perflab_render_generator' );
		$this->assertSame( $expected, $output );

		// Assert that the function is hooked into 'wp_head'.
		ob_start();
		do_action( 'wp_head' );
		$output = ob_get_clean();
		$this->assertContains( $expected, $output );
	}

	/**
	 * @dataProvider data_perflab_can_load_module
	 */
	public function test_perflab_is_valid_module( $dummy_module, $expected_status ) {
		$this->assertSame( $expected_status, perflab_is_valid_module( $dummy_module ) );
	}

	public function data_perflab_is_valid_module() {
		return array(
			array( '', false ),
			array( '../tests/testdata/demo-modules/something/non-existing-module', false ),
			array( '../tests/testdata/demo-modules/javascript/demo-module-1', false ),
			array( '../tests/testdata/demo-modules/something/demo-module-2', true ),
			array( '../tests/testdata/demo-modules/images/demo-module-3', true ),
		);
	}

	/**
	 * @dataProvider data_perflab_can_load_module
	 */
	public function test_perflab_can_load_module( $dummy_module, $expected_status ) {
		$this->assertSame( $expected_status, perflab_can_load_module( $dummy_module ) );
	}

	public function data_perflab_can_load_module() {
		return array(
			array( '../tests/testdata/demo-modules/javascript/demo-module-1', false ),
			array( '../tests/testdata/demo-modules/something/demo-module-2', true ),
			array( '../tests/testdata/demo-modules/images/demo-module-3', true ),
		);
	}

	private function get_expected_default_option() {
		// This code is essentially copied over from the perflab_register_modules_setting() function.
		$default_enabled_modules = require PERFLAB_MAIN_DIR_PATH . 'default-enabled-modules.php';
		return array_reduce(
			$default_enabled_modules,
			function( $module_settings, $module_dir ) {
				$module_settings[ $module_dir ] = array( 'enabled' => true );
				return $module_settings;
			},
			array()
		);
	}
}
