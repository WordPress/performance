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
if ( false !== getenv( 'WP_TESTS_DIR' ) ) {
	$_test_root = getenv( 'WP_TESTS_DIR' );
} elseif ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$_test_root = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} elseif ( false !== getenv( 'WP_PHPUNIT__DIR' ) ) {
	$_test_root = getenv( 'WP_PHPUNIT__DIR' );
} elseif ( file_exists( TESTS_PLUGIN_DIR . '/../../../../tests/phpunit/includes/functions.php' ) ) {
	$_test_root = TESTS_PLUGIN_DIR . '/../../../../tests/phpunit';
} else { // Fallback.
	$_test_root = '/tmp/wordpress-tests-lib';
}

require_once $_test_root . '/includes/functions.php';

// Check if we use the plugin's test suite. If so, disable the PL plugin and only load the requested plugin.
$plugin_name = '';
foreach ( $_SERVER['argv'] as $index => $arg ) {
	if (
		'--testsuite' === $arg &&
		isset( $_SERVER['argv'][ $index + 1 ] ) &&
		'performance-lab' !== $_SERVER['argv'][ $index + 1 ] &&
		file_exists( TESTS_PLUGIN_DIR . '/plugins/' . $_SERVER['argv'][ $index + 1 ] )
	) {
		$plugin_name = $_SERVER['argv'][ $index + 1 ];
	}
}

if ( $plugin_name ) {
	$plugin_test_path = TESTS_PLUGIN_DIR . '/plugins/' . $plugin_name;
	tests_add_filter(
		'plugins_loaded',
		static function () use ( $plugin_test_path, $plugin_name ) {
			// Check if plugin has a "plugin/plugin.php" file.
			if ( file_exists( $plugin_test_path . '/' . $plugin_name . '.php' ) ) {
				require_once $plugin_test_path . '/' . $plugin_name . '.php';
			} elseif ( file_exists( $plugin_test_path . '/load.php' ) ) {
				// Check if plugin has a "plugin/load.php" file.
				require_once $plugin_test_path . '/load.php';
			} else {
				echo "Unable to locate standalone plugin bootstrap file in $plugin_test_path.";
				exit( 1 );
			}
		},
		1
	);
} else {
	// Force plugin to be active.
	$GLOBALS['wp_tests_options'] = array(
		'active_plugins' => array( basename( TESTS_PLUGIN_DIR ) . '/load.php' ),
	);

	// Add filter to ensure the plugin's admin integration is loaded for tests.
	tests_add_filter(
		'plugins_loaded',
		static function () {
			require_once TESTS_PLUGIN_DIR . '/includes/admin/load.php';
			require_once TESTS_PLUGIN_DIR . '/includes/admin/server-timing.php';
			require_once TESTS_PLUGIN_DIR . '/includes/admin/plugins.php';
		},
		1
	);
}

// Start up the WP testing environment.
require $_test_root . '/includes/bootstrap.php';
