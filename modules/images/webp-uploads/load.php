<?php
/**
 * Module Name: WebP Uploads
 * Description: Creates WebP versions for new JPEG image uploads if supported by the server.
 * Experimental: No
 *
 * @since   1.0.0
 * @package performance-lab
 */

/**
 * Hook called by `wp_generate_attachment_metadata` to create the `sources` property for every image
 * size, the sources' property would create a new image size with all the mime types specified in
 * `webp_uploads_get_supported_image_mime_transforms`. If the original image is one of the mimes from
 * `webp_uploads_get_supported_image_mime_transforms` the image is just added to the `sources` property and not
 * created again. If the uploaded attachment is not a supported mime by this function, the hook does not alter the
 * metadata of the attachment. In addition to every single size the `sources` property is added at the
 * top level of the image metadata to store the references for all the mime types for the `full` size image of the
 * attachment.
 *
 * @since 1.0.0
 *
 * @see   wp_generate_attachment_metadata()
 * @see   webp_uploads_get_supported_image_mime_transforms()
 *
 * @param array $metadata      An array with the metadata from this attachment.
 * @param int   $attachment_id The ID of the attachment where the hook was dispatched.
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

	// Make sure the top level `sources` key is a valid array.
	if ( ! isset( $metadata['sources'] ) || ! is_array( $metadata['sources'] ) ) {
		$metadata['sources'] = array();
	}

	if ( empty( $metadata['sources'][ $mime_type ] ) ) {
		$metadata['sources'][ $mime_type ] = array(
			'file'     => wp_basename( $file ),
			'filesize' => filesize( $file ),
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
	$allowed_mimes      = array_flip( wp_get_mime_types() );
	// Create the sources for the full sized image.
	foreach ( $valid_mime_transforms[ $mime_type ] as $targeted_mime ) {
		// If this property exists no need to create the image again.
		if ( ! empty( $metadata['sources'][ $targeted_mime ] ) ) {
			continue;
		}

		// The targeted mime is not allowed in the current installation.
		if ( empty( $allowed_mimes[ $targeted_mime ] ) ) {
			continue;
		}

		$extension   = explode( '|', $allowed_mimes[ $targeted_mime ] );
		$destination = trailingslashit( $original_directory ) . "{$filename}.{$extension[0]}";
		$image       = webp_uploads_generate_additional_image_source( $attachment_id, $original_size_data, $targeted_mime, $destination );

		if ( is_wp_error( $image ) ) {
			continue;
		}

		$metadata['sources'][ $targeted_mime ] = $image;
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	// Make sure we have some sizes to work with, otherwise avoid any work.
	if ( empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
		return $metadata;
	}

	foreach ( $metadata['sizes'] as $size_name => $properties ) {
		// This image size is not defined or not an array.
		if ( ! is_array( $properties ) ) {
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
		if ( empty( $current_mime ) ) {
			continue;
		}

		// Ensure a `sources` property exists on the existing size.
		if ( empty( $properties['sources'] ) || ! is_array( $properties['sources'] ) ) {
			$properties['sources'] = array();
		}

		if ( empty( $properties['sources'][ $current_mime ] ) ) {
			$properties['sources'][ $current_mime ] = array(
				'file'     => isset( $properties['file'] ) ? $properties['file'] : '',
				'filesize' => 0,
			);
			// Set the filesize from the current mime image.
			$file_location = path_join( $original_directory, $properties['file'] );
			if ( file_exists( $file_location ) ) {
				$properties['sources'][ $current_mime ]['filesize'] = filesize( $file_location );
			}
			$metadata['sizes'][ $size_name ] = $properties;
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		$formats = isset( $valid_mime_transforms[ $current_mime ] ) ? $valid_mime_transforms[ $current_mime ] : array();

		foreach ( $formats as $mime ) {
			// If this property exists no need to create the image again.
			if ( ! empty( $properties['sources'][ $mime ] ) ) {
				continue;
			}

			$source = webp_uploads_generate_image_size( $attachment_id, $size_name, $mime );
			if ( is_wp_error( $source ) ) {
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

add_filter( 'wp_generate_attachment_metadata', 'webp_uploads_create_sources_property', 10, 2 );

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

	return webp_uploads_generate_additional_image_source( $attachment_id, $size_data, $mime );
}

/**
 * Returns an array with the list of valid mime types that a specific mime type can be converted into it,
 * for example an image/jpeg can be converted into an image/webp.
 *
 * @since 1.0.0
 *
 * @return array<string, array<string>> An array of valid mime types, where the key is the mime type and the value is the extension type.
 */
