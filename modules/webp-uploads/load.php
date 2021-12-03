<?php
/**
 * WebP-Uploads Module
 *
 * @package performance-lab
 * @since 1.0.0
 *
 * Module Name: WebP uploads.
 * Description: Use WebP as the default sub sized image format when uploading images.
 * Focus: images
 * Experimental: No
 */

// Only enable if the server supports WebP.
if ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
	add_filter( 'image_editor_output_format', 'webp_default_filter_image_editor_output_format', 10, 3 );
}

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
function webp_default_filter_image_editor_output_format( $output_format, $filename, $mime_type ) {

	// WebP lossless support is still limited on servers, so only apply to JPEGs.
	if ( 'image/jpeg' !== $mime_type ) {
		return $output_format;
	}

	$output_format['image/jpeg'] = 'image/webp';

	return $output_format;
}

