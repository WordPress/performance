<?php
/**
 * Module Name: Fetchpriority
 * Description: Adds `fetchpriority=high` parameter to the non lazy loaded images.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since 1.5.0
 */

/**
 * Filter image tags in content to add fetchpriority to the image tag if not lazy loaded set.
 *
 * @since 1.5.0
 *
 * @param string $filtered_image The filtered image.
 * @param string $context        The context of the image.
 *
 * @return string image tag
 */
function fetchpriority_img_tag_add( $filtered_image, $context ) {

	// Only apply this in `the_content` for now, since otherwise it can result in duplicate runs due to a problem with full site editing logic.
	if ( 'the_content' !== $context ) {
		return $filtered_image;
	}

	if ( strpos( $filtered_image, 'loading="lazy"' ) === false ) {
		$filtered_image = str_replace( '<img ', '<img fetchpriority="high" ', $filtered_image );
	}

	return $filtered_image;
}
add_filter( 'wp_content_img_tag', 'fetchpriority_img_tag_add', 30, 2 );
