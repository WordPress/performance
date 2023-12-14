<?php
/**
 * Hook callbacks used for Auto Sizes for Lazy-loaded Images.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds auto to the sizes attribute to the image, if applicable.
 *
 * @since n.e.x.t
 *
 * @param array $attr Attributes for the image markup.
 * @return array The filtered attributes for the image markup.
 */
function performance_lab_auto_sizes_attr( $attr ) {
	// Bail early if the image is not lazy-loaded.
	if ( ! isset( $attr['loading'] ) || 'lazy' !== $attr['loading'] ) {
		return $attr;
	}

	// Bail early if the image is not responsive.
	if ( ! isset( $attr['sizes'] ) ) {
		return $attr;
	}

	// Don't add 'auto' to the sizes attribute if it already exists.
	if ( false !== strpos( $attr['sizes'], 'auto,' ) ) {
		return $attr;
	}

	$attr['sizes'] = 'auto, ' . $attr['sizes'];

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'performance_lab_auto_sizes_attr' );

/**
 * Adds auto to the sizes attribute to the image, if applicable.
 *
 * @since n.e.x.t
 *
 * @param string $html The HTML image tag markup being filtered.
 * @return string The filtered HTML image tag markup.
 */
function performance_lab_auto_sizes_img_tag( $html ) {
	// Bail early if the image is not lazy-loaded.
	if ( false === strpos( $html, 'loading="lazy"' ) ) {
		return $html;
	}

	// Bail early if the image is not responsive.
	if ( false === strpos( $html, 'sizes="' ) ) {
		return $html;
	}

	// Don't double process content images.
	if ( false !== strpos( $html, 'sizes="auto,' ) ) {
		return $html;
	}

	$html = str_replace( 'sizes="', 'sizes="auto, ', $html );

	return $html;
}
add_filter( 'wp_content_img_tag', 'performance_lab_auto_sizes_img_tag' );
