<?php
/**
 * Hook callbacks used for Dominant Color Images.
 *
 * @package performance-lab
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add the dominant color metadata to the attachment.
 *
 * @since 1.2.0
 *
 * @param array $metadata      The attachment metadata.
 * @param int   $attachment_id The attachment ID.
 * @return array $metadata The attachment metadata.
 */
function dominant_color_metadata( $metadata, $attachment_id ) {
	$dominant_color_data = dominant_color_get_dominant_color_data( $attachment_id );
	if ( ! is_wp_error( $dominant_color_data ) ) {
		if ( isset( $dominant_color_data['dominant_color'] ) ) {
			$metadata['dominant_color'] = $dominant_color_data['dominant_color'];
		}

		if ( isset( $dominant_color_data['has_transparency'] ) ) {
			$metadata['has_transparency'] = $dominant_color_data['has_transparency'];
		}
	}

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'dominant_color_metadata', 10, 2 );

/**
 * Filters various image attributes to add the dominant color to the image.
 *
 * @since 1.2.0
 *
 * @param array  $attr       Attributes for the image markup.
 * @param object $attachment Image attachment post.
 * @return mixed $attr Attributes for the image markup.
 */
function dominant_color_update_attachment_image_attributes( $attr, $attachment ) {
	$image_meta = wp_get_attachment_metadata( $attachment->ID );
	if ( ! is_array( $image_meta ) ) {
		return $attr;
	}

	if ( isset( $image_meta['has_transparency'] ) ) {
		$attr['data-has-transparency'] = $image_meta['has_transparency'] ? 'true' : 'false';

		$class = $image_meta['has_transparency'] ? 'has-transparency' : 'not-transparent';
		if ( empty( $attr['class'] ) ) {
			$attr['class'] = $class;
		} else {
			$attr['class'] .= ' ' . $class;
		}
	}

	if ( ! empty( $image_meta['dominant_color'] ) ) {
		$attr['data-dominant-color'] = esc_attr( $image_meta['dominant_color'] );
		$style_attribute             = empty( $attr['style'] ) ? '' : $attr['style'];
		$attr['style']               = '--dominant-color: #' . esc_attr( $image_meta['dominant_color'] ) . ';' . $style_attribute;
	}

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'dominant_color_update_attachment_image_attributes', 10, 2 );

/**
 * Filter image tags in content to add the dominant color to the image.
 *
 * @since 1.2.0
 *
 * @param string $filtered_image The filtered image.
 * @param string $context        The context of the image.
 * @param int    $attachment_id  The attachment ID.
 * @return string image tag
 */
function dominant_color_img_tag_add_dominant_color( $filtered_image, $context, $attachment_id ) {

	// Only apply this in `the_content` for now, since otherwise it can result in duplicate runs due to a problem with full site editing logic.
	if ( 'the_content' !== $context ) {
		return $filtered_image;
	}

	// Only apply the dominant color to images that have a src attribute that
	// starts with a double quote, ensuring escaped JSON is also excluded.
	if ( ! str_contains( $filtered_image, ' src="' ) ) {
		return $filtered_image;
	}

	// Ensure to not run the logic below in case relevant attributes are already present.
	if ( str_contains( $filtered_image, ' data-dominant-color="' ) || str_contains( $filtered_image, ' data-has-transparency="' ) ) {
		return $filtered_image;
	}

	$image_meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! is_array( $image_meta ) ) {
		return $filtered_image;
	}

	/**
	 * Filters whether dominant color is added to the image.
	 *
	 * You can set this to false in order disable adding the dominant color to the image.
	 *
	 * @since 1.2.0
	 *
	 * @param bool   $add_dominant_color Whether to add the dominant color to the image. default true.
	 * @param int    $attachment_id      The image attachment ID.
	 * @param array  $image_meta         The image meta data all ready set.
	 * @param string $filtered_image     The filtered image. html including img tag
	 * @param string $context            The context of the image.
	 */
	$check = apply_filters( 'dominant_color_img_tag_add_dominant_color', true, $attachment_id, $image_meta, $filtered_image, $context );
	if ( ! $check ) {
		return $filtered_image;
	}

	$data        = '';
	$extra_class = '';

	if ( ! empty( $image_meta['dominant_color'] ) ) {
		$data .= sprintf( 'data-dominant-color="%s" ', esc_attr( $image_meta['dominant_color'] ) );

		if ( str_contains( $filtered_image, ' style="' ) ) {
			$filtered_image = str_replace( ' style="', ' style="--dominant-color: #' . esc_attr( $image_meta['dominant_color'] ) . '; ', $filtered_image );
		} else {
			$filtered_image = str_replace( '<img ', '<img style="--dominant-color: #' . esc_attr( $image_meta['dominant_color'] ) . ';" ', $filtered_image );
		}
	}

	if ( isset( $image_meta['has_transparency'] ) ) {
		$transparency = $image_meta['has_transparency'] ? 'true' : 'false';
		$data        .= sprintf( 'data-has-transparency="%s" ', $transparency );
		$extra_class  = $image_meta['has_transparency'] ? 'has-transparency' : 'not-transparent';
	}

	if ( ! empty( $data ) ) {
		$filtered_image = str_replace( '<img ', '<img ' . $data, $filtered_image );
	}

	if ( ! empty( $extra_class ) ) {
		$filtered_image = str_replace( ' class="', ' class="' . $extra_class . ' ', $filtered_image );
	}

	return $filtered_image;
}
add_filter( 'wp_content_img_tag', 'dominant_color_img_tag_add_dominant_color', 20, 3 );

