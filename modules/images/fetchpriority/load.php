<?php
/**
 * Module Name: Fetchpriority
 * Description: Adds `fetchpriority=high` parameter to the non lazy loaded images.
 * Experimental: Yes
 *
 * @since n.e.x.t
 * @package performance-lab
 */

/**
 * Filter image tags in content to add fetchpriority to the image tag if lazy loaded is not set.
 *
 * @since n.e.x.t
 *
 * @param string $filtered_image The filtered image.
 * @param string $context        The context of the image.
 *
 * @return string image tag
 */
function fetchpriority_img_tag_add( $filtered_image, $context ) {

	if ( 'the_content' !== $context ) {
		return $filtered_image;
	}

	if ( strpos( $filtered_image, ' loading="lazy"' ) === false && strpos( $filtered_image, ' fetchpriority=' ) === false ) {
		$filtered_image = str_replace( '<img ', '<img fetchpriority="high" ', $filtered_image );
		remove_filter( 'wp_content_img_tag', 'fetchpriority_img_tag_add', 10, 2 );
	}

	return $filtered_image;
}
add_filter( 'wp_content_img_tag', 'fetchpriority_img_tag_add', 10, 2 );
