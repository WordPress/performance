<?php
/**
 * Detection for image loading optimization.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Prints the script for detecting loaded images and the LCP element.
 *
 * @since n.e.x.t
 * @access private
 */
function ilo_print_detection_script() {
	if ( ! ilo_can_optimize_response() ) {
		return;
	}

	$query_vars = ilo_get_normalized_query_vars();
	$slug       = ilo_get_url_metrics_slug( $query_vars );
	$microtime  = microtime( true );

	// Abort if we already have all the sample size we need for all breakpoints.
	$needed_minimum_viewport_widths = ilo_get_needed_minimum_viewport_widths_now_for_slug( $slug );
	if ( ! ilo_needs_url_metric_for_breakpoint( $needed_minimum_viewport_widths ) ) {
		return;
	}

	/**
	 * Filters the time window between serve time and run time in which loading detection is allowed to run.
	 *
	 * Allow this amount of milliseconds between when the page was first generated (and perhaps cached) and when the
	 * detect function on the page is allowed to perform its detection logic and submit the request to store the results.
	 * This avoids situations in which there is missing detection metrics in which case a site with page caching which
	 * also has a lot of traffic could result in a cache stampede.
	 *
	 * @since n.e.x.t
	 * @todo The value should probably be something like the 99th percentile of Time To Last Byte (TTLB) for WordPress sites in CrUX.
	 *
	 * @param int $detection_time_window Detection time window in milliseconds.
	 */
	$detection_time_window = apply_filters( 'perflab_image_loading_detection_time_window', 5000 );

	$detect_args = array(
		'serveTime'                   => $microtime * 1000, // In milliseconds for comparison with `Date.now()` in JavaScript.
		'detectionTimeWindow'         => $detection_time_window,
		'isDebug'                     => WP_DEBUG,
		'restApiEndpoint'             => rest_url( ILO_REST_API_NAMESPACE . ILO_URL_METRICS_ROUTE ),
		'restApiNonce'                => wp_create_nonce( 'wp_rest' ),
		'urlMetricsSlug'              => $slug,
		'urlMetricsNonce'             => ilo_get_url_metrics_storage_nonce( $slug ),
		'neededMinimumViewportWidths' => $needed_minimum_viewport_widths,
		'storageLockTTL'              => ilo_get_url_metric_storage_lock_ttl(),
	);
	wp_print_inline_script_tag(
		sprintf(
			'import detect from %s; detect( %s );',
			wp_json_encode( add_query_arg( 'ver', IMAGE_LOADING_OPTIMIZATION_VERSION, plugin_dir_url( __FILE__ ) . 'detection/detect.js' ) ),
			wp_json_encode( $detect_args )
		),
		array( 'type' => 'module' )
	);
}
add_action( 'wp_print_footer_scripts', 'ilo_print_detection_script' );
