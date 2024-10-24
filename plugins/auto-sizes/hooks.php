<?php
/**
 * Hook callbacks used for Enhanced Responsive Images.
 *
 * @package auto-sizes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds auto to the sizes attribute to the image, if applicable.
 *
 * @since 1.0.0
 *
 * @param array<string, string>|mixed $attr Attributes for the image markup.
 * @return array<string, string> The filtered attributes for the image markup.
 */
function auto_sizes_update_image_attributes( $attr ): array {
	if ( ! is_array( $attr ) ) {
		$attr = array();
	}

	// Bail early if the image is not lazy-loaded.
	if ( ! isset( $attr['loading'] ) || 'lazy' !== $attr['loading'] ) {
		return $attr;
	}

	// Bail early if the image is not responsive.
	if ( ! isset( $attr['sizes'] ) ) {
		return $attr;
	}

	// Don't add 'auto' to the sizes attribute if it already exists.
	if ( auto_sizes_attribute_includes_valid_auto( $attr['sizes'] ) ) {
		return $attr;
	}

	$attr['sizes'] = 'auto, ' . $attr['sizes'];

	return $attr;
}

/**
 * Adds auto to the sizes attribute to the image, if applicable.
 *
 * @since 1.0.0
 *
 * @param string|mixed $html The HTML image tag markup being filtered.
 * @return string The filtered HTML image tag markup.
 */
function auto_sizes_update_content_img_tag( $html ): string {
	if ( ! is_string( $html ) ) {
		$html = '';
	}

	$processor = new WP_HTML_Tag_Processor( $html );

	// Bail if there is no IMG tag.
	if ( ! $processor->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
		return $html;
	}

	// Bail early if the image is not lazy-loaded.
	$value = $processor->get_attribute( 'loading' );
	if ( ! is_string( $value ) || 'lazy' !== strtolower( trim( $value, " \t\f\r\n" ) ) ) {
		return $html;
	}

	$sizes = $processor->get_attribute( 'sizes' );

	// Bail early if the image is not responsive.
	if ( ! is_string( $sizes ) ) {
		return $html;
	}

	// Don't add 'auto' to the sizes attribute if it already exists.
	if ( auto_sizes_attribute_includes_valid_auto( $sizes ) ) {
		return $html;
	}

	$processor->set_attribute( 'sizes', "auto, $sizes" );
	return $processor->get_updated_html();
}

// Skip loading plugin filters if WordPress Core already loaded the functionality.
if ( ! function_exists( 'wp_sizes_attribute_includes_valid_auto' ) ) {
	add_filter( 'wp_get_attachment_image_attributes', 'auto_sizes_update_image_attributes' );
	add_filter( 'wp_content_img_tag', 'auto_sizes_update_content_img_tag' );
}

/**
 * Checks whether the given 'sizes' attribute includes the 'auto' keyword as the first item in the list.
 *
 * Per the HTML spec, if present it must be the first entry.
 *
 * @since 1.2.0
 *
 * @param string $sizes_attr The 'sizes' attribute value.
 * @return bool True if the 'auto' keyword is present, false otherwise.
 */
function auto_sizes_attribute_includes_valid_auto( string $sizes_attr ): bool {
	list( $first_size ) = explode( ',', $sizes_attr, 2 );
	return 'auto' === strtolower( trim( $first_size, " \t\f\r\n" ) );
}

/**
 * Displays the HTML generator tag for the plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 1.0.1
 */
function auto_sizes_render_generator(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="auto-sizes ' . esc_attr( IMAGE_AUTO_SIZES_VERSION ) . '">' . "\n";
}
add_action( 'wp_head', 'auto_sizes_render_generator' );

/**
 * Gets the smaller image size if the layout width is bigger.
 *
 * It will return the smaller image size and return "px" if the layout width
 * is something else, e.g. min(640px, 90vw) or 90vw.
 *
 * @since 1.1.0
 *
 * @param string $layout_width The layout width.
 * @param int    $image_width  The image width.
 * @return string The proper width after some calculations.
 */
function auto_sizes_get_width( string $layout_width, int $image_width ): string {
	if ( str_ends_with( $layout_width, 'px' ) ) {
		return $image_width > (int) $layout_width ? $layout_width : $image_width . 'px';
	}
	return $image_width . 'px';
}

/**
 * Primes attachment into the cache with a single database query.
 *
 * @since n.e.x.t
 *
 * @param string $content The HTML content.
 * @param string $context Optional. Additional context to pass to the filters.
 *                        Defaults to `current_filter()` when not set.
 * @return string The HTML content.
 */
