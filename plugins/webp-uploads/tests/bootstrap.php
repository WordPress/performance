<?php
/**
 * PHPUnit bootstrap file
 *
 * @package webp-uploads
 */

define( 'TESTS_PLUGIN_DIR', dirname( __DIR__ ) );
define( 'TESTS_PLUGIN_ROOT', dirname( dirname( TESTS_PLUGIN_DIR ) ) );

// Determine correct location for plugins directory to use.
if ( false !== getenv( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', getenv( 'WP_PLUGIN_DIR' ) );
} else {
	define( 'WP_PLUGIN_DIR', dirname( TESTS_PLUGIN_DIR ) );
}

// Load Composer dependencies if applicable.
if ( file_exists( TESTS_PLUGIN_ROOT . '/vendor/autoload.php' ) ) {
	require_once TESTS_PLUGIN_ROOT . '/vendor/autoload.php';
}

// Detect where to load the WordPress tests environment from.
$_tests_dir = WPP_Tests_Helpers::get_path_to_wp_test_dir();
require_once $_tests_dir . '/includes/functions.php';

// Force plugin to be active.
$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( basename( TESTS_PLUGIN_DIR ) . '/webp-uploads.php' ),
);

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
