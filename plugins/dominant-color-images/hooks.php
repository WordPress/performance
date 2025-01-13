<?php
/**
 * Hook callbacks used for Image Placeholders.
 *
 * @package dominant-color-images
 *
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add the dominant color metadata to the attachment.
 *
 * @since 1.0.0
 *
 * @param array|mixed $metadata      The attachment metadata.
 * @param int         $attachment_id The attachment ID.
 * @return array{ has_transparency?: bool, dominant_color?: string } $metadata The attachment metadata.
 */
function dominant_color_metadata( $metadata, int $attachment_id ): array {
	if ( ! is_array( $metadata ) ) {
		$metadata = array();
	}

	$dominant_color_data = dominant_color_get_dominant_color_data( $attachment_id );
	if ( ! is_wp_error( $dominant_color_data ) ) {
		if ( isset( $dominant_color_data['dominant_color'] ) ) {
			$metadata['dominant_color'] = $dominant_color_data['dominant_color'];
		}

		if ( isset( $dominant_color_data['has_transparency'] ) ) {
			$metadata['has_transparency'] = $dominant_color_data['has_transparency'];
		}
	}

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'dominant_color_metadata', 10, 2 );

/**
 * Filters various image attributes to add the dominant color to the image.
 *
 * @since 1.0.0
 *
 * @param array|mixed $attr       Attributes for the image markup.
 * @param WP_Post     $attachment Image attachment post.
 * @return array{ 'data-has-transparency'?: string, class?: string, 'data-dominant-color'?: string, style?: string } Attributes for the image markup.
 */
function dominant_color_update_attachment_image_attributes( $attr, WP_Post $attachment ): array {
	if ( ! is_array( $attr ) ) {
		$attr = array();
	}

	$image_meta = wp_get_attachment_metadata( $attachment->ID );
	if ( ! is_array( $image_meta ) ) {
		return $attr;
	}

	if ( isset( $image_meta['has_transparency'] ) ) {
		$attr['data-has-transparency'] = $image_meta['has_transparency'] ? 'true' : 'false';

		$class = $image_meta['has_transparency'] ? 'has-transparency' : 'not-transparent';
		if ( empty( $attr['class'] ) ) {
			$attr['class'] = $class;
		} else {
			$attr['class'] .= ' ' . $class;
		}
	}

	if ( ! empty( $image_meta['dominant_color'] ) ) {
		$attr['data-dominant-color'] = esc_attr( $image_meta['dominant_color'] );
		$style_attribute             = empty( $attr['style'] ) ? '' : $attr['style'];
		$attr['style']               = '--dominant-color: #' . esc_attr( $image_meta['dominant_color'] ) . ';' . $style_attribute;
	}

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'dominant_color_update_attachment_image_attributes', 10, 2 );

/**
 * Filter image tags in content to add the dominant color to the image.
 *
 * @since 1.0.0
 *
 * @param string|mixed $filtered_image The filtered image.
 * @param string       $context        The context of the image.
 * @param int          $attachment_id  The attachment ID.
 * @return string image tag
 */
function dominant_color_img_tag_add_dominant_color( $filtered_image, string $context, int $attachment_id ): string {
	if ( ! is_string( $filtered_image ) ) {
		$filtered_image = '';
	}

	// Only apply this in `the_content` for now, since otherwise it can result in duplicate runs due to a problem with full site editing logic.
	if ( 'the_content' !== $context ) {
		return $filtered_image;
	}

	$processor = new WP_HTML_Tag_Processor( $filtered_image );
	if ( ! $processor->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
		return $filtered_image;
	}

	// Only apply the dominant color to images that have a src attribute.
	if ( ! is_string( $processor->get_attribute( 'src' ) ) ) {
		return $filtered_image;
	}

	// Ensure to not run the logic below in case relevant attributes are already present.
	if ( null !== $processor->get_attribute( 'data-dominant-color' ) || null !== $processor->get_attribute( 'data-has-transparency' ) ) {
		return $filtered_image;
	}

	$image_meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! is_array( $image_meta ) ) {
		return $filtered_image;
	}

	/**
	 * Filters whether dominant color is added to the image.
	 *
	 * You can set this to false in order disable adding the dominant color to the image.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $add_dominant_color Whether to add the dominant color to the image. default true.
	 * @param int    $attachment_id      The image attachment ID.
	 * @param array  $image_meta         The image meta data all ready set.
	 * @param string $filtered_image     The filtered image. html including img tag
	 * @param string $context            The context of the image.
	 */
	$check = apply_filters( 'dominant_color_img_tag_add_dominant_color', true, $attachment_id, $image_meta, $filtered_image, $context );
	if ( ! $check ) {
		return $filtered_image;
	}

	if ( ! empty( $image_meta['dominant_color'] ) ) {
		$processor->set_attribute( 'data-dominant-color', $image_meta['dominant_color'] );

		$style_attribute = '--dominant-color: #' . $image_meta['dominant_color'] . '; ';
		if ( null !== $processor->get_attribute( 'style' ) ) {
			$style_attribute .= $processor->get_attribute( 'style' );
		}
		$processor->set_attribute( 'style', trim( $style_attribute ) );
	}

	if ( isset( $image_meta['has_transparency'] ) ) {
		$transparency = $image_meta['has_transparency'] ? 'true' : 'false';
		$processor->set_attribute( 'data-has-transparency', $transparency );
		$processor->add_class( $image_meta['has_transparency'] ? 'has-transparency' : 'not-transparent' );
	}

	return $processor->get_updated_html();
}
add_filter( 'wp_content_img_tag', 'dominant_color_img_tag_add_dominant_color', 20, 3 );

