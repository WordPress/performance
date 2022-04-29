<?php
/**
 * Module Name: Dominant Color
 * Description: Adds support to store dominant-color for an image and create a placeholder background with that color.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Add the dominant color metadata to the attachment.
 *
 * @since n.e.x.t
 *
 * @param array $metadata The attachment metadata.
 * @param int   $attachment_id The attachment ID.
 *
 * @return array $metadata
 */
function dominant_color_metadata( $metadata, $attachment_id ) {
	if ( ! wp_attachment_is_image( $attachment_id ) ) {

		return $metadata;
	}

	$dominant_color = dominant_color_get( $attachment_id );

	if ( ! empty( $dominant_color ) ) {
		$metadata['dominant_color'] = $dominant_color;
	}

	$has_transparency = dominant_color_get_has_transparency( $attachment_id );

	if ( ! empty( $has_transparency ) ) {
		$metadata['has_transparency'] = $has_transparency;
	} else {
		$metadata['has_transparency'] = false;
	}

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'dominant_color_metadata', 10, 2 );

/**
 * Filter various image attributes to add the dominant color to the image
 *
 * @since n.e.x.t
 *
 * @param array  $attr Attributes for the image markup.
 * @param object $attachment Image attachment post.
 *
 * @return mixed
 */
function dominant_color_tag_add_adjust_to_image_attributes( $attr, $attachment ) {

	$image_meta = wp_get_attachment_metadata( $attachment->ID );
	if ( ! is_array( $image_meta ) ) {
		return $attr;
	}

	$has_transparency = isset( $image_meta['has_transparency'] ) ? $image_meta['has_transparency'] : false;

	$extra_class = '';
	if ( ! isset( $attr['style'] ) ) {
		$attr['style'] = ' --has-transparency: ' . $has_transparency . '; ';
	} else {
		$attr['style'] .= ' --has-transparency: ' . $has_transparency . '; ';
		$extra_class    = ' has-transparency ';
	}

	if ( isset( $image_meta['dominant_color'] ) ) {
		$dominant_color = $image_meta['dominant_color'];
	} else {
		/**
		 * Filters the default color to use when no dominant color is found.
		 *
		 * @since n.e.x.t
		 *
		 * @param string $default_color The default color 'cccccc'.
		 */
		$dominant_color = apply_filters( 'dominant_color_default_color', 'cccccc' );
	}

	if ( ! empty( $dominant_color ) ) {

		$attr['data-dominant-color'] = $dominant_color;

		if ( empty( $attr['style'] ) ) {
			$attr['style'] = '';
		}
		$attr['style'] .= '--dominant-color: #' . $image_meta['dominant_color'] . ';';

		$extra_class .= ( dominant_color_color_is_light( $image_meta['dominant_color'] ) ) ? 'dominant-color-light' : 'dominant-color-dark';
		if ( empty( $attr['class'] ) ) {
			$attr['class'] = '';
		}
		if ( isset( $attr['class'] ) && ! array_intersect( explode( ' ', $attr['class'] ), explode( ' ', $extra_class ) ) ) {
			$attr['class'] .= ' ' . $attr['class'];
		}
	}

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'dominant_color_tag_add_adjust_to_image_attributes', 10, 2 );

/**
 * Filter image tags in content to add the dominant color to the image.
 *
 * @since n.e.x.t
 *
 * @param string $filtered_image The filtered image.
 * @param string $context The context of the image.
 * @param int    $attachment_id The attachment ID.
 *
 * @return string image tag
 */
function dominant_color_img_tag_add_dominant_color( $filtered_image, $context, $attachment_id ) {
	/**
	 * Filters the image meta data before try to add the dominant color to the image tag.
	 *
	 * @since n.e.x.t
	 *
	 * @param array $image_meta The image meta returned by wp_get_attachment_metadata function.
	 */
	$image_meta = apply_filters( 'dominant_color_img_tag_add_dominant_color_meta', wp_get_attachment_metadata( $attachment_id ), $attachment_id  );

	if ( ! is_array( $image_meta ) || ! isset( $image_meta['has_transparency'] ) ) {

		return $filtered_image;
	}

	if ( isset( $image_meta['dominant_color'] ) ) {
		$dominant_color = $image_meta['dominant_color'];
	} else {
		/**
		 * Filters the default color to use when no dominant color is found.
		 *
		 * @since n.e.x.t
		 *
		 * @param string $default_color The default color 'cccccc'.
		 */
		$dominant_color = apply_filters( 'dominant_color_default_color', 'cccccc' );
	}

	if ( empty( $dominant_color ) ) {

		return $filtered_image;
	}

	/**
	 * Filters whether dominant color is added to the image.
	 * set to false inorder disable adding the dominant color to the image.
	 *
	 * @since n.e.x.t
	 *
	 * @param bool   $add_dominant_color_to_image Whether to add the dominant color to the image. default true.
	 * @param int    $attachment_id The image attachment ID.
	 * @param array  $image_meta The image meta data all ready set.
	 * @param string $filtered_image The filtered image. html including img tag
	 * @param string $context The context of the image.
	 */
	if ( apply_filters( 'dominant_color_img_tag_add_dominant_color', true, $attachment_id, $image_meta, $filtered_image, $context ) ) {
		$data  = sprintf( 'data-dominantColor="%s"', $dominant_color );
		$style = '';
		if ( str_contains( $filtered_image, 'loading="lazy"' ) ) {
			$style = ' style="--dominant-color: #' . $dominant_color . ';" ';
		}

		$extra_class = '';

		if ( true === $image_meta['has_transparency'] ) {
			$data       .= ' data-has-transparency="true"';
			$extra_class = ' has-transparency ';
		} else {
			$data .= ' data-has-transparency="false"';
		}

		$filtered_image = str_replace( '<img ', '<img ' . $data . $style, $filtered_image );

		$extra_class   .= ( dominant_color_color_is_light( $dominant_color ) ) ? 'dominant-color-light' : 'dominant-color-dark';
		$filtered_image = str_replace( 'class="', 'class="' . $extra_class . ' ', $filtered_image );
	}

	return $filtered_image;
}
add_filter( 'wp_content_img_tag', 'dominant_color_img_tag_add_dominant_color', 20, 3 );

