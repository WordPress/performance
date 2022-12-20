<?php
/**
 * Object cache drop-in from Performance Lab plugin.
 *
 * This drop-in is used, admittedly as a hack, to be able to measure server
 * timings in WordPress as early as possible. Once a plugin is loaded, it is
 * too late to capture several critical events.
 *
 * This file respects any real object cache implementation the site may already
 * be using, and it is implemented in a way that there is no risk for breakage.
 *
 * If you do not want the Performance Lab plugin to place this file and thus be
 * limited to server timings only from after plugins are loaded, you can remove
 * this file and set the following constant (e.g. in wp-config.php):
 *
 *     define( 'PERFLAB_DISABLE_OBJECT_CACHE_DROPIN', true );
 *
 * @package performance-lab
 * @since 1.8.0
 */

// Set constant to be able to later check for whether this file was loaded.
define( 'PERFLAB_OBJECT_CACHE_DROPIN_VERSION', 1 );

/**
 * Loads the Performance Lab Server-Timing API if available.
 *
 * This function will short-circuit if the constant
 * 'PERFLAB_DISABLE_OBJECT_CACHE_DROPIN' is set as true.
 *
 * @since 1.8.0
 */
function perflab_load_server_timing_api_from_dropin() {
	if ( defined( 'PERFLAB_DISABLE_OBJECT_CACHE_DROPIN' ) && PERFLAB_DISABLE_OBJECT_CACHE_DROPIN ) {
		return;
	}

	$plugins_dir = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins';
	$plugin_dir  = $plugins_dir . '/performance-lab/';
	if ( ! file_exists( $plugin_dir . 'server-timing/load.php' ) ) {
		$plugin_dir = $plugins_dir . '/performance/';
		if ( ! file_exists( $plugin_dir . 'server-timing/load.php' ) ) {
			return;
		}
	}

	require_once $plugin_dir . 'server-timing/class-perflab-server-timing-metric.php';
	require_once $plugin_dir . 'server-timing/class-perflab-server-timing.php';
	require_once $plugin_dir . 'server-timing/load.php';
	require_once $plugin_dir . 'server-timing/defaults.php';
}
perflab_load_server_timing_api_from_dropin();

// Load the original object cache drop-in if present.
if ( file_exists( WP_CONTENT_DIR . '/object-cache-plst-orig.php' ) ) {
	require_once WP_CONTENT_DIR . '/object-cache-plst-orig.php';
}
