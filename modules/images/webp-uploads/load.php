<?php
/**
 * Module Name: WebP Uploads
 * Description: Uses WebP as the default format for new JPEG image uploads if the server supports it.
 * Experimental: No
 *
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Filter the image editor default output format mapping.
 *
 * For uploaded JPEG images, map the default output format to WebP.
 *
 * @since 1.0.0
 *
 * @param string $output_format The image editor default output format mapping.
 * @param string $filename      Path to the image.
 * @param string $mime_type     The source image mime type.
 * @return string The new output format mapping.
 */
function webp_uploads_filter_image_editor_output_format( $output_format, $filename, $mime_type ) {
	// Only enable if the server supports WebP.
	if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		return $output_format;
	}

	// WebP lossless support is still limited on servers, so only apply to JPEGs.
	if ( 'image/jpeg' !== $mime_type ) {
		return $output_format;
	}

	// Skip conversion when creating the `-scaled` image (for large image uploads).
	if ( preg_match( '/-scaled\..{3}.?$/', $filename ) ) {
		return $output_format;
	}

	$output_format['image/jpeg'] = 'image/webp';

	return $output_format;
}
add_filter( 'image_editor_output_format', 'webp_uploads_filter_image_editor_output_format', 10, 3 );

function webp_uploads_update_images_on_page( $dom, $xpath ) {
	$images = $xpath->query( '//img[contains(@class, "wp-image-")]' );
	if ( empty( $images ) ) {
		return;
	}

	foreach ( $images as $image ) {
		$src       = $image->getAttribute( 'src' );
		$src_parts = pathinfo( $src );

		$webp_src = sprintf(
			'%s/%s.webp',
			$src_parts['dirname'],
			$src_parts['filename']
		);

		$image->setAttribute( 'src', $webp_src );
		$image->setAttribute( 'onerror', "this.onerror=null;this.src='{$src}';" );
	}
}
add_action( 'perflab_page_content', 'webp_uploads_update_images_on_page', 10, 2 );
