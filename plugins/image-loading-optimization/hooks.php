<?php
/**
 * Hook callbacks used for Image Loading Optimization.
 *
 * @package image-loading-optimization
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
 *          $template = apply_filters( 'template_include', $template );
 *     +    ob_start( 'wp_template_output_buffer_callback' );
 *          if ( $template ) {
 *              include $template;
 *          } elseif ( current_user_can( 'switch_themes' ) ) {
 *
 * @since n.e.x.t
 * @access private
 * @link https://core.trac.wordpress.org/ticket/43258
 *
 * @param string $passthrough Optional. Filter value. Default null.
 * @return string Unmodified value of $passthrough.
 */
function ilo_buffer_output( string $passthrough ): string {
	ob_start(
		static function ( string $output ): string {
			/**
			 * Filters the template output buffer prior to sending to the client.
			 *
			 * @since n.e.x.t
			 *
			 * @param string $output Output buffer.
			 * @return string Filtered output buffer.
			 */
			return (string) apply_filters( 'ilo_template_output_buffer', $output );
		}
	);
	return $passthrough;
}
add_filter( 'template_include', 'ilo_buffer_output', PHP_INT_MAX );
