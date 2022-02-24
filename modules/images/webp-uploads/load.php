<?php
/**
 * Module Name: WebP Uploads
 * Description: Uses WebP as the default format for new JPEG image uploads if the server supports it.
 * Experimental: No
 *
 * @since   1.0.0
 * @package performance-lab
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
 * @see   wp_generate_attachment_metadata()
 * @see   webp_uploads_valid_image_mime_types()
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
			if ( empty( $sources[ $mime ] ) ) {
				$source = webp_uploads_generate_image_size( $attachment_id, $size_name, $mime );
				if ( is_array( $source ) ) {
					$sources[ $mime ] = $source;
				}
			}
		}

		$current_size['sources']         = $sources;
		$metadata['sizes'][ $size_name ] = $current_size;
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

	return array(
		'file'     => $image['file'],
		'filesize' => array_key_exists( 'path', $image ) ? filesize( $image['path'] ) : 0,
	);
}

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
	 * @param array $image_mime_transforms A map with the valid mime transforms.
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
 * @see wp_delete_attachment()
 *
 * @param int $attachment_id The ID of the attachment the sources are going to be deleted.
 */
function webp_uploads_remove_sources_files( $attachment_id ) {
	$metadata = wp_get_attachment_metadata( $attachment_id );
	$file     = get_attached_file( $attachment_id );

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

	$intermediate_dir = path_join( $upload_path['basedir'], dirname( $file ) );
	$basename         = wp_basename( $file );

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

			$intermediate_file = str_replace( $basename, $properties['file'], $file );
			if ( ! empty( $intermediate_file ) ) {
				$intermediate_file = path_join( $upload_path['basedir'], $intermediate_file );
				wp_delete_file_from_directory( $intermediate_file, $intermediate_dir );
			}
		}
	}
}
add_action( 'delete_attachment', 'webp_uploads_remove_sources_files', 10, 1 );

/**
 * Filters on `the_content` to update the references for supported mime of images into the
 * `webp_uploads_preferred_mime_type()` for the most part `image/webp` if the current
 * attachment contains the targeted mime type.
 *
 * @since n.e.x.t
 *
 * @see the_content
 *
 * @param string $content The content of the current post.
 * @return string The content with the updated references to the images.
 */
function webp_uploads_update_image_references( $content ) {
	// This content does not have any tag on it, move forward.
	if ( ! preg_match_all( '/<(img)\s[^>]+>/', $content, $tags, PREG_SET_ORDER ) ) {
		return $content;
	}

	$images = array();
	foreach ( $tags as list( $tag ) ) {
		// Find the ID of each image by the class.
		if ( ! preg_match( '/wp-image-([\d]+)/i', $tag, $class_id ) ) {
			continue;
		}

		if ( empty( $class_id ) ) {
			continue;
		}

		$attachment_id = (int) end( $class_id );

		if ( ! $attachment_id ) {
			continue;
		}

		// Create an array if attachment_id has not been added to the images array.
		if ( empty( $images[ $attachment_id ] ) ) {
			$images[ $attachment_id ] = array();
		}

		// TODO: Possible a filter can be added here in order to detect the image extensions supported.
		$target_extensions = array(
			'jpeg',
			'jpg',
			'webp',
		);

		// Creates a regular extension to find all the files with the provided extensions above.
		preg_match_all( '/[^\s"]+\.(?:' . implode( '|', $target_extensions ) . ')/i', $tag, $matches );

		$urls = empty( $matches ) ? array() : reset( $matches );

		foreach ( $urls as $url ) {
			$images[ $attachment_id ][ $url ] = true;
		}
	}

	$target_mime = webp_uploads_preferred_mime_type();
	$replacement = array();
	foreach ( $images as $attachment_id => $urls ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( empty( $metadata['file'] ) ) {
			continue;
		}

		$basename = wp_basename( $metadata['file'] );

		foreach ( $urls as $url => $exists ) {

			if ( isset( $metadata['file'] ) && strpos( $url, $basename ) !== false ) {
				// TODO: we don't have a replacement for full image yet.
				continue;
			}

			if ( empty( $metadata['sizes'] ) ) {
				continue;
			}

			$src_filename = wp_basename( $url );
			$extension    = wp_check_filetype( $src_filename );

			// Extension was not set properly no action possible.
			if ( empty( $extension['type'] ) || $extension['type'] === $target_mime ) {
				continue;
			}

			foreach ( $metadata['sizes'] as $name => $size_data ) {

				// Not the size we are looking for.
				if ( $src_filename !== $size_data['file'] ) {
					continue;
				}

				if ( empty( $size_data['sources'][ $target_mime ]['file'] ) ) {
					continue;
				}

				$replacement[ $src_filename ] = $size_data['sources'][ $target_mime ]['file'];
			}
		}
	}

	// Replacement of URLs where the keys are the searched value and the values of each key the value to replace with.
	return str_replace( array_keys( $replacement ), array_values( $replacement ), $content );
}

add_filter( 'the_content', 'webp_uploads_update_image_references', 10 );

/**
 * Function to get access to the desired mime type of image to be used in specifc areas
 * like the content of a blog post, this function uses `webp_uploads_webp_is_supported`
 * in order to make sure WebP is supported before deciding with WebP as WebP would be
 * the default if is supported.
 *
 * @since n.e.x.t
 *
 * @return string The prefered mime type for images `image/webp` by default if WebP is supported.
 */
function webp_uploads_preferred_mime_type() {
	$preferred_mime = 'image/jpeg';

	if ( webp_uploads_webp_is_supported() ) {
		$preferred_mime = 'image/webp';
	}

	/**
	 * The preferred mime type for the images to be rendered.
	 *
	 * @since n.e.x.t
	 *
	 * @param string The preferred mime type for images.
	 *
	 * @return string The preferred mime type, image/webp by default if supported.
	 */
	return (string) apply_filters( 'wp_preferred_image_mime', $preferred_mime );
}

/**
 * Client in this context means the Browser making the request for a particular page
 * if the browser supports WebP the mime would be present in the `HTTP_ACCEPT` header.
 *
 * @since n.e.x.t
 *
 * @see https://developers.google.com/speed/webp/faq#server-side_content_negotiation_via_accept_headers
 *
 * @return bool If WebP is supported or not by the current client.
 */
function webp_uploads_webp_is_supported() {
	$webp_is_accepted = array_key_exists( 'HTTP_ACCEPT', $_SERVER ) && false !== strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' );

	/**
	 * Add a filter to bypass the detection of WebP in the client via HTTP_ACCEPT headers
	 * useful if a cache mechanism is storing the full HTML of the page.
	 *
	 * @since n.e.x.t
	 *
	 * @param bool $webp_is_accepted If WebP is accepted by the client requesting the page.
	 *
	 * @return bool If WebP is accepted or not on the current request.
	 */
	return (bool) apply_filters( 'webp_is_accepted', $webp_is_accepted );
}
