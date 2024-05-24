<?php
/**
 * Add Picture Element support
 * Description: Use <picture> element when image has more than one mime type.
 * Experimental: No
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Potentially wrap an image tag in a picture element.
 *
 * @since n.e.x.t
 *
 * @param string $image         The image tag.
 * @param string $context       The context for the image tag.
 * @param int    $attachment_id The attachment id.
 *
 * @return string The new image tag.
 */
function webp_uploads_wrap_image_in_picture( string $image, string $context, int $attachment_id ): string {
	if ( 'the_content' !== $context ) {
		return $image;
	}
	$image_meta              = wp_get_attachment_metadata( $attachment_id );
	$original_file_mime_type = get_post_mime_type( $attachment_id );
	if ( false === $original_file_mime_type || ! isset( $image_meta['sizes'] ) ) {
		return $image;
	}

	// Collect all of the sub size image mime types.
	$mime_type_data = array();
	foreach ( $image_meta['sizes'] as $size ) {
		if ( isset( $size['sources'] ) && isset( $size['width'] ) && isset( $size['height'] ) ) {
			foreach ( $size['sources'] as $mime_type => $data ) {
				$mime_type_data[ $mime_type ]                         = $mime_type_data[ $mime_type ] ?? array();
				$mime_type_data[ $mime_type ]['w'][ $size['width'] ]  = $data;
				$mime_type_data[ $mime_type ]['h'][ $size['height'] ] = $data;
			}
		}
	}
	$sub_size_mime_types = array_keys( $mime_type_data );

	/**
	 * Filter the image mime types that can be used for the <picture> element.
	 *
	 * Default is: ['image/webp', 'image/jpeg']. Returning an empty array will skip using the picture element.
	 *
	 * The mime types will output in the picture element in the order they are provided.
	 * The original image will be used as the fallback image for browsers that don't support the picture element.
	 *
	 * @since n.e.x.t
	 * @param string[] $mime_types    Mime types than can be used.
	 * @param int      $attachment_id The id of the image being evaluated.
	 */
	$enabled_mime_types = apply_filters(
		'webp_uploads_picture_element_mime_types',
		array(
			'image/webp',
			'image/jpeg',
		),
		$attachment_id
	);

	$mime_types = array_filter(
		$enabled_mime_types,
		static function ( $mime_type ) use ( $sub_size_mime_types ) {
			return in_array( $mime_type, $sub_size_mime_types, true );
		}
	);

	// No eligible mime types.
	if ( ! $mime_types ) {
		return $image;
	}

	// If the original mime types is the only one available, no picture element is needed.
	if ( 1 === count( $mime_types ) && $mime_types[0] === $original_file_mime_type ) {
		return $image;
	}

	// Add each mime type to the picture's sources.
	$picture_sources = '';

	// Extract sizes using regex to parse image tag, then use to retrieve tag.
	$image_sizes = array();
	preg_match( '/width="([^"]+)"/', $image, $image_sizes );
	$width = isset( $image_sizes[1] ) ? (int) $image_sizes[1] : false;
	preg_match( '/height="([^"]+)"/', $image, $image_sizes );
	$height      = isset( $image_sizes[1] ) ? (int) $image_sizes[1] : false;
	$size_to_use = ( $width && $height ) ? array( $width, $height ) : 'full';

	$image_src = wp_get_attachment_image_src( $attachment_id, $size_to_use, false );
	if ( ! $image_src ) {
		return $image;
	}
	list( $src, $width, $height ) = $image_src;
	$size_array                   = array( absint( $width ), absint( $height ) );

	foreach ( $mime_types as $image_mime_type ) {
		$sizes = wp_calculate_image_sizes( $size_array, $src, $image_meta, $attachment_id );
		// Filter core's wp_get_attachment_image_srcset to return the sources for the current mime type.

		add_filter(
			'wp_calculate_image_srcset',
			static function ( $sources ) use ( $mime_type_data, $image_mime_type ) {
				$filtered_sources = array();
				foreach ( $sources as $source ) {
					// Swap the URL for the current mime type.
					if ( isset( $mime_type_data[ $image_mime_type ][ $source['descriptor'] ][ $source['value'] ] ) ) {
						$filename  = $mime_type_data[ $image_mime_type ][ $source['descriptor'] ][ $source['value'] ]['file'];
						$url_array = explode( '/', $source['url'] );
						array_pop( $url_array );

						$filtered_sources[] = array(
							'url'        => implode( '/', $url_array ) . '/' . $filename,
							'descriptor' => $source['descriptor'],
							'value'      => $source['value'],
						);
					}
				}
				return $filtered_sources;
			}
		);
		$image_srcset = wp_get_attachment_image_srcset( $attachment_id, $size_array, $image_meta );
		remove_all_filters( 'wp_calculate_image_srcset' );

		$picture_sources .= sprintf(
			'<source type="%s" srcset="%s" sizes="%s">',
			$image_mime_type,
			$image_srcset,
			$sizes
		);
	}

	// Fall back to the original image without a srcset.
	$original_sizes = array( $image_src[1], $image_src[2] );
	$original_image = wp_get_original_image_url( $attachment_id );
	// Fail gracefully if the original image is not found.
	if ( ! $original_image ) {
		return $image;
	}
	add_filter( 'wp_calculate_image_srcset_meta', '__return_false' );
	$original_image_without_srcset = wp_get_attachment_image( $attachment_id, $original_sizes, false, array( 'src' => $original_image ) );
	remove_filter( 'wp_calculate_image_srcset_meta', '__return_false' );

	return sprintf(
		'<picture class=%s>%s %s</picture>',
		'wp-picture-' . $attachment_id,
		$picture_sources,
		$original_image_without_srcset
	);
}
