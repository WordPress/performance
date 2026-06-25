<?php
/**
 * WordPress Image Editor Class for Image Manipulation through Imagick
 * with dominant color detection
 *
 * @package dominant-color-images
 *
 * @since 1.0.0
 */

declare( strict_types = 1 );

/**
 * WordPress Image Editor Class for Image Manipulation through Imagick
 * with dominant color detection.
 *
 * @since 1.0.0
 *
 * @see WP_Image_Editor
 */
class Dominant_Color_Image_Editor_Imagick extends WP_Image_Editor_Imagick {

	/**
	 * Get dominant color from a file.
	 *
	 * @since 1.0.0
	 *
	 * @return string|WP_Error Dominant hex color string, or an error on failure.
	 */
	public function get_dominant_color() {
		$rgb = $this->get_dominant_color_rgb();
		if ( is_wp_error( $rgb ) ) {
			return $rgb;
		}

		$hex = dominant_color_rgb_to_hex( $rgb['r'], $rgb['g'], $rgb['b'] );
		if ( null === $hex ) {
			return new WP_Error( 'image_editor_dominant_color_error', __( 'Dominant color detection failed.', 'dominant-color-images' ) );
		}

		return $hex;
	}

	/**
	 * Get dominant color from a file as RGB values.
	 *
	 * @since n.e.x.t
	 *
	 * @return array{r: int, g: int, b: int}|WP_Error RGB values (0-255), or WP_Error on failure.
	 */
	public function get_dominant_color_rgb() {
		if ( ! (bool) $this->image ) {
			return new WP_Error( 'image_editor_dominant_color_error_no_image', __( 'Dominant color detection no image found.', 'dominant-color-images' ) );
		}

		try {
			// Clone so $this->image is not mutated — otherwise subsequent
			// calls (e.g. get_lqip_grid_values) would operate on a 1×1 image.
			$thumb = clone $this->image;

			// Convert to linear RGB before resizing to avoid gamma-skewed averaging.
			$thumb->transformImageColorspace( Imagick::COLORSPACE_RGB );
			$thumb->resizeImage( 1, 1, Imagick::FILTER_LANCZOS, 1 );
			$thumb->transformImageColorspace( Imagick::COLORSPACE_SRGB );
			$pixel = $thumb->getImagePixelColor( 0, 0 );
			$color = $pixel->getColor();

			// Cast to int: ImagickPixel::getColor() may return floats depending on
			// ImageMagick/Imagick configuration, which would break the int contract
			// of this method under strict_types.
			return array(
				'r' => (int) $color['r'],
				'g' => (int) $color['g'],
				'b' => (int) $color['b'],
			);
		} catch ( Exception $e ) {
			/* translators: %s is the error message. */
			return new WP_Error( 'image_editor_dominant_color_error', sprintf( __( 'Dominant color detection failed: %s', 'dominant-color-images' ), $e->getMessage() ) );
		}
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
			return new WP_Error( 'image_editor_has_transparency_error', sprintf( __( 'Transparency detection failed: %s', 'dominant-color-images' ), $e->getMessage() ) );
		}
	}

	/**
	 * Get the 3×2 grid pixel values from the image.
	 *
	 * The grid is a 3-column × 2-row sampling of the image, resized to
	 * exactly 6 pixels. Each cell's raw RGB values are returned for use
	 * in LQIP generation.
	 *
	 * @since 1.3.0
	 *
	 * @return array<int, array{r: int, g: int, b: int}> 6 grid cells as ['r'=>R, 'g'=>G, 'b'=>B].
	 */
	public function get_lqip_grid_values(): array {

		// Skip LQIP generation for images with transparency (the gradient
		// placeholder would show through transparent areas).
		$has_transparency = $this->has_transparency();
		if ( is_wp_error( $has_transparency ) || $has_transparency ) {
			return array();
		}

		try {
			$small = clone $this->image;

			// Resize to 3×2.
			$small->resizeImage( 3, 2, Imagick::FILTER_LANCZOS, 1 );
			$small->sharpenImage( 0, 0.5 );

			// Flatten if the image has an alpha channel.
			if ( is_callable( array( $small, 'getImageAlphaChannel' ) ) && $small->getImageAlphaChannel() ) {
				$small->setImageBackgroundColor( 'white' );
				$small = $small->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
			}

			// Cell positions: left-to-right, top-to-bottom.
			$cell_positions = array(
				array( 0, 0 ),
				array( 1, 0 ),
				array( 2, 0 ),
				array( 0, 1 ),
				array( 1, 1 ),
				array( 2, 1 ),
			);

			$values = array();

			foreach ( $cell_positions as $pos ) {
				$pixel    = $small->getImagePixelColor( $pos[0], $pos[1] );
				$color    = $pixel->getColor();
				$values[] = array(
					'r' => $color['r'],
					'g' => $color['g'],
					'b' => $color['b'],
				);
			}

			return $values;

		} catch ( Exception $e ) {
			return array();
		}
	}
}
