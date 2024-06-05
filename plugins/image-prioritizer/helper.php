<?php
/**
 * Helper functions for Image Prioritizer.
 *
 * @package image-prioritizer
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Displays the HTML generator meta tag for the Image Prioritizer plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 0.1.0
 */
function image_prioritizer_render_generator_meta_tag(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="image-prioritizer ' . esc_attr( IMAGE_PRIORITIZER_VERSION ) . '">' . "\n";
}

/**
 * Registers tag visitors for images.
 *
 * @since 0.1.0
 *
 * @param OD_Tag_Visitor_Registry         $registry                     Tag visitor registry.
 * @param OD_URL_Metrics_Group_Collection $url_metrics_group_collection URL Metrics Group Collection.
 * @param OD_Preload_Link_Collection      $preload_links_collection     Preload Links Collection.
 */
function image_prioritizer_register_tag_visitors( OD_Tag_Visitor_Registry $registry, OD_URL_Metrics_Group_Collection $url_metrics_group_collection, OD_Preload_Link_Collection $preload_links_collection ): void {
	// Note: The class is invocable (it has an __invoke() method).
	$img_visitor = new Image_Prioritizer_Img_Tag_Visitor( $url_metrics_group_collection, $preload_links_collection );
	$registry->register( 'img-tags', $img_visitor );

	$bg_image_visitor = new Image_Prioritizer_Background_Image_Styled_Tag_Visitor( $url_metrics_group_collection, $preload_links_collection );
	$registry->register( 'bg-image-tags', $bg_image_visitor );
}
