<?php
/**
 * Bootstrap functions for the tests.
 *
 * @package performance-lab
 */

namespace PerformanceLab\Tests;

/**
 * Initialize the test environment.
 *
 * @param string $plugin_root The root directory of the plugin.
 * @param string $plugin_dir  The directory of the plugin.
 *
 * @return string The path to the WP test directory.
 */
function init( $plugin_root, $plugin_dir = '' ) {
	// If plugin directory is not set, assume it is the same as the plugin root.
	if ( empty( $plugin_dir ) ) {
		$plugin_dir = $plugin_root;
	}

	// Determine correct location for plugins directory to use.
	if ( false !== getenv( 'WP_PLUGIN_DIR' ) ) {
		define( 'WP_PLUGIN_DIR', getenv( 'WP_PLUGIN_DIR' ) );
	} else {
		define( 'WP_PLUGIN_DIR', dirname( $plugin_dir ) );
	}

	// Load the Composer dependencies if applicable.
	if ( file_exists( $plugin_root . '/vendor/autoload.php' ) ) {
		require_once $plugin_root . '/vendor/autoload.php';
	}

	$wp_tests_dir = get_path_to_wp_test_dir();

	// Load the WordPress tests environment.
	require_once $wp_tests_dir . '/includes/functions.php';

	return $wp_tests_dir;
}

/**
 * This function is documented at <https://github.com/Yoast/wp-test-utils/blob/46566884fe74d2f758273efd71f6c07c7d2e4f53/src/WPIntegration/bootstrap-functions.php>.
 *
 * @return string|false The path to the WP test directory, or false if it could not be determined.
 */
function get_path_to_wp_test_dir() {
	if ( false !== getenv( 'WP_TESTS_DIR' ) ) {
		$tests_dir = getenv( 'WP_TESTS_DIR' );
		$tests_dir = realpath( $tests_dir );
		if ( false !== $tests_dir ) {
			$tests_dir = normalize_path( $tests_dir ) . '/';
			if ( is_dir( $tests_dir ) === true
				&& file_exists( $tests_dir . 'includes/bootstrap.php' )
			) {
				return $tests_dir;
			}
		}

		unset( $tests_dir );
	}

	if ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
		$dev_dir = getenv( 'WP_DEVELOP_DIR' );
		$dev_dir = realpath( $dev_dir );
		if ( false !== $dev_dir ) {
			$dev_dir = normalize_path( $dev_dir ) . '/';
			if ( is_dir( $dev_dir ) === true
				&& file_exists( $dev_dir . 'tests/phpunit/includes/bootstrap.php' )
			) {
				return $dev_dir . 'tests/phpunit/';
			}
		}

		unset( $dev_dir );
	}

	/*
	* If neither of the constants was set, check whether the plugin is installed
	* in `src/wp-content/plugins`. In that case, this file would be in
	* `src/wp-content/plugins/performance/tests`.
	*/
	if ( file_exists( __DIR__ . '/../../../../../tests/phpunit/includes/bootstrap.php' ) ) {
		$tests_dir = __DIR__ . '/../../../../../tests/phpunit/';
		$tests_dir = realpath( $tests_dir );
		if ( false !== $tests_dir ) {
			return normalize_path( $tests_dir ) . '/';
		}

		unset( $tests_dir );
	}

	/*
	* Last resort: see if this is a typical WP-CLI scaffold command set-up where a subset of
	* the WP test files have been put in the system temp directory.
	*/
	$tests_dir = sys_get_temp_dir() . '/wordpress-tests-lib';
	$tests_dir = realpath( $tests_dir );
	if ( false !== $tests_dir ) {
		$tests_dir = normalize_path( $tests_dir ) . '/';
		if ( true === is_dir( $tests_dir )
			&& file_exists( $tests_dir . 'includes/bootstrap.php' )
		) {
			return $tests_dir;
		}
	}

	return false;
}

/**
 * Normalizes all slashes in a file path to forward slashes.
 *
 * @param string $path File path.
 *
 * @return string The file path with normalized slashes.
 */
function normalize_path( $path ) {
	return \str_replace( '\\', '/', $path );
}