function auto_sizes_prime_attachment_caches( string $content, string $context = null ): string {
	if ( null === $context ) {
		$context = current_filter();
	}

	$processor = new WP_HTML_Tag_Processor( $content );

	$images = array();
	while ( $processor->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
		$class = $processor->get_attribute( 'class' );

		// Only apply the dominant color to images that have a src attribute.
		if ( ! is_string( $class ) ) {
			continue;
		}

		if ( preg_match( '/wp-image-([0-9]+)/i', $class, $class_id ) === 1 ) {
			$attachment_id = absint( $class_id[1] );
			if ( $attachment_id > 0 ) {
				$images[] = $attachment_id;
			}
		}
	}

	// Reduce the array to unique attachment IDs.
	$attachment_ids = array_unique( array_filter( $images ) );

	if ( count( $attachment_ids ) > 1 ) {
		/*
		 * Warm the object cache with post and meta information for all found
		 * images to avoid making individual database calls.
		 */
		_prime_post_caches( $attachment_ids, false, true );
	}

	return $content;
}
add_filter( 'the_content', 'auto_sizes_prime_attachment_caches', 6 );

/**
 * Filter the sizes attribute for images to improve the default calculation.
 *
 * @since 1.1.0
 *
 * @param string                                                   $content      The block content about to be rendered.
 * @param array{ attrs?: array{ align?: string, width?: string } } $parsed_block The parsed block.
 * @param WP_Block                                                 $block        Block instance.
 * @return string The updated block content.
 */
function auto_sizes_filter_image_tag( string $content, array $parsed_block, WP_Block $block ): string {
	$processor = new WP_HTML_Tag_Processor( $content );
	$has_image = $processor->next_tag( array( 'tag_name' => 'img' ) );

	// Only update the markup if an image is found.
	if ( $has_image ) {

		/**
		 * Callback for calculating image sizes attribute value for an image block.
		 *
		 * This is a workaround to use block context data when calculating the img sizes attribute.
		 *
		 * @since n.e.x.t
		 *
		 * @param string $sizes The image sizes attribute value.
		 * @param string $size  The image size data.
		 */
		$filter = static function ( $sizes, $size ) use ( $block ) {
			$id        = $block->attributes['id'] ?? 0;
			$alignment = $block->attributes['align'] ?? '';
			$width     = $block->attributes['width'] ?? '';

			// Hypothetical function to calculate better sizes.
			$sizes = auto_sizes_calculate_better_sizes( $id, $size, $alignment, $width );

			return $sizes;
		};

		// Hook this filter early, before default filters are run.
		add_filter( 'wp_calculate_image_sizes', $filter, 9, 2 );

		$sizes = wp_calculate_image_sizes(
			// If we don't have a size slug, assume the full size was used.
			$parsed_block['attrs']['sizeSlug'] ?? 'full',
			null,
			null,
			$parsed_block['attrs']['id'] ?? 0
		);

		remove_filter( 'wp_calculate_image_sizes', $filter, 9 );

		// Bail early if sizes are not calculated.
		if ( false === $sizes ) {
			return $content;
		}

		$processor->set_attribute( 'sizes', $sizes );

		return $processor->get_updated_html();
	}

	return $content;
}
add_filter( 'render_block_core/image', 'auto_sizes_filter_image_tag', 10, 3 );
add_filter( 'render_block_core/cover', 'auto_sizes_filter_image_tag', 10, 3 );

/**
 * Hypothetical function to calculate better sizes.
 *
 * @param int    $id           The image id.
 * @param string $size         The image size data.
 * @param string $align        The image alignment.
 * @param string $resize_width Resize image width.
 * @return string The sizes attribute value.
 */
function auto_sizes_calculate_better_sizes( int $id, string $size, string $align, string $resize_width ): string {
	$sizes = '';
	$image = wp_get_attachment_image_src( $id, $size );

	if ( false === $image ) {
		return $sizes;
	}

	// Retrieve width from the image tag itself.
	$image_width = '' !== $resize_width ? (int) $resize_width : $image[1];

	$layout = wp_get_global_settings( array( 'layout' ) );

	// Handle different alignment use cases.
	switch ( $align ) {
		case 'full':
			$sizes = '100vw';
			break;

		case 'wide':
			if ( array_key_exists( 'wideSize', $layout ) ) {
				$sizes = sprintf( '(max-width: %1$s) 100vw, %1$s', $layout['wideSize'] );
			}
			break;

		case 'left':
		case 'right':
		case 'center':
			$sizes = sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $image_width );
			break;

		default:
			if ( array_key_exists( 'contentSize', $layout ) ) {
				$width = auto_sizes_get_width( $layout['contentSize'], $image_width );
				$sizes = sprintf( '(max-width: %1$s) 100vw, %1$s', $width );
			}
			break;
	}

	return $sizes;
}
