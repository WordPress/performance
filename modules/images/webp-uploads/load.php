<?php
/**
 * Module Name: WebP Uploads
 * Description: Uses WebP as the default format for new JPEG image uploads if the server supports it.
 * Experimental: No
 *
 * @since   1.0.0
 * @package performance-lab
 */

add_filter( 'wp_generate_attachment_metadata', 'webp_uploads_create_images_with_additional_mime_types', 10, 3 );
add_action( 'webp_uploads_create_additional_mime_types', 'webp_uploads_create_additional_mime_types', 10, 1 );
add_action( 'plugins_loaded', 'webp_uploads_setup_initial_image_format' );

/**
 * Function used to customize the filters applied by this plugin, once all the plugins have
 * been loaded and if customization has been applied to define which image formats are supported
 * certain plugins are removed or added.
 *
 * If only 1 image type is supported additional mime types generation is removed.
 * If only WebP format is supported a new filter is added to only generate WebP on uploads instead of JPEG
 *
 * @since n.e.x.t
 *
 * @see plugins_loaded
 *
 * @return void
 */
function webp_uploads_setup_initial_image_format() {
	$formats = webp_uploads_valid_image_mime_types();
	$total   = count( $formats );

	// If we are targeting more or 2 mime types the logic can remain as it is.
	if ( $total >= 2 ) {
		return;
	}

	if ( 1 === $total ) {
		// No need to generate backup sizes if we are only supporting 1 size.
		remove_filter( 'wp_generate_attachment_metadata', 'webp_uploads_create_images_with_additional_mime_types' );
		// If the only supported format is webp make sure JPEG are uploaded as webp instead.
		if ( reset( $formats ) === 'webp' ) {
			add_filter( 'image_editor_output_format', 'webp_uploads_filter_image_editor_output_format', 10, 3 );
		}
	}

	// TODO: Handle the case when no image format is allowed, in this case should we not generate any image size or fallback to a default mime type ?
}

/**
 * Filter the image editor default output format mapping.
 *
 * For uploaded JPEG images, map the default output format to WebP.
 *
 * @since 1.0.0
 *
 * @param array<string> $output_format The image editor default output format mapping.
 * @param string        $filename      Path to the image.
 * @param string        $mime_type     The source image mime type.
 *
 * @return array<string> The new output format mapping.
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

/**
 * As soon as a new image has been uploaded into the media library a new event is schedule to
 * trigger the additional mime type generation of images. This would defer the generation of
 * additional images when the cron is executed.
 *
 * @see wp_generate_attachment_metadata
 * @see webp_uploads_valid_image_mime_types
 * @see wp_schedule_single_event
 * @see webp_uploads_create_additional_mime_types
 *
 * @param array<string, mixed> $metadata      An array with the metadata from this attachment.
 * @param int                  $attachment_id The ID of the attachment where the hook was dispatched.
 *
 * @return array<string, mixed>
 */
function webp_uploads_create_images_with_additional_mime_types( array $metadata, $attachment_id ) {
	// This should take place only on the JPEG image.
	$valid_mime_types = webp_uploads_valid_image_mime_types();

	// Not a supported mime type to create the sources property.
	if ( ! array_key_exists( get_post_mime_type( $attachment_id ), $valid_mime_types ) ) {
		return $metadata;
	}

	// Move the additional image generations into a cron call.
	wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'webp_uploads_create_additional_mime_types', array( $attachment_id ) );

	return $metadata;
}

/**
 * Function used to create / update the additional images for each mime type. Each image information is
 * stored in the `_wp_attachment_backup_sizes` meta. The function mimics the same behavior as
 * WordPress core when dealing with existing images in the same meta key.
 *
 * @since n.e.x.t
 *
 * @see   webp_uploads_create_images_with_additional_mime_types
 *
 * @param int        $attachment_id The ID of the attachment being processed.
 * @param array|null $backup_sizes  An array of the current backup sizes, when `null` the metadata would be fetch from the DB.
 *
 * @return void
 */
