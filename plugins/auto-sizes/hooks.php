<?php
/**
 * Hook callbacks used for Auto-sizes for Lazy-loaded Images.
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
	if ( str_contains( $attr['sizes'], 'auto,' ) ) {
		return $attr;
	}

	$attr['sizes'] = 'auto, ' . $attr['sizes'];

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'auto_sizes_update_image_attributes' );

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
add_filter( 'wp_content_img_tag', 'auto_sizes_update_content_img_tag' );

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
 * @since n.e.x.t
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
 * Filter the sizes attribute for images to improve the default calculation.
 *
 * @since n.e.x.t
 *
 * @param string               $content      The block content about to be rendered.
 * @param array<string, mixed> $parsed_block The parsed block.
 * @return string The updated block content.
 */
function auto_sizes_improve_image_sizes_attribute( string $content, array $parsed_block ): string {

	$processor = new WP_HTML_Tag_Processor( $content );
	$has_image = $processor->next_tag( array( 'tag_name' => 'img' ) );

	// Only update the markup if an image is found.
	if ( $has_image ) {
		$layout     = wp_get_global_settings( array( 'layout' ) );
		$align      = $parsed_block['attrs']['align'] ?? null;
		$image_id   = $parsed_block['attrs']['id'] ?? '';
		$image_size = $parsed_block['attrs']['sizeSlug'] ?? '';

		$image_attributes = wp_get_attachment_image_src( $image_id, $image_size );
		if ( ! $image_attributes ) {
			return $content;
		}

		$image_width = $image_attributes[1] ?? '';
		$sizes       = null;
		// Handle different alignment use cases.
		switch ( $align ) {
			case 'full':
				$sizes = '100vw';
				break;

			case 'wide':
				if ( array_key_exists( 'wideSize', $layout ) ) {
					$width = auto_sizes_get_width( $layout['wideSize'], $image_width );
					$sizes = sprintf( '(max-width: %1$s) 100vw, %1$s', $width );
				}
				break;

				// @todo: handle left/right alignments.
			default:
				if ( array_key_exists( 'contentSize', $layout ) ) {
					$width = auto_sizes_get_width( $layout['contentSize'], $image_width );
					$sizes = sprintf( '(max-width: %1$s) 100vw, %1$s', $width );
				}
				break;
		}

		if ( $sizes ) {
			$processor->set_attribute( 'sizes', $sizes );
		}

		$content = $processor->get_updated_html();
	}
	return $content;
}
add_filter( 'render_block_core/image', 'auto_sizes_improve_image_sizes_attribute', 10, 2 );
add_filter( 'render_block_core/cover', 'auto_sizes_improve_image_sizes_attribute', 10, 2 );
