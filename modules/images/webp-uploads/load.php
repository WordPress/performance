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
add_filter( 'wp_die_ajax_handler', 'webp_uploads_wp_die_ajax_handler' );

/**
 * Function used to create / update the additional images for each mime type. Each image information is
 * stored in the `_wp_attachment_backup_sizes` meta. The function mimics the same behavior as
 * WordPress core when dealing with existing images in the same meta key.
 *
 * @since n.e.x.t
 *
 * @see   wp_generate_attachment_metadata
 * @see   webp_uploads_valid_image_mime_types
 *
 * @param array      $metadata      An array with the metadata from this attachment.
 * @param int        $attachment_id The ID of the attachment where the hook was dispatched.
 * @param array|null $backup_sizes  An array with the current backup sizes, if not provided it would be queried against the meta data.
 *
 * @return array An array with the updated structure for the metadata before is stored in the database.
 */
function webp_uploads_create_images_with_additional_mime_types( array $metadata, $attachment_id, $backup_sizes = null ) {
	// This should take place only on the JPEG image.
	$valid_mime_types = webp_uploads_valid_image_mime_types();

	// Not a supported mime type to create the sources property.
	if ( ! array_key_exists( get_post_mime_type( $attachment_id ), $valid_mime_types ) ) {
		return $metadata;
	}

	// If no size is present there's no need to generate any additional image as a backup.
	if ( empty( $metadata['sizes'] ) ) {
		return $metadata;
	}

	// All subsizes are created out of the `file` property and not the original image.
	$file = get_attached_file( $attachment_id, true );

	// File does not exist.
	if ( ! file_exists( $file ) ) {
		return $metadata;
	}

	if ( ! is_array( $backup_sizes ) ) {
		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		$backup_sizes = is_array( $backup_sizes ) ? $backup_sizes : array();
	}

	$dirname = pathinfo( $file, PATHINFO_DIRNAME );
	// Find the hash of the file name to use it as a identifier if present.
	preg_match( '/-e([\d]{13})\./', $file, $matches );

	// $matches would have at least 2 values if the regex above matches.
	if ( count( $matches ) >= 2 ) {
		$hash = $matches[1];
	} else {
		$hash = time() + mt_rand( 100, 999 );
	}

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
					// Test to make sure this is an edited version, if so we can remove it safely.
					preg_match( '/-e[\d]{13}-/', $file_name, $matches );
					if ( ! empty( $matches ) && file_exists( $file_location ) ) {
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
			$image = $editor->save( null, $mime );

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
	 * @return array<string, string> array with the mime type as the key and extension as the value.
	 */
	return (array) apply_filters( 'webp_uploads_images_with_multiple_mime_types', $valid_formats );
}

/**
 * Callback used to return a callback used by `webp_uploads_ajax_wp_die_handler` filter.
 *
 * @since n.e.x.t
 *
 * @see   wp_die_ajax_handler
 *
 * @param callable $callback A callback executed after the images operation (restore, creation).
 *
 * @return callback A function that would be used as a callback.
 */
function webp_uploads_wp_die_ajax_handler( $callback ) {
	if (
		isset( $_REQUEST['action'], $_REQUEST['do'], $_REQUEST['postid'] )
		&& 'image-editor' === $_REQUEST['action']
		&& is_numeric( $_REQUEST['postid'] )
	) {
		$attachment_id = absint( $_REQUEST['postid'] );
		switch ( $_REQUEST['do'] ) {
			case 'restore':
				webp_uploads_restore_image_from_backup( $attachment_id );
				break;
			case 'save':
				webp_uploads_update_background_sizes( $attachment_id );
				break;
		}
	}

	return $callback;
}

/**
 * Update the keys on the backup sizes, by reordering the keys in the meta `_wp_attachment_backup_sizes`,
 * files are removed only if the constant `IMAGE_EDIT_OVERWRITE` is defined to `true` and if the files
 * were edited files, this pattern follows the same criteria used by WordPress core to handle edited
 * images.
 *
 * @since n.e.x.t
 *
 * @see   webp_uploads_ajax_wp_die_handler
 *
 * @param int $attachment_id ID of the attachment being modified.
 */
function webp_uploads_restore_image_from_backup( $attachment_id ) {
	$metadata = wp_get_attachment_metadata( $attachment_id );
	// This should take place only on the JPEG image.
	$valid_mime_types = webp_uploads_valid_image_mime_types();

	// Not a supported mime type to create the sources property.
	if ( ! array_key_exists( get_post_mime_type( $attachment_id ), $valid_mime_types ) ) {
		return;
	}

	// If no size is present there's no need to generate any additional image as a backup.
	if ( empty( $metadata['sizes'] ) ) {
		// TODO: Maybe delete the meta key and files depending on `IMAGE_EDIT_OVERWRITE` ? if no size is present.
		return;
	}

	// All subsizes are created out of the `file` property and not the original image.
	$file = get_attached_file( $attachment_id, true );

	// File does not exist.
	if ( ! file_exists( $file ) ) {
		// TODO: Maybe delete the meta key and files depending on `IMAGE_EDIT_OVERWRITE` ? if the file no longer exists.
		return;
	}

	$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
	$backup_sizes = is_array( $backup_sizes ) ? $backup_sizes : array();
	$dirname      = pathinfo( $file, PATHINFO_DIRNAME );
	foreach ( webp_uploads_get_image_sizes() as $size => $properties ) {
		// Generate backups only for the missing mime types.
		$formats = webp_uploads_get_remaining_image_mimes( $metadata, $size );

		foreach ( $formats as $mime => $extension ) {
			$key = $extension . '-' . $size;

			// The original values for this mime type does not exist.
			if ( ! array_key_exists( "{$key}-orig", $backup_sizes ) ) {
				continue;
			}

			// The key is not present on the backup sizes, create an empty array to fill with `-orig` values.
			if ( ! array_key_exists( $key, $backup_sizes ) ) {
				$backup_sizes[ $key ] = array();
			}

			if ( defined( 'IMAGE_EDIT_OVERWRITE' ) && IMAGE_EDIT_OVERWRITE ) {
				$file_name = empty( $backup_sizes[ $key ]['file'] ) ? '' : $backup_sizes[ $key ]['file'];
				$file_path = path_join( $dirname, $file_name );
				// delete file only if is an edited version, otherwise keep it as it is.
				preg_match( '/-e[\d]{13}-/', $file_name, $matches );
				if ( ! empty( $matches ) && file_exists( $file_path ) ) {
					wp_delete_file( $file_path );
				}
			}

			// Restore properties from -orig to the original key value.
			foreach ( $backup_sizes[ "{$key}-orig" ] as $property => $value ) {
				$backup_sizes[ $key ][ $property ] = $value;
			}

			// "-orig" key is not removed, respect current core behavior.
			// @see https://core.trac.wordpress.org/ticket/55150
			// unset( $backup_sizes[ "{$key}-orig"] );
		}
	}

	update_post_meta( $attachment_id, '_wp_attachment_backup_sizes', $backup_sizes );
}

/**
 * Function that would backup the current sizes and then create the sizes based on the new
 * image edited.
 *
 * @param int        $attachment_id The ID of the attachment being updated.
 * @param array|null $backup_sizes  An array with the current backup sizes if `null` it would be queried from the meta table.
 * @return void
 */
function webp_uploads_update_background_sizes( $attachment_id, $backup_sizes = null ) {
	// This should take place only on the JPEG image.
	$valid_mime_types = webp_uploads_valid_image_mime_types();

	// Not a supported mime type to create the sources' property.
	if ( ! array_key_exists( get_post_mime_type( $attachment_id ), $valid_mime_types ) ) {
		return;
	}

	// All subsizes are created out of the `file` property and not the original image.
	$file = get_attached_file( $attachment_id, true );

	// File does not exist.
	if ( ! file_exists( $file ) ) {
		// TODO: Handle the scenario when the file was deleted, maybe delete the meta could be an option?
		return;
	}

	if ( null === $backup_sizes ) {
		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
	}

	if ( ! is_array( $backup_sizes ) ) {
		$backup_sizes = array();
	}

	$metadata = wp_get_attachment_metadata( $attachment_id );

	// Backup current sizes into "-orig" size.
	foreach ( webp_uploads_get_image_sizes() as $size => $properties ) {
		// Generate backups only for the missing mime types.
		$formats = webp_uploads_get_remaining_image_mimes( $metadata, $size );

		foreach ( $formats as $mime => $extension ) {
			$key = $extension . '-' . $size;
			if ( ! array_key_exists( $key, $backup_sizes ) ) {
				// The backup image not even exists yet, so we can't back up a non-existing image.
				continue;
			}

			// The actual original image has been backup already nothing to do for us here.
			if ( array_key_exists( "{$key}-orig", $backup_sizes ) ) {
				continue;
			}
			$backup_sizes[ $key . '-orig' ] = $backup_sizes[ $key ];
			unset( $backup_sizes[ $key ] );
		}
	}

	webp_uploads_create_images_with_additional_mime_types( $metadata, $attachment_id, $backup_sizes );

	/**
	 * The only reason to trigger this filter at this point is to indicate plugins that the metadata
	 * of the main image was updated, in this case it was the backup sizes structure, which allows
	 * external plugins an opportunity to process the newly created images for each additional mime type.
	 */
	apply_filters( 'wp_update_attachment_metadata', $metadata, $attachment_id );
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
