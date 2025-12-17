<?php
/**
 * WordPress Image Editor Class for Image Manipulation through Imagick
 * for transparency detection
 *
 * @package webp-uploads
 *
 * @since n.e.x.t
 */

/**
 * WordPress Image Editor Class for Image Manipulation through Imagick
 * for transparency detection.
 *
 * @since n.e.x.t
 *
 * @see WP_Image_Editor
 */
class WebP_Uploads_Image_Editor_Imagick extends WP_Image_Editor_Imagick {
	/**
	 * The current instance of the image editor.
	 *
	 * @since n.e.x.t
	 *
	 * @var WebP_Uploads_Image_Editor_Imagick
	 */
	public static $current_instance;

	/**
	 * Load the image and set the current instance.
	 *
	 * @since n.e.x.t
	 *
	 * @return WP_Error|true True on success, WP_Error on failure.
	 */
	public function load() {
		$result = parent::load();
		if ( ! is_wp_error( $result ) ) {
			self::$current_instance = $this;
		}
		return $result;
	}

	/**
	 * Get the file path of the image.
	 *
	 * @since n.e.x.t
	 *
	 * @return string The file path of the image.
	 */
	public function get_file(): string {
		return $this->file;
	}

	/**
	 * Looks for transparent pixels in the image.
	 * If there are none, it returns false.
	 *
	 * @since n.e.x.t
	 *
	 * @return bool|WP_Error True or false based on whether there are transparent pixels, or an error on failure.
	 */
	public function has_transparency() {

		if ( ! (bool) $this->image ) {
			return new WP_Error( 'image_editor_has_transparency_error_no_image', __( 'Transparency detection no image found.', 'webp-uploads' ) );
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
					$color = $pixel->getColor( 2 );
					if ( $color['a'] > 0 ) {
						return true;
					}
				}
			}
			return false;

		} catch ( Exception $e ) {
			/* translators: %s is the error message */
			return new WP_Error( 'image_editor_has_transparency_error', sprintf( __( 'Transparency detection failed: %s', 'webp-uploads' ), $e->getMessage() ) );
		}
	}
}
