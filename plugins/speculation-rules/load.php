<?php
/**
 * Plugin Name: Speculative Loading
 * Plugin URI: https://github.com/WordPress/performance/tree/trunk/plugins/speculation-rules
 * Description: Enables browsers to speculatively prerender or prefetch pages when hovering over links.
 * Requires at least: 6.4
 * Requires PHP: 7.2
 * Version: 1.2.2
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: speculation-rules
 *
 * @package speculation-rules
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
	static function ( string $global_var_name, string $version, Closure $load ) {
		if ( ! isset( $GLOBALS[ $global_var_name ] ) ) {
			$bootstrap = static function () use ( $global_var_name ) {
				if (
					isset( $GLOBALS[ $global_var_name ]['load'] )
					&&
					$GLOBALS[ $global_var_name ]['load'] instanceof Closure
				) {
					call_user_func( $GLOBALS[ $global_var_name ]['load'] );
					unset( $GLOBALS[ $global_var_name ] );
				}
			};

			// Wait until after the plugins have loaded and the theme has loaded. The after_setup_theme action is used
			// because it is the first action that fires once the theme is loaded.
			add_action( 'after_setup_theme', $bootstrap, 0 );
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
	'plsr_pending_plugin_info',
	'1.2.2',
	static function () {

		// Define the constant.
		if ( defined( 'SPECULATION_RULES_VERSION' ) ) {
			return;
		}

		define( 'SPECULATION_RULES_VERSION', '1.2.1' );
		define( 'SPECULATION_RULES_MAIN_FILE', plugin_basename( __FILE__ ) );

		require_once __DIR__ . '/class-plsr-url-pattern-prefixer.php';
		require_once __DIR__ . '/helper.php';
		require_once __DIR__ . '/hooks.php';
		require_once __DIR__ . '/settings.php';
	}
);
