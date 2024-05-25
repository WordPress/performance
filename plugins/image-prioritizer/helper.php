<?php
/**
 * Helper functions for Image Prioritizer.
 *
 * @package image-prioritizer
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Displays the HTML generator meta tag for the Image Prioritizer plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since n.e.x.t
 */
function ip_render_generator_meta_tag(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="image-prioritizer ' . esc_attr( IMAGE_PRIORITIZER_VERSION ) . '">' . "\n";
}

/**
 * Register tag visitor for images.
 *
 * @since n.e.x.t
 *
 * @param OD_Tag_Visitor_Registry         $registry                     Tag visitor registry.
 * @param OD_URL_Metrics_Group_Collection $url_metrics_group_collection URL Metrics Group Collection.
 * @param OD_Preload_Link_Collection      $preload_links_collection     Preload Links Collection.
 */
function ip_register_tag_visitor( OD_Tag_Visitor_Registry $registry, OD_URL_Metrics_Group_Collection $url_metrics_group_collection, OD_Preload_Link_Collection $preload_links_collection ): void {
	// Note: The IP_Image_Tag_Visitor class is invocable (it as an __invoke() method).
	$visitor = new IP_Image_Tag_Visitor( $url_metrics_group_collection, $preload_links_collection );
	$registry->register( 'images', $visitor );
}
