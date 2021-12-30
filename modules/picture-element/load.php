<?php
/**
 * Module Name: Picture Element
 * Description: Replaces default image tags with <picture> elements supporting a primary and fallback image.
 * Focus: images
 * Experimental: No
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Potentially wrap content images in a picture element.
 *
 * @since n.e.x.t
 *
 * @param string $content The content to be filtered.
 */
function wrap_images_in_picture_element( $content ) {
	$pattern = '/(<img[^>]+>)/';
	$images  = preg_match_all( $pattern, $content, $matches );
	if ( $images ) {
		foreach ( $matches[0] as $image ) {
			// Wrap WordPress images where we can extract an attachment id.
			if ( preg_match( '/wp-image-([0-9]+)/i', $image, $class_id ) ) {
				$attachment_id = absint( $class_id[1] );
				$new_image     = wrap_image_in_picture( $image, $attachment_id );
				if ( false !== $new_image ) {
					$content = str_replace( $image, $new_image, $content );
				}
			}
		}
	}

	return $content;
}
add_filter( 'the_content', 'wrap_images_in_picture_element' );

/**
 * Wrap an image tag in a picture element.
 *
 * @since n.e.x.t
 *
 * @param string $image         The image tag.
 * @param int    $attachment_id The attachment id.
 *
 * @return string The new image tag.
 */
function wrap_image_in_picture( $image, $attachment_id ) {
	$image_meta              = wp_get_attachment_metadata( $attachment_id );
	$original_file_mime_type = get_post_mime_type( $attachment_id );
	if ( false === $original_file_mime_type ) {
		return false;
	}

	// Collect all of the sub size image mime types.
	$sub_size_mime_types_heap = array();
	foreach ( $image_meta['sizes'] as $size ) {
		$sub_size_mime_types_heap[ $size['mime-type'] ] = true;
	}
	$sub_size_mime_types = array_keys( $sub_size_mime_types_heap );

	/**
	 * Filter the image mime types that can used for the <picture> element.
	 *
	 * The mime types will be used in the order they are provided.
	 * The original image will be used as the fallback.
	 *
	 * Returning an empty array will prevent the picture element from being applied.
	 *
	 * The image being evaluated's attachment_id is provided for context.
	 */
	$enabled_mime_types = apply_filters(
		'wp_picture_element_mime_types',
		array(
			'image/webp',
		),
		$attachment_id
	);

	$mime_types = array_filter(
		$sub_size_mime_types,
		function( $mime_type ) use ( $enabled_mime_types ) {
			return in_array( $mime_type, $enabled_mime_types, true );
		}
	);

	if ( ! $mime_types ) {
		return false;
	}

	// If the original mime types is the only one available, no picture element is needed.
	if ( 1 === count( $mime_types ) && $mime_types[0] === $original_file_mime_type ) {
		return false;
	}

	// Add each mime type to the sources.
	$sources = '';
	foreach ( $mime_types as $image_mime_type ) {
		$image_srcset = wp_get_attachment_image_srcset( $attachment_id );
		$sources .= sprintf(
			'<source type="%s" srcset="%s">',
			$image_mime_type,
			$image_srcset
		);
	}

	// Fall back to the original image.
	add_filter( 'wp_calculate_image_srcset_meta', '__return_false' );
	$original_image_without_srcset = wp_get_attachment_image( $attachment_id, 'full' );
	remove_filter( 'wp_calculate_image_srcset_meta', '__return_false' );

	return sprintf(
		'<picture>%s %s</picture>',
		$sources,
		$original_image_without_srcset
	);
}
