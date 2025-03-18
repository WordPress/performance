<?php
/**
 * Hook callbacks used for Modern Image Formats.
 *
 * @package webp-uploads
 *
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once plugin_dir_path( __FILE__ ) . 'helper.php';

add_filter( 'wp_generate_attachment_metadata', 'webp_uploads_create_sources_property', 10, 2 );
add_filter( 'wp_get_missing_image_subsizes', 'webp_uploads_wp_get_missing_image_subsizes', 10, 3 );
add_filter( 'image_editor_output_format', 'webp_uploads_filter_image_editor_output_format', 10, 3 );
add_action( 'delete_attachment', 'webp_uploads_remove_sources_files', 10, 1 );
add_filter( 'post_thumbnail_html', 'webp_uploads_update_featured_image', 10, 3 );
add_filter( 'wp_editor_set_quality', 'webp_uploads_modify_webp_quality', 10, 2 );
add_action( 'wp_head', 'webp_uploads_render_generator' );
add_action( 'init', 'webp_uploads_init' );
add_action( 'plugins_loaded', 'webp_uploads_opt_in_extra_image_sizes' );
add_filter( 'webp_uploads_image_sizes_with_additional_mime_type_support', 'webp_uploads_enable_additional_mime_type_support_for_all_sizes' );
// @codeCoverageIgnoreEnd
