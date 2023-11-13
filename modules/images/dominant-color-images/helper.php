<?php
/**
 * Helper functions used for Dominant Color Images.
 *
 * @package performance-lab
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

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
 * @since 2.6.0 Function renamed to remove the `_` prefix.
 * @access private
 *
 * @param int $attachment_id The attachment ID.
 * @return array|WP_Error Array with the dominant color and has transparency values or WP_Error on error.
 */
function dominant_color_get_dominant_color_data( $attachment_id ) {
	$mime_type = get_post_mime_type( $attachment_id );
	if ( 'application/pdf' === $mime_type ) {
		return new WP_Error( 'no_image_found', __( 'Unable to load image.', 'performance-lab' ) );
	}
	$file = dominant_color_get_attachment_file_path( $attachment_id );
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
 * @since 2.6.0 Function renamed to change `wp_` prefix to `dominant_color_`.
 *
 * @param int    $attachment_id Attachment ID for image.
 * @param string $size          Optional. Image size. Default 'medium'.
 * @return false|string Path to an image or false if not found.
 */
function dominant_color_get_attachment_file_path( $attachment_id, $size = 'medium' ) {
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