function webp_uploads_create_additional_mime_types( $attachment_id, $backup_sizes = null ) {
	$metadata = wp_get_attachment_metadata( $attachment_id );

	// If no size is present there's no need to generate any additional image as a backup.
	if ( empty( $metadata['sizes'] ) ) {
		return;
	}

	// All subsizes are created out of the `file` property and not the original image.
	$file = get_attached_file( $attachment_id, true );

	// File does not exist.
	if ( ! file_exists( $file ) ) {
		return;
	}

	if ( ! is_array( $backup_sizes ) ) {
		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		$backup_sizes = is_array( $backup_sizes ) ? $backup_sizes : array();
	}

	$dirname = pathinfo( $file, PATHINFO_DIRNAME );
	$hash    = webp_uploads_get_hash_from_edited_file( $file );
	if ( empty( $hash ) ) {
		$hash = time() + mt_rand( 100, 999 );
	}

	$prefix_in_file_name = '/-(scaled|rotated|imagifyresized)/';
	foreach ( webp_uploads_get_image_sizes() as $size => $properties ) {
		// Generate backups only for the missing mime types.
		$formats = webp_uploads_get_remaining_image_mimes( $metadata, $size );

		foreach ( $formats as $mime => $extension ) {
			$key = $extension . '-' . $size;

			// The file already exists as part of the backup sizes in the same key.
			if ( array_key_exists( $key, $backup_sizes ) ) {
				// This case would remove the file if is an edited version.
				if ( defined( 'IMAGE_EDIT_OVERWRITE' ) && IMAGE_EDIT_OVERWRITE ) {
					$file_name     = empty( $backup_sizes[ $key ]['file'] ) ? '' : $backup_sizes[ $key ]['file'];
					$file_location = path_join( $dirname, $file_name );
					$hash          = webp_uploads_get_hash_from_edited_file( $file_name );
					if ( ! empty( $hash ) && file_exists( $file_location ) ) {
						wp_delete_file( $file_location );
					}
				} else {
					$backup_sizes[ "{$key}-{$hash}" ] = $backup_sizes[ $key ];
				}
				// Clear the key, so the subsequent section can add the new image there.
				unset( $backup_sizes[ $key ] );
			}

			// Editor needs to be recreated every time as there is not flush() or clear() function that can be used after we created an image.
			$editor = wp_get_image_editor( $file );

			if ( is_wp_error( $editor ) ) {
				continue;
			}

			$editor->resize( (int) $properties['width'], (int) $properties['height'], $properties['crop'] );
			// TODO: handle the case when the file already exists in location.
			$filename = $editor->generate_filename( null, null, $extension );
			$filename = preg_replace( $prefix_in_file_name, '', $filename );
			$image    = $editor->save( $filename, $mime );

			if ( is_wp_error( $image ) ) {
				continue;
			}

			// TODO: Store the file size of the created image.
			// $image['filesize'] = filesize( $image['path'] );
			// Remove the path of the image to follow the same pattern as core.
			unset( $image['path'] );

			$backup_sizes[ $key ] = $image;
		}
	}

	update_post_meta( $attachment_id, '_wp_attachment_backup_sizes', $backup_sizes );
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
 * @return array<string, array> An array with the details of all available image sizes: width, height and crop.
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
 * @return string[] An array of valid mime types, where the key is the mime type and the value is the extension type.
 */
function webp_uploads_valid_image_mime_types() {
	$valid_formats = array(
		'image/jpeg' => 'jpg',
		'image/webp' => 'webp',
	);

	/**
	 * An array representing all the valid mime types where multiple images would be created if
	 * the image does not exist in that mime type.
	 *
	 * @since n.e.x.t
	 *
	 * @param array<string, string> $valid_formats array with the mime type as the key and extension as the value.
	 *
	 * @return array<string, string> array with the mime type as the key and extension as the value.
	 */
	return (array) apply_filters( 'webp_uploads_images_with_multiple_mime_types', $valid_formats );
}

/**
 * Based on the size and the metadata information of an image, a diff of the remaining mime types
 * required for the specified image size is returned. For instance if a JPEG image is selected
 * and the only valid mime type returned would be WebP.
 *
 * @since n.e.x.t
 *
 * @param array  $metadata An array with the metadata of the attachment.
 * @param string $size     The size name we are looking for.
 *
 * @return array|string[] An array with the remaining mime types for the specified size.
 */
function webp_uploads_get_remaining_image_mimes( array $metadata, $size ) {
	// No need to create a backup for a size that does not exist on the main image.
	if ( empty( $metadata['sizes'][ $size ] ) || ! is_array( $metadata['sizes'][ $size ] ) ) {
		return array();
	}

	$current_size = $metadata['sizes'][ $size ];
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
		return array();
	}

	$valid_mime_types = webp_uploads_valid_image_mime_types();
	// Make sure the current mime is considered a valid mime type.
	if ( array_key_exists( $current_mime, $valid_mime_types ) ) {
		// Generate backups only for the missing mime types.
		return array_diff_assoc( $valid_mime_types, array( $current_mime => $valid_mime_types[ $current_mime ] ) );
	}

	// If the mime is not valid return an empty array.
	return array();
}

/**
 * An edited image in WordPress would contain a hash of type `e-{digits}` where
 * `digits` is number with 13 digits on it, if this is present on the file name we can
 * infer that this file has been edited.
 *
 * @since n.e.x.t
 *
 * @see   wp_restore_image
 * @see   wp_save_image
 *
 * @param string $file The file name where we are looking for a hash.
 *
 * @return string The hash if present empty string if not.
 */
function webp_uploads_get_hash_from_edited_file( $file ) {
	$file_name_when_edited = '/-e([\d]{13})\./';
	preg_match( $file_name_when_edited, $file, $matches );

	// $matches would have at least 2 values if the regex above matches.
	if ( count( $matches ) >= 2 ) {
		return $matches[1];
	}

	return '';
}