/**
 * Add CSS needed for to show the dominant color as an image background.
 *
 * @since 1.0.0
 */
function dominant_color_add_inline_style(): void {
	$handle = 'dominant-color-styles';
	// PHPCS ignore reason: Version not used since this handle is only registered for adding an inline style.
	// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_register_style( $handle, false );
	wp_enqueue_style( $handle );
	$custom_css = 'img[data-dominant-color]:not(.has-transparency) { background-color: var(--dominant-color); }';
	wp_add_inline_style( $handle, $custom_css );
}
add_action( 'wp_enqueue_scripts', 'dominant_color_add_inline_style' );

/**
 * Displays the HTML generator tag for the Image Placeholders plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 1.0.0
 */
function dominant_color_render_generator(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="dominant-color-images ' . esc_attr( DOMINANT_COLOR_IMAGES_VERSION ) . '">' . "\n";
}
add_action( 'wp_head', 'dominant_color_render_generator' );

/**
 * Adds inline CSS for dominant color styling in the WordPress admin area.
 *
 * This function registers and enqueues a custom style handle, then adds inline CSS
 * to apply background color based on the dominant color for attachment previews
 * in the WordPress admin interface.
 *
 * @since 1.2.0
 */
function dominant_color_admin_inline_style(): void {
	$handle = 'dominant-color-admin-styles';
	// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Version not used since this handle is only registered for adding an inline style.
	wp_register_style( $handle, false );
	wp_enqueue_style( $handle );
	$custom_css = '.wp-core-ui .attachment-preview[data-dominant-color]:not(.has-transparency) { background-color: var(--dominant-color); }';
	wp_add_inline_style( $handle, $custom_css );
}
add_action( 'admin_enqueue_scripts', 'dominant_color_admin_inline_style' );

/**
 * Adds a script to the admin footer to modify the attachment template.
 *
 * This function injects a JavaScript snippet into the admin footer that modifies
 * the attachment template. It adds attributes for dominant color and transparency
 * to the template, allowing these properties to be displayed in the media library.
 *
 * @since 1.2.0
 * @see wp_print_media_templates()
 */
function dominant_color_admin_script(): void {
	?>
	<script type="module">
		const tmpl = document.getElementById( 'tmpl-attachment' );
		if ( tmpl ) {
			tmpl.textContent = tmpl.textContent.replace( /^\s*<div[^>]*?(?=>)/, ( match ) => {
				let replaced = match.replace( /\sclass="/, " class=\"{{ data.hasTransparency ? 'has-transparency' : 'not-transparent' }} " );
				replaced += ' data-dominant-color="{{ data.dominantColor }}"';
				replaced += ' data-has-transparency="{{ data.hasTransparency }}"';
				let hasStyleAttr = false;
				const colorStyle = "{{ data.dominantColor ? '--dominant-color: #' + data.dominantColor + ';' : '' }}";
				replaced = replaced.replace( /\sstyle="/, ( styleMatch ) => {
					hasStyleAttr = true;
					return styleMatch + colorStyle;
				} );
				if ( ! hasStyleAttr ) {
					replaced += ` style="${colorStyle}"`;
				}
				return replaced;
			} );
		}
	</script>
	<?php
}
add_action( 'admin_print_footer_scripts', 'dominant_color_admin_script' );

/**
 * Prepares attachment data for JavaScript, adding dominant color and transparency information.
 *
 * This function enhances the attachment data for JavaScript by including information about
 * the dominant color and transparency of the image. It modifies the response array to include
 * these additional properties, which can be used in the media library interface.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed>|mixed $response   The current response array for the attachment.
 * @param WP_Post                    $attachment The attachment post object.
 * @param array<string, mixed>|false $meta       The attachment metadata.
 * @return array<string, mixed> The modified response array with added dominant color and transparency information.
 */
function dominant_color_prepare_attachment_for_js( $response, WP_Post $attachment, $meta ): array {
	if ( ! is_array( $response ) ) {
		$response = array();
	}
	if ( ! is_array( $meta ) ) {
		return $response;
	}

	$response['dominantColor'] = '';
	if (
		isset( $meta['dominant_color'] )
		&&
		1 === preg_match( '/^[0-9a-f]+$/', $meta['dominant_color'] ) // See format returned by dominant_color_rgb_to_hex().
	) {
		$response['dominantColor'] = $meta['dominant_color'];
	}
	$response['hasTransparency'] = '';
	if ( isset( $meta['has_transparency'] ) ) {
		$response['hasTransparency'] = (bool) $meta['has_transparency'];
	}

	return $response;
}
add_filter( 'wp_prepare_attachment_for_js', 'dominant_color_prepare_attachment_for_js', 10, 3 );
