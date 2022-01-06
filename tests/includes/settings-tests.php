<?php
/**
 * Tests for includes/settings.php
 *
 * @package performance-lab
 */

class Settings_Tests extends WP_UnitTestCase {

	private static $demo_modules = array(
		'javascript/demo-module-1' => array(
			'name'         => 'Demo Module 1',
			'description'  => 'This is the description for demo module 1.',
			'experimental' => false,
			'focus'        => 'javascript',
			'slug'         => 'demo-module-1',
		),
		'something/demo-module-2'  => array(
			'name'         => 'Demo Module 2',
			'description'  => 'This is the description for demo module 2.',
			'experimental' => true,
			'focus'        => 'something',
			'slug'         => 'demo-module-2',
		),
		'images/demo-module-3'     => array(
			'name'         => 'Demo Module 3',
			'description'  => 'This is the description for demo module 3.',
			'experimental' => false,
			'focus'        => 'images',
			'slug'         => 'demo-module-3',
		),
	);

	private static $demo_focus_areas = array(
		'images'         => array(
			'name' => 'Images',
		),
		'javascript'     => array(
			'name' => 'JavaScript',
		),
		'site-health'    => array(
			'name' => 'Site Health',
		),
		'measurement'    => array(
			'name' => 'Measurement',
		),
		'object-caching' => array(
			'name' => 'Object caching',
		),
	);

	public function test_perflab_add_modules_page() {
		global $_wp_submenu_nopriv;

		// Reset relevant globals.
		$_wp_submenu_nopriv = array();

		// The default user does not have the 'manage_options' capability.
		$hook_suffix = perflab_add_modules_page();
		$this->assertFalse( $hook_suffix );
		$this->assertTrue( isset( $_wp_submenu_nopriv['options-general.php'][ PERFLAB_MODULES_SCREEN ] ) );

		// Reset relevant globals.
		$_wp_submenu_nopriv = array();

		// Rely on current user to be an administrator (with 'manage_options' capability).
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$hook_suffix = perflab_add_modules_page();
		$this->assertSame( get_plugin_page_hookname( PERFLAB_MODULES_SCREEN, 'options-general.php' ), $hook_suffix );
		$this->assertFalse( isset( $_wp_submenu_nopriv['options-general.php'][ PERFLAB_MODULES_SCREEN ] ) );
	}

	public function test_perflab_load_modules_page() {
		global $wp_settings_sections, $wp_settings_fields;

		// Reset relevant globals.
		$wp_settings_sections = array();
		$wp_settings_fields   = array();

		// Pass no modules, resulting in nothing being registered.
		perflab_load_modules_page( array(), self::$demo_focus_areas );
		$this->assertFalse( ! empty( $wp_settings_sections[ PERFLAB_MODULES_SCREEN ] ) );
		$this->assertFalse( ! empty( $wp_settings_fields[ PERFLAB_MODULES_SCREEN ] ) );

		// Reset relevant globals.
		$wp_settings_sections = array();
		$wp_settings_fields   = array();

		// Pass demo modules, resulting in relevant sections and fields for those modules being registered.
		perflab_load_modules_page( self::$demo_modules, self::$demo_focus_areas );
		$this->assertTrue( ! empty( $wp_settings_sections[ PERFLAB_MODULES_SCREEN ] ) );
		$this->assertSame(
			array(
				'images'     => array(
					'id'       => 'images',
					'title'    => 'Images',
					'callback' => null,
				),
				'javascript' => array(
					'id'       => 'javascript',
					'title'    => 'JavaScript',
					'callback' => null,
				),
				'other'      => array(
					'id'       => 'other',
					'title'    => 'Other',
					'callback' => null,
				),
			),
			$wp_settings_sections[ PERFLAB_MODULES_SCREEN ]
		);
		$this->assertEqualSets(
			array(
				'images',
				'javascript',
				'other',
			),
			array_keys( $wp_settings_fields[ PERFLAB_MODULES_SCREEN ] )
		);
		$this->assertEqualSets(
			array( 'images/demo-module-3' ),
			array_keys( $wp_settings_fields[ PERFLAB_MODULES_SCREEN ]['images'] )
		);
		$this->assertEqualSets(
			array( 'javascript/demo-module-1' ),
			array_keys( $wp_settings_fields[ PERFLAB_MODULES_SCREEN ]['javascript'] )
		);
		$this->assertEqualSets(
			array( 'something/demo-module-2' ),
			array_keys( $wp_settings_fields[ PERFLAB_MODULES_SCREEN ]['other'] )
		);
	}

	public function test_perflab_render_modules_page() {
		ob_start();
		perflab_render_modules_page();
		$output = ob_get_clean();
		$this->assertContains( '<div class="wrap">', $output );
		$this->assertContains( "<input type='hidden' name='option_page' value='" . PERFLAB_MODULES_SCREEN . "' />", $output );
	}

	public function test_perflab_render_modules_page_field() {
		$module_slug     = 'javascript/demo-module-1';
		$module_data     = self::$demo_modules[ $module_slug ];
		$module_settings = array( 'enabled' => false );

		// Assert correct 'id' and 'name' attributes, label, and unchecked checkbox.
		ob_start();
		perflab_render_modules_page_field( $module_slug, $module_data, $module_settings );
		$output = ob_get_clean();
		$this->assertContains( 'id="module_' . $module_slug . '_enabled"', $output );
		$this->assertContains( 'name="' . PERFLAB_MODULES_SETTING . '[' . $module_slug . '][enabled]"', $output );
		$this->assertContains( 'Enable ' . $module_data['name'] . '?', $output );
		$this->assertNotContains( 'checked', $output );

		// Assert correct 'id' and 'name' attributes, experimental label, and checked checkbox.
		$module_data['experimental'] = true;
		$module_settings['enabled']  = true;
		ob_start();
		perflab_render_modules_page_field( $module_slug, $module_data, $module_settings );
		$output = ob_get_clean();
		$this->assertContains( 'id="module_' . $module_slug . '_enabled"', $output );
		$this->assertContains( 'name="' . PERFLAB_MODULES_SETTING . '[' . $module_slug . '][enabled]"', $output );
		$this->assertContains( 'Enable ' . $module_data['name'] . ' <strong>(experimental)</strong>?', $output );
		$this->assertContains( "checked='checked'", $output );
	}

	public function test_perflab_get_focus_areas() {
		$expected_focus_areas = array(
			'images',
			'javascript',
			'site-health',
			'measurement',
			'object-caching',
		);
		$this->assertSame( $expected_focus_areas, array_keys( perflab_get_focus_areas() ) );
	}

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

}
