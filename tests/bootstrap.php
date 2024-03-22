<?php
/**
 * PHPUnit bootstrap file
 *
 * @package performance-lab
 */

define( 'TESTS_PLUGIN_DIR', dirname( __DIR__ ) );

// Load bootstrap functions.
require_once TESTS_PLUGIN_DIR . '/tests/bootstrap-functions.php';

// Initialize the WP testing environment.
$_tests_dir = PerformanceLab\Tests\init( TESTS_PLUGIN_DIR );

// Force plugin to be active.
$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( basename( TESTS_PLUGIN_DIR ) . '/load.php' ),
);

// Add filter to ensure the plugin's admin for tests.
tests_add_filter(
	'plugins_loaded',
	static function () {
		require_once TESTS_PLUGIN_DIR . '/includes/admin/load.php';
		require_once TESTS_PLUGIN_DIR . '/includes/admin/server-timing.php';
		require_once TESTS_PLUGIN_DIR . '/includes/admin/plugins.php';
	},
	1
);

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
