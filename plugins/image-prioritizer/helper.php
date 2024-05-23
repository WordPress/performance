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
 * Determines if the provided URL is a data: URL.
 *
 * @param string $url URL.
 * @return bool Whether data URL.
 */
function ip_is_data_url( string $url ): bool {
	return str_starts_with( strtolower( $url ), 'data:' );
}

/**
 * Filters the tag walker visitors which apply image prioritizer optimizations.
 *
 * @since 0.1.0
 *
 * @param array<string|int, callable( OD_HTML_Tag_Walker, OD_URL_Metrics_Group_Collection, OD_Preload_Link_Collection ): bool>|mixed $visitors         Visitors which are invoked for each tag in the document.
 * @param OD_HTML_Tag_Walker                                                                                                         $walker           HTML tag walker.
 * @param OD_URL_Metrics_Group_Collection                                                                                            $group_collection URL metrics group collection.
 * @param OD_Preload_Link_Collection                                                                                                 $preload_links    Preload link collection.
 * @return array<string|int, callable( OD_HTML_Tag_Walker, OD_URL_Metrics_Group_Collection, OD_Preload_Link_Collection ): bool> Visitors.
 */
function ip_filter_tag_walker_visitors( $visitors, OD_HTML_Tag_Walker $walker, OD_URL_Metrics_Group_Collection $group_collection, OD_Preload_Link_Collection $preload_links ): array {
	if ( ! is_array( $visitors ) ) {
		$visitors = array();
	}

	/**
	 * Visitors.
	 *
	 * @var array<string|int, callable( OD_HTML_Tag_Walker, OD_URL_Metrics_Group_Collection, OD_Preload_Link_Collection ): bool> $visitors
	 */

	// Note: The IP_Image_Tag_Visitor class is invocable (it as an __invoke() method).
	$visitors[] = new IP_Image_Tag_Visitor( $group_collection, $preload_links );

	return $visitors;
}
