<?php
/**
 * WordPress Image Editor Class for Image Manipulation through GD
 * with dominant color detection
 *
 * @package performance-lab
 * @group dominant-color
 *
 * @since n.e.x.t
 */

/**
 * WordPress Image Editor Class for Image Manipulation through GD
 * with dominant color detection
 *
 * @since n.e.x.t
 *
 * @see WP_Image_Editor
 */
class Dominant_Color_Image_Editor_GD extends WP_Image_Editor_GD {

	/**
	 * Get dominant color from a file.
	 *
	 * @since n.e.x.t
	 *
	 * @return string|WP_Error Dominant hex color string.
	 */
	public function get_dominant_color() {

		if ( ! $this->image ) {
			return new WP_Error( 'image_editor_dominant_color_error_no_image', __( 'Dominant color detection no image found.', 'performance-lab' ) );
		}

		$shorted_image = imagecreatetruecolor( 1, 1 );
		imagecopyresampled( $shorted_image, $this->image, 0, 0, 0, 0, 1, 1, imagesx( $this->image ), imagesy( $this->image ) );

		$hex = dechex( imagecolorat( $shorted_image, 0, 0 ) );

		if ( strlen( $hex ) < 6 ) {
			return new WP_Error( 'image_editor_dominant_color_error', __( 'Dominant color detection failed.', 'performance-lab' ) );
		}
		return $hex;
	}


	/**
	 * Looks for transparent pixels in the image.
	 * If there are none, it returns false.
	 *
	 * @since n.e.x.t
	 *
	 * @return bool|WP_Error True if there are transparent pixels, false if not.
	 */
	public function has_transparency() {

		if ( ! $this->image ) {
			return new WP_Error( 'image_editor_has_transparency_error_no_image', __( 'Transparency detection no image found.', 'performance-lab' ) );
		}

		// walk through the pixels.
		$w = imagesx( $this->image );
		$h = imagesy( $this->image );
		for ( $x = 0; $x < $w; $x++ ) {
			for ( $y = 0; $y < $h; $y++ ) {
				$rgb  = imagecolorat( $this->image, $x, $y );
				$rgba = imagecolorsforindex( $this->image, $rgb );
				if ( $rgba['alpha'] > 0 ) {
					return true;
				}
			}
		}
		return false;
	}
}
