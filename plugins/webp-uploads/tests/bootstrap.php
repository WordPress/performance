<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package webp-uploads
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

// Load tests helpers.
require_once TESTS_PLUGIN_DIR . '/tests/data/testcase.php';
require_once TESTS_PLUGIN_DIR . '/tests/data/class-wp-image-edit.php';
require_once TESTS_PLUGIN_DIR . '/tests/data/class-wp-image-doesnt-support-webp.php';
require_once TESTS_PLUGIN_DIR . '/tests/data/class-image-has-source-constraint.php';
require_once TESTS_PLUGIN_DIR . '/tests/data/class-image-has-size-source-constraint.php';
