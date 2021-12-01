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

// Detect where to load the WordPress tests environment from.
if ( false !== getenv( 'WP_TESTS_DIR' ) ) {
	$_test_root = getenv( 'WP_TESTS_DIR' );
} elseif ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$_test_root = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} else {
	// Ensure Composer autoloader is available.
	require_once TESTS_PLUGIN_DIR . '/vendor/autoload.php';

	if ( ! getenv( 'WP_PHPUNIT__DIR' ) ) {
		printf( '%s is not defined. Run `composer install` to install the WordPress tests library.' . "\n", 'WP_PHPUNIT__DIR' );
		exit;
	}

	$_test_root = getenv( 'WP_PHPUNIT__DIR' );
}

// Force plugin to be active.
$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( basename( TESTS_PLUGIN_DIR ) . '/load.php' ),
);

// Start up the WP testing environment.
require $_test_root . '/includes/bootstrap.php';
