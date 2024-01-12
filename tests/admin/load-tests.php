<?php
/**
 * Tests for admin/load.php
 *
 * @package performance-lab
 */

/**
 * @group admin
 */
class Admin_Load_Tests extends WP_UnitTestCase {

	private static $demo_modules = array(
		'js-and-css/demo-module-1'  => array(
			'name'         => 'Demo Module 1',
			'description'  => 'This is the description for demo module 1.',
			'experimental' => false,
			'focus'        => 'js-and-css',
			'slug'         => 'demo-module-1',
		),
		'something/demo-module-2'   => array(
			'name'         => 'Demo Module 2',
			'description'  => 'This is the description for demo module 2.',
			'experimental' => true,
			'focus'        => 'something',
			'slug'         => 'demo-module-2',
		),
		'images/demo-module-3'      => array(
			'name'         => 'Demo Module 3',
			'description'  => 'This is the description for demo module 3.',
			'experimental' => false,
			'focus'        => 'images',
			'slug'         => 'demo-module-3',
		),
		'check-error/demo-module-4' => array(
			'name'         => 'Demo Module 4',
			'description'  => 'This is the description for demo module 4.',
			'experimental' => false,
			'focus'        => 'check-error',
			'slug'         => 'demo-module-4',
		),
	);

	private static $demo_focus_areas = array(
		'images'       => array(
			'name' => 'Images',
		),
		'js-and-css'   => array(
			'name' => 'js-and-css',
		),
		'database'     => array(
			'name' => 'Database',
		),
		'measurement'  => array(
			'name' => 'Measurement',
		),
		'object-cache' => array(
			'name' => 'Object Cache',
		),
	);

	public function test_perflab_add_modules_page() {
		global $_wp_submenu_nopriv;

		// Reset relevant globals and filters.
		$_wp_submenu_nopriv = array();
		remove_all_filters( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ) );

