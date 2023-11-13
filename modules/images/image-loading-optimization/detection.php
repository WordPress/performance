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
 * @todo This should eventually only print the script if metrics are needed.
 * @todo This script should not be printed if the page was requested with non-removal (non-canonical) query args.
 */
function ilo_print_detection_script() {
	$query_vars = ilo_get_normalized_query_vars();
	$slug       = ilo_get_page_metrics_slug( $query_vars );
	$data       = ilo_get_page_metrics_data( $slug );
	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$metrics_by_breakpoint = ilo_group_page_metrics_by_breakpoint( $data, ilo_get_breakpoint_max_widths() );
	$sample_size           = ilo_get_page_metrics_breakpoint_sample_size();
	$freshness_ttl         = ilo_get_page_metric_freshness_ttl();

	// TODO: This same logic needs to be in the endpoint so that we can reject requests when not needed.
	$current_time                   = time();
	$needed_minimum_viewport_widths = array();
	foreach ( $metrics_by_breakpoint as $minimum_viewport_width => $page_metrics ) {
		$needs_page_metrics = false;
		if ( count( $page_metrics ) < $sample_size ) {
			$needs_page_metrics = true;
		} else {
			foreach ( $page_metrics as $page_metric ) {
				if ( isset( $page_metric['timestamp'] ) && $page_metric['timestamp'] + $freshness_ttl < $current_time ) {
					$needs_page_metrics = true;
					break;
				}
			}
		}
		$needed_minimum_viewport_widths[ $minimum_viewport_width ] = $needs_page_metrics;
	}

	// Abort if we already have all the sample size we need for all breakpoints.
	if ( count( array_filter( $needed_minimum_viewport_widths ) ) === 0 ) {
		return;
	}

	// Abort if storage is locked.
	if ( ilo_is_page_metric_storage_locked() ) {
		return;
	}

	$serve_time = ceil( microtime( true ) * 1000 );

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
		'serveTime'           => $serve_time,
		'detectionTimeWindow' => $detection_time_window,
		'isDebug'             => WP_DEBUG,
		'restApiEndpoint'     => rest_url( ILO_REST_API_NAMESPACE . ILO_PAGE_METRICS_ROUTE ),
		'restApiNonce'        => wp_create_nonce( 'wp_rest' ),
		'pageMetricsSlug'     => $slug,
		'pageMetricsNonce'    => ilo_get_page_metrics_storage_nonce( $slug ),
	);
	wp_print_inline_script_tag(
		sprintf(
			'import detect from %s; detect( %s );',
			wp_json_encode( add_query_arg( 'ver', PERFLAB_VERSION, plugin_dir_url( __FILE__ ) . 'detection/detect.js' ) ),
			wp_json_encode( $detect_args )
		),
		array( 'type' => 'module' )
	);
}
add_action( 'wp_print_footer_scripts', 'ilo_print_detection_script' );
