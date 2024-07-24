<?php
/**
 * WordPress Image Editor Class for Image Manipulation through GD
 * with dominant color detection
 *
 * @package dominant-color-images
 *
 * @since 1.0.0
 */

/**
 * WordPress Image Editor Class for Image Manipulation through GD
 * with dominant color detection.
 *
 * @since 1.0.0
 *
 * @see WP_Image_Editor
 */
class Dominant_Color_Image_Editor_GD extends WP_Image_Editor_GD {

	/**
	 * Get dominant color from a file.
	 *
	 * @since 1.0.0
	 *
	 * @return string|WP_Error Dominant hex color string, or an error on failure.
	 */
	public function get_dominant_color() {

		if ( ! (bool) $this->image ) {
			return new WP_Error( 'image_editor_dominant_color_error_no_image', __( 'Dominant color detection no image found.', 'dominant-color-images' ) );
		}
		// The logic here is resize the image to 1x1 pixel, then get the color of that pixel.
		$shorted_image = imagecreatetruecolor( 1, 1 );
		if ( false === $shorted_image ) {
			return new WP_Error( 'image_editor_dominant_color_error', __( 'Dominant color detection failed.', 'dominant-color-images' ) );
		}
		// Note: These two functions only return integers, but they used to return int|false in PHP<8. This was changed in the PHP documentation in
		// <https://github.com/php/doc-en/commit/0462f49> and <https://github.com/php/doc-en/commit/37f858a>. However, PhpStorm's stubs still think
		// they return int|false. However, from looking at <https://github.com/php/php-src/blob/5db847e/ext/gd/gd.stub.php#L716-L718> these functions
		// apparently only ever returned integers. So the type casting is here for the possible sake PHP<8.
		$image_width  = (int) imagesx( $this->image ); // @phpstan-ignore cast.useless
		$image_height = (int) imagesy( $this->image ); // @phpstan-ignore cast.useless
		imagecopyresampled( $shorted_image, $this->image, 0, 0, 0, 0, 1, 1, $image_width, $image_height );

		$rgb = imagecolorat( $shorted_image, 0, 0 );
		if ( false === $rgb ) {
			return new WP_Error( 'image_editor_dominant_color_error', __( 'Dominant color detection failed.', 'dominant-color-images' ) );
		}
		$r   = ( $rgb >> 16 ) & 0xFF;
		$g   = ( $rgb >> 8 ) & 0xFF;
		$b   = $rgb & 0xFF;
		$hex = dominant_color_rgb_to_hex( $r, $g, $b );
		if ( null === $hex ) {
			return new WP_Error( 'image_editor_dominant_color_error', __( 'Dominant color detection failed.', 'dominant-color-images' ) );
		}

		return $hex;
	}

	/**
	 * Looks for transparent pixels in the image.
	 * If there are none, it returns false.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True or false based on whether there are transparent pixels, or an error on failure.
	 */
	public function has_transparency() {

		if ( ! (bool) $this->image ) {
			return new WP_Error( 'image_editor_has_transparency_error_no_image', __( 'Transparency detection no image found.', 'dominant-color-images' ) );
		}

		// Walk through the pixels and look transparent pixels.
		$w = imagesx( $this->image );
		$h = imagesy( $this->image );
		for ( $x = 0; $x < $w; $x++ ) {
			for ( $y = 0; $y < $h; $y++ ) {
				$rgb = imagecolorat( $this->image, $x, $y );
				if ( false === $rgb ) {
					return new WP_Error( 'unable_to_obtain_rgb_via_imagecolorat' );
				}
				try {
					// Note: In PHP<8, this returns false if the color is out of range. In PHP8, this throws a ValueError instead.
					$rgba = imagecolorsforindex( $this->image, $rgb );
				} catch ( ValueError $error ) {
					$rgba = false;
				}
				if ( ! is_array( $rgba ) ) {
					return new WP_Error( 'unable_to_obtain_rgba_via_imagecolorsforindex' );
				}
				if ( $rgba['alpha'] > 0 ) {
					return true;
				}
			}
		}
		return false;
	}
}
