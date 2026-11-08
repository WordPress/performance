<?php
/**
 * Deprecated functions.
 *
 * @package webp-uploads
 *
 * @since 1.1.1
 */

declare( strict_types = 1 );

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

/**
 * Updates the references of the featured image to the new image format if available.
 *
 * @since 1.0.0
 * @deprecated 2.7.0 Featured images are now rewritten through the `wp_get_attachment_image`
 *                     filter; see webp_uploads_filter_wp_get_attachment_image().
 *
 * @param string $html          The current HTML markup of the featured image.
 * @param int    $post_id       The current post ID where the featured image is requested.
 * @param int    $attachment_id The ID of the attachment image.
 * @return string The updated HTML markup.
 */
function webp_uploads_update_featured_image( string $html, int $post_id, int $attachment_id ): string {
	_deprecated_function( __FUNCTION__, 'Modern Image Formats 2.7.0', 'webp_uploads_filter_wp_get_attachment_image()' );

	if ( webp_uploads_is_picture_element_enabled() ) {
		return webp_uploads_wrap_image_in_picture( $html, 'post_thumbnail_html', $attachment_id );
	}

	return webp_uploads_img_tag_update_mime_type( $html, 'post_thumbnail_html', $attachment_id );
}