		// The default user does not have the 'manage_options' capability.
		$hook_suffix = perflab_add_modules_page();
		$this->assertFalse( $hook_suffix );
		$this->assertTrue( isset( $_wp_submenu_nopriv['options-general.php'][ PERFLAB_MODULES_SCREEN ] ) );
		// Ensure plugin action link is not added.
		$this->assertFalse( (bool) has_action( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ), 'perflab_plugin_action_links_add_settings' ) );

		// Reset relevant globals and filters.
		$_wp_submenu_nopriv = array();
		remove_all_filters( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ) );

		// Rely on current user to be an administrator (with 'manage_options' capability).
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$hook_suffix = perflab_add_modules_page();
		$this->assertSame( get_plugin_page_hookname( PERFLAB_MODULES_SCREEN, 'options-general.php' ), $hook_suffix );
		$this->assertFalse( isset( $_wp_submenu_nopriv['options-general.php'][ PERFLAB_MODULES_SCREEN ] ) );
		// Ensure plugin action link is added.
		$this->assertTrue( (bool) has_action( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ), 'perflab_plugin_action_links_add_settings' ) );

		// Reset relevant globals and filters.
		$_wp_submenu_nopriv = array();
		remove_all_filters( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ) );

		// Does not register the page if the perflab_active_modules filter is used.
		add_filter( 'perflab_active_modules', '__return_array' );
		$hook_suffix = perflab_add_modules_page();
		$this->assertFalse( $hook_suffix );
		$this->assertFalse( isset( $_wp_submenu_nopriv['options-general.php'][ PERFLAB_MODULES_SCREEN ] ) );
		// Ensure plugin action link is not added.
		$this->assertFalse( (bool) has_action( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ), 'perflab_plugin_action_links_add_settings' ) );
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
					'id'             => 'images',
					'title'          => 'Images',
					'callback'       => null,
					'before_section' => '',
					'after_section'  => '',
					'section_class'  => '',
				),
				'js-and-css' => array(
					'id'             => 'js-and-css',
					'title'          => 'js-and-css',
					'callback'       => null,
					'before_section' => '',
					'after_section'  => '',
					'section_class'  => '',
				),
				'other'      => array(
					'id'             => 'other',
					'title'          => 'Other',
					'callback'       => null,
					'before_section' => '',
					'after_section'  => '',
					'section_class'  => '',
				),
			),
			$wp_settings_sections[ PERFLAB_MODULES_SCREEN ]
		);
		$this->assertEqualSets(
			array(
				'images',
				'js-and-css',
				'other',
			),
			array_keys( $wp_settings_fields[ PERFLAB_MODULES_SCREEN ] )
		);
		$this->assertEqualSets(
			array( 'images/demo-module-3' ),
			array_keys( $wp_settings_fields[ PERFLAB_MODULES_SCREEN ]['images'] )
		);
		$this->assertEqualSets(
			array( 'js-and-css/demo-module-1' ),
			array_keys( $wp_settings_fields[ PERFLAB_MODULES_SCREEN ]['js-and-css'] )
		);
		$this->assertEqualSets(
			array( 'something/demo-module-2', 'check-error/demo-module-4' ),
			array_keys( $wp_settings_fields[ PERFLAB_MODULES_SCREEN ]['other'] )
		);
	}

	public function test_perflab_render_modules_page() {
		ob_start();
		perflab_render_modules_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( '<div class="wrap">', $output );
		$this->assertStringContainsString( "<input type='hidden' name='option_page' value='" . PERFLAB_MODULES_SCREEN . "' />", $output );
	}

	public function test_perflab_render_modules_page_field() {
		$module_slug     = 'js-and-css/demo-module-1';
		$module_data     = self::$demo_modules[ $module_slug ];
		$module_settings = array( 'enabled' => false );

		// Assert correct 'id' and 'name' attributes, label, and unchecked checkbox.
		ob_start();
		perflab_render_modules_page_field( $module_slug, $module_data, $module_settings );
		$output = ob_get_clean();
		$this->assertStringContainsString( ' id="module_' . $module_slug . '_enabled"', $output );
		$this->assertStringContainsString( ' name="' . PERFLAB_MODULES_SETTING . '[' . $module_slug . '][enabled]"', $output );
		$this->assertStringContainsString( 'Enable ' . $module_data['name'], $output );
		$this->assertStringNotContainsString( ' checked', $output );

		// Assert correct 'id' and 'name' attributes, experimental label, and checked checkbox.
		$module_data['experimental'] = true;
		$module_settings['enabled']  = true;
		ob_start();
		perflab_render_modules_page_field( $module_slug, $module_data, $module_settings );
		$output = ob_get_clean();
		$this->assertStringContainsString( ' id="module_' . $module_slug . '_enabled"', $output );
		$this->assertStringContainsString( ' name="' . PERFLAB_MODULES_SETTING . '[' . $module_slug . '][enabled]"', $output );
		$this->assertStringContainsString( 'Enable ' . $module_data['name'] . ' <strong>(experimental)</strong>', $output );
		$this->assertStringContainsString( " checked='checked'", $output );
	}

	public function test_perflab_get_focus_areas() {
		$expected_focus_areas = array(
			'images',
			'js-and-css',
			'database',
			'measurement',
			'object-cache',
		);
		$this->assertSame( $expected_focus_areas, array_keys( perflab_get_focus_areas() ) );
	}

	public function test_perflab_get_modules() {
		// Use test data directory with demo modules that match the modules declared on top of this file.
		$modules = perflab_get_modules( TESTS_PLUGIN_DIR . '/tests/testdata/demo-modules' );
		$this->assertSame( self::$demo_modules, $modules );
	}

	public function test_perflab_get_module_data() {
		// Use test data directory with demo modules that match the modules declared on top of this file.
		foreach ( self::$demo_modules as $module_slug => $expected_module_data ) {
			$module_data = perflab_get_module_data( TESTS_PLUGIN_DIR . '/tests/testdata/demo-modules/' . $module_slug . '/load.php' );
			$this->assertSame( $expected_module_data, $module_data );
		}
	}

	public function test_perflab_plugin_action_links_add_settings() {
		$original_links = array( '<a href="https://wordpress.org">wordpress.org</a>' );
		$expected_links = array(
			'<a href="' . admin_url( '/' ) . 'options-general.php?page=' . PERFLAB_MODULES_SCREEN . '">Settings</a>',
			$original_links[0],
		);

		$actual_links = perflab_plugin_action_links_add_settings( $original_links );
		$this->assertSame( $expected_links, $actual_links );
	}
}
