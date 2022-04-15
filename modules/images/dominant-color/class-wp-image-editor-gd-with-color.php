<?php
/**
 * WordPress Image Editor Class for Image Manipulation through GD
 * with dominant color detection
 *
 * @package performance-lab
 * @group dominant-color
 *
 * @since 1.0.0
 */

/**
 * WordPress Image Editor Class for Image Manipulation through GD
 * with dominant color detection
 *
 * @since n.e.x.t
 *
 * @see WP_Image_Editor
 */
class WP_Image_Editor_GD_With_Color extends WP_Image_Editor_GD {

	/**
	 * Get dominant color from a file.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $default_color Optional. Hex color string, without leading #. Default is light grey.
	 * @return string Dominant hex color string.
	 */
	public function get_dominant_color( $default_color = 'eee' ) {

		if ( $this->image ) {
			$shortend_image = imagecreatetruecolor( 1, 1 );
			imagecopyresampled( $shortend_image, $this->image, 0, 0, 0, 0, 1, 1, imagesx( $this->image ), imagesy( $this->image ) );

			$hex = dechex( imagecolorat( $shortend_image, 0, 0 ) );

			return ( '0' === $hex ) ? $default_color : $hex;
		}

		return $default_color;
	}


	/**
	 * Looks for transparent pixels in the image.
	 * If there are none, it returns false.
	 *
	 * @return bool
	 */
	public function get_has_transparency() {

		if ( $this->image ) {
			// walk through the pixels.
			$w = imagesx( $this->image );
			$h = imagesy( $this->image );
			for ( $x = 0; $x < $w; $x ++ ) {
				for ( $y = 0; $y < $h; $y ++ ) {
					$rgb  = imagecolorat( $this->image, $x, $y );
					$rgba = imagecolorsforindex( $this->image, $rgb );
					if ( $rgba['alpha'] > 0 ) {
						return true;
					}
				}
			}
		}

		return false;
	}
}
