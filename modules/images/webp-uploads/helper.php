<?php
/**
 * Helper functions used by module.
 *
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Returns an array with the list of valid mime types that a specific mime type can be converted into it,
 * for example an image/jpeg can be converted into an image/webp.
 *
 * @since 1.0.0
 *
 * @return array<string, array<string>> An array of valid mime types, where the key is the mime type and the value is the extension type.
 */
function webp_uploads_get_upload_image_mime_transforms() {
	$default_transforms = array(
		'image/jpeg' => array( 'image/webp' ),
		'image/webp' => array( 'image/webp' ),
	);

	// Check setting for whether to generate both JPEG and WebP.
	if ( true === (bool) get_option( 'perflab_generate_webp_and_jpeg' ) ) {
		$default_transforms = array(
			'image/jpeg' => array( 'image/jpeg', 'image/webp' ),
			'image/webp' => array( 'image/webp', 'image/jpeg' ),
		);
	}

	/**
	 * Filter to allow the definition of a custom mime types, in which a defined mime type
	 * can be transformed and provide a wide range of mime types.
	 *
	 * The order of supported mime types matters. If the original mime type of the uploaded image
	 * is not needed, then the first mime type in the list supported by the image editor will be
	 * selected for the default subsizes.
	 *
	 * @since 1.0.0
	 *
	 * @param array $default_transforms A map with the valid mime transforms.
	 */
	$transforms = apply_filters( 'webp_uploads_upload_image_mime_transforms', $default_transforms );

	// Return the default mime transforms if a non-array result is returned from the filter.
	if ( ! is_array( $transforms ) ) {
		return $default_transforms;
	}

	// Ensure that all mime types have correct transforms. If a mime type has invalid transforms array,
	// then fallback to the original mime type to make sure that the correct subsizes are created.
	foreach ( $transforms as $mime_type => $transform_types ) {
		if ( ! is_array( $transform_types ) || empty( $transform_types ) ) {
			$transforms[ $mime_type ] = array( $mime_type );
		}
	}

	return $transforms;
}

/**
 * Creates a resized image with the provided dimensions out of an original attachment, the created image
 * would be saved in the specified mime and stored in the destination file. If the image can't be saved correctly
 * a WP_Error would be returned otherwise an array with the file and filesize properties.
 *
 * @since 1.0.0
 * @access private
 *
 * @param int         $attachment_id         The ID of the attachment from where this image would be created.
 * @param string      $image_size            The size name that would be used to create the image source, out of the registered subsizes.
 * @param array       $size_data             An array with the dimensions of the image: height, width and crop.
 * @param string      $mime                  The target mime in which the image should be created.
 * @param string|null $destination_file_name The path where the file would be stored, including the extension. If null, `generate_filename` is used to create the destination file name.
 *
 * @return array|WP_Error An array with the file and filesize if the image was created correctly, otherwise a WP_Error.
 */
