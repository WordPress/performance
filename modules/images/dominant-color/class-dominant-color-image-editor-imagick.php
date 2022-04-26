<?php
/**
 * WordPress Image Editor Class for Image Manipulation through Imagick
 * with dominant color detection
 *
 * @package performance-lab
 * @group dominant-color
 *
 * @since n.e.x.t
 */

/**
 * WordPress Image Editor Class for Image Manipulation through Imagick
 * with dominant color detection
 *
 * @since 6.x
 *
 * @see WP_Image_Editor
 */
class Dominant_Color_Image_Editor_Imagick extends WP_Image_Editor_Imagick {

	/**
	 * Get dominant color from a file.
	 *
	 * @since n.e.x.t
	 *
	 * @return string|WP_Error hex color
	 */
	public function get_dominant_color() {

		if ( ! $this->image ) {

			return new WP_Error( 'image_editor_dominant_color_error_no_image', __( 'Dominant color detection no image found.', 'performance-lab' ) );
		}

		try {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			$this->image->setImageColorspace( Imagick::COLORSPACE_RGB );
			$this->image->setImageFormat( 'RGB' );
			$this->image->resizeImage( 1, 1, Imagick::FILTER_LANCZOS, 1 );
			$pixel = $this->image->getImagePixelColor( 0, 0 );
			$color = $pixel->getColor();

			return dechex( $color['r'] ) . dechex( $color['g'] ) . dechex( $color['b'] );
		} catch ( Exception $e ) {

			return new WP_Error( 'image_editor_dominant_color_error', __( 'Dominant color detection failed.', 'performance-lab' ) );
		}
	}

	/**
	 * Looks for transparent pixels in the image.
	 * If there are none, it returns false.
	 *
	 * @since n.e.x.t
	 *
	 * @return bool
	 */
	public function get_has_transparency() {

		if ( ! $this->image ) {

			return false;
		}

		try {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return (bool) @$this->image->getImageAlphaChannel();
		} catch ( Exception $e ) {

			return false;
		}
	}
}
