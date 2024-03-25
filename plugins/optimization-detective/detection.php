<?php
/**
 * Detection for Optimization Detective.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Prints the script for detecting loaded images and the LCP element.
 *
 * @since 0.1.0
 * @access private
 *
 * @param string                          $slug             URL metrics slug.
 * @param OD_URL_Metrics_Group_Collection $group_collection URL metrics group collection.
 */
function od_get_detection_script( string $slug, OD_URL_Metrics_Group_Collection $group_collection ): string {
	/**
	 * Filters the time window between serve time and run time in which loading detection is allowed to run.
	 *
	 * This is the allowance of milliseconds between when the page was first generated (and perhaps cached) and when the
	 * detect function on the page is allowed to perform its detection logic and submit the request to store the results.
	 * This avoids situations in which there is missing URL Metrics in which case a site with page caching which
	 * also has a lot of traffic could result in a cache stampede.
	 *
	 * @since 0.1.0
	 * @todo The value should probably be something like the 99th percentile of Time To Last Byte (TTLB) for WordPress sites in CrUX.
	 *
	 * @param int $detection_time_window Detection time window in milliseconds.
	 */
	$detection_time_window = apply_filters( 'od_detection_time_window', 5000 );

	$web_vitals_lib_data = require __DIR__ . '/build/web-vitals.asset.php';
	$web_vitals_lib_src  = add_query_arg( 'ver', $web_vitals_lib_data['version'], plugin_dir_url( __FILE__ ) . '/build/web-vitals.js' );

	$current_url = od_get_current_url();
	$detect_args = array(
		'serveTime'               => microtime( true ) * 1000, // In milliseconds for comparison with `Date.now()` in JavaScript.
		'detectionTimeWindow'     => $detection_time_window,
		'isDebug'                 => WP_DEBUG,
		'restApiEndpoint'         => rest_url( OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE ),
		'restApiNonce'            => wp_create_nonce( 'wp_rest' ),
		'currentUrl'              => $current_url,
		'urlMetricsSlug'          => $slug,
		'urlMetricsNonce'         => od_get_url_metrics_storage_nonce( $slug, $current_url ),
		'urlMetricsGroupStatuses' => array_map(
			static function ( OD_URL_Metrics_Group $group ): array {
				return array(
					'minimumViewportWidth' => $group->get_minimum_viewport_width(),
					'complete'             => $group->is_complete(),
				);
			},
			iterator_to_array( $group_collection )
		),
		'storageLockTTL'          => OD_Storage_Lock::get_ttl(),
		'webVitalsLibrarySrc'     => $web_vitals_lib_src,
	);

	return wp_get_inline_script_tag(
		sprintf(
			'import detect from %s; detect( %s );',
			wp_json_encode( add_query_arg( 'ver', OPTIMIZATION_DETECTIVE_VERSION, plugin_dir_url( __FILE__ ) . 'detect.js' ) ),
			wp_json_encode( $detect_args )
		),
		array( 'type' => 'module' )
	);
}
