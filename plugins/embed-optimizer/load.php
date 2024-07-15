<?php
/**
 * Plugin Name: Embed Optimizer
 * Plugin URI: https://github.com/WordPress/performance/tree/trunk/plugins/embed-optimizer
 * Description: Optimizes the performance of embeds by lazy-loading iframes and scripts.
 * Requires at least: 6.5
 * Requires PHP: 7.2
 * Version: 0.1.2
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: embed-optimizer
 *
 * @package embed-optimizer
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
	'embed_optimizer_pending_plugin',
	'0.1.2',
	static function ( string $version ): void {

		define( 'EMBED_OPTIMIZER_VERSION', $version );

		// Load in the Embed Optimizer plugin hooks.
		require_once __DIR__ . '/hooks.php';
	}
);