function webp_uploads_get_supported_image_mime_transforms() {
	$image_mime_transforms = array(
		'image/jpeg' => array( 'image/webp' ),
		'image/webp' => array( 'image/jpeg' ),
	);

	$valid_transforms = array();
	/**
	 * Filter to allow the definition of a custom mime types, in which a defined mime type
	 * can be transformed and provide a wide range of mime types.
	 *
	 * @since 1.0.0
	 *
	 * @param array $image_mime_transforms A map with the valid mime transforms.
	 */
	$transforms = (array) apply_filters( 'webp_uploads_supported_image_mime_transforms', $image_mime_transforms );
	// Remove any invalid transform, by making sure all the transform values are arrays.
	foreach ( $transforms as $mime => $list_transforms ) {
		if ( ! is_array( $list_transforms ) ) {
			continue;
		}
		$valid_transforms[ $mime ] = $list_transforms;
	}
	return $valid_transforms;
}

/**
 * Creates a resized image with the provided dimensions out of an original attachment, the created image
 * would be saved in the specified mime and stored in the destination file. If the image can't be saved correctly
 * a WP_Error would be returned otherwise an array with the file and filesize properties.
 *
 * @since n.e.xt
 * @private
 *
 * @param int    $attachment_id         The ID of the attachment from where this image would be created.
 * @param array  $size_data             An array with the dimensions of the image: height, width and crop.
 * @param string $mime                  The target mime in which the image should be created.
 * @param string $destination_file_name The path where the file would be stored, including the extension. If empty, `generate_filename` is used to create the destination file name.
 * @return array|WP_Error An array with the file and filesize if the image was created correctly otherwise a WP_Error
 */
