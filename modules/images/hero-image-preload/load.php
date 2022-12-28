<?php
/**
 * Module Name: Hero Image Preload
 * Description: Adds preload link tag for the hero image of any page.
 * Experimental: Yes
 *
 * @since n.e.x.t
 * @package performance-lab
 */

/**
 * Intercepts image rendered in content to detect what is most likely the hero image.
 *
 * @since n.e.x.t
 *
 * @param string $filtered_image The image tag.
 * @param string $context        The context of the image.
 * @return string The unmodified image tag.
 */
function perflab_hip_img_tag_check( $filtered_image, $context ) {

	if ( 'the_content' !== $context && 'the_post_thumbnail' !== $context ) {
		return $filtered_image;
	}

	// Determining hero image relies on lazy loading logic.
	if ( ! wp_lazy_loading_enabled( 'img', $context ) ) {
		return $filtered_image;
	}

	if ( ! empty( $filtered_image ) && strpos( $filtered_image, 'loading="lazy"' ) === false ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			$image_preload = array();
			if ( preg_match( '/ src="([^"]+)/', $filtered_image, $matches ) ) {
				$image_preload['href'] = $matches[1];
			}
			if ( preg_match( '/ srcset="([^"]+)/', $filtered_image, $matches ) ) {
				$image_preload['imagesrcset'] = $matches[1];
			}
			if ( preg_match( '/ sizes="([^"]+)/', $filtered_image, $matches ) ) {
				$image_preload['imagesizes'] = $matches[1];
			}
			if ( isset( $image_preload['href'] ) || isset( $image_preload['imagesrcset'] ) ) {
				update_post_meta( $post->ID, 'perflab_hero_image_preload', $image_preload );
			}
		}
		remove_filter( 'wp_content_img_tag', 'perflab_hip_img_tag_check' );
		remove_filter( 'post_thumbnail_html', 'perflab_hip_post_thumbnail_html_check' );
	}

	return $filtered_image;
}

/**
 * Intercepts the post thumbnail HTML to detect what is most likely the hero image.
 *
 * @since n.e.x.t
 *
 * @param string $html The post thumbnail HTML.
 * @return string The unmodified thumbnail HTML.
 */
function perflab_hip_post_thumbnail_html_check( $html ) {
	return perflab_hip_img_tag_check( $html, 'the_post_thumbnail' );
}

/**
 * Adds hooks to detect hero image, based on current user permissions.
 *
 * To avoid this from running on any (unauthenticated) page load and thus avoid race conditions due to high traffic,
 * this logic should only be run when a user with capabilities to edit posts is logged-in.
 *
 * The current user is set before the 'init' action.
 *
 * @since n.e.x.t
 */
function perflab_hip_add_hooks() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	add_filter( 'wp_content_img_tag', 'perflab_hip_img_tag_check', 10, 2 );
	add_filter( 'post_thumbnail_html', 'perflab_hip_post_thumbnail_html_check' );
}
add_action( 'init', 'perflab_hip_add_hooks' );

/**
 * Add queried post's hero image as a resource to preload, if available.
 *
 * @since n.e.x.t
 *
 * @param array $resources Resources to preload.
 * @return array Modified $resources.
 */
function perflab_hip_preload_resources( $resources ) {
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		if ( ! isset( $GLOBALS['wp_the_query']->posts[0] ) || ! $GLOBALS['wp_the_query']->posts[0] instanceof WP_Post ) {
			return $resources;
		}
		$post = $GLOBALS['wp_the_query']->posts[0];
	}

	$image_preload = get_post_meta( $post->ID, 'perflab_hero_image_preload', true );
	if ( ! $image_preload ) {
		return $resources;
	}

	$image_preload['as'] = 'image';
	$resources[]         = $image_preload;

	return $resources;
}
add_filter( 'wp_preload_resources', 'perflab_hip_preload_resources' );
