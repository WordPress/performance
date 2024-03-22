<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package dominant-color-images
 */

define( 'TESTS_PLUGIN_DIR', dirname( __DIR__ ) );
define( 'TESTS_PLUGIN_ROOT', dirname( dirname( TESTS_PLUGIN_DIR ) ) );

// Load bootstrap functions.
require_once TESTS_PLUGIN_ROOT . '/tests/bootstrap-functions.php';

// Initialize the WP testing environment.
$_tests_dir = PerformanceLab\Tests\init( TESTS_PLUGIN_ROOT, TESTS_PLUGIN_DIR );

// Force plugin to be active.
$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( basename( TESTS_PLUGIN_DIR ) . '/load.php' ),
);

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
