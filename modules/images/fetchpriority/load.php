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
 * Filters an image tag in content to add the fetchpriority attribute if it is not lazy-loaded.
 *
 * @since n.e.x.t
 *
 * @param string $filtered_image The image tag to filter.
 * @param string $context        The context of the image.
 * @return string The filtered image tag.
 */
function fetchpriority_img_tag_add_attr( $filtered_image, $context ) {

	if ( 'the_content' !== $context && 'the_post_thumbnail' !== $context ) {
		return $filtered_image;
	}

	if ( strpos( $filtered_image, ' loading="lazy"' ) === false && strpos( $filtered_image, ' fetchpriority=' ) === false ) {
		$filtered_image = str_replace( '<img ', '<img fetchpriority="high" ', $filtered_image );
		remove_filter( 'wp_content_img_tag', 'fetchpriority_img_tag_add_attr', 10 );
		remove_filter( 'post_thumbnail_html', 'fetchpriority_filter_post_thumbnail_html' );
	}

	return $filtered_image;
}
add_filter( 'wp_content_img_tag', 'fetchpriority_img_tag_add_attr', 10, 2 );

/**
 * Filters the post thumbnail HTML to conditionally add the fetchpriority attribute.
 *
 * @since n.e.x.t
 *
 * @param string $html The post thumbnail HTML to filter.
 * @return string The filtered post thumbnail HTML.
 */
function fetchpriority_filter_post_thumbnail_html( $html ) {
	return fetchpriority_img_tag_add_attr( $html, 'the_post_thumbnail' );
}
add_filter( 'post_thumbnail_html', 'fetchpriority_filter_post_thumbnail_html' );
