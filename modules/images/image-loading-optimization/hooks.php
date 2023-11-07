<?php
/**
 * Hook callbacks used for Image Loading Optimization.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Starts output buffering at the end of the 'template_include' filter.
 *
 * This is to implement #43258 in core.
 *
 * This is a hack which would eventually be replaced with something like this in wp-includes/template-loader.php:
 *
 * ```
 *          $template = apply_filters( 'template_include', $template );
 *     +    ob_start( 'wp_template_output_buffer_callback' );
 *          if ( $template ) {
 *              include $template;
 *          } elseif ( current_user_can( 'switch_themes' ) ) {
 * ```
 *
 * @since n.e.x.t
 * @link https://core.trac.wordpress.org/ticket/43258
 *
 * @param mixed $passthrough Optional. Filter value. Default null.
 * @return mixed Unmodified value of $passthrough.
 */
function image_loading_optimization_buffer_output( $passthrough = null ) {
	ob_start(
		static function ( $output ) {
			/**
			 * Filters the template output buffer prior to sending to the client.
			 *
			 * @param string $output Output buffer.
			 * @return string Filtered output buffer.
			 */
			return apply_filters( 'perflab_template_output_buffer', $output );
		}
	);
	return $passthrough;
}
add_filter( 'template_include', 'image_loading_optimization_buffer_output', PHP_INT_MAX );

/**
 * Prints the script for detecting loaded images and the LCP element.
 */
function image_loading_optimization_print_detection_script() {
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

	$detect_args = array( $serve_time, $detection_time_window, WP_DEBUG );
	wp_print_inline_script_tag(
		sprintf(
			'import detect from %s; detect( ...%s )',
			wp_json_encode( add_query_arg( 'ver', PERFLAB_VERSION, plugin_dir_url( __FILE__ ) . 'detect.js' ) ),
			wp_json_encode( $detect_args )
		),
		array( 'type' => 'module' )
	);
}
add_action( 'wp_print_footer_scripts', 'image_loading_optimization_print_detection_script' );