// We don't need to use this filter anymore as the filter wp_content_img_tag is used instead.
if ( version_compare( '6', $GLOBALS['wp_version'], '>=' ) ) {

	/**
	 * Filter the content to allow us to filter the image tags.
	 *
	 * @since 1.2.0
	 *
	 * @param string $content The content to filter.
	 * @param string $context The context of the content.
	 * @return string The updated $content.
	 */
	function dominant_color_filter_content_tags( $content, $context = null ) {
		if ( null === $context ) {
			$context = current_filter();
		}

		if ( ! preg_match_all( '/<(img)\s[^>]+>/', $content, $matches, PREG_SET_ORDER ) ) {
			return $content;
		}

		// List of the unique `img` tags found in $content.
		$images = array();

		foreach ( $matches as $match ) {
			list( $tag, $tag_name ) = $match;

			switch ( $tag_name ) {
				case 'img':
					if ( preg_match( '/wp-image-([0-9]+)/i', $tag, $class_id ) ) {
						$attachment_id = absint( $class_id[1] );

						if ( $attachment_id ) {
							// If exactly the same image tag is used more than once, overwrite it.
							// All identical tags will be replaced later with 'str_replace()'.
							$images[ $tag ] = $attachment_id;
							break;
						}
					}
					$images[ $tag ] = 0;
					break;
			}
		}

		// Reduce the array to unique attachment IDs.
		$attachment_ids = array_unique( array_filter( array_values( $images ) ) );

		if ( count( $attachment_ids ) > 1 ) {
			/*
			 * Warm the object cache with post and meta information for all found.
			 * images to avoid making individual database calls.
			 */
			_prime_post_caches( $attachment_ids, false, true );
		}

		foreach ( $matches as $match ) {
			// Filter an image match.
			if ( empty( $images[ $match[0] ] ) ) {
				continue;
			}

			$filtered_image = $match[0];
			$attachment_id  = $images[ $match[0] ];
			$filtered_image = dominant_color_img_tag_add_dominant_color( $filtered_image, $context, $attachment_id );

			if ( null !== $filtered_image && $filtered_image !== $match[0] ) {
				$content = str_replace( $match[0], $filtered_image, $content );
			}
		}
		return $content;
	}

	$filters = array( 'the_content', 'the_excerpt', 'widget_text_content', 'widget_block_content' );
	foreach ( $filters as $filter ) {
		add_filter( $filter, 'dominant_color_filter_content_tags', 20 );
	}
}

/**
 * Add CSS needed for to show the dominant color as an image background.
 *
 * @since 1.2.0
 */
function dominant_color_add_inline_style() {
	$handle = 'dominant-color-styles';
	// PHPCS ignore reason: Version not used since this handle is only registered for adding an inline style.
	// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_register_style( $handle, false );
	wp_enqueue_style( $handle );
	$custom_css = 'img[data-dominant-color]:not(.has-transparency) { background-color: var(--dominant-color); }';
	wp_add_inline_style( $handle, $custom_css );
}
add_filter( 'wp_enqueue_scripts', 'dominant_color_add_inline_style' );

/**
 * Displays the HTML generator tag for the Dominant Color Images plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 2.3.0
 */
function dominant_color_render_generator() {
	if (
		defined( 'DOMINANT_COLOR_IMAGES_VERSION' ) &&
		! str_starts_with( DOMINANT_COLOR_IMAGES_VERSION, 'Performance Lab ' )
	) {
		echo '<meta name="generator" content="Dominant Color Images ' . esc_attr( DOMINANT_COLOR_IMAGES_VERSION ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'dominant_color_render_generator' );
