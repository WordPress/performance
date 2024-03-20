<?php
/**
 * Hook callbacks used for Optimization Detective.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_filter( 'template_include', 'od_buffer_output', PHP_INT_MAX );
OD_URL_Metrics_Post_Type::add_hooks();
add_action( 'wp', 'od_maybe_add_template_output_buffer_filter' );
add_action( 'wp_head', 'od_render_generator_meta_tag' );
