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
 * Only applies when the image_editor_output_formats filter returns a single mime
 * type. In that case, the mime type is used as the default output format.
 *
 * @since 1.0.0
 *
 * @param string $output_format The image editor default output format mapping.
 * @param string $filename      Path to the image.
 * @param string $mime_type     The source image mime type.
 * @return string The new output format mapping.
 */
function filter_image_editor_output_format( $output_format, $filename, $mime_type ) {


	// Only enable if WebP is the only output format.
	$output_formats = apply_filters( 'image_editor_output_formats', array( 'image/webp', 'image/jpeg' ), $attachment_id, $context );
	$default_format = $output_formats[0];

	// Only enable if the server supports the output format.
	if ( ! wp_image_editor_supports( array( 'mime_type' => $default_format ) ) ) {
		return $output_format;
	}

	// WebP lossless support is still limited on servers, so only apply to JPEGs.
	if ( 'image/webp' === $default_format && 'image/jpeg' !== $mime_type ) {
		return $output_format;
	}

	$output_format[ $mime_type ] = $default_format;

	return $output_format;
}
add_filter( 'image_editor_output_format', 'filter_image_editor_output_format', 10, 3 );

/**
 * Create additional mime types with uploaded images by hooking into the 'image_editor_output_formats' filter.
 *
 * Behavior is filterable with a 'image_editor_output_formats' filter that determines
 * the mime types to add. The filter returns an array of mime types - the first
 * mime type is used as the default sub size type and added to the image meta 'sizes'
 * array. If a large image is uploaded, the '-scaled' version of the image will always
 * use the same mime type as the original uploaded image.
 *
 * @since 1.0.0
 *
 *
 * @param array  $metadata      An array of attachment meta data.
 * @param int    $attachment_id Current attachment ID.
 * @param string $context       Additional context. Can be 'create' when metadata was initially created for new attachment
 *                              or 'update' when the metadata was updated.
 */
function filter_wp_generate_attachment_metadata( $metadata, $attachment_id, $context ) {

	// @TODO Handle update context.
	if ( 'create' !== $context ) {
		return $metadata;
	}
	/**
	 * Filter the output image formats.
	 *
	 * @since 1.0.0
	 * @param array  $output_formats The image editor output formats.
 	 * @param int    $attachment_id  Current attachment ID.
 	 * @param string $context        Additional context. Can be 'create' when metadata was initially created for new attachment
 	 *                               or 'update' when the metadata was updated.
	 */
	$output_formats = apply_filters( 'image_editor_output_formats', array( 'image/webp', 'image/jpeg' ), $attachment_id, $context );

	// When only a single mime output type is specified, the default mime type will be set
	// using the 'image_editor_output_format' filter.
	if ( 1 === sizeof( $output_formats ) ) {
		return $metadata;
	}

	// Only handle jpeg and webp mime type files.
	$upload_mime_type = get_post_mime_type( $attachment_id );
	if ( 'image/jpeg' !== $upload_mime_type && 'image/webp' !== $upload_mime_type ) {
		return $metadata;
	}
	$file = wp_get_original_image_path( $attachment_id );
	$new_sizes = array();
	// Generate the additional mime type images, skipping the first mime type.
	for( $i = 1; $i < sizeof( $output_formats ); $i++ ) {
		$mime_type = $output_formats[ $i ];
		// Generate all sub sizes in the mime type, preventing meta update.
		add_filter( 'wp_update_attachment_metadata', 'use_original_image_meta_data', 10, 2 );
		$metadata = wp_create_image_subsizes( $file, $attachment_id );
		remove_filter( 'wp_update_attachment_metadata', 'use_original_image_meta_data', 10, 2 );
		array_push( $new_sizes, $metadata );
	}
	error_log( json_encode( $new_sizes, JSON_PRETTY_PRINT));
	// Add the additional mime type images to the meta data with the 'additional_sizes' key.
	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'filter_wp_generate_attachment_metadata', 10, 3 );

function use_original_image_meta_data( $metadata, $attachment_id ) {
	$original_image_meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! empty( $original_image_meta ) ) {
		return $original_image_meta;
	}
	return $metadata;
}
