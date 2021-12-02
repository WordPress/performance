<?php
/**
 * Tests for admin/load.php
 *
 * @package performance-lab
 */

class Admin_Load_Tests extends WP_UnitTestCase {

	private static $demo_modules = array(
		'demo-module-1' => array(
			'name'         => 'Demo Module 1',
			'description'  => 'This is the description for demo module 1.',
			'focus'        => 'javascript',
			'experimental' => false,
		),
		'demo-module-2' => array(
			'name'         => 'Demo Module 2',
			'description'  => 'This is the description for demo module 2.',
			'focus'        => 'something',
			'experimental' => true,
		),
		'demo-module-3' => array(
			'name'         => 'Demo Module 3',
			'description'  => 'This is the description for demo module 3.',
			'focus'        => 'images',
			'experimental' => false,
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
			array( 'demo-module-3' ),
			array_keys( $wp_settings_fields[ PERFLAB_MODULES_SCREEN ]['images'] )
		);
		$this->assertEqualSets(
			array( 'demo-module-1' ),
			array_keys( $wp_settings_fields[ PERFLAB_MODULES_SCREEN ]['javascript'] )
		);
		$this->assertEqualSets(
			array( 'demo-module-2' ),
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
}
