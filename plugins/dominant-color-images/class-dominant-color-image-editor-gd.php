<?php
/**
 * WordPress Image Editor Class for Image Manipulation through GD
 * with dominant color detection
 *
 * @package dominant-color-images
 *
 * @since 1.0.0
 */

declare( strict_types = 1 );

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
	 * Averages all pixels in linear light to avoid the gamma-skewed
	 * dominant color that results from averaging gamma-encoded sRGB values.
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

		$image_width  = (int) imagesx( $this->image ); // @phpstan-ignore cast.useless
		$image_height = (int) imagesy( $this->image ); // @phpstan-ignore cast.useless

		// Build a 256-entry LUT for sRGB 8-bit → linear float conversion.
		$srgb_to_linear = array();
		for ( $i = 0; $i < 256; $i++ ) {
			$val = $i / 255;
			if ( $val <= 0.04045 ) {
				$srgb_to_linear[ $i ] = $val / 12.92;
			} else {
				$srgb_to_linear[ $i ] = pow( ( $val + 0.055 ) / 1.055, 2.4 );
			}
		}

		// Helper: linear float (0.0–1.0) → sRGB 8-bit integer.
		$linear_to_srgb = static function ( float $linear ): int {
			if ( $linear <= 0.0031308 ) {
				$srgb = 12.92 * $linear;
			} else {
				$srgb = 1.055 * pow( $linear, 1 / 2.4 ) - 0.055;
			}
			$clamped = max( 0.0, min( 1.0, $srgb ) );
			return (int) round( $clamped * 255 );
		};

		// Determine if the image is truecolor or palette-based.
		$is_truecolor = imageistruecolor( $this->image );

		// For palette-based images, cache the palette for fast lookup.
		$palette = null;
		if ( ! $is_truecolor ) {
			$palette    = array();
			$num_colors = imagecolorstotal( $this->image );
			for ( $i = 0; $i < $num_colors; $i++ ) {
				$palette[ $i ] = imagecolorsforindex( $this->image, $i );
			}
		}

		// Iterate every pixel, decode to linear light, and accumulate.
		// Skip transparent pixels on the first pass; if no opaque pixels
		// are found, fall back to including all pixels.
		$include_transparent = false;

		do {
			$sum_r         = 0.0;
			$sum_g         = 0.0;
			$sum_b         = 0.0;
			$count         = 0;
			$loop_again    = false;

			for ( $y = 0; $y < $image_height; $y++ ) {
				for ( $x = 0; $x < $image_width; $x++ ) {
					$rgb = imagecolorat( $this->image, $x, $y );
					if ( false === $rgb ) {
						continue;
					}

					if ( $is_truecolor ) {
						$r     = ( $rgb >> 16 ) & 0xFF;
						$g     = ( $rgb >> 8 ) & 0xFF;
						$b     = $rgb & 0xFF;
						if ( ! $include_transparent ) {
							$alpha = ( $rgb >> 24 ) & 0x7F;
							if ( $alpha > 0 ) {
								continue;
							}
						}
					} else {
						$index = $rgb;
						if ( ! isset( $palette[ $index ] ) ) {
							continue;
						}
						$rgba = $palette[ $index ];
						$r    = $rgba['red'];
						$g    = $rgba['green'];
						$b    = $rgba['blue'];
						if ( ! $include_transparent && $rgba['alpha'] > 0 ) {
							continue;
						}
					}

					$sum_r += $srgb_to_linear[ $r ];
					$sum_g += $srgb_to_linear[ $g ];
					$sum_b += $srgb_to_linear[ $b ];
					$count++;
				}
			}

			if ( 0 === $count && ! $include_transparent ) {
				$include_transparent = true;
				$loop_again          = true;
			}
		} while ( $loop_again );

		if ( 0 === $count ) {
			return new WP_Error( 'image_editor_dominant_color_error', __( 'Dominant color detection failed.', 'dominant-color-images' ) );
		}

		return array(
			'r' => $linear_to_srgb( $sum_r / $count ),
			'g' => $linear_to_srgb( $sum_g / $count ),
			'b' => $linear_to_srgb( $sum_b / $count ),
		);
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

		$small = imagecreatetruecolor( 3, 2 );
		if ( false === $small ) {
			return array();
		}

		// Fill with fully transparent white so imagecopyresampled can blend
		// against it, then we will flatten below.
		imagesavealpha( $small, true );
		$transparent = imagecolorallocatealpha( $small, 255, 255, 255, 127 );
		if ( false !== $transparent ) {
			imagefill( $small, 0, 0, $transparent );
		}

		$image_width  = (int) imagesx( $this->image );
		$image_height = (int) imagesy( $this->image );
		imagecopyresampled( $small, $this->image, 0, 0, 0, 0, 3, 2, $image_width, $image_height );

		// Flatten any residual alpha against white background so the
		// returned RGB values are always opaque.
		$flat = imagecreatetruecolor( 3, 2 );
		if ( false !== $flat ) {
			$white = imagecolorallocate( $flat, 255, 255, 255 );
			if ( false !== $white ) {
				imagefill( $flat, 0, 0, $white );
			}
			imagealphablending( $flat, true );
			imagesavealpha( $flat, false );
			imagecopy( $flat, $small, 0, 0, 0, 0, 3, 2 );
			$small = $flat;
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
			$rgb = imagecolorat( $small, $pos[0], $pos[1] );
			if ( false === $rgb ) {
				continue;
			}
			$values[] = array(
				'r' => ( $rgb >> 16 ) & 0xFF,
				'g' => ( $rgb >> 8 ) & 0xFF,
				'b' => $rgb & 0xFF,
			);
		}

		if ( count( $values ) < 6 ) {
			return array();
		}

		return $values;
	}
}
