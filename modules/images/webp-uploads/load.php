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

	// Get the default format.
	$output_formats = apply_filters( 'image_editor_output_formats', array( 'image/webp', 'image/jpeg' ), $filename );
	$default_format = $output_formats[0];

	// If the file mime and output mime are the same, return the default format.
	if ( $mime_type === $default_format ) {
		return $output_format;
	}

	// Only enable if the server supports the output format.
	if ( ! wp_image_editor_supports( array( 'mime_type' => $default_format ) ) ) {
		return $output_format;
	}

	// WebP lossless support is still limited on servers, so only apply to JPEGs.
	if ( 'image/webp' === $default_format && 'image/jpeg' !== $mime_type ) {
		return $output_format;
	}

	// Skip conversion when creating the `-scaled` image (for large image uploads).
	if ( preg_match( '/-scaled\..{3}.?$/', $filename ) ) {
		return $output_format;
	}

	$output_format = array( $mime_type => $default_format );

	return $output_format;
}
add_filter( 'image_editor_output_format', 'filter_image_editor_output_format', 9, 3 );

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
	$file = wp_get_original_image_path( $attachment_id );

	/**
	 * Filter the output image formats.
	 *
	 * @since 1.0.0
	 * @param array  $output_formats The image editor output formats.
	 * @param string $file           Path to the image.
	 */
	$output_formats = apply_filters( 'image_editor_output_formats', array( 'image/webp', 'image/jpeg' ), $file );

	// Create the full sized image in the new format.
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		return $metadata;
	}
	$upload_mime_type = get_post_mime_type( $attachment_id );
	$new_sizes        = array();

	// When only a single mime output type is specified, the default mime type will be set
	// using the 'image_editor_output_format' filter.
	if ( 1 === sizeof( $output_formats ) ) {
		// If the output mime type doesn't match the source mime type, convert the
		// full size image to the new mime type.
		if ( $upload_mime_type !== $output_formats[0] ) {
			$extension    = wp_get_default_extension_for_mime_type( $output_formats[0] );
			$new_metadata = $editor->save( $editor->generate_filename( $extension ) );
			array_push( $new_sizes, $new_metadata );
		}
	} else {

		// Only handle jpeg and webp mime type files.
		if ( 'image/jpeg' !== $upload_mime_type && 'image/webp' !== $upload_mime_type ) {
			return $metadata;
		}

		// Generate the additional mime type images, skipping the first mime type
		// which has already been created at this point.
		for ( $i = 1; $i < sizeof( $output_formats ); $i++ ) {
			$mime_type = $output_formats[ $i ];
			// If the output mime type doesn't match the source mime type, convert the
			// full size image to the new mime type.
			// Generate all sub sizes in the mime type, preventing meta update.
			$output_use_mime = function() use ( $upload_mime_type, $mime_type ) {
				return array( $upload_mime_type => $mime_type );
			};

			// If the mime type doesn't match the original, create a full sized image.
			if ( $upload_mime_type !== $mime_type ) {
				// Construct the filename with the format image-{mime-extension}.{mime-extension}.
				$extension = wp_get_default_extension_for_mime_type( $mime_type );
				$filename  = $editor->generate_filename( $extension );

				// Output files with the current mime type.
				add_filter( 'image_editor_output_format', $output_use_mime );
				$new_metadata = $editor->save( $filename );
				remove_filter( 'image_editor_output_format', $output_use_mime );
				array_push( $new_sizes, $new_metadata );
			}

			// Output files with the current mime type.
			add_filter( 'image_editor_output_format', $output_use_mime );

			// Don't update meta when creating these images, we will update it later.
			add_filter( 'wp_update_attachment_metadata', 'use_original_image_meta_data', 10, 2 );
			$new_metadata = wp_create_image_subsizes( $file, $attachment_id );

			// Remove the filters to set output mime type and prevent meta update.
			remove_filter( 'image_editor_output_format', $output_use_mime );
			remove_filter( 'wp_update_attachment_metadata', 'use_original_image_meta_data', 10, 2 );

			array_push( $new_sizes, $new_metadata );
		}
	}

	// Add the additional mime type images to the meta data with the 'sizes->variations' key.
	if ( ! isset( $metadata['sizes']['variations'] ) ) {
		$metadata['sizes']['variations'] = array();
	}
	$metadata['sizes']['variations'] = array_merge( $metadata['sizes']['variations'], $new_sizes );

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'filter_wp_generate_attachment_metadata', 10, 3 );

/**
 * Shim to prevent core from updating the image meta data when we
 * create additional mime type images with `wp_create_image_subsizes`.
 *
 * @param array $metadata      Array of updated attachment meta data.
 * @param int   $attachment_id Attachment ID.
 * @return array $metadata Array of meta data.
 */
function use_original_image_meta_data( $metadata, $attachment_id ) {
	$original_image_meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! empty( $original_image_meta ) ) {
		return $original_image_meta;
	}
	return $metadata;
}