function webp_uploads_generate_additional_image_source( $attachment_id, $image_size, array $size_data, $mime, $destination_file_name = null ) {
	/**
	 * Filter to allow the generation of additional image sources, in which a defined mime type
	 * can be transformed and create additional mime types for the file.
	 *
	 * Returning an image data array or WP_Error here effectively short-circuits the default logic to generate the image source.
	 *
	 * @since 1.1.0
	 *
	 * @param array|null|WP_Error $image         Image data {'path'=>string, 'file'=>string, 'width'=>int, 'height'=>int, 'mime-type'=>string} or null or WP_Error.
	 * @param int                 $attachment_id The ID of the attachment from where this image would be created.
	 * @param string              $image_size    The size name that would be used to create this image, out of the registered subsizes.
	 * @param array               $size_data     An array with the dimensions of the image: height, width and crop {'height'=>int, 'width'=>int, 'crop'}.
	 * @param string              $mime          The target mime in which the image should be created.
	 */
	$image = apply_filters( 'webp_uploads_pre_generate_additional_image_source', null, $attachment_id, $image_size, $size_data, $mime );
	if ( is_wp_error( $image ) ) {
		return $image;
	}

	if (
		is_array( $image ) &&
		! empty( $image['file'] ) &&
		(
			! empty( $image['path'] ) ||
			array_key_exists( 'filesize', $image )
		)
	) {
		return array(
			'file'     => $image['file'],
			'filesize' => array_key_exists( 'filesize', $image )
				? $image['filesize']
				: wp_filesize( $image['path'] ),
		);
	}

	$allowed_mimes = array_flip( wp_get_mime_types() );
	if ( ! isset( $allowed_mimes[ $mime ] ) || ! is_string( $allowed_mimes[ $mime ] ) ) {
		return new WP_Error( 'image_mime_type_invalid', __( 'The provided mime type is not allowed.', 'performance-lab' ) );
	}

	if ( ! wp_image_editor_supports( array( 'mime_type' => $mime ) ) ) {
		return new WP_Error( 'image_mime_type_not_supported', __( 'The provided mime type is not supported.', 'performance-lab' ) );
	}

	$image_path = wp_get_original_image_path( $attachment_id );
	if ( ! file_exists( $image_path ) ) {
		return new WP_Error( 'original_image_file_not_found', __( 'The original image file does not exists, subsizes are created out of the original image.', 'performance-lab' ) );
	}

	$editor = wp_get_image_editor( $image_path, array( 'mime_type' => $mime ) );
	if ( is_wp_error( $editor ) ) {
		return $editor;
	}

	$height = isset( $size_data['height'] ) ? (int) $size_data['height'] : 0;
	$width  = isset( $size_data['width'] ) ? (int) $size_data['width'] : 0;
	$crop   = isset( $size_data['crop'] ) && $size_data['crop'];
	if ( $width <= 0 && $height <= 0 ) {
		return new WP_Error( 'image_wrong_dimensions', __( 'At least one of the dimensions must be a positive number.', 'performance-lab' ) );
	}

	$image_meta = wp_get_attachment_metadata( $attachment_id );
	// If stored EXIF data exists, rotate the source image before creating sub-sizes.
	if ( ! empty( $image_meta['image_meta'] ) ) {
		$editor->maybe_exif_rotate();
	}

	$editor->resize( $width, $height, $crop );

	if ( null === $destination_file_name ) {
		$ext                   = pathinfo( $image_path, PATHINFO_EXTENSION );
		$suffix                = $editor->get_suffix();
		$suffix               .= "-{$ext}";
		$extension             = explode( '|', $allowed_mimes[ $mime ] );
		$destination_file_name = $editor->generate_filename( $suffix, null, $extension[0] );
	}

	remove_filter( 'image_editor_output_format', 'webp_uploads_filter_image_editor_output_format', 10, 3 );
	$image = $editor->save( $destination_file_name, $mime );
	add_filter( 'image_editor_output_format', 'webp_uploads_filter_image_editor_output_format', 10, 3 );

	if ( is_wp_error( $image ) ) {
		return $image;
	}

	if ( empty( $image['file'] ) ) {
		return new WP_Error( 'image_file_not_present', __( 'The file key is not present on the image data', 'performance-lab' ) );
	}

	return array(
		'file'     => $image['file'],
		'filesize' => isset( $image['path'] ) ? wp_filesize( $image['path'] ) : 0,
	);
}

/**
 * Creates a new image based of the specified attachment with a defined mime type
 * this image would be stored in the same place as the provided size name inside the
 * metadata of the attachment.
 *
 * @since 1.0.0
 *
 * @see wp_create_image_subsizes()
 *
 * @param int    $attachment_id The ID of the attachment we are going to use as a reference to create the image.
 * @param string $size          The size name that would be used to create this image, out of the registered subsizes.
 * @param string $mime          A mime type we are looking to use to create this image.
 *
 * @return array|WP_Error
 */
