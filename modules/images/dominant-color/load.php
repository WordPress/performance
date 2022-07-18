<?php
/**
 * Module Name: Dominant Color
 * Description: Adds support to store dominant color for an image and create a placeholder background with that color.
 * Experimental: No
 *
 * @package performance-lab
 * @since 1.2.0
 */

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
	$dominant_color_data = _dominant_color_get_dominant_color_data( $attachment_id );
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
 * Filter various image attributes to add the dominant color to the image
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
		if ( empty( $attr['style'] ) ) {
			$attr['style'] = '';
		}
		$attr['style'] .= '--dominant-color: #' . esc_attr( $image_meta['dominant_color'] ) . ';';
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
	$style       = '';
	$extra_class = '';

	if ( ! empty( $image_meta['dominant_color'] ) ) {
		$data .= sprintf( 'data-dominant-color="%s" ', esc_attr( $image_meta['dominant_color'] ) );
		$style = 'style="--dominant-color: #' . esc_attr( $image_meta['dominant_color'] ) . ';" ';
	}

	if ( isset( $image_meta['has_transparency'] ) ) {
		$transparency = $image_meta['has_transparency'] ? 'true' : 'false';
		$data        .= sprintf( 'data-has-transparency="%s" ', $transparency );
		$extra_class  = $image_meta['has_transparency'] ? 'has-transparency' : 'not-transparent';
	}

	if ( ! empty( $data ) || ! empty( $style ) ) {
		$filtered_image = str_replace( '<img ', '<img ' . $data . $style, $filtered_image );
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
	 * @param string $content the content to filter.
	 * @param string $context the context of the content.
	 * @return string content
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
	wp_register_style( $handle, false );
	wp_enqueue_style( $handle );
	$custom_css = 'img[data-dominant-color]:not(.has-transparency) { background-color: var(--dominant-color); background-clip: content-box, padding-box; }';
	wp_add_inline_style( $handle, $custom_css );
}
add_filter( 'wp_enqueue_scripts', 'dominant_color_add_inline_style' );


/**
 * Overloads wp_image_editors() to load the extended classes.
 *
 * @since 1.2.0
 *
 * @param string[] $editors Array of available image editor class names. Defaults are 'WP_Image_Editor_Imagick', 'WP_Image_Editor_GD'.
 * @return string[] Registered image editors class names.
 */
function dominant_color_set_image_editors( $editors ) {
	if ( ! class_exists( 'Dominant_Color_Image_Editor_GD' ) ) {
		require_once __DIR__ . '/class-dominant-color-image-editor-gd.php';
	}
	if ( ! class_exists( 'Dominant_Color_Image_Editor_Imagick' ) ) {
		require_once __DIR__ . '/class-dominant-color-image-editor-imagick.php';
	}

	$replaces = array(
		'WP_Image_Editor_GD'      => 'Dominant_Color_Image_Editor_GD',
		'WP_Image_Editor_Imagick' => 'Dominant_Color_Image_Editor_Imagick',
	);

	foreach ( $replaces as $old => $new ) {
		$key = array_search( $old, $editors, true );
		if ( false !== $key ) {
			$editors[ $key ] = $new;
		}
	}

	return $editors;
}

/**
 * Computes the dominant color of the given attachment image and whether it has transparency.
 *
 * @since 1.2.0
 * @access private
 *
 * @param int $attachment_id The attachment ID.
 * @return array|WP_Error Array with the dominant color and has transparency values or WP_Error on error.
 */
function _dominant_color_get_dominant_color_data( $attachment_id ) {
	$mime_type = get_post_mime_type( $attachment_id );
	if ( 'application/pdf' === $mime_type ) {
		return new WP_Error( 'no_image_found', __( 'Unable to load image.', 'performance-lab' ) );
	}
	$file = wp_get_attachment_file_path( $attachment_id );
	if ( ! $file ) {
		$file = get_attached_file( $attachment_id );
	}
	add_filter( 'wp_image_editors', 'dominant_color_set_image_editors' );
	$editor = wp_get_image_editor(
		$file,
		array(
			'methods' => array(
				'get_dominant_color',
				'has_transparency',
			),
		)
	);
	remove_filter( 'wp_image_editors', 'dominant_color_set_image_editors' );

	if ( is_wp_error( $editor ) ) {
		return $editor;
	}

	$has_transparency = $editor->has_transparency();
	if ( is_wp_error( $has_transparency ) ) {
		return $has_transparency;
	}
	$dominant_color_data['has_transparency'] = $has_transparency;

	$dominant_color = $editor->get_dominant_color();
	if ( is_wp_error( $dominant_color ) ) {
		return $dominant_color;
	}
	$dominant_color_data['dominant_color'] = $dominant_color;

	return $dominant_color_data;
}

/**
 * Gets file path of image based on size.
 *
 * @since 1.2.0
 *
 * @param int    $attachment_id Attachment ID for image.
 * @param string $size          Optional. Image size. Default 'thumbnail'.
 * @return false|string Path to an image or false if not found.
 */
function wp_get_attachment_file_path( $attachment_id, $size = 'medium' ) {
	$imagedata = wp_get_attachment_metadata( $attachment_id );
	if ( ! is_array( $imagedata ) ) {
		return false;
	}

	if ( ! isset( $imagedata['sizes'][ $size ] ) ) {
		return false;
	}

	$file = get_attached_file( $attachment_id );

	$filepath = str_replace( wp_basename( $file ), $imagedata['sizes'][ $size ]['file'], $file );

	return $filepath;
}

/**
 * Gets the dominant color for an image attachment.
 *
 * @since 1.3.0
 *
 * @param int $attachment_id Attachment ID for image.
 * @return string|null Hex value of dominant color or null if not set.
 */
function dominant_color_get_dominant_color( $attachment_id ) {
	if ( ! wp_attachment_is_image( $attachment_id ) ) {
		return null;
	}
	$image_meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! is_array( $image_meta ) ) {
		return null;
	}

	if ( ! isset( $image_meta['dominant_color'] ) ) {
		return null;
	}

	return $image_meta['dominant_color'];
}

/**
 * Returns whether an image attachment has transparency.
 *
 * @since 1.3.0
 *
 * @param int $attachment_id Attachment ID for image.
 * @return bool|null Whether the image has transparency, or null if not set.
 */
function dominant_color_has_transparency( $attachment_id ) {
	$image_meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! is_array( $image_meta ) ) {
		return null;
	}

	if ( ! isset( $image_meta['has_transparency'] ) ) {
		return null;
	}

	return $image_meta['has_transparency'];
}


/**
 * Gets hex color from RGB.
 *
 * @since 1.3.0
 *
 * @param int $red Red 0-255.
 * @param int $green Green 0-255.
 * @param int $blue Blue 0-255.
 *
 * @return string|null Hex color or null if error.
 */
function dominant_color_rgb_to_hex( $red, $green, $blue ) {
	$range = range( 0, 255 );
	if ( ! in_array( $red, $range, true ) || ! in_array( $green, $range, true ) || ! in_array( $blue, $range, true ) ) {
		return null;
	}

	return sprintf( '%02x%02x%02x', $red, $green, $blue );
}
