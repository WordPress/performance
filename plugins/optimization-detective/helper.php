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
 * Displays the HTML generator meta tag for the Optimization Detective plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 0.1.0
 */
function od_render_generator_meta_tag() {
	echo '<meta name="generator" content="Optimization Detective ' . esc_attr( OPTIMIZATION_DETECTIVE_VERSION ) . '">' . "\n";
}