function webp_uploads_generate_additional_image_source( $attachment_id, array $size_data, $mime, $destination_file_name = null ) {
	$image_path = wp_get_original_image_path( $attachment_id );

	// File does not exist.
	if ( ! file_exists( $image_path ) ) {
		return new WP_Error( 'original_image_file_not_found', __( 'The original image file does not exists, subsizes are created out of the original image.', 'performance-lab' ) );
	}

	$editor = wp_get_image_editor( $image_path );

	if ( is_wp_error( $editor ) ) {
		return $editor;
	}

	$allowed_mimes = array_flip( wp_get_mime_types() );
	if ( ! isset( $allowed_mimes[ $mime ] ) || ! is_string( $allowed_mimes[ $mime ] ) ) {
		return new WP_Error( 'image_mime_type_invalid', __( 'The provided mime type is not allowed.', 'performance-lab' ) );
	}

	if ( ! wp_image_editor_supports( array( 'mime_type' => $mime ) ) ) {
		return new WP_Error( 'image_mime_type_not_supported', __( 'The provided mime type is not supported.', 'performance-lab' ) );
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
		$extension             = explode( '|', $allowed_mimes[ $mime ] );
		$destination_file_name = $editor->generate_filename( null, null, $extension[0] );
	}

	$image = $editor->save( $destination_file_name, $mime );

	if ( is_wp_error( $image ) ) {
		return $image;
	}

	if ( empty( $image['file'] ) ) {
		return new WP_Error( 'image_file_not_present', __( 'The file key is not present on the image data', 'performance-lab' ) );
	}

	return array(
		'file'     => $image['file'],
		'filesize' => isset( $image['path'] ) ? filesize( $image['path'] ) : 0,
	);
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
function webp_uploads_remove_sources_files( $attachment_id ) {
	$file = get_attached_file( $attachment_id );

	if ( empty( $file ) ) {
		return;
	}

	$metadata = wp_get_attachment_metadata( $attachment_id );
	// Make sure $sizes is always defined to allow the removal of original images after the first foreach loop.
	$sizes = ! isset( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ? array() : $metadata['sizes'];

	$upload_path = wp_get_upload_dir();
	if ( empty( $upload_path['basedir'] ) ) {
		return;
	}

	$intermediate_dir = path_join( $upload_path['basedir'], dirname( $file ) );
	$basename         = wp_basename( $file );

	foreach ( $sizes as $size ) {
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

			$intermediate_file = str_replace( $basename, $properties['file'], $file );
			if ( empty( $intermediate_file ) ) {
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

		if ( ! is_array( $properties ) || empty( $properties['file'] ) ) {
			continue;
		}

		$full_size = str_replace( $basename, $properties['file'], $file );
		if ( empty( $full_size ) ) {
			continue;
		}

		$full_size_file = path_join( $upload_path['basedir'], $full_size );
		if ( ! file_exists( $full_size_file ) ) {
			continue;
		}
		wp_delete_file_from_directory( $full_size_file, $intermediate_dir );
	}
}

add_action( 'delete_attachment', 'webp_uploads_remove_sources_files', 10, 1 );

/**
 * Filter on `wp_get_missing_image_subsizes` acting as an action for the logic of the plugin
 * to determine if additional mime types still need to be created.
 *
 * @since 1.0.0
 *
 * @see wp_get_missing_image_subsizes()
 *
 * @param array $missing_sizes Associative array of arrays of image sub-sizes.
 * @param array $image_meta The metadata from the image.
 * @param int   $attachment_id The ID of the attachment.
 * @return array Associative array of arrays of image sub-sizes.
 */
function webp_uploads_wp_get_missing_image_subsizes( $missing_sizes, $image_meta, $attachment_id ) {
	// Only setup the trace array if we no longer have more sizes.
	if ( ! empty( $missing_sizes ) ) {
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
	$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );

	foreach ( $trace as $element ) {
		if ( isset( $element['function'] ) && 'wp_update_image_subsizes' === $element['function'] ) {
			webp_uploads_create_sources_property( $image_meta, $attachment_id );
			break;
		}
	}

	return array();
}

add_filter( 'wp_get_missing_image_subsizes', 'webp_uploads_wp_get_missing_image_subsizes', 10, 3 );

/**
 * Filters `the_content` to update images so that they use the preferred MIME type where possible.
 *
 * By default, this is `image/webp`, if the current attachment contains the targeted MIME
 * type. In the near future this will be filterable.
 *
 * Note that most of this function will not be needed for an eventual core implementation as it
 * would rely on `wp_filter_content_tags()`.
 *
 * @since 1.0.0
 *
 * @see wp_filter_content_tags()
 *
 * @param string $content The content of the current post.
 * @return string The content with the updated references to the images.
 */
function webp_uploads_update_image_references( $content ) {
	// This content does not have any tag on it, move forward.
	if ( ! preg_match_all( '/<(img)\s[^>]+>/', $content, $img_tags, PREG_SET_ORDER ) ) {
		return $content;
	}

	$images = array();
	foreach ( $img_tags as list( $img ) ) {
		// Find the ID of each image by the class.
		if ( ! preg_match( '/wp-image-([\d]+)/i', $img, $class_name ) ) {
			continue;
		}

		if ( empty( $class_name ) ) {
			continue;
		}

		// Make sure we use the last item on the list of matches.
		$attachment_id = (int) $class_name[1];

		if ( ! $attachment_id ) {
			continue;
		}

		$images[ $img ] = $attachment_id;
	}

	$attachment_ids = array_unique( array_filter( array_values( $images ) ) );
	if ( count( $attachment_ids ) > 1 ) {
		/**
		 * Warm the object cache with post and meta information for all found
		 * images to avoid making individual database calls.
		 */
		_prime_post_caches( $attachment_ids, false, true );
	}

	foreach ( $images as $img => $attachment_id ) {
		$content = str_replace( $img, webp_uploads_img_tag_update_mime_type( $img, 'the_content', $attachment_id ), $content );
	}

	return $content;
}

/**
 * Finds all the urls with *.jpg and *.jpeg extension and updates with *.webp version for the provided image
 * for the specified image sizes, the *.webp references are stored inside of each size.
 *
 * @since 1.0.0
 *
 * @param string $image         An <img> tag where the urls would be updated.
 * @param string $context       The context where this is function is being used.
 * @param int    $attachment_id The ID of the attachment being modified.
 * @return string The updated img tag.
 */
function webp_uploads_img_tag_update_mime_type( $image, $context, $attachment_id ) {
	$metadata = wp_get_attachment_metadata( $attachment_id );
	if ( empty( $metadata['file'] ) ) {
		return $image;
	}

	// TODO: Add a filterable option to determine image extensions, see https://github.com/WordPress/performance/issues/187 for more details.
	$target_image_extensions = array(
		'jpg',
		'jpeg',
	);

	// Creates a regular extension to find all the URLS with the provided extension for img tag.
	preg_match_all( '/[^\s"]+\.(?:' . implode( '|', $target_image_extensions ) . ')/i', $image, $matches );
	if ( empty( $matches ) ) {
		return $image;
	}

	$urls = $matches[0];
	// TODO: Add a filterable option to change the selected mime type. See https://github.com/WordPress/performance/issues/187.
	$target_mime = 'image/webp';

	$basename = wp_basename( $metadata['file'] );
	foreach ( $urls as $url ) {
		$src_filename = wp_basename( $url );

		// Replace the full size image if present.
		if ( isset( $metadata['sources'][ $target_mime ]['file'] ) && strpos( $url, $basename ) !== false ) {
			$image = str_replace( $src_filename, $metadata['sources'][ $target_mime ]['file'], $image );
			continue;
		}

		if ( empty( $metadata['sizes'] ) ) {
			continue;
		}

		$extension = wp_check_filetype( $src_filename );
		// Extension was not set properly no action possible or extension is already in the expected mime.
		if ( empty( $extension['type'] ) || $extension['type'] === $target_mime ) {
			continue;
		}

		// Find the appropriate size for the provided URL.
		foreach ( $metadata['sizes'] as $name => $size_data ) {
			// Not the size we are looking for.
			if ( empty( $size_data['file'] ) || $src_filename !== $size_data['file'] ) {
				continue;
			}

			if ( empty( $size_data['sources'][ $target_mime ]['file'] ) ) {
				continue;
			}

			// This is the same as the file we want to replace nothing to do here.
			if ( $size_data['sources'][ $target_mime ]['file'] === $src_filename ) {
				continue;
			}

			$image = str_replace( $src_filename, $size_data['sources'][ $target_mime ]['file'], $image );
			break;
		}
	}

	return $image;
}

add_filter( 'the_content', 'webp_uploads_update_image_references', 10 );

/**
 * Returns the attachment sources array ordered by filesize.
 *
 * @since n.e.xt
 * @private
 *
 * @param int    $attachment_id The attachment ID.
 * @param string $size          The attachment size.
 * 
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
	if ( isset( $metadata['sizes'][ $size ]['sources'] ) && ! empty( $metadata['sizes'][ $size ]['sources'] ) ) {
		return $metadata['sizes'][ $size ]['sources'];	
	}

	// Return an empty array if no sources found.
	return [];
}

/**
* Returns the attachment smallest filesize source.
 *
 * @since n.e.xt
 * @private
 *
 * @param int    $attachment_id The attachment ID.
 * @param string $size          The attachment size.
 * 
 * @return string The attachment source filename.
 */
function webp_uploads_get_smallest_file_src( $attachment_id, $size = 'thumbnail' ) {
	$sources = webp_uploads_get_attachment_sources( $attachment_id, $size );

	// TODO: Filter out unsupported mime types.

	// Order sources on fileszie in ascending order.
	uasort(
		$sources,
		function( $a, $b ) {
			if ( $a['filesize'] === $b['filesize'] ) {
				return 0;
			}

			return ($a['filesize'] < $b['filesize']) ? -1 : 1;
		}
	);

	// Get the smallest file size source.
	$source = array_shift( $sources );

	return $source['file'];
}