<?php
/**
 * Deprecated functions.
 *
 * @package webp-uploads
 *
 * @since 1.1.1
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

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
	if (
		'full' === $size &&
		isset( $metadata['sources'] ) &&
		is_array( $metadata['sources'] ) &&
		count( $metadata['sources'] ) > 0
	) {
		return $metadata['sources'];
	}

	// Return the resized image sources.
	if ( isset( $metadata['sizes'][ $size ]['sources'] ) && is_array( $metadata['sizes'][ $size ]['sources'] ) ) {
		return $metadata['sizes'][ $size ]['sources'];
	}

	// Return an empty array if no sources found.
	return array();
}

/**
 * Adds custom styles to hide specific elements in media settings.
 *
 * @since 1.0.0
 * @deprecated This function is not used as of Modern Image Formats versions 2.0.0.
 */
function webp_uploads_media_setting_style(): void {
	_deprecated_function( __FUNCTION__, 'Modern Image Formats 2.0.0' );

	if ( is_multisite() ) {
		return;
	}
	?>
	<style>
		.form-table .perflab-generate-webp-and-jpeg th,
		.form-table .perflab-generate-webp-and-jpeg td:not(.td-full) {
			display: none;
		}
	</style>
	<?php
}
