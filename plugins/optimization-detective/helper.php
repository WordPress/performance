<?php
/**
 * Helper functions for Optimization Detective.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Generates a media query for the provided minimum and maximum viewport widths.
 *
 * @since n.e.x.t
 *
 * @param int|null $minimum_viewport_width Minimum viewport width.
 * @param int|null $maximum_viewport_width Maximum viewport width.
 * @return non-empty-string|null Media query, or null if the min/max were both unspecified or invalid.
 */
function od_generate_media_query( ?int $minimum_viewport_width, ?int $maximum_viewport_width ): ?string {
	if ( is_int( $minimum_viewport_width ) && is_int( $maximum_viewport_width ) && $minimum_viewport_width > $maximum_viewport_width ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'The minimum width must be greater than the maximum width.', 'optimization-detective' ), 'Optimization Detective n.e.x.t' );
		return null;
	}
	$media_attributes = array();
	if ( null !== $minimum_viewport_width && $minimum_viewport_width > 0 ) {
		$media_attributes[] = sprintf( '(min-width: %dpx)', $minimum_viewport_width );
	}
	if ( null !== $maximum_viewport_width && PHP_INT_MAX !== $maximum_viewport_width ) {
		$media_attributes[] = sprintf( '(max-width: %dpx)', $maximum_viewport_width );
	}
	if ( count( $media_attributes ) === 0 ) {
		return null;
	}
	return join( ' and ', $media_attributes );
}

/**
 * Displays the HTML generator meta tag for the Optimization Detective plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 0.1.0
 */
function od_render_generator_meta_tag(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="optimization-detective ' . esc_attr( OPTIMIZATION_DETECTIVE_VERSION ) . '">' . "\n";
}
