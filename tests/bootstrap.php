<?php
/**
 * PHPUnit bootstrap file
 *
 * @package performance-lab
 */

define( 'TESTS_PLUGIN_DIR', dirname( __DIR__ ) );

// Determine correct location for plugins directory to use.
if ( false !== getenv( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', getenv( 'WP_PLUGIN_DIR' ) );
} else {
	define( 'WP_PLUGIN_DIR', dirname( TESTS_PLUGIN_DIR ) );
}

// Load Composer dependencies if applicable.
if ( file_exists( TESTS_PLUGIN_DIR . '/vendor/autoload.php' ) ) {
	require_once TESTS_PLUGIN_DIR . '/vendor/autoload.php';
}

// Detect where to load the WordPress tests environment from.
$_tests_dir = get_path_to_wp_test_dir();
require_once $_tests_dir . '/includes/functions.php';

// Force plugin to be active.
$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( basename( TESTS_PLUGIN_DIR ) . '/load.php' ),
);

// Add filter to ensure the plugin's admin integration and all modules are loaded for tests.
tests_add_filter(
	'plugins_loaded',
	static function () {
		require_once TESTS_PLUGIN_DIR . '/admin/load.php';
		require_once TESTS_PLUGIN_DIR . '/admin/server-timing.php';
		require_once TESTS_PLUGIN_DIR . '/admin/plugins.php';
		$module_files = glob( TESTS_PLUGIN_DIR . '/modules/*/*/load.php' );
		if ( $module_files ) {
			foreach ( $module_files as $module_file ) {
				require_once $module_file;
			}
		}
	},
	1
);

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
