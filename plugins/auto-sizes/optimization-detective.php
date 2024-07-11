<?php
/**
 * Optimization Detective extensions by Auto Sizes.
 *
 * @since n.e.x.t
 * @package auto-sizes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Visits responsive lazy-loaded IMG tags to ensure they include sizes=auto.
 *
 * @since n.e.x.t
 *
 * @param OD_Tag_Visitor_Context $context Tag visitor context.
 * @return bool Whether visited.
 */
function auto_sizes_visit_tag( OD_Tag_Visitor_Context $context ): bool {
	if ( 'IMG' !== $context->processor->get_tag() ) {
		return false;
	}

	$sizes = $context->processor->get_attribute( 'sizes' );
	if ( ! is_string( $sizes ) || 'lazy' !== $context->processor->get_attribute( 'loading' ) ) {
		return false;
	}

	$sizes = preg_split( '/\s*,\s*/', $sizes );
	if ( is_array( $sizes ) && ! in_array( 'auto', $sizes, true ) ) {
		array_unshift( $sizes, 'auto' );
		$context->processor->set_attribute( 'sizes', join( ', ', $sizes ) );
	}

	return false; // Since this tag visitor does not require this tag to be included in the URL Metrics.
}

/**
 * Registers the tag visitor for image tags.
 *
 * @since n.e.x.t
 *
 * @param OD_Tag_Visitor_Registry $registry Tag visitor registry.
 */
function auto_sizes_register_tag_visitors( OD_Tag_Visitor_Registry $registry ): void {
	$registry->register( 'auto-sizes', 'auto_sizes_visit_tag' );
}

// Important: The Image Prioritizer's IMG tag visitor is registered at priority 10, so priority 100 ensures that the loading attribute has been correctly set by the time the Auto Sizes visitor runs.
add_action( 'od_register_tag_visitors', 'auto_sizes_register_tag_visitors', 100 );
