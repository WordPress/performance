<?php
/**
 * WordPress Image Editor Class for Image Manipulation through Imagick
 * with dominant color detection
 *
 * @package performance-lab
 * @group dominant-color
 *
 * @since 1.2.0
 */

/**
 * WordPress Image Editor Class for Image Manipulation through Imagick
 * with dominant color detection.
 *
 * @since 1.2.0
 *
 * @see WP_Image_Editor
 */
class Dominant_Color_Image_Editor_Imagick extends WP_Image_Editor_Imagick {

	/**
	 * Get dominant color from a file.
	 *
	 * @since 1.2.0
	 *
	 * @return string|WP_Error Dominant hex color string, or an error on failure.
	 */
	public function get_dominant_color() {

		if ( ! $this->image ) {
			return new WP_Error( 'image_editor_dominant_color_error_no_image', __( 'Dominant color detection no image found.', 'performance-lab' ) );
		}

		try {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			// The logic here is resize the image to 1x1 pixel, then get the color of that pixel.
			$this->image->resizeImage( 1, 1, Imagick::FILTER_LANCZOS, 1 );
			$pixel = $this->image->getImagePixelColor( 0, 0 );
			$color = $pixel->getColor();
			$hex   = dominant_color_rgb_to_hex( $color['r'], $color['g'], $color['b'] );
			if ( ! $hex ) {
				return new WP_Error( 'image_editor_dominant_color_error', __( 'Dominant color detection failed.', 'performance-lab' ) );
			}

			return $hex;
		} catch ( Exception $e ) {
			/* translators: %s is the error message. */
			return new WP_Error( 'image_editor_dominant_color_error', sprintf( __( 'Dominant color detection failed: %s', 'performance-lab' ), $e->getMessage() ) );
		}
	}

	/**
	 * Looks for transparent pixels in the image.
	 * If there are none, it returns false.
	 *
	 * @since 1.2.0
	 *
	 * @return bool|WP_Error True or false based on whether there are transparent pixels, or an error on failure.
	 */
	public function has_transparency() {

		if ( ! $this->image ) {
			return new WP_Error( 'image_editor_has_transparency_error_no_image', __( 'Transparency detection no image found.', 'performance-lab' ) );
		}

		try {
			/*
			 * Check if the image has an alpha channel if false, then it can't have transparency so return early.
			 *
			 * Note that Imagick::getImageAlphaChannel() is only available if Imagick
			 * has been compiled against ImageMagick version 6.4.0 or newer.
			 */
			if ( is_callable( array( $this->image, 'getImageAlphaChannel' ) ) ) {
				if ( ! $this->image->getImageAlphaChannel() ) {
					return false;
				}
			}

			// Walk through the pixels and look transparent pixels.
			$w = $this->image->getImageWidth();
			$h = $this->image->getImageHeight();
			for ( $x = 0; $x < $w; $x++ ) {
				for ( $y = 0; $y < $h; $y++ ) {
					$pixel = $this->image->getImagePixelColor( $x, $y );
					$color = $pixel->getColor();
					if ( $color['a'] > 0 ) {
						return true;
					}
				}
			}
			return false;

		} catch ( Exception $e ) {
			/* translators: %s is the error message */
			return new WP_Error( 'image_editor_has_transparency_error', sprintf( __( 'Transparency detection failed: %s', 'performance-lab' ), $e->getMessage() ) );
		}
	}
}
