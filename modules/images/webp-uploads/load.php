<?php
/**
 * Module Name: WebP Uploads
 * Description: Uses WebP as the default format for new JPEG image uploads if the server supports it.
 * Experimental: No
 *
 * @package performance-lab
 * @since   1.0.0
 */

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
	$valid_mime_transforms = webp_uploads_get_supported_image_mime_transforms();

	// Not a supported mime type to create the sources property.
	$mime_type = get_post_mime_type( $attachment_id );
	if ( ! isset( $valid_mime_transforms[ $mime_type ] ) ) {
		return $metadata;
	}

	$file = get_attached_file( $attachment_id, true );

	// File does not exist.
	if ( ! file_exists( $file ) ) {
		return $metadata;
	}

	$dirname     = pathinfo( $file, PATHINFO_DIRNAME );
	$image_sizes = array();
	if ( array_key_exists( 'sizes', $metadata ) && is_array( $metadata['sizes'] ) ) {
		$image_sizes = $metadata['sizes'];
	}

	foreach ( wp_get_registered_image_subsizes() as $size_name => $properties ) {
		// This image size does not exist on the defined sizes.
		if ( ! isset( $image_sizes[ $size_name ] ) || ! is_array( $image_sizes[ $size_name ] ) ) {
			continue;
		}

		$current_size = $image_sizes[ $size_name ];
		$sources      = array();
		if ( isset( $current_size['sources'] ) && is_array( $current_size['sources'] ) ) {
			$sources = $current_size['sources'];
		}

		// Try to find the mime type of the image size.
		$current_mime = '';
		if ( isset( $current_size['mime-type'] ) ) {
			$current_mime = $current_size['mime-type'];
		} elseif ( isset( $current_size['file'] ) ) {
			$current_mime = wp_check_filetype( $current_size['file'] )['type'];
		}

		if ( empty( $current_mime ) ) {
			continue;
		}

		$sources[ $current_mime ] = array(
			'file'     => array_key_exists( 'file', $current_size ) ? $current_size['file'] : '',
			'filesize' => 0,
		);

		// Set the filesize from the current mime image.
		$file_location = path_join( $dirname, $sources[ $current_mime ]['file'] );
		if ( file_exists( $file_location ) ) {
			$sources[ $current_mime ]['filesize'] = filesize( $file_location );
		}

		$formats = isset( $valid_mime_transforms[ $current_mime ] ) ? $valid_mime_transforms[ $current_mime ] : array();

		foreach ( $formats as $mime ) {
			wp_schedule_single_event( time(), 'webp_uploads_create_image', array( $attachment_id, $size_name, $mime ) );
		}

		$current_size['sources'] = $sources;
		$metadata['sizes'][ $size_name ]     = $current_size;
	}

	return $metadata;
}

add_filter( 'wp_generate_attachment_metadata', 'webp_uploads_create_sources_property', 10, 2 );

