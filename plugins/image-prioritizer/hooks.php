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

add_action( 'od_init', 'image_prioritizer_init' );
add_filter( 'od_extension_module_urls', 'image_prioritizer_filter_extension_module_urls' );
add_filter( 'od_url_metric_schema_root_additional_properties', 'image_prioritizer_add_element_item_schema_properties' );
add_filter( 'rest_request_before_callbacks', 'image_prioritizer_filter_rest_request_before_callbacks', 10, 3 );
