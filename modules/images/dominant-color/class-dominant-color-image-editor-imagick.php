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
	 * @param string $default_color default is light grey.
	 *
	 * @since n.e.x.t
	 *
	 * @return string hex color
	 */
	public function get_dominant_color( $default_color = 'eee' ) {

		if ( ! $this->image ) {

			return $default_color;
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

			return $default_color;
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

		return false;
	}
}
