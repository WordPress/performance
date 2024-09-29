<?php
/**
 * Third-party integration loader for Web Worker Offloading.
 *
 * @since 0.1.0
 * @package web-worker-offloading
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds scripts to be offloaded to a worker.
 *
 * @since 0.1.0
 * @access private
 *
 * @param string[] $script_handles Script handles.
 */
function wwo_mark_scripts_for_offloading( array $script_handles ): void {
	add_filter(
		'print_scripts_array',
		static function ( $to_do ) use ( $script_handles ) {
			$worker_script_handles = array_intersect( (array) $to_do, $script_handles );
			foreach ( $worker_script_handles as $worker_script_handle ) {
				wp_script_add_data( $worker_script_handle, 'worker', true );
			}
			return $to_do;
		}
	);
}

/**
 * Loads third party plugin integrations for active plugins.
 *
 * @since 0.1.0
 * @access private
 */
function wwo_load_third_party_integrations(): void {
	$plugins_with_integrations = array(
		// TODO: google-site-kit.
		// TODO: seo-by-rank-math.
		'woocommerce',
	);

	// Load corresponding file for each string in $plugins if the WordPress plugin is installed and active.
	$active_plugin_slugs = array_filter(
		array_map(
			static function ( $plugin_file ) {
				if ( is_string( $plugin_file ) && str_contains( $plugin_file, '/' ) ) {
					return strtok( $plugin_file, '/' );
				} else {
					return false;
				}
			},
			(array) get_option( 'active_plugins' )
		)
	);

	foreach ( array_intersect( $active_plugin_slugs, $plugins_with_integrations ) as $plugin_slug ) {
		require_once __DIR__ . '/third-party/' . $plugin_slug . '.php';
	}
}
add_action( 'plugins_loaded', 'wwo_load_third_party_integrations' );
