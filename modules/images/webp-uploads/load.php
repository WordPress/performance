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

	$output_format['image/jpeg'] = 'image/webp';

	return $output_format;
}

/**
 * Hook called by `wp_generate_attachment_metadata` to create the `sources` property for every image
 * size, the sources' property would create a new image size with all the mime types specified in
 * `webp_uploads_valid_image_mime_types`. If the original image is one of the mimes from
 * `webp_uploads_valid_image_mime_types` the image is just added to the `sources` property and  not
 * created again. If the uploaded attachment is not a valid image this function does not alter the
 * metadata of the attachment, on the other hand a `sources` property is added.
 *
 * @since n.e.x.t
 *
 * @see   wp_generate_attachment_metadata
 * @see   webp_uploads_valid_image_mime_types
 *
 * @param array $metadata      An array with the metadata from this attachment.
 * @param int   $attachment_id The ID of the attachment where the hook was dispatched.
 *
 * @return array An array with the updated structure for the metadata before is stored in the database.
 */
function webp_uploads_create_sources_property( array $metadata, $attachment_id ) {
	// This should take place only on the JPEG image.
	$valid_mime_types = webp_uploads_valid_image_mime_types();

	// Not a supported mime type to create the sources property.
	if ( ! array_key_exists( get_post_mime_type( $attachment_id ), $valid_mime_types ) ) {
		return $metadata;
	}

	// All subsizes are created out of the `file` property.
	$file = get_attached_file( $attachment_id, true );

	// File does not exist.
	if ( ! file_exists( $file ) ) {
		return $metadata;
	}

	// Prevent to convert JPEG to WebP if we are creating JPEG versions of the image.
	remove_filter( 'image_editor_output_format', 'webp_uploads_filter_image_editor_output_format' );

	$sizes = array();
	foreach ( webp_uploads_get_image_sizes() as $size => $properties ) {
		$image_sizes = array();
		if ( array_key_exists( 'sizes', $metadata ) && is_array( $metadata['sizes'] ) ) {
			$image_sizes = $metadata['sizes'];
		}

		$current_size = array();
		if ( array_key_exists( $size, $image_sizes ) && is_array( $image_sizes[ $size ] ) ) {
			$current_size = $image_sizes[ $size ];
		}

		$sources = array();
		if ( array_key_exists( 'sources', $current_size ) && is_array( $current_size['sources'] ) ) {
			$sources = $current_size['sources'];
		}

		// Try to find the mime type of the image size.
		if ( array_key_exists( 'mime-type', $current_size ) ) {
			$current_mime = $current_size['mime-type'];
		} elseif ( array_key_exists( 'file', $current_size ) ) {
			$current_mime = wp_check_filetype( $current_size['file'] )['type'];
		} else {
			$current_mime = '';
		}

		// The mime for this file couldn't be determined.
		if ( empty( $current_mime ) ) {
			continue;
		}

		// Make sure the current mime is consider a valid mime type.
		if ( ! array_key_exists( $current_mime, $valid_mime_types ) ) {
			continue;
		}

		$sources[ $current_mime ] = array(
			'file' => array_key_exists( 'file', $current_size ) ? $current_size['file'] : '',
			// TOOD: Add filesize from the original version of this image.
		);

		$formats = array_diff_assoc( $valid_mime_types, array( $current_mime => $valid_mime_types[ $current_mime ] ) );

		foreach ( $formats as $mime => $extension ) {
			// Editor needs to be recreated every time as there is not flush() or clear() function that can be used after we created an image.
			$editor = wp_get_image_editor( $file );

			if ( is_wp_error( $editor ) ) {
				continue;
			}

			$editor->resize( (int) $properties['width'], (int) $properties['height'], $properties['crop'] );
			$filename = $editor->generate_filename( null, null, $extension );
			$image    = $editor->save( $filename, $mime );

			if ( is_wp_error( $image ) ) {
				continue;
			}

			// TODO: Store the file size of the created image.
			// $image['filesize'] = filesize( $image['path'] );
			// Remove duplicated properties from the size image.
			unset( $image['path'], $image['height'], $image['width'], $image['mime-type'] );

			$sources[ $mime ] = $image;
		}

		$current_size['sources'] = $sources;
		$sizes[ $size ]          = $current_size;
	}

	$metadata['sizes'] = $sizes;

	return $metadata;
}

/**
 * List all the available image sizes as well with the properties for each, the properties included
 * are: width, height and crop. The logic behind this function tries to use the defined sizes by
 * WordPress if the values is not found on the options it would fallback to the user defined
 * sizes. Each property uses the default for each property `null` for height and width and `false`
 * for crop the same way as `make_subsize` the editor image.
 *
 * @since n.e.x.t
 *
 * @see   WP_Image_Editor_Imagick::make_subsize()
 * @see   WP_Image_Editor_GD::make_subsize()
 * @return array An array with the details of all available image sizes: width, height and crop.
 */
function webp_uploads_get_image_sizes() {
	$wp_image_sizes = wp_get_additional_image_sizes();
	$sizes          = array();

	// Create the full array with sizes and crop info.
	foreach ( get_intermediate_image_sizes() as $size ) {
		// Set the default values similar to `make_subsize`.
		$width  = null;
		$height = null;
		$crop   = false;

		if ( array_key_exists( $size, $wp_image_sizes ) ) {
			if ( array_key_exists( 'width', $wp_image_sizes[ $size ] ) ) {
				$width = (int) $wp_image_sizes[ $size ]['width'];
			}
			if ( array_key_exists( 'height', $wp_image_sizes[ $size ] ) ) {
				$height = (int) $wp_image_sizes[ $size ]['height'];
			}
			if ( array_key_exists( 'crop', $wp_image_sizes[ $size ] ) ) {
				$crop = (bool) $wp_image_sizes[ $size ]['crop'];
			}
		}

		/**
		 * Get the values from the option if is not present on the options' fallback to the
		 * defined image sizes instead due if the option is not present it means is a custom image size.
		 */
		$sizes[ $size ] = array(
			'width'  => get_option( $size . '_size_w', $width ),
			'height' => get_option( $size . '_size_h', $height ),
			'crop'   => (bool) get_option( $size . '_crop', $crop ),
		);
	}

	return $sizes;
}

/**
 * Return an array with the list of valid mime types for a sources' property.
 *
 * @since n.e.x.t
 *
 * @todo Add a filter to support more mime types.
 *
 * @return string[] An array of valid mime types, where the key is the mime type and the value is the extension type.
 */
function webp_uploads_valid_image_mime_types() {
	return array(
		'image/jpeg' => 'jpg',
		'image/webp' => 'wepb',
	);
}

add_filter( 'image_editor_output_format', 'webp_uploads_filter_image_editor_output_format', 10, 3 );
add_filter( 'wp_generate_attachment_metadata', 'webp_uploads_create_sources_property', 10, 2 );
