<?php
/**
 * Hook callbacks used for Image Prioritizer.
 *
 * @package image-prioritizer
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_head', 'image_prioritizer_render_generator_meta_tag' );

add_action( 'od_register_tag_visitors', 'image_prioritizer_register_tag_visitors' );
