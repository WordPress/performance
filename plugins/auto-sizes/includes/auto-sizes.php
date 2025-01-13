<?php
/**
 * Functionality to implement auto-sizes for lazy loaded images.
 *
 * @package auto-sizes
 * @since 1.4.0
 */

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
