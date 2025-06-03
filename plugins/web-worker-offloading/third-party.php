<?php
/**
 * Third-party integration loader for Web Worker Offloading.
 *
 * @since 0.1.0
 * @package web-worker-offloading
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Adds scripts to be offloaded to a worker.
 *
 * @since 0.1.0
 * @access private
 *
 * @param non-empty-string[] $script_handles Script handles.
 */
function plwwo_mark_scripts_for_offloading( array $script_handles ): void {
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
function plwwo_load_third_party_integrations(): void {
	$plugins_with_integrations = array(
		'google-site-kit'  => static function (): bool {
			return defined( 'GOOGLESITEKIT_VERSION' );
		},
		'seo-by-rank-math' => static function (): bool {
			return class_exists( 'RankMath' );
		},
		'woocommerce'      => static function (): bool {
			// See <https://woocommerce.com/document/query-whether-woocommerce-is-activated/>.
			return class_exists( 'WooCommerce' );
		},
	);

	foreach ( $plugins_with_integrations as $plugin_slug => $active_callback ) {
		if ( $active_callback() ) {
			require_once __DIR__ . '/third-party/' . $plugin_slug . '.php';
		}
	}
}
add_action( 'plugins_loaded', 'plwwo_load_third_party_integrations' );
