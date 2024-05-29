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
	$layout    = wp_get_global_settings( array( 'layout' ) );

	// Only update the markup if an image is found.
	if ( $has_image ) {

		$align = $parsed_block['attrs']['align'] ?? null;
		$sizes = null;
		// Handle different alignment use cases.
		switch ( $align ) {
			case 'full':
				$sizes = '100vw';
				break;

			case 'wide':
				if ( isset( $layout['wideSize'] ) ) {
					$sizes = sprintf( '(max-width: %1$s) 100vw, %1$s', $layout['wideSize'] );
				}
				break;

			// @todo: handle left/right alignments.
			default:
				if ( isset( $layout['contentSize'] ) ) {
					$sizes = sprintf( '(max-width: %1$s) 100vw, %1$s', $layout['contentSize'] );
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
