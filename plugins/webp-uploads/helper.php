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
 * @param int    $attachment_id  The ID of the attachment.
 * @param string $src            The original image src url.
 * @param string $mime           A mime type we are looking to get image url.
 * @return non-empty-string|null Returns mime type image if available.
 */
function webp_uploads_get_mime_type_image( int $attachment_id, string $src, string $mime ): ?string {
	$metadata     = wp_get_attachment_metadata( $attachment_id );
	$src_basename = wp_basename( $src );
	if ( isset( $metadata['sources'][ $mime ]['file'] ) ) {
		$basename = wp_basename( $metadata['file'] );

		if ( $src_basename === $basename ) {
			$result = str_replace(
				$basename,
				$metadata['sources'][ $mime ]['file'],
				$src
			);
			return '' === $result ? null : $result;
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

			$result = str_replace(
				$size_data['file'],
				$size_data['sources'][ $mime ]['file'],
				$src
			);
			return '' === $result ? null : $result;
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
