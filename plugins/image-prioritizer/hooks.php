<?php
/**
 * Hook callbacks used for Image Prioritizer.
 *
 * @package image-prioritizer
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_head', 'ip_render_generator_meta_tag' );

add_action( 'od_register_tag_visitors', 'ip_register_tag_visitor', 10, 3 );
