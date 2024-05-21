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

add_filter(
	'od_html_tag_walker_visitors',
	'ip_filter_tag_walker_visitors',
	10,
	4
);
