<?php
/**
 * Hook callbacks used for Enhanced Responsive Images.
 *
 * @package auto-sizes
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Displays the HTML generator tag for the plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 1.0.1
 */
function auto_sizes_render_generator(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="auto-sizes ' . esc_attr( IMAGE_AUTO_SIZES_VERSION ) . '">' . "\n";
}
add_action( 'wp_head', 'auto_sizes_render_generator' );

/**
 * Filters related to the auto-sizes functionality.
 */
// Skip loading plugin filters if WordPress Core already loaded the functionality.
if ( ! function_exists( 'wp_img_tag_add_auto_sizes' ) ) {
	add_filter( 'wp_get_attachment_image_attributes', 'auto_sizes_update_image_attributes' );
	add_filter( 'wp_content_img_tag', 'auto_sizes_update_content_img_tag' );
}

/**
 * Filters related to the improved image sizes functionality.
 */
add_filter( 'the_content', 'auto_sizes_prime_attachment_caches', 9 ); // This must run before 'do_blocks', which runs at priority 9.
add_filter( 'render_block_core/image', 'auto_sizes_filter_image_tag', 10, 3 );
add_filter( 'render_block_core/cover', 'auto_sizes_filter_image_tag', 10, 3 );
add_filter( 'get_block_type_uses_context', 'auto_sizes_filter_uses_context', 10, 2 );
add_filter( 'render_block_context', 'auto_sizes_filter_render_block_context', 10, 2 );
