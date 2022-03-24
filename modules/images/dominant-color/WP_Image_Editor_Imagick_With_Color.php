<?php

class WP_Image_Editor_Imagick_With_Color extends WP_Image_Editor_Imagick {

	/**

	 * @param $default_color
	 *
	 * @return string
	 */
	public function get_dominant_color(  $default_color = 'eee' ) {

		try {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $this->image ) {

				$this->image->setImageColorspace( Imagick::COLORSPACE_RGB );
				$this->image->setImageFormat( 'RGB' );
				$this->image->resizeImage( 1, 1, Imagick::FILTER_LANCZOS, 1 );
				$pixel = $this->image->getImagePixelColor( 0, 0 );
				$color = $pixel->getColor();

				return dechex( $color['r'] ) . dechex( $color['g'] ) . dechex( $color['b'] );
			} else {
				return $default_color;
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'get_dominant_color', $e->getMessage() );

		}
	}

	/**
	 * @param $file
	 *
	 * @return bool
	 * @throws ImagickException
	 */
	public function get_has_transparency( $file ) {

		if ( $this->image ) {

			try {
				$colorspace = $this->image->getColorspace();
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return (bool) @$this->image->getImageAlphaChannel() == Imagick::$colorspace;
			} catch ( Exception $e ) {
				return new WP_Error( 'get_has_transparency', $e->getMessage() );
			}
		} else {
			return false;
		}
	}
}
