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
 * Adds forwarded events for Google Analytics.
 *
 * @since 0.1.0
 * @link https://partytown.builder.io/google-tag-manager#forward-events
 *
 * @param array<string, mixed>|mixed $configuration Configuration.
 * @return array<string, mixed> Configuration.
 */
function wwo_add_google_analytics_forwarded_events( $configuration ): array {
	$configuration = (array) $configuration;

	$configuration['forward'][] = 'dataLayer.push';
	return $configuration;
}

/**
 * Adds a script to be offloaded to a worker.
 *
 * @param string $script_handle Script handle.
 */
function wwo_mark_script_for_offloading( string $script_handle ): void {
	add_filter(
		'print_scripts_array',
		static function ( $script_handles ) use ( $script_handle ) {
			if ( in_array( $script_handle, (array) $script_handles, true ) ) {
				wp_script_add_data( $script_handle, 'worker', true );
			}
			return $script_handles;
		}
	);
}

/**
 * Loads third party plugin integrations for active plugins.
 *
 * @since 0.1.0
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
