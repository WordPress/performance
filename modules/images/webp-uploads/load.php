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
 * Require helper functions and specific integrations.
 */
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/rest-api.php';
require_once __DIR__ . '/image-edit.php';

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
 * @param array $metadata      An array with the metadata from this attachment.
 * @param int   $attachment_id The ID of the attachment where the hook was dispatched.
 * @return array An array with the updated structure for the metadata before is stored in the database.
 */
function webp_uploads_create_sources_property( array $metadata, $attachment_id ) {
	// This should take place only on the JPEG image.
	$valid_mime_transforms = webp_uploads_get_upload_image_mime_transforms();

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

	if (
		empty( $metadata['sources'][ $mime_type ] ) &&
		in_array( $mime_type, $valid_mime_transforms[ $mime_type ], true )
	) {
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
		if ( ! empty( $metadata['sources'][ $targeted_mime ] ) ) {
			continue;
		}

		// The targeted mime is not allowed in the current installation.
		if ( empty( $allowed_mimes[ $targeted_mime ] ) ) {
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

	// Make sure we have some sizes to work with, otherwise avoid any work.
	if ( empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
		return $metadata;
	}

	$sizes_with_mime_type_support = webp_uploads_get_image_sizes_additional_mime_type_support();

	foreach ( $metadata['sizes'] as $size_name => $properties ) {
		// Do nothing if this image size is not an array or is not allowed to have additional mime types.
		if ( ! is_array( $properties ) || empty( $sizes_with_mime_type_support[ $size_name ] ) ) {
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
				$properties['sources'][ $current_mime ]['filesize'] = wp_filesize( $file_location );
			}
			$metadata['sizes'][ $size_name ] = $properties;
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		foreach ( $valid_mime_transforms[ $mime_type ] as $mime ) {
			// If this property exists no need to create the image again.
			if ( ! empty( $properties['sources'][ $mime ] ) ) {
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
add_filter( 'wp_generate_attachment_metadata', 'webp_uploads_create_sources_property', 10, 2 );

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
 * Filter the image editor default output format mapping to select the most appropriate
 * output format depending on desired output formats and supported mime types by the image
 * editor.
 *
 * @since 1.0.0
 *
 * @param string $output_format The image editor default output format mapping.
 * @param string $filename      Path to the image.
 * @param string $mime_type     The source image mime type.
 * @return string The new output format mapping.
 */
function webp_uploads_filter_image_editor_output_format( $output_format, $filename, $mime_type ) {
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
add_filter( 'image_editor_output_format', 'webp_uploads_filter_image_editor_output_format', 10, 3 );

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

	$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
	$backup_sizes = is_array( $backup_sizes ) ? $backup_sizes : array();

	foreach ( $backup_sizes as $backup_size ) {
		if ( ! isset( $backup_size['sources'] ) || ! is_array( $backup_size['sources'] ) ) {
			continue;
		}

		$original_backup_size_mime = empty( $backup_size['mime-type'] ) ? '' : $backup_size['mime-type'];

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

			if ( ! is_array( $backup_properties ) || empty( $backup_properties['file'] ) ) {
				continue;
			}

			$backup_intermediate_file = str_replace( $basename, $backup_properties['file'], $file );
			if ( empty( $backup_intermediate_file ) ) {
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
			if ( ! is_array( $backup_mime_properties ) || empty( $backup_mime_properties['file'] ) ) {
				continue;
			}

			$full_size = str_replace( $basename, $backup_mime_properties['file'], $file );
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
}
add_action( 'delete_attachment', 'webp_uploads_remove_sources_files', 10, 1 );

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
	// Bail early if request is not for the frontend.
	if ( ! webp_uploads_in_frontend_body() ) {
		return $content;
	}

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
add_filter( 'the_content', 'webp_uploads_update_image_references', 10 );

/**
 * Finds all the urls with *.jpg and *.jpeg extension and updates with *.webp version for the provided image
 * for the specified image sizes, the *.webp references are stored inside of each size.
 *
 * @since 1.0.0
 * @since 1.3.0 Remove `webp_uploads_prefer_smaller_image_file` filter.
 *
 * @param string $original_image An <img> tag where the urls would be updated.
 * @param string $context        The context where this is function is being used.
 * @param int    $attachment_id  The ID of the attachment being modified.
 * @return string The updated img tag.
 */
function webp_uploads_img_tag_update_mime_type( $original_image, $context, $attachment_id ) {
	$image    = $original_image;
	$metadata = wp_get_attachment_metadata( $attachment_id );

	if ( empty( $metadata['file'] ) ) {
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

	// Replace sub sizes for the image if present.
	foreach ( $metadata['sizes'] as $size => $size_data ) {
		if ( empty( $size_data['file'] ) ) {
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

			/** This filter is documented in modules/images/webp-uploads/load.php */
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

	foreach ( $target_mimes as $target_mime ) {
		if ( $target_mime === $original_mime ) {
			continue;
		}

		if (
			! has_action( 'wp_footer', 'webp_uploads_wepb_fallback' ) &&
			$image !== $original_image &&
			'the_content' === $context &&
			'image/jpeg' === $original_mime &&
			'image/webp' === $target_mime
		) {
			add_action( 'wp_footer', 'webp_uploads_wepb_fallback' );
		}
	}

	return $image;
}

/**
 * Updates the references of the featured image to the a new image format if available, in the same way it
 * occurs in the_content of a post.
 *
 * @since 1.1.0
 *
 * @param string $html          The current HTML markup of the featured image.
 * @param int    $post_id       The current post ID where the featured image is requested.
 * @param int    $attachment_id The ID of the attachment image.
 * @return string The updated HTML markup.
 */
function webp_uploads_update_featured_image( $html, $post_id, $attachment_id ) {
	return webp_uploads_img_tag_update_mime_type( $html, 'post_thumbnail_html', $attachment_id );
}
add_filter( 'post_thumbnail_html', 'webp_uploads_update_featured_image', 10, 3 );

/**
 * Adds a fallback mechanism to replace webp images with jpeg alternatives on older browsers.
 *
 * @since 1.3.0
 */
function webp_uploads_wepb_fallback() {
	// Get mime type transforms for the site.
	$transforms = webp_uploads_get_upload_image_mime_transforms();

	// We need to add fallback only if jpeg alternatives for the webp images are enabled for the server.
	$preserve_jpegs_for_jpeg_transforms = isset( $transforms['image/jpeg'] ) && in_array( 'image/jpeg', $transforms['image/jpeg'], true ) && in_array( 'image/webp', $transforms['image/jpeg'], true );
	$preserve_jpegs_for_webp_transforms = isset( $transforms['image/webp'] ) && in_array( 'image/jpeg', $transforms['image/webp'], true ) && in_array( 'image/webp', $transforms['image/webp'], true );
	if ( ! $preserve_jpegs_for_jpeg_transforms && ! $preserve_jpegs_for_webp_transforms ) {
		return;
	}

	ob_start();

	?>
	( function( d, i, s, p ) {
		s = d.createElement( s );
		s.src = '<?php echo esc_url_raw( plugins_url( '/fallback.js', __FILE__ ) ); ?>';

		i = d.createElement( i );
		i.src = p + 'jIAAABXRUJQVlA4ICYAAACyAgCdASoCAAEALmk0mk0iIiIiIgBoSygABc6zbAAA/v56QAAAAA==';
		i.onload = function() {
			i.onload = undefined;
			i.src = p + 'h4AAABXRUJQVlA4TBEAAAAvAQAAAAfQ//73v/+BiOh/AAA=';
		};

		i.onerror = function() {
			d.body.appendChild( s );
		};
	} )( document, 'img', 'script', 'data:image/webp;base64,UklGR' );
	<?php
	$javascript = ob_get_clean();

	wp_print_inline_script_tag(
		preg_replace( '/\s+/', '', $javascript ),
		array(
			'id'            => 'webpUploadsFallbackWebpImages',
			'data-rest-api' => esc_url_raw( trailingslashit( get_rest_url() ) ),
		)
	);
}

/**
 * Returns an array of image size names that have secondary mime type output enabled. Core sizes and
 * core theme sizes are enabled by default.
 *
 * Developers can control the generation of additional mime images for all sizes using the
 * webp_uploads_image_sizes_with_additional_mime_type_support filter.
 *
 * @since 1.3.0
 *
 * @return array An array of image sizes that can have additional mime types.
 */
function webp_uploads_get_image_sizes_additional_mime_type_support() {
	$additional_sizes = wp_get_additional_image_sizes();
	$allowed_sizes    = array(
		'thumbnail'      => true,
		'medium'         => true,
		'medium_large'   => true,
		'large'          => true,
		'post-thumbnail' => true,
	);

	foreach ( $additional_sizes as $size => $size_details ) {
		$allowed_sizes[ $size ] = ! empty( $size_details['provide_additional_mime_types'] );
	}

	/**
	 * Filters whether additional mime types are allowed for image sizes.
	 *
	 * @since 1.3.0
	 *
	 * @param array $allowed_sizes A map of image size names and whether they are allowed to have additional mime types.
	 */
	$allowed_sizes = apply_filters( 'webp_uploads_image_sizes_with_additional_mime_type_support', $allowed_sizes );

	return $allowed_sizes;
}
