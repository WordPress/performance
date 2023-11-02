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
