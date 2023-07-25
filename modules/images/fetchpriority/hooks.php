<?php
/**
 * Hook callbacks used for Fetchpriority.
 *
 * @package performance-lab
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Filters an image tag in content to add the fetchpriority attribute if it is not lazy-loaded.
 *
 * @since 1.8.0
 *
 * @param string $filtered_image The image tag to filter.
 * @param string $context        The context of the image.
 * @return string The filtered image tag.
 */
function fetchpriority_img_tag_add_attr( $filtered_image, $context ) {

	if ( 'the_content' !== $context && 'the_post_thumbnail' !== $context ) {
		return $filtered_image;
	}

	// Fetchpriority relies on lazy loading logic.
	if ( ! wp_lazy_loading_enabled( 'img', $context ) ) {
		return $filtered_image;
	}

	if ( ! empty( $filtered_image ) && strpos( $filtered_image, 'loading="lazy"' ) === false && strpos( $filtered_image, 'fetchpriority=' ) === false ) {
		$filtered_image = str_replace( '<img ', '<img fetchpriority="high" ', $filtered_image );
		remove_filter( 'wp_content_img_tag', 'fetchpriority_img_tag_add_attr' );
		remove_filter( 'post_thumbnail_html', 'fetchpriority_filter_post_thumbnail_html' );
	}

	return $filtered_image;
}
add_filter( 'wp_content_img_tag', 'fetchpriority_img_tag_add_attr', 10, 2 );

/**
 * Filters the post thumbnail HTML to conditionally add the fetchpriority attribute.
 *
 * @since 1.8.0
 *
 * @param string $html The post thumbnail HTML to filter.
 * @return string The filtered post thumbnail HTML.
 */
function fetchpriority_filter_post_thumbnail_html( $html ) {
	$html = fetchpriority_img_tag_add_attr( $html, 'the_post_thumbnail' );

	return $html;
}
add_filter( 'post_thumbnail_html', 'fetchpriority_filter_post_thumbnail_html' );

/**
 * Displays the HTML generator tag for the Fetchpriority plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 2.3.0
 */
function fetchpriority_render_generator() {
	if (
		defined( 'FETCHPRIORITY_VERSION' ) &&
		! str_starts_with( FETCHPRIORITY_VERSION, 'Performance Lab ' )
	) {
		echo '<meta name="generator" content="Fetchpriority ' . esc_attr( FETCHPRIORITY_VERSION ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'fetchpriority_render_generator' );

// Show an admin notice if fetchpriority is already available in WordPress core (only relevant for the standalone plugin).
if ( function_exists( 'wp_get_loading_optimization_attributes' ) && ! str_starts_with( FETCHPRIORITY_VERSION, 'Performance Lab ' ) ) {
	add_action(
		'admin_notices',
		static function() {
			?>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e( 'Fetchpriority is already part of your WordPress version. Please deactivate the Fetchpriority plugin.', 'performance-lab' ); ?>
				</p>
			</div>
			<?php
		}
	);
}