// We don't need to use this filter anymore as the filter wp_content_img_tag is used instead.
if ( version_compare( '6', $GLOBALS['wp_version'], '>=' ) ) {

	/**
	 * Filter the content to allow us to filter the image tags.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $content the content to filter.
	 * @param string $context the context of the content.
	 *
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

		// Iterate through the matches in order of occurrence as it is relevant for whether or not to lazy-load.
		foreach ( $matches as $match ) {
			// Filter an image match.
			if ( isset( $images[ $match[0] ] ) ) {
				$filtered_image = $match[0];
				$attachment_id  = $images[ $match[0] ];
				$filtered_image = dominant_color_img_tag_add_dominant_color( $filtered_image, $context, $attachment_id );

				if ( $filtered_image !== $match[0] ) {
					$content = str_replace( $match[0], $filtered_image, $content );
				}
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
 * @since n.e.x.t
 */
function dominant_color_add_inline_style() {
	$handle = 'dominant-color-styles';
	wp_register_style( $handle, false );
	wp_enqueue_style( $handle );
	$custom_css = 'img[data-dominantcolor]:not(.has-transparency) { background-color: var(--dominant-color); background-clip: content-box, padding-box; }';
	wp_add_inline_style( $handle, $custom_css );
}
add_filter( 'wp_enqueue_scripts', 'dominant_color_add_inline_style' );


/**
 * Overloads wp_image_editors() to load the extended classes.
 *
 * @since n.e.x.t
 *
 * @return string[]
 */
function dominant_color_set_image_editors() {

	require_once 'class-dominant-color-image-editor-gd.php';
	require_once 'class-dominant-color-image-editor-imagick.php';

	return array( 'Dominant_Color_Image_Editor_GD', 'Dominant_Color_Image_Editor_Imagick' );
}

/**
 * Get dominant color of image
 *
 *@since n.e.x.t
 *
 * @param integer $attachment_id the image id.
 *
 * @return string|null
 */
function dominant_color_get( $attachment_id ) {

	add_filter( 'wp_image_editors', 'dominant_color_set_image_editors' );

	$file   = get_attached_file( $attachment_id );
	$editor = wp_get_image_editor( $file );

	if ( ! is_wp_error( $editor ) && method_exists( $editor, 'get_dominant_color' ) ) {
		$dominant_color = $editor->get_dominant_color();

		if ( ! is_wp_error( $dominant_color ) ) {

			return $dominant_color;
		}
	}

	remove_filter( 'wp_image_editors', 'dominant_color_set_image_editors' );

	return null;
}

/**
 * Works out if color has transparency
 *
 * @since n.e.x.t
 *
 * @param integer $id the attachment id.
 *
 * @return bool
 */
function dominant_color_get_has_transparency( $id ) {

	add_filter( 'wp_image_editors', 'dominant_color_set_image_editors' );

	$file = get_attached_file( $id );

	$editor = wp_get_image_editor( $file );
	remove_filter( 'wp_image_editors', 'dominant_color_set_image_editors' );

	if ( is_wp_error( $editor ) || ! method_exists( $editor, 'get_has_transparency' ) ) {

		return true; // safer to set to trans than not.
	}

	return $editor->get_has_transparency();
}

/**
 * Works out if the color is dark or light from a give hex color.
 *
 * @param string $hexadecimal_color color in hex.
 *
 * @return bool
 *@since n.e.x.t
 *
 */
function dominant_color_color_is_light( $hexadecimal_color ) {
	$hexadecimal_color = str_replace( '#', '', $hexadecimal_color );
	if ( 3 === strlen( $hexadecimal_color ) ) {
		$hexadecimal_color[5] = $hexadecimal_color[2];
		$hexadecimal_color[4] = $hexadecimal_color[2];
		$hexadecimal_color[3] = $hexadecimal_color[1];
		$hexadecimal_color[2] = $hexadecimal_color[1];
		$hexadecimal_color[1] = $hexadecimal_color[0];
	}

	$r         = ( hexdec( substr( $hexadecimal_color, 0, 2 ) ) / 255 );
	$g         = ( hexdec( substr( $hexadecimal_color, 2, 2 ) ) / 255 );
	$b         = ( hexdec( substr( $hexadecimal_color, 4, 2 ) ) / 255 );
	$lightness = round( ( ( ( max( $r, $g, $b ) + min( $r, $g, $b ) ) / 2 ) * 100 ) );

	return $lightness >= 50;
}
