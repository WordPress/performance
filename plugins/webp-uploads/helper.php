<?php
/**
 * Helper functions used for Modern Image Formats.
 *
 * @package webp-uploads
 *
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Returns an array with the list of valid mime types that a specific mime type can be converted into it,
 * for example an image/jpeg can be converted into an image/webp.
 *
 * @since 1.0.0
 * @since 2.0.0 Added support for AVIF.
 * @since 2.2.0 Added support for PNG.
 *
 * @return array<string, array<string>> An array of valid mime types, where the key is the mime type and the value is the extension type.
 */
function webp_uploads_get_upload_image_mime_transforms(): array {

	// Check the selected output format.
	$output_format = webp_uploads_mime_type_supported( 'image/avif' ) ? webp_uploads_get_image_output_format() : 'webp';

	$default_transforms = array(
		'image/jpeg' => array( 'image/' . $output_format ),
		'image/webp' => array( 'image/' . $output_format ),
		'image/avif' => array( 'image/avif' ),
		'image/png'  => array( 'image/' . $output_format ),
	);

	// Check setting for whether to generate both JPEG and the modern output format.
	if ( webp_uploads_is_fallback_enabled() ) {
		$default_transforms = array(
			'image/jpeg'              => array( 'image/jpeg', 'image/' . $output_format ),
			'image/png'               => array( 'image/png', 'image/' . $output_format ),
			'image/' . $output_format => array( 'image/' . $output_format, 'image/jpeg' ),
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
		if ( ! is_array( $transform_types ) || 0 === count( $transform_types ) ) {
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
 * @param int                                                               $attachment_id         The ID of the attachment from where this image would be created.
 * @param string                                                            $image_size            The size name that would be used to create the image source, out of the registered subsizes.
 * @param array{ width: int, height: int, crop: bool|array{string, string}} $size_data             An array with the dimensions of the image: height, width and crop.
 * @param string                                                            $mime                  The target mime in which the image should be created.
 * @param string|null                                                       $destination_file_name The path where the file would be stored, including the extension. If null, `generate_filename` is used to create the destination file name.
 *
 * @return array{ file: string, filesize: int }|WP_Error An array with the file and filesize if the image was created correctly, otherwise a WP_Error.
 */
function webp_uploads_generate_additional_image_source( int $attachment_id, string $image_size, array $size_data, string $mime, ?string $destination_file_name = null ) {
	/**
	 * Filter to allow the generation of additional image sources, in which a defined mime type
	 * can be transformed and create additional mime types for the file.
	 *
	 * Returning an image data array or WP_Error here effectively short-circuits the default logic to generate the image source.
	 *
	 * @since 1.1.0
	 *
	 * @param array{
	 *            file: string,
	 *            path?: string,
	 *            filesize?: int
	 *        }|null|WP_Error $image         Image data, null, or WP_Error.
	 * @param int             $attachment_id The ID of the attachment from where this image would be created.
	 * @param string          $image_size    The size name that would be used to create this image, out of the registered subsizes.
	 * @param array{
	 *            width: int,
	 *            height: int,
	 *            crop: bool|array{string, string}
	 *        }               $size_data     An array with the dimensions of the image.
	 * @param string          $mime          The target mime in which the image should be created.
	 */
	$image = apply_filters( 'webp_uploads_pre_generate_additional_image_source', null, $attachment_id, $image_size, $size_data, $mime );
	if ( is_wp_error( $image ) ) {
		return $image;
	}
	if ( is_array( $image ) && array_key_exists( 'file', $image ) && is_string( $image['file'] ) ) {
		// The filtered image provided all we need to short-circuit here.
		if ( array_key_exists( 'filesize', $image ) && is_int( $image['filesize'] ) && $image['filesize'] > 0 ) {
			return $image;
		}

		// Supply the filesize based on the filter-provided path.
		if ( array_key_exists( 'path', $image ) && is_int( $image['path'] ) ) {
			$filesize = wp_filesize( $image['path'] );
			if ( $filesize > 0 ) {
				return array(
					'file'     => $image['file'],
					'filesize' => $filesize,
				);
			}
		}
	}

	$allowed_mimes = array_flip( wp_get_mime_types() );
	if ( ! isset( $allowed_mimes[ $mime ] ) || ! is_string( $allowed_mimes[ $mime ] ) ) {
		return new WP_Error( 'image_mime_type_invalid', __( 'The provided mime type is not allowed.', 'webp-uploads' ) );
	}

	if ( ! wp_image_editor_supports( array( 'mime_type' => $mime ) ) ) {
		return new WP_Error( 'image_mime_type_not_supported', __( 'The provided mime type is not supported.', 'webp-uploads' ) );
	}

	$image_path = wp_get_original_image_path( $attachment_id );
	if ( false === $image_path || ! file_exists( $image_path ) ) {
		return new WP_Error( 'original_image_file_not_found', __( 'The original image file does not exists, subsizes are created out of the original image.', 'webp-uploads' ) );
	}

	$editor = wp_get_image_editor( $image_path, array( 'mime_type' => $mime ) );
	if ( is_wp_error( $editor ) ) {
		return $editor;
	}

	$height = isset( $size_data['height'] ) ? (int) $size_data['height'] : 0;
	$width  = isset( $size_data['width'] ) ? (int) $size_data['width'] : 0;
	$crop   = isset( $size_data['crop'] ) ? $size_data['crop'] : false;
	if ( $width <= 0 && $height <= 0 ) {
		return new WP_Error( 'image_wrong_dimensions', __( 'At least one of the dimensions must be a positive number.', 'webp-uploads' ) );
	}

	$image_meta = wp_get_attachment_metadata( $attachment_id );
	// If stored EXIF data exists, rotate the source image before creating sub-sizes.
	if ( isset( $image_meta['image_meta'] ) && is_array( $image_meta['image_meta'] ) && count( $image_meta['image_meta'] ) > 0 ) {
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

	remove_filter( 'image_editor_output_format', 'webp_uploads_filter_image_editor_output_format', 10 );
	$image = $editor->save( $destination_file_name, $mime );
	add_filter( 'image_editor_output_format', 'webp_uploads_filter_image_editor_output_format', 10, 3 );

	if ( is_wp_error( $image ) ) {
		return $image;
	}

	if ( ! isset( $image['file'] ) || ! is_string( $image['file'] ) || '' === $image['file'] ) {
		return new WP_Error( 'image_file_not_present', __( 'The file key is not present on the image data', 'webp-uploads' ) );
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
 * @return array{ file: string, filesize: int }|WP_Error
 */
function webp_uploads_generate_image_size( int $attachment_id, string $size, string $mime ) {
	$sizes    = wp_get_registered_image_subsizes();
	$metadata = wp_get_attachment_metadata( $attachment_id );

	if (
		! isset( $metadata['sizes'][ $size ], $sizes[ $size ] )
		|| ! is_array( $metadata['sizes'][ $size ] )
		|| ! is_array( $sizes[ $size ] )
	) {
		return new WP_Error( 'image_mime_type_invalid_metadata', __( 'The image does not have a valid metadata.', 'webp-uploads' ) );
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
		$size_data['crop'] = $sizes[ $size ]['crop'];
	}

	return webp_uploads_generate_additional_image_source( $attachment_id, $size, $size_data, $mime );
}

/**
 * Returns mime types that should be used for an image in the specific context.
 *
 * @since 1.0.0
 *
 * @param int    $attachment_id The attachment ID.
 * @param string $context       The current context.
 * @return string[] Mime types to use for the image.
 */
function webp_uploads_get_content_image_mimes( int $attachment_id, string $context ): array {
	$target_mimes = array( 'image/' . webp_uploads_get_image_output_format(), 'image/jpeg' );

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
 * @since 1.0.0
 *
 * @global WP_Query $wp_query WordPress Query object.
 *
 * @return bool True if in the <body> within a frontend request, false otherwise.
 */
function webp_uploads_in_frontend_body(): bool {
	global $wp_query;

	// Check if this request is generally outside (or before) any frontend context.
	if ( ! isset( $wp_query ) || defined( 'REST_REQUEST' ) || defined( 'XMLRPC_REQUEST' ) || is_feed() ) {
		return false;
	}

	// Check if we're anywhere before 'template_redirect' or within the 'wp_head' action.
	if ( 0 === did_action( 'template_redirect' ) || doing_action( 'wp_head' ) ) {
		return false;
	}

	return true;
}

/**
 * Check whether the additional image is larger than the original image.
 *
 * @since 1.0.0
 *
 * @param array{ filesize?: int } $original   An array with the metadata of the attachment.
 * @param array{ filesize?: int } $additional An array containing the filename and file size for additional mime.
 * @return bool True if the additional image is larger than the original image, otherwise false.
 */
function webp_uploads_should_discard_additional_image_file( array $original, array $additional ): bool {
	$original_image_filesize   = isset( $original['filesize'] ) ? (int) $original['filesize'] : 0;
	$additional_image_filesize = isset( $additional['filesize'] ) ? (int) $additional['filesize'] : 0;
	if ( $original_image_filesize > 0 && $additional_image_filesize > 0 ) {
		/**
		 * Filter whether WebP images that are larger than the matching JPEG should be discarded.
		 *
		 * By default the performance lab plugin will use the mime type with the smaller filesize
		 * rather than defaulting to `webp`.
		 *
		 * @since 1.0.0
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

/**
 * Checks if a mime type is supported by the server.
 *
 * Includes special handling for false positives on AVIF support.
 *
 * @since 2.0.0
 *
 * @param string $mime_type The mime type to check.
 * @return bool Whether the server supports a given mime type.
 */
function webp_uploads_mime_type_supported( string $mime_type ): bool {
	if ( ! wp_image_editor_supports( array( 'mime_type' => $mime_type ) ) ) {
		return false;
	}

	// In certain server environments Image editors can report a false positive for AVIF support.
	if ( 'image/avif' === $mime_type ) {
		$editor = _wp_image_editor_choose( array( 'mime_type' => 'image/avif' ) );
		if ( false === $editor ) {
			return false;
		}
		if ( is_a( $editor, WP_Image_Editor_GD::class, true ) ) {
			return function_exists( 'imageavif' );
		}
		if ( is_a( $editor, WP_Image_Editor_Imagick::class, true ) && class_exists( 'Imagick' ) ) {
			return 0 !== count( Imagick::queryFormats( 'AVIF' ) );
		}
	}

	return true;
}

/**
 * Get the image output format setting from the option. Default is avif.
 *
 * @since 2.0.0
 *
 * @return string The image output format. One of 'webp' or 'avif'.
 */
function webp_uploads_get_image_output_format(): string {
	$image_format = get_option( 'perflab_modern_image_format' );
	return webp_uploads_sanitize_image_format( $image_format );
}

/**
 * Sanitizes the image format.
 *
 * @since 2.0.0
 *
 * @param string|mixed $image_format The image format to check.
 * @return string Supported image format.
 */
function webp_uploads_sanitize_image_format( $image_format ): string {
	return in_array( $image_format, array( 'webp', 'avif' ), true ) ? $image_format : 'webp';
}

/**
 * Checks if the `webp_uploads_use_picture_element` option is enabled.
 *
 * @since 2.0.0
 *
 * @return bool True if the option is enabled, false otherwise.
 */
function webp_uploads_is_picture_element_enabled(): bool {
	return webp_uploads_is_fallback_enabled() && (bool) get_option( 'webp_uploads_use_picture_element', false );
}

/**
 * Checks if the `perflab_generate_webp_and_jpeg` option is enabled.
 *
 * @since 2.0.0
 * @since 2.2.0 Renamed to webp_uploads_is_fallback_enabled().
 *
 * @return bool True if the option is enabled, false otherwise.
 */
function webp_uploads_is_fallback_enabled(): bool {
	return (bool) get_option( 'perflab_generate_webp_and_jpeg' );
}

/**
 * Checks if the `perflab_generate_all_fallback_sizes` option is enabled.
 *
 * @since 2.4.0
 *
 * @return bool Whether the option is enabled. Default is false.
 */
function webp_uploads_should_generate_all_fallback_sizes(): bool {
	return (bool) get_option( 'perflab_generate_all_fallback_sizes', 0 );
}

/**
 * Retrieves the image URL for a specified MIME type from the attachment metadata.
 *
 * This function attempts to locate an alternate image source URL in the
 * attachment's metadata that matches the provided MIME type.
 *
 * @since 2.2.0
 *
 * @param int    $attachment_id The ID of the attachment.
 * @param string $src           The original image src url.
 * @param string $mime          A mime type we are looking to get image url.
 * @return string|null Returns mime type image if available.
 */
function webp_uploads_get_mime_type_image( int $attachment_id, string $src, string $mime ): ?string {
	$metadata     = wp_get_attachment_metadata( $attachment_id );
	$src_basename = wp_basename( $src );
	if ( isset( $metadata['sources'][ $mime ]['file'] ) ) {
		$basename = wp_basename( $metadata['file'] );

		if ( $src_basename === $basename ) {
			return str_replace(
				$basename,
				$metadata['sources'][ $mime ]['file'],
				$src
			);
		}
	}

	if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
		foreach ( $metadata['sizes'] as $size => $size_data ) {

			if ( ! isset( $size_data['file'] ) ) {
				continue;
			}

			if ( ! isset( $size_data['sources'][ $mime ]['file'] ) ) {
				continue;
			}

			if ( $size_data['file'] === $size_data['sources'][ $mime ]['file'] ) {
				continue;
			}

			if ( $src_basename !== $size_data['file'] ) {
				continue;
			}

			return str_replace(
				$size_data['file'],
				$size_data['sources'][ $mime ]['file'],
				$src
			);
		}
	}

	return null;
}

/**
 * Retrieves the MIME type of an attachment file, checking the file directly if possible.
 *
 * If checking the file directly fails, the function falls back to the attachment's MIME type.
 *
 * @since 2.3.0
 *
 * @param int    $attachment_id The attachment ID.
 * @param string $file          Optional. The path to the file.
 * @return string The MIME type of the file, or an empty string if not found.
 */
function webp_uploads_get_attachment_file_mime_type( int $attachment_id, string $file = '' ): string {
	if ( '' === $file ) {
		$file = get_attached_file( $attachment_id, true );
		// File does not exist.
		if ( false === $file ) {
			return '';
		}
	}

	/*
	 * We need to get the MIME type ideally from the file, as WordPress Core may have already altered it.
	 * The post MIME type is typically not updated during that process.
	 */
	$filetype  = wp_check_filetype( $file );
	$mime_type = $filetype['type'] ?? get_post_mime_type( $attachment_id );
	return is_string( $mime_type ) ? $mime_type : '';
}

/**
 * Hook called by `wp_generate_attachment_metadata` to create the `sources` property for every image
 * size, the sources' property would create a new image size with all the mime types specified in
 * `webp_uploads_get_upload_image_mime_transforms`. If the original image is one of the mimes from
 * `webp_uploads_get_upload_image_mime_transforms` the image is just added to the `sources` property and not
 * created again. If the uploaded attachment is not a supported mime by this function, the hook does not alter the
 * metadata of the attachment. In addition to every single size the `sources` property is added at the
 * top level of the image metadata to store the references for all the mime types for the `full` size image of the
 * attachment.
 *
 * @since 1.0.0
 *
 * @see   wp_generate_attachment_metadata()
 * @see   webp_uploads_get_upload_image_mime_transforms()
 *
 * @phpstan-param array{
 *      width: int,
 *      height: int,
 *      file: string,
 *      sizes: array<string, array{ file: string, width: int, height: int, 'mime-type': string }>,
 *      image_meta: array<string, mixed>,
 *      filesize: int
 *  } $metadata
 *
 * @param array<string, mixed> $metadata      An array with the metadata from this attachment.
 * @param int                  $attachment_id The ID of the attachment where the hook was dispatched.
 *
 * @return array{
 *     width: int,
 *     height: int,
 *     file: string,
 *     sizes: array<string, array{ file: string, width: int, height: int, 'mime-type': string, sources?: array<string, array{ file: string, filesize: int }> }>,
 *     image_meta: array<string, mixed>,
 *     filesize: int,
 *     sources?: array<string, array{
 *         file: string,
 *         filesize: int
 *     }>
 * } An array with the updated structure for the metadata before is stored in the database.
 */
function webp_uploads_create_sources_property( array $metadata, int $attachment_id ): array {
	$file = get_attached_file( $attachment_id, true );
	// File does not exist.
	if ( false === $file || ! file_exists( $file ) ) {
		return $metadata;
	}

	$mime_type = webp_uploads_get_attachment_file_mime_type( $attachment_id, $file );
	if ( '' === $mime_type ) {
		return $metadata;
	}

	$valid_mime_transforms = webp_uploads_get_upload_image_mime_transforms();

	// Not a supported mime type to create the sources property.
	if ( ! isset( $valid_mime_transforms[ $mime_type ] ) ) {
		return $metadata;
	}

	// Make sure the top level `sources` key is a valid array.
	if ( ! isset( $metadata['sources'] ) || ! is_array( $metadata['sources'] ) ) {
		$metadata['sources'] = array();
	}

	if ( ! isset( $metadata['sources'][ $mime_type ]['file'] ) ) {
		$metadata['sources'][ $mime_type ] = array(
			'file'     => wp_basename( $file ),
			'filesize' => wp_filesize( $file ),
		);
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	$original_size_data = array(
		'width'  => isset( $metadata['width'] ) ? (int) $metadata['width'] : 0,
		'height' => isset( $metadata['height'] ) ? (int) $metadata['height'] : 0,
		'crop'   => false,
	);

	$original_directory = pathinfo( $file, PATHINFO_DIRNAME );
	$filename           = pathinfo( $file, PATHINFO_FILENAME );
	$ext                = pathinfo( $file, PATHINFO_EXTENSION );
	$allowed_mimes      = array_flip( wp_get_mime_types() );

	// Create the sources for the full sized image.
	foreach ( $valid_mime_transforms[ $mime_type ] as $targeted_mime ) {
		// If this property exists no need to create the image again.
		if ( isset( $metadata['sources'][ $targeted_mime ]['file'] ) ) {
			continue;
		}

		// The targeted mime is not allowed in the current installation.
		if ( ! isset( $allowed_mimes[ $targeted_mime ] ) ) {
			continue;
		}

		$extension   = explode( '|', $allowed_mimes[ $targeted_mime ] );
		$destination = trailingslashit( $original_directory ) . "{$filename}-{$ext}.{$extension[0]}";
		$image       = webp_uploads_generate_additional_image_source( $attachment_id, 'full', $original_size_data, $targeted_mime, $destination );

		if ( is_wp_error( $image ) ) {
			continue;
		}

		if ( webp_uploads_should_discard_additional_image_file( $metadata, $image ) ) {
			wp_delete_file_from_directory( $destination, $original_directory );
			continue;
		}

		$metadata['sources'][ $targeted_mime ] = $image;
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	// If the original MIME type should not be generated/used, override the main image
	// with the first MIME type image that actually should be generated. In that case,
	// the original should be backed up.
	if (
		! in_array( $mime_type, $valid_mime_transforms[ $mime_type ], true ) &&
		isset( $valid_mime_transforms[ $mime_type ][0] ) &&
		isset( $allowed_mimes[ $mime_type ] ) &&
		array_key_exists( 'file', $metadata ) &&
		is_string( $metadata['file'] )
	) {
		$valid_mime_type = $valid_mime_transforms[ $mime_type ][0];

		// Only do the replacement if the attachment file is still set to the original MIME type one,
		// and if there is a possible replacement source.
		$file_data = wp_check_filetype( $metadata['file'], array( $allowed_mimes[ $mime_type ] => $mime_type ) );
		if ( $file_data['type'] === $mime_type && isset( $metadata['sources'][ $valid_mime_type ] ) ) {
			$saved_data = array(
				'path'   => trailingslashit( $original_directory ) . $metadata['sources'][ $valid_mime_type ]['file'],
				'width'  => $metadata['width'],
				'height' => $metadata['height'],
			);

			$original_image = wp_get_original_image_path( $attachment_id );

			// If WordPress already modified the original itself, keep the original and discard WordPress's generated version.
			if ( isset( $metadata['original_image'] ) && is_string( $metadata['original_image'] ) && '' !== $metadata['original_image'] ) {
				$uploadpath    = wp_get_upload_dir();
				$attached_file = get_attached_file( $attachment_id );
				if ( false !== $attached_file ) {
					wp_delete_file_from_directory( $attached_file, $uploadpath['basedir'] );
				}
			}

			// Replace the attached file with the custom MIME type version.
			if ( false !== $original_image ) {
				$metadata = _wp_image_meta_replace_original( $saved_data, $original_image, $metadata, $attachment_id );
			}

			// Unset sources entry for the original MIME type, then save (to avoid inconsistent data
			// in case of an error after this logic).
			unset( $metadata['sources'][ $mime_type ] );
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
	}

	// Make sure we have some sizes to work with, otherwise avoid any work.
	if (
		! isset( $metadata['sizes'] ) ||
		! is_array( $metadata['sizes'] ) ||
		0 === count( $metadata['sizes'] )
	) {
		return $metadata;
	}

	$sizes_with_mime_type_support = webp_uploads_get_image_sizes_additional_mime_type_support();

	foreach ( $metadata['sizes'] as $size_name => $properties ) {
		// Do nothing if this image size is not an array or is not allowed to have additional mime types.
		if (
			! is_array( $properties ) ||
			! isset( $sizes_with_mime_type_support[ $size_name ] ) ||
			false === $sizes_with_mime_type_support[ $size_name ]
		) {
			continue;
		}

		// Try to find the mime type of the image size.
		$current_mime = '';
		if ( isset( $properties['mime-type'] ) ) {
			$current_mime = $properties['mime-type'];
		} elseif ( isset( $properties['file'] ) ) {
			$current_mime = wp_check_filetype( $properties['file'] )['type'];
		}

		// The mime type can't be determined.
		if ( ! is_string( $current_mime ) || '' === $current_mime ) {
			continue;
		}

		// Ensure a `sources` property exists on the existing size.
		if ( ! isset( $properties['sources'] ) || ! is_array( $properties['sources'] ) ) {
			$properties['sources'] = array();
		}

		if ( ! isset( $properties['sources'][ $current_mime ]['file'] ) && isset( $properties['file'] ) ) {
			$properties['sources'][ $current_mime ] = array(
				'file'     => $properties['file'],
				'filesize' => 0,
			);
			// Set the filesize from the current mime image.
			$file_location = path_join( $original_directory, $properties['file'] );
			if ( file_exists( $file_location ) ) {
				$properties['sources'][ $current_mime ]['filesize'] = wp_filesize( $file_location );
			}
			$metadata['sizes'][ $size_name ] = $properties;
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		foreach ( $valid_mime_transforms[ $mime_type ] as $mime ) {
			// If this property exists no need to create the image again.
			if ( isset( $properties['sources'][ $mime ]['file'] ) ) {
				continue;
			}

			$source = webp_uploads_generate_image_size( $attachment_id, $size_name, $mime );
			if ( is_wp_error( $source ) ) {
				continue;
			}

			if ( webp_uploads_should_discard_additional_image_file( $properties, $source ) ) {
				$destination = path_join( $original_directory, $source['file'] );
				wp_delete_file_from_directory( $destination, $original_directory );
				continue;
			}

			$properties['sources'][ $mime ]  = $source;
			$metadata['sizes'][ $size_name ] = $properties;
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		$metadata['sizes'][ $size_name ] = $properties;
	}

	return $metadata;
}

/**
 * Filter on `wp_get_missing_image_subsizes` acting as an action for the logic of the plugin
 * to determine if additional mime types still need to be created.
 *
 * This function only exists to work around a missing filter in WordPress core, to call the above
 * `webp_uploads_create_sources_property()` function correctly.
 *
 * @since 1.0.0
 *
 * @see wp_get_missing_image_subsizes()
 *
 * @phpstan-param array{
 *     width: int,
 *     height: int,
 *     file: string,
 *     sizes: array<string, array{file: string, width: int, height: int, mime-type: string}>,
 *     image_meta: array<string, mixed>,
 *     filesize: int
 * } $image_meta
 *
 * @param array|mixed          $missing_sizes Associative array of arrays of image sub-sizes.
 * @param array<string, mixed> $image_meta    The metadata from the image.
 * @param int                  $attachment_id The ID of the attachment.
 * @return array<string, array{ width: int, height: int, crop: bool }> Associative array of arrays of image sub-sizes.
 */
function webp_uploads_wp_get_missing_image_subsizes( $missing_sizes, array $image_meta, int $attachment_id ): array {
	if ( ! is_array( $missing_sizes ) ) {
		$missing_sizes = array();
	}

	// Only setup the trace array if we no longer have more sizes.
	if ( count( $missing_sizes ) > 0 ) {
		return $missing_sizes;
	}

	/**
	 * The usage of `debug_backtrace` in this particular case is mainly to ensure the call to
	 * `wp_get_missing_image_subsizes()` originated from `wp_update_image_subsizes()`, since only then the
	 * additional image sizes should be generated. `wp_get_missing_image_subsizes()` could also be called
	 * from other places in which case the custom logic should not trigger. In an ideal world an action
	 * would exist in `wp_update_image_subsizes` that runs any time, but the current
	 * `wp_generate_attachment_metadata` filter is skipped when all core sub-sizes have been generated.
	 * An eventual core implementation will not require this workaround. The limit of 10 is used to allow
	 * for some flexibility. While by default the function would be on index 5, other custom code may
	 * cause the index to be slightly higher.
	 *
	 * @see wp_update_image_subsizes()
	 * @see wp_get_missing_image_subsizes()
	 */
	// PHPCS ignore reason: Only the way to generate missing image subsize if all core sub-sizes have been generated.
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
	$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );

	foreach ( $trace as $element ) {
		if ( 'wp_update_image_subsizes' === $element['function'] ) {
			webp_uploads_create_sources_property( $image_meta, $attachment_id );
			break;
		}
	}

	return array();
}

/**
 * Filter the image editor default output format mapping to select the most appropriate
 * output format depending on desired output formats and supported mime types by the image
 * editor.
 *
 * @since 1.0.0
 *
 * @param array<string, string>|mixed $output_format An array of mime type mappings. Maps a source mime type to a new destination mime type. Default empty array.
 * @param string|null                 $filename      Path to the image.
 * @param string|null                 $mime_type     The source image mime type.
 * @return array<string, string> The new output format mapping.
 */
function webp_uploads_filter_image_editor_output_format( $output_format, ?string $filename, ?string $mime_type ): array {
	if ( ! is_array( $output_format ) ) {
		$output_format = array();
	}

	// Use the original mime type if this type is allowed.
	$valid_mime_transforms = webp_uploads_get_upload_image_mime_transforms();
	if (
		! isset( $valid_mime_transforms[ $mime_type ] ) ||
		in_array( $mime_type, $valid_mime_transforms[ $mime_type ], true )
	) {
		return $output_format;
	}

	// Find the first supported mime type by the image editor to use it as the default one.
	foreach ( $valid_mime_transforms[ $mime_type ] as $target_mime ) {
		if ( wp_image_editor_supports( array( 'mime_type' => $target_mime ) ) ) {
			$output_format[ $mime_type ] = $target_mime;
			break;
		}
	}

	return $output_format;
}

/**
 * Hook fired when an attachment is deleted, this hook is in charge of removing any
 * additional mime types created by this plugin besides the original image. Any source
 * with the same as the main image would not be removed by this hook due this file would
 * be removed by WordPress when the attachment is deleted, usually this happens after this
 * hook is executed.
 *
 * @since 1.0.0
 *
 * @see wp_delete_attachment()
 *
 * @param int $attachment_id The ID of the attachment the sources are going to be deleted.
 */
function webp_uploads_remove_sources_files( int $attachment_id ): void {
	$file = get_attached_file( $attachment_id );

	if ( false === (bool) $file ) {
		return;
	}

	$metadata = wp_get_attachment_metadata( $attachment_id );
	// Make sure $sizes is always defined to allow the removal of original images after the first foreach loop.
	$sizes = ! isset( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ? array() : $metadata['sizes'];

	$upload_path = wp_get_upload_dir();
	if (
		! isset( $upload_path['basedir'] ) ||
		! is_string( $upload_path['basedir'] ) ||
		'' === $upload_path['basedir']
	) {
		return;
	}

	$intermediate_dir = path_join( $upload_path['basedir'], dirname( $file ) );
	$basename         = wp_basename( $file );

	foreach ( $sizes as $size ) {
		if ( ! isset( $size['sources'] ) || ! is_array( $size['sources'] ) ) {
			continue;
		}

		$original_size_mime = isset( $size['mime-type'] ) && is_string( $size['mime-type'] ) ? $size['mime-type'] : '';

		foreach ( $size['sources'] as $mime => $properties ) {
			/**
			 * When we face the same mime type as the original image, we ignore this file as this file
			 * would be removed when the size is removed by WordPress itself. The meta information as well
			 * would be deleted as soon as the image is removed.
			 *
			 * @see wp_delete_attachment
			 */
			if ( $original_size_mime === $mime ) {
				continue;
			}

			if (
				! isset( $properties['file'] ) ||
				! is_string( $properties['file'] ) ||
				'' === $properties['file']
			) {
				continue;
			}

			$intermediate_file = str_replace( $basename, $properties['file'], $file );
			if ( '' === $intermediate_file ) {
				continue;
			}

			$intermediate_file = path_join( $upload_path['basedir'], $intermediate_file );
			if ( ! file_exists( $intermediate_file ) ) {
				continue;
			}

			wp_delete_file_from_directory( $intermediate_file, $intermediate_dir );
		}
	}

	if ( ! isset( $metadata['sources'] ) || ! is_array( $metadata['sources'] ) ) {
		return;
	}

	$original_mime_from_post = get_post_mime_type( $attachment_id );
	$original_mime_from_file = wp_check_filetype( $file )['type'];

	// Delete full sizes mime types.
	foreach ( $metadata['sources'] as $mime => $properties ) {
		// Don't remove the image with the same mime type as the original image as this would be removed by WordPress.
		if ( $mime === $original_mime_from_post || $mime === $original_mime_from_file ) {
			continue;
		}

		if (
			! isset( $properties['file'] ) ||
			! is_string( $properties['file'] ) ||
			'' === $properties['file']
		) {
			continue;
		}

		$full_size = str_replace( $basename, $properties['file'], $file );
		if ( '' === $full_size ) {
			continue;
		}

		$full_size_file = path_join( $upload_path['basedir'], $full_size );
		if ( ! file_exists( $full_size_file ) ) {
			continue;
		}
		wp_delete_file_from_directory( $full_size_file, $intermediate_dir );
	}

	$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
	$backup_sizes = is_array( $backup_sizes ) ? $backup_sizes : array();

	foreach ( $backup_sizes as $backup_size ) {
		if ( ! isset( $backup_size['sources'] ) || ! is_array( $backup_size['sources'] ) ) {
			continue;
		}

		$original_backup_size_mime = isset( $backup_size['mime-type'] ) && is_string( $backup_size['mime-type'] ) ? $backup_size['mime-type'] : '';

		foreach ( $backup_size['sources'] as $backup_mime => $backup_properties ) {
			/**
			 * When we face the same mime type as the original image, we ignore this file as this file
			 * would be removed when the size is removed by WordPress itself. The meta information as well
			 * would be deleted as soon as the image is removed.
			 *
			 * @see wp_delete_attachment
			 */
			if ( $original_backup_size_mime === $backup_mime ) {
				continue;
			}

			if (
				! isset( $backup_properties['file'] ) ||
				! is_string( $backup_properties['file'] ) ||
				'' === $backup_properties['file']
			) {
				continue;
			}

			$backup_intermediate_file = str_replace( $basename, $backup_properties['file'], $file );
			if ( '' === $backup_intermediate_file ) {
				continue;
			}

			$backup_intermediate_file = path_join( $upload_path['basedir'], $backup_intermediate_file );
			if ( ! file_exists( $backup_intermediate_file ) ) {
				continue;
			}

			wp_delete_file_from_directory( $backup_intermediate_file, $intermediate_dir );
		}
	}

	$backup_sources = get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true );
	$backup_sources = is_array( $backup_sources ) ? $backup_sources : array();

	// Delete full sizes backup mime types.
	foreach ( $backup_sources as $backup_mimes ) {

		foreach ( $backup_mimes as $backup_mime_properties ) {
			if (
				! isset( $backup_mime_properties['file'] ) ||
				! is_string( $backup_mime_properties['file'] ) ||
				'' === $backup_mime_properties['file']
			) {
				continue;
			}

			$full_size = str_replace( $basename, $backup_mime_properties['file'], $file );
			if ( '' === $full_size ) {
				continue;
			}

			$full_size_file = path_join( $upload_path['basedir'], $full_size );
			if ( ! file_exists( $full_size_file ) ) {
				continue;
			}
			wp_delete_file_from_directory( $full_size_file, $intermediate_dir );
		}
	}
}

/**
 * Filters `wp_content_img_tag` to update images so that they use the preferred MIME type where possible.
 *
 * @since 2.5.0
 *
 * @param string $filtered_image Full img tag with attributes that will replace the source img tag.
 * @param string $context        Additional context, like the current filter name or the function name from where this was called.
 * @param int    $attachment_id  The image attachment ID. May be 0 in case the image is not an attachment.
 * @return string The updated IMG tag with references to the new MIME type if available.
 */
function webp_uploads_filter_image_tag( string $filtered_image, string $context, int $attachment_id ): string {
	// Bail early if request is not for the frontend.
	if ( ! webp_uploads_in_frontend_body() ) {
		return $filtered_image;
	}

	$filtered_image = str_replace( $filtered_image, webp_uploads_img_tag_update_mime_type( $filtered_image, 'the_content', $attachment_id ), $filtered_image );

	return $filtered_image;
}

/**
 * Finds all the urls with *.jpg and *.jpeg extension and updates with *.webp version for the provided image
 * for the specified image sizes, the *.webp references are stored inside of each size.
 *
 * @since 1.0.0
 *
 * @param string $original_image An <img> tag where the urls would be updated.
 * @param string $context        The context where this is function is being used.
 * @param int    $attachment_id  The ID of the attachment being modified.
 * @return string The updated img tag.
 */
function webp_uploads_img_tag_update_mime_type( string $original_image, string $context, int $attachment_id ): string {
	$image    = $original_image;
	$metadata = wp_get_attachment_metadata( $attachment_id );

	if ( ! isset( $metadata['file'] ) || ! is_string( $metadata['file'] ) || '' === $metadata['file'] ) {
		return $image;
	}

	$original_mime = get_post_mime_type( $attachment_id );
	$target_mimes  = webp_uploads_get_content_image_mimes( $attachment_id, $context );

	foreach ( $target_mimes as $target_mime ) {
		if ( $target_mime === $original_mime ) {
			continue;
		}

		if ( ! isset( $metadata['sources'][ $target_mime ]['file'] ) ) {
			continue;
		}

		/**
		 * Filter to replace additional image source file, by locating the original
		 * mime types of the file and return correct file path in the end.
		 *
		 * Altering the $image tag through this filter effectively short-circuits the default replacement logic using the preferred MIME type.
		 *
		 * @since 1.1.0
		 *
		 * @param string $image         An <img> tag where the urls would be updated.
		 * @param int    $attachment_id The ID of the attachment being modified.
		 * @param string $size          The size name that would be used to create this image, out of the registered subsizes.
		 * @param string $target_mime   The target mime in which the image should be created.
		 * @param string $context       The context where this is function is being used.
		 */
		$filtered_image = (string) apply_filters( 'webp_uploads_pre_replace_additional_image_source', $image, $attachment_id, 'full', $target_mime, $context );

		// If filtered image is same as the image, run our own replacement logic, otherwise rely on the filtered image.
		if ( $filtered_image === $image ) {
			$basename = wp_basename( $metadata['file'] );
			$image    = str_replace(
				$basename,
				$metadata['sources'][ $target_mime ]['file'],
				$image
			);
		} else {
			$image = $filtered_image;
		}
	}

	if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
		// Replace sub sizes for the image if present.
		foreach ( $metadata['sizes'] as $size => $size_data ) {

			if ( ! isset( $size_data['file'] ) || ! is_string( $size_data['file'] ) || '' === $size_data['file'] ) {
				continue;
			}

			foreach ( $target_mimes as $target_mime ) {
				if ( $target_mime === $original_mime ) {
					continue;
				}

				if ( ! isset( $size_data['sources'][ $target_mime ]['file'] ) ) {
					continue;
				}

				if ( $size_data['file'] === $size_data['sources'][ $target_mime ]['file'] ) {
					continue;
				}

				/** This filter is documented in plugins/webp-uploads/load.php */
				$filtered_image = (string) apply_filters( 'webp_uploads_pre_replace_additional_image_source', $image, $attachment_id, $size, $target_mime, $context );

				// If filtered image is same as the image, run our own replacement logic, otherwise rely on the filtered image.
				if ( $filtered_image === $image ) {
					$image = str_replace(
						$size_data['file'],
						$size_data['sources'][ $target_mime ]['file'],
						$image
					);
				} else {
					$image = $filtered_image;
				}
			}
		}
	}

	return $image;
}

/**
 * Updates the references of the featured image to the a new image format if available, in the same way it
 * occurs in the_content of a post.
 *
 * @since 1.0.0
 *
 * @param string $html          The current HTML markup of the featured image.
 * @param int    $post_id       The current post ID where the featured image is requested.
 * @param int    $attachment_id The ID of the attachment image.
 * @return string The updated HTML markup.
 */
function webp_uploads_update_featured_image( string $html, int $post_id, int $attachment_id ): string {
	return webp_uploads_img_tag_update_mime_type( $html, 'post_thumbnail_html', $attachment_id );
}

/**
 * Returns an array of image size names that have secondary mime type output enabled. Core sizes and
 * core theme sizes are enabled by default.
 *
 * Developers can control the generation of additional mime images for all sizes using the
 * webp_uploads_image_sizes_with_additional_mime_type_support filter.
 *
 * @since 1.0.0
 *
 * @return array<string, bool> An array of image sizes that can have additional mime types.
 */
function webp_uploads_get_image_sizes_additional_mime_type_support(): array {
	$additional_sizes = wp_get_additional_image_sizes();
	$allowed_sizes    = array(
		'thumbnail'      => true,
		'medium'         => true,
		'medium_large'   => true,
		'large'          => true,
		'post-thumbnail' => true,
	);

	foreach ( $additional_sizes as $size => $size_details ) {
		$allowed_sizes[ $size ] = isset( $size_details['provide_additional_mime_types'] ) && true === (bool) $size_details['provide_additional_mime_types'];
	}

	/**
	 * Filters whether additional mime types are allowed for image sizes.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, bool> $allowed_sizes A map of image size names and whether they are allowed to have additional mime types.
	 */
	$allowed_sizes = (array) apply_filters( 'webp_uploads_image_sizes_with_additional_mime_type_support', $allowed_sizes );

	return $allowed_sizes;
}

/**
 * Updates the quality of WebP image sizes generated by WordPress to 82.
 *
 * @since 1.0.0
 *
 * @param int    $quality   Quality level between 1 (low) and 100 (high).
 * @param string $mime_type Image mime type.
 * @return int The updated quality for mime types.
 */
function webp_uploads_modify_webp_quality( int $quality, string $mime_type ): int {
	// For WebP images, always return 82 (other MIME types were already using 82 by default anyway).
	if ( 'image/webp' === $mime_type ) {
		return 82;
	}

	// Return default quality for non-WebP images in WP.
	return $quality;
}

/**
 * Displays the HTML generator tag for the Modern Image Formats plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 1.0.0
 */
function webp_uploads_render_generator(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="webp-uploads ' . esc_attr( WEBP_UPLOADS_VERSION ) . '">' . "\n";
}

/**
 * Initializes custom functionality for handling image uploads and content filters.
 *
 * @since 2.1.0
 */
function webp_uploads_init(): void {
	add_filter( 'wp_content_img_tag', webp_uploads_is_picture_element_enabled() ? 'webp_uploads_wrap_image_in_picture' : 'webp_uploads_filter_image_tag', 10, 3 );
}

/**
 * Automatically opt into extra image sizes when generating fallback images.
 *
 * @since 2.4.0
 *
 * @global array $_wp_additional_image_sizes Associative array of additional image sizes.
 */
function webp_uploads_opt_in_extra_image_sizes(): void {
	if ( ! webp_uploads_is_fallback_enabled() ) {
		return;
	}

	global $_wp_additional_image_sizes;

	// Modify global to mimic the "hypothetical" WP core API behavior via an additional `add_image_size()` parameter.

	if ( isset( $_wp_additional_image_sizes['1536x1536'] ) && ! isset( $_wp_additional_image_sizes['1536x1536']['provide_additional_mime_types'] ) ) {
		$_wp_additional_image_sizes['1536x1536']['provide_additional_mime_types'] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	if ( isset( $_wp_additional_image_sizes['2048x2048'] ) && ! isset( $_wp_additional_image_sizes['2048x2048']['provide_additional_mime_types'] ) ) {
		$_wp_additional_image_sizes['2048x2048']['provide_additional_mime_types'] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}
}

/**
 * Enables additional MIME type support for all image sizes based on the generate all fallback sizes settings.
 *
 * @since 2.4.0
 *
 * @param array<string, bool> $allowed_sizes A map of image size names and whether they are allowed to have additional MIME types.
 * @return array<string, bool> Modified map of image sizes with additional MIME type support.
 */
function webp_uploads_enable_additional_mime_type_support_for_all_sizes( array $allowed_sizes ): array {
	if ( ! webp_uploads_should_generate_all_fallback_sizes() ) {
		return $allowed_sizes;
	}

	foreach ( array_keys( $allowed_sizes ) as $size ) {
		$allowed_sizes[ $size ] = true;
	}

	return $allowed_sizes;
}
