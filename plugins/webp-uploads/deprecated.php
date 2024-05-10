<?php
/**
 * Deprecated functions.
 *
 * @package webp-uploads
 *
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Returns the attachment sources array ordered by filesize.
 *
 * @since 1.0.0
 * @deprecated This function is not used anymore as of Performance Lab 1.1.0 when this was still part of the WebP Uploads module. It should have been removed as part of <https://github.com/WordPress/performance/pull/302>.
 *
 * @param int    $attachment_id The attachment ID.
 * @param string $size          The attachment size.
 * @return array<string, array{ file: string, filesize: int }> The attachment sources array.
 */
function webp_uploads_get_attachment_sources( int $attachment_id, string $size = 'thumbnail' ): array {
	_deprecated_function( __FUNCTION__, 'Performance Lab 1.1.0' );

	// Check for the sources attribute in attachment metadata.
	$metadata = wp_get_attachment_metadata( $attachment_id );

	// Return full image size sources.
	if ( 'full' === $size && ! empty( $metadata['sources'] ) ) {
		return $metadata['sources'];
	}

	// Return the resized image sources.
	if ( ! empty( $metadata['sizes'][ $size ]['sources'] ) ) {
		return $metadata['sizes'][ $size ]['sources'];
	}

	// Return an empty array if no sources found.
	return array();
}
