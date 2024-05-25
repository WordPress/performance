<?php
/**
 * Plugin Name: Optimization Detective
 * Plugin URI: https://github.com/WordPress/performance/issues/869
 * Description: Uses real user metrics to improve heuristics WordPress applies on the frontend to improve image loading priority.
 * Requires at least: 6.4
 * Requires PHP: 7.2
 * Version: 0.2.0
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: optimization-detective
 *
 * @package optimization-detective
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

(
	/**
	 * Register this copy of the plugin among other potential copies embedded in plugins or themes.
	 *
	 * @param string  $global_var_name Global variable name for storing the plugin pending loading.
	 * @param string  $version         Version.
	 * @param Closure $load            Callback that loads the plugin.
	 */
	static function ( string $global_var_name, string $version, Closure $load ): void {
		if ( ! isset( $GLOBALS[ $global_var_name ] ) ) {
			$bootstrap = static function () use ( $global_var_name ): void {
				if (
					isset( $GLOBALS[ $global_var_name ]['load'], $GLOBALS[ $global_var_name ]['version'] )
					&&
					$GLOBALS[ $global_var_name ]['load'] instanceof Closure
					&&
					is_string( $GLOBALS[ $global_var_name ]['version'] )
				) {
					call_user_func( $GLOBALS[ $global_var_name ]['load'], $GLOBALS[ $global_var_name ]['version'] );
					unset( $GLOBALS[ $global_var_name ] );
				}
			};

			// Wait until after the plugins have loaded and the theme has loaded. The after_setup_theme action is used
			// because it is the first action that fires once the theme is loaded.
			add_action( 'after_setup_theme', $bootstrap, PHP_INT_MIN );
		}

		// Register this copy of the plugin.
		if (
			// Register this copy if none has been registered yet.
			! isset( $GLOBALS[ $global_var_name ]['version'] )
			||
			// Or register this copy if the version greater than what is currently registered.
			version_compare( $version, $GLOBALS[ $global_var_name ]['version'], '>' )
			||
			// Otherwise, register this copy if it is actually the one installed in the directory for plugins.
			rtrim( WP_PLUGIN_DIR, '/' ) === dirname( __DIR__ )
		) {
			$GLOBALS[ $global_var_name ]['version'] = $version;
			$GLOBALS[ $global_var_name ]['load']    = $load;
		}
	}
)(
	'optimization_detective_pending_plugin',
	'0.2.0',
	static function ( string $version ): void {

		// Define the constant.
		if ( defined( 'OPTIMIZATION_DETECTIVE_VERSION' ) ) {
			return;
		}

		if (
			( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) &&
			! file_exists( __DIR__ . '/build/web-vitals.asset.php' )
		) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error(
				esc_html(
					sprintf(
						/* translators: 1: File path. 2: CLI command. */
						'[Optimization Detective] ' . __( 'Unable to load %1$s. Please make sure you have run %2$s.', 'optimization-detective' ),
						'build/web-vitals.asset.php',
						'`npm install && npm run build:optimization-detective`'
					)
				),
				E_USER_ERROR
			);
		}

		define( 'OPTIMIZATION_DETECTIVE_VERSION', $version );

		require_once __DIR__ . '/helper.php';

		// Core infrastructure classes.
		require_once __DIR__ . '/class-od-data-validation-exception.php';
		require_once __DIR__ . '/class-od-html-tag-processor.php';
		require_once __DIR__ . '/class-od-url-metric.php';
		require_once __DIR__ . '/class-od-url-metrics-group.php';
		require_once __DIR__ . '/class-od-url-metrics-group-collection.php';

		// Storage logic.
		require_once __DIR__ . '/storage/class-od-url-metrics-post-type.php';
		require_once __DIR__ . '/storage/class-od-storage-lock.php';
		require_once __DIR__ . '/storage/data.php';
		require_once __DIR__ . '/storage/rest-api.php';

		// Detection logic.
		require_once __DIR__ . '/detection.php';

		// Optimization logic.
		require_once __DIR__ . '/class-od-html-tag-walker.php';
		require_once __DIR__ . '/class-od-preload-link-collection.php';
		require_once __DIR__ . '/class-od-tag-visitor-registry.php';
		require_once __DIR__ . '/optimization.php';

		// Add hooks for the above requires.
		require_once __DIR__ . '/hooks.php';
	}
);
