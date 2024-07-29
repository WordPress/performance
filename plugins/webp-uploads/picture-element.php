<?php
/**
 * Add `picture` element support
 *
 * @package webp-uploads
 *
 * @since 2.0.0
 */

/**
 * Potentially wrap an image tag in a picture element.
 *
 * @since 2.0.0
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

	// Collect all the sub size image mime types.
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
	 * Default is: ['image/avif', 'image/webp', 'image/jpeg']. Returning an empty array will skip using the `picture` element.
	 *
	 * The mime types will output in the picture element in the order they are provided.
	 * The original image will be used as the fallback image for browsers that don't support the picture element.
	 *
	 * @since 2.0.0
	 * @param string[] $mime_types    Mime types than can be used.
	 * @param int      $attachment_id The id of the image being evaluated.
	 */
	$enabled_mime_types = (array) apply_filters(
		'webp_uploads_picture_element_mime_types',
		array(
			'image/avif',
			'image/webp',
			'image/jpeg',
		),
		$attachment_id
	);

	$mime_types = array_intersect( $enabled_mime_types, $sub_size_mime_types );

	// No eligible mime types.
	if ( count( $mime_types ) === 0 ) {
		return $image;
	}

	// If the original mime types is the only one available, no picture element is needed.
	if ( 1 === count( $mime_types ) && current( $mime_types ) === $original_file_mime_type ) {
		return $image;
	}

	// Add each mime type to the picture's sources.
	$picture_sources = '';

	// Extract sizes using regex to parse image tag, then use to retrieve tag.
	$width     = 0;
	$height    = 0;
	$processor = new WP_HTML_Tag_Processor( $image );
	if ( $processor->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
		$width  = (int) $processor->get_attribute( 'width' );
		$height = (int) $processor->get_attribute( 'height' );
	}
	$size_to_use = ( $width > 0 && $height > 0 ) ? array( $width, $height ) : 'full';

	$image_src = wp_get_attachment_image_src( $attachment_id, $size_to_use );
	if ( false === $image_src ) {
		return $image;
	}
	list( $src, $width, $height ) = $image_src;
	$size_array                   = array( absint( $width ), absint( $height ) );

	// Get the sizes from the IMG tag.
	$sizes = $processor->get_attribute( 'sizes' );

	foreach ( $mime_types as $image_mime_type ) {
		// Filter core's wp_get_attachment_image_srcset to return the sources for the current mime type.
		$filter = static function ( $sources ) use ( $mime_type_data, $image_mime_type ): array {
			$filtered_sources = array();
			foreach ( $sources as $source ) {
				// Swap the URL for the current mime type.
				if ( isset( $mime_type_data[ $image_mime_type ][ $source['descriptor'] ][ $source['value'] ] ) ) {
					$filename           = $mime_type_data[ $image_mime_type ][ $source['descriptor'] ][ $source['value'] ]['file'];
					$filtered_sources[] = array(
						'url'        => dirname( $source['url'] ) . '/' . $filename,
						'descriptor' => $source['descriptor'],
						'value'      => $source['value'],
					);
				}
			}
			return $filtered_sources;
		};
		add_filter( 'wp_calculate_image_srcset', $filter );
		$image_srcset = wp_get_attachment_image_srcset( $attachment_id, $size_array, $image_meta );
		remove_filter( 'wp_calculate_image_srcset', $filter );
		if ( false === $image_srcset ) {
			continue;
		}
		$picture_sources .= sprintf(
			'<source type="%s"%s%s>',
			esc_attr( $image_mime_type ),
			is_string( $image_srcset ) ? sprintf( ' srcset="%s"', esc_attr( $image_srcset ) ) : '',
			is_string( $sizes ) ? sprintf( ' sizes="%s"', esc_attr( $sizes ) ) : ''
		);
	}

	return sprintf(
		'<picture class="%s" style="display: contents;">%s%s</picture>',
		esc_attr( 'wp-picture-' . $attachment_id ),
		$picture_sources,
		$image
	);
}
