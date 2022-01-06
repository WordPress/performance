<?php
/**
 * Tests for includes/modules.php
 *
 * @package performance-lab
 */

class Modules_Tests extends WP_UnitTestCase {

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