/**
 * Creates a new image based of the specified attachment with a defined mime type
 * this image would be stored in the same place as the provided size name inside the
 * metadata of the attachment.
 *
 * @since n.e.x.t
 *
 * @param int    $attachment_id The ID of the attachment we are going to use as a reference to create the image.
 * @param string $size          The size name that would be used to create this image, out of the registered subsizes.
 * @param string $mime          A mime type we are looking to use to create this image.
 *
 * @return bool|int|WP_Error
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

	// All subsizes are created out of the attached file.
	$file = get_attached_file( $attachment_id );

	// File does not exist.
	if ( ! file_exists( $file ) ) {
		return new WP_Error( 'image_file_size_not_found', __( 'The provided size does not have a valid image file.', 'performance-lab' ) );
	}

	// Create the subsizes out of the attached file.
	$editor = wp_get_image_editor( $file );

	if ( is_wp_error( $editor ) ) {
		return $editor;
	}

	$allowed_mimes = array_flip( wp_get_mime_types() );
	if ( ! array_key_exists( $mime, $allowed_mimes ) || ! is_string( $allowed_mimes[ $mime ] ) ) {
		return new WP_Error( 'image_mime_type_invalid', __( 'The provided mime type is not allowed.', 'performance-lab' ) );
	}

	if ( ! wp_image_editor_supports( array( 'mime_type' => $mime ) ) ) {
		return new WP_Error( 'image_mime_type_not_supported', __( 'The provided mime type is not supported.', 'performance-lab' ) );
	}

	$extension = explode( '|', $allowed_mimes[ $mime ] );
	$extension = reset( $extension );

	$width  = null;
	$height = null;
	$crop   = false;

	if ( array_key_exists( 'width', $metadata['sizes'][ $size ] ) ) {
		$width = $metadata['sizes'][ $size ]['width'];
	} elseif ( array_key_exists( 'width', $sizes[ $size ] ) ) {
		$width = $sizes[ $size ];
	}

	if ( array_key_exists( 'height', $metadata['sizes'][ $size ] ) ) {
		$height = $metadata['sizes'][ $size ]['height'];
	} elseif ( array_key_exists( 'width', $sizes[ $size ] ) ) {
		$height = $sizes[ $size ];
	}

	if ( array_key_exists( 'crop', $sizes[ $size ] ) ) {
		$crop = (bool) $sizes[ $size ]['crop'];
	}

	$editor->resize( $width, $height, $crop );
	$filename = $editor->generate_filename( null, null, $extension );
	$filename = preg_replace( '/-(scaled|rotated|imagifyresized)/', '', $filename );
	$image    = $editor->save( $filename, $mime );

	if ( is_wp_error( $image ) ) {
		return $image;
	}

	if ( empty( $image['file'] ) ) {
		return new WP_Error( 'image_file_not_present', __( 'The file key is not present on the image data', 'performance-lab' ) );
	}

	if ( ! isset( $metadata['sizes'][ $size ]['sources'] ) || ! is_array( $metadata['sizes'][ $size ]['sources'] ) ) {
		$metadata['sizes'][ $size ]['sources'] = array();
	}

	$metadata['sizes'][ $size ]['sources'][ $mime ] = array(
		'file'     => $image['file'],
		'filesize' => array_key_exists( 'path', $image ) ? filesize( $image['path'] ) : 0,
	);

	return wp_update_attachment_metadata( $attachment_id, $metadata );
}

add_action( 'webp_uploads_create_image', 'webp_uploads_generate_image_size', 10, 3 );

/**
 * Returns an array with the list of valid mime types that a specific mime type can be converted into it,
 * for example an image/jpeg can be converted into an image/webp.
 *
 * @since n.e.x.t
 *
 * @return array<string, array<string>> An array of valid mime types, where the key is the mime type and the value is the extension type.
 */
function webp_uploads_get_supported_image_mime_transforms() {
	$image_mime_transforms = array(
		'image/jpeg' => array( 'image/webp' ),
		'image/webp' => array( 'image/jpeg' ),
	);

	/**
	 * Filter to allow the definition of a custom mime types, in which a defined mime type
	 * can be transformed and provide a wide range of mime types.
	 *
	 * @since n.e.x.t
	 *
	 * @param array<string, array<string>> An array with the valid mime transforms.
	 *
	 * @return array<string, array<string>> An array with the valid mime transformation
	 */
	return (array) apply_filters( 'webp_uploads_supported_image_mime_transforms', $image_mime_transforms );
}

/**
 * Hook fired when an attachment is deleted, this hook is in charge of removing any
 * additional mime types created by this plugin besides the original image. Any source
 * with the same as the main image would not be removed by this hook due this file would
 * be removed by WordPress when the attachment is deleted, usually this happens after this
 * hook is executed.
 *
 * @since n.e.x.t
 *
 * @see wp_delete_attachment
 *
 * @param int $attachment_id The ID of the attachment the sources are going to be deleted.
 */
function webp_uploads_remove_sources_files( $attachment_id ) {
	$metadata = wp_get_attachment_metadata( $attachment_id );
	$file = get_attached_file( $attachment_id );

	if (
		! isset( $metadata['sizes'] )
		|| empty( $file )
		|| ! is_array( $metadata['sizes'] )
	) {
		return;
	}

	$upload_path = wp_get_upload_dir();
	if ( empty( $upload_path['basedir'] ) ) {
		return;
	}

	$file_directory = dirname( $file );
	$directory      = path_join( $upload_path['basedir'], $file_directory );

	foreach ( $metadata['sizes'] as $size ) {
		if ( ! isset( $size['sources'] ) || ! is_array( $size['sources'] ) ) {
			continue;
		}

		$original_size_mime = empty( $size['mime-type'] ) ? '' : $size['mime-type'];

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

			if ( ! is_array( $properties ) || empty( $properties['file'] ) ) {
				continue;
			}

			$file_deletion = path_join( $file_directory, $properties['file'] );
			$file_deletion = path_join( $upload_path['basedir'], $file_deletion );
			wp_delete_file_from_directory( $file_deletion, $directory );
		}
	}
}

add_action( 'delete_attachment', 'webp_uploads_remove_sources_files', 10, 1 );