function webp_uploads_generate_image_size( $attachment_id, $size, $mime ) {
	$sizes    = wp_get_registered_image_subsizes();
	$metadata = wp_get_attachment_metadata( $attachment_id );

	if (
		! isset( $metadata['sizes'][ $size ], $sizes[ $size ] )
		|| ! is_array( $metadata['sizes'][ $size ] )
		|| ! is_array( $sizes[ $size ] )
	) {
		return new WP_Error( 'image_mime_type_invalid_metadata', __( 'The image does not have a valid metadata.', 'performance-lab' ) );
	}

	$size_data = array(
		'width'  => 0,
		'height' => 0,
		'crop'   => false,
	);

	if ( isset( $sizes[ $size ]['width'] ) ) {
		$size_data['width'] = $sizes[ $size ]['width'];
	} elseif ( isset( $metadata['sizes'][ $size ]['width'] ) ) {
		$size_data['width'] = $metadata['sizes'][ $size ]['width'];
	}

	if ( isset( $sizes[ $size ]['height'] ) ) {
		$size_data['height'] = $sizes[ $size ]['height'];
	} elseif ( isset( $metadata['sizes'][ $size ]['height'] ) ) {
		$size_data['height'] = $metadata['sizes'][ $size ]['height'];
	}

	if ( isset( $sizes[ $size ]['crop'] ) ) {
		$size_data['crop'] = (bool) $sizes[ $size ]['crop'];
	}

	return webp_uploads_generate_additional_image_source( $attachment_id, $size, $size_data, $mime );
}

/**
 * Returns the attachment sources array ordered by filesize.
 *
 * @since 1.0.0
 *
 * @param int    $attachment_id The attachment ID.
 * @param string $size          The attachment size.
 * @return array The attachment sources array.
 */
function webp_uploads_get_attachment_sources( $attachment_id, $size = 'thumbnail' ) {
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

/**
 * Returns mime types that should be used for an image in the specific context.
 *
 * @since 1.4.0
 *
 * @param int    $attachment_id The attachment ID.
 * @param string $context       The current context.
 * @return array Mime types to use for the image.
 */
function webp_uploads_get_content_image_mimes( $attachment_id, $context ) {
	$target_mimes = array( 'image/webp', 'image/jpeg' );

	/**
	 * Filters mime types that should be used to update all images in the content. The order of
	 * mime types matters. The first mime type in the list will be used if it is supported by an image.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $target_mimes  The list of mime types that can be used to update images in the content.
	 * @param int    $attachment_id The attachment ID.
	 * @param string $context       The current context.
	 */
	$target_mimes = apply_filters( 'webp_uploads_content_image_mimes', $target_mimes, $attachment_id, $context );
	if ( ! is_array( $target_mimes ) ) {
		$target_mimes = array();
	}

	return $target_mimes;
}

/**
 * Verifies if the request is for a frontend context within the <body> tag.
 *
 * @since 1.3.0
 *
 * @return bool True if in the <body> within a frontend request, false otherwise.
 */
function webp_uploads_in_frontend_body() {
	global $wp_query;

	// Check if this request is generally outside (or before) any frontend context.
	if ( ! isset( $wp_query ) || defined( 'REST_REQUEST' ) || defined( 'XMLRPC_REQUEST' ) || is_feed() ) {
		return false;
	}

	// Check if we're anywhere before 'template_redirect' or within the 'wp_head' action.
	if ( ! did_action( 'template_redirect' ) || doing_action( 'wp_head' ) ) {
		return false;
	}

	return true;
}

/**
 * Check whether the additional image is larger than the original image.
 *
 * @since 1.3.0
 *
 * @param array $original   An array with the metadata of the attachment.
 * @param array $additional An array containing the filename and file size for additional mime.
 * @return bool True if the additional image is larger than the original image, otherwise false.
 */
function webp_uploads_should_discard_additional_image_file( array $original, array $additional ) {
	$original_image_filesize   = isset( $original['filesize'] ) ? (int) $original['filesize'] : 0;
	$additional_image_filesize = isset( $additional['filesize'] ) ? (int) $additional['filesize'] : 0;
	if ( $original_image_filesize > 0 && $additional_image_filesize > 0 ) {
		/**
		 * Filter whether WebP images that are larger than the matching JPEG should be discarded.
		 *
		 * By default the performance lab plugin will use the mime type with the smaller filesize
		 * rather than defaulting to `webp`.
		 *
		 * @since 1.3.0
		 *
		 * @param bool $preferred_filesize Prioritize file size over mime type. Default true.
		 */
		$webp_discard_larger_images = apply_filters( 'webp_uploads_discard_larger_generated_images', true );

		if ( $webp_discard_larger_images && $additional_image_filesize >= $original_image_filesize ) {
			return true;
		}
	}
	return false;
}
