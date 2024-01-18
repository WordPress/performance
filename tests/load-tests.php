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
		$default_enabled_modules = require PERFLAB_PLUGIN_DIR_PATH . 'default-enabled-modules.php';
		$expected                = array();
		foreach ( $default_enabled_modules as $default_enabled_module ) {
			$expected[ $default_enabled_module ] = array( 'enabled' => true );
		}

		$this->assertSame( $expected, perflab_get_modules_setting_default() );
	}

	public function test_perflab_get_module_settings() {
		// Assert that by default the settings are using the same value as the registered default.
		$settings = perflab_get_module_settings();
		$this->assertEqualSetsWithIndex( perflab_get_modules_setting_default(), $settings );

		// More specifically though, assert that the default is also passed through to the
		// get_option() call, to support scenarios where the function is called before 'init'.
		// Unhook the registered default logic to verify the default comes from the passed value.
		remove_all_filters( 'default_option_' . PERFLAB_MODULES_SETTING );
		$has_passed_default = false;
		add_filter(
			'default_option_' . PERFLAB_MODULES_SETTING,
			static function ( $current_default, $option, $passed_default ) use ( &$has_passed_default ) {
				// This callback just records whether there is a default value being passed.
				$has_passed_default = $passed_default;
				return $current_default;
			},
			10,
			3
		);
		$settings = perflab_get_module_settings();
		$this->assertTrue( $has_passed_default );
		$this->assertEqualSetsWithIndex( perflab_get_modules_setting_default(), $settings );

		// Assert that option updates are reflected in the settings correctly.
		$new_value = array( 'my-module' => array( 'enabled' => true ) );
		update_option( PERFLAB_MODULES_SETTING, $new_value );
		$settings = perflab_get_module_settings();
		$this->assertEqualSetsWithIndex( $new_value, $settings );
	}

	/**
	 * @dataProvider data_legacy_modules
	 */
	public function test_legacy_module_for_perflab_get_module_settings( $legacy_module_slug, $current_module_slug ) {
		$new_value = array( $legacy_module_slug => array( 'enabled' => true ) );
		update_option( PERFLAB_MODULES_SETTING, $new_value );

		$settings = perflab_get_module_settings();
		$this->assertArrayNotHasKey( $legacy_module_slug, $settings, 'The settings do not contain the old legacy module slug in the database' );
		$this->assertArrayHasKey( $current_module_slug, $settings, 'The settings contain an updated module slug in the database' );
	}

	/**
	 * Data provider for test_legacy_module_for_perflab_get_module_settings().
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $legacy_module_slug  The legacy module slug.
	 *         @type string $current_module_slug The new/updated module slug.
	 *     }
	 * }
	 */
	public function data_legacy_modules() {
		return array(
			array( 'site-health/audit-autoloaded-options', 'database/audit-autoloaded-options' ),
			array( 'site-health/audit-enqueued-assets', 'js-and-css/audit-enqueued-assets' ),
			array( 'site-health/webp-support', 'images/webp-support' ),
			array( 'images/dominant-color', 'images/dominant-color-images' ),
		);
	}

	public function test_perflab_get_active_modules() {
		// Assert that by default there are no active modules.
		$active_modules          = perflab_get_active_modules();
		$expected_active_modules = array_keys(
			array_filter(
				perflab_get_modules_setting_default(),
				static function ( $module_settings ) {
					return $module_settings['enabled'];
				}
			)
		);
		$this->assertEqualSetsWithIndex( $expected_active_modules, $active_modules );

		// Assert that option updates affect the active modules correctly.
		$new_value = array(
			'inactive-module' => array( 'enabled' => false ),
			'active-module'   => array( 'enabled' => true ),
		);
		update_option( PERFLAB_MODULES_SETTING, $new_value );
		$active_modules = perflab_get_active_modules();
		$this->assertEqualSetsWithIndex( array( 'active-module' ), $active_modules );
	}

	public function test_perflab_get_generator_content() {
		// Assert that it returns the current version and active modules.
		// For this test, set the active modules to all defaults but the last one.
		$active_modules = require PERFLAB_PLUGIN_DIR_PATH . 'default-enabled-modules.php';
		array_pop( $active_modules );
		add_filter(
			'perflab_active_modules',
			static function () use ( $active_modules ) {
				return $active_modules;
			}
		);
		$active_modules = array_filter( perflab_get_active_modules(), 'perflab_is_valid_module' );
		$expected       = 'Performance Lab ' . PERFLAB_VERSION . '; modules: ' . implode( ', ', $active_modules ) . '; plugins: ';
		$content        = perflab_get_generator_content();
		$this->assertSame( $expected, $content );
	}

	public function test_perflab_render_generator() {
		// Assert generator tag is rendered. Content does not matter, so just use no modules active.
		add_filter( 'perflab_active_modules', '__return_empty_array' );
		$expected = '<meta name="generator" content="Performance Lab ' . PERFLAB_VERSION . '; modules: ; plugins: ">' . "\n";
		$output   = get_echo( 'perflab_render_generator' );
		$this->assertSame( $expected, $output );

		// Assert that the function is hooked into 'wp_head'.
		ob_start();
		do_action( 'wp_head' );
		$output = ob_get_clean();
		$this->assertStringContainsString( $expected, $output );
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
			array( '../tests/testdata/demo-modules/js-and-css/demo-module-1', false ),
			array( '../tests/testdata/demo-modules/something/demo-module-2', true ),
			array( '../tests/testdata/demo-modules/images/demo-module-3', true ),
			array( '../tests/testdata/demo-modules/check-error/demo-module-4', false ),
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
			array( '../tests/testdata/demo-modules/js-and-css/demo-module-1', false ),
			array( '../tests/testdata/demo-modules/something/demo-module-2', true ),
			array( '../tests/testdata/demo-modules/images/demo-module-3', true ),
		);
	}

	public function test_perflab_can_load_module_with_not_loaded_module() {
		$can_load_module = perflab_can_load_module( '../tests/testdata/demo-modules/check-error/demo-module-4' );
		$this->assertInstanceOf( 'WP_Error', $can_load_module );
		$this->assertSame( 'cannot_load_module', $can_load_module->get_error_code() );
	}

	public function test_perflab_activate_module() {
		perflab_activate_module( __DIR__ . '/testdata/demo-modules/something/demo-module-2' );
		$this->assertSame( 'activated', get_option( 'test_demo_module_activation_status' ) );
	}

	public function test_perflab_deactivate_module() {
		perflab_deactivate_module( __DIR__ . '/testdata/demo-modules/something/demo-module-2' );
		$this->assertSame( 'deactivated', get_option( 'test_demo_module_activation_status' ) );
	}

	private function get_expected_default_option() {
		// This code is essentially copied over from the perflab_register_modules_setting() function.
		$default_enabled_modules = require PERFLAB_PLUGIN_DIR_PATH . 'default-enabled-modules.php';
		return array_reduce(
			$default_enabled_modules,
			static function ( $module_settings, $module_dir ) {
				$module_settings[ $module_dir ] = array( 'enabled' => true );
				return $module_settings;
			},
			array()
		);
	}

	public function test_perflab_maybe_set_object_cache_dropin_no_conflict() {
		global $wp_filesystem;

		$this->set_up_mock_filesystem();

		// Ensure PL object-cache.php drop-in is not present and constant is not set.
		$this->assertFalse( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertFalse( PERFLAB_OBJECT_CACHE_DROPIN_VERSION );

		// Run function to place drop-in and ensure it exists afterwards.
		perflab_maybe_set_object_cache_dropin();
		$this->assertTrue( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertSame( file_get_contents( PERFLAB_PLUGIN_DIR_PATH . 'server-timing/object-cache.copy.php' ), $wp_filesystem->get_contents( WP_CONTENT_DIR . '/object-cache.php' ) );
	}

	public function test_perflab_maybe_set_object_cache_dropin_no_conflict_but_failing() {
		global $wp_filesystem;

		$this->set_up_mock_filesystem();

		// Ensure PL object-cache.php drop-in is not present and constant is not set.
		$this->assertFalse( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertFalse( PERFLAB_OBJECT_CACHE_DROPIN_VERSION );

		// Run function to place drop-in, but then delete file, effectively
		// simulating that (for whatever reason) placing the file failed.
		perflab_maybe_set_object_cache_dropin();
		$wp_filesystem->delete( WP_CONTENT_DIR . '/object-cache.php' );

		// Running the function again should not place the file at this point,
		// as there is a transient timeout present to avoid excessive retries.
		perflab_maybe_set_object_cache_dropin();
		$this->assertFalse( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
	}

	public function test_perflab_maybe_set_object_cache_dropin_with_conflict() {
		global $wp_filesystem;

		$this->set_up_mock_filesystem();

		$dummy_file_content = '<?php /* Empty object-cache.php drop-in file. */';
		$wp_filesystem->put_contents( WP_CONTENT_DIR . '/object-cache.php', $dummy_file_content );

		// Ensure dummy object-cache.php drop-in is present and PL constant is not set.
		$this->assertTrue( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertFalse( PERFLAB_OBJECT_CACHE_DROPIN_VERSION );

		// Run function to place drop-in and ensure it does not override the existing drop-in.
		perflab_maybe_set_object_cache_dropin();
		$this->assertTrue( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertSame( $dummy_file_content, $wp_filesystem->get_contents( WP_CONTENT_DIR . '/object-cache.php' ) );
	}

	public function test_perflab_object_cache_dropin_may_be_disabled_via_filter() {
		global $wp_filesystem;

		$this->set_up_mock_filesystem();

		// Ensure PL object-cache.php drop-in is not present and constant is not set.
		$this->assertFalse( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertFalse( PERFLAB_OBJECT_CACHE_DROPIN_VERSION );

		// Add filter to disable drop-in.
		add_filter( 'perflab_disable_object_cache_dropin', '__return_true' );

		// Run function to place drop-in and ensure it still doesn't exist afterwards.
		perflab_maybe_set_object_cache_dropin();
		$this->assertFalse( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
	}

	private function set_up_mock_filesystem() {
		global $wp_filesystem;

		add_filter(
			'filesystem_method_file',
			static function () {
				return __DIR__ . '/utils/Filesystem/WP_Filesystem_MockFilesystem.php';
			}
		);
		add_filter(
			'filesystem_method',
			static function () {
				return 'MockFilesystem';
			}
		);
		WP_Filesystem();

		// Simulate that the original object-cache.copy.php file exists.
		$wp_filesystem->put_contents( PERFLAB_PLUGIN_DIR_PATH . 'server-timing/object-cache.copy.php', file_get_contents( PERFLAB_PLUGIN_DIR_PATH . 'server-timing/object-cache.copy.php' ) );
	}
}
