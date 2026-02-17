<?php
/**
 * WordPress Image Editor Class for Image Manipulation through Imagick
 * for transparency detection
 *
 * @package webp-uploads
 *
 * @since n.e.x.t
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

if ( class_exists( 'WebP_Uploads_Image_Editor_Imagick_Base' ) ) {

	/**
	 * WordPress Image Editor Class for Image Manipulation through Imagick
	 * for transparency detection.
	 *
	 * @since n.e.x.t
	 *
	 * @see WP_Image_Editor
	 */
	class WebP_Uploads_Image_Editor_Imagick extends WebP_Uploads_Image_Editor_Imagick_Base {
		/**
		 * The current instance of the image editor.
		 *
		 * @since n.e.x.t
		 *
		 * @var WebP_Uploads_Image_Editor_Imagick|null $current_instance The current instance.
		 */
		public static $current_instance = null;

		/**
		 * Stores already checked images for transparency.
		 *
		 * @since n.e.x.t
		 *
		 * @var array<string, bool> Associative array with file paths as keys and transparency detection results as values.
		 */
		private static $checked_images = array();

		/**
		 * Load the image and set the current instance.
		 *
		 * @since n.e.x.t
		 *
		 * @return WP_Error|true True on success, WP_Error on failure.
		 */
		public function load() {
			// @phpstan-ignore-next-line -- Parent class is created via class_alias at runtime.
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
		 * @return string|null The file path of the image, or null if not available.
		 */
		public function get_file(): ?string {
			if ( property_exists( $this, 'file' ) && is_string( $this->file ) ) {
				return $this->file;
			}
			return null;
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
			if ( ! property_exists( $this, 'image' ) || ! $this->image instanceof Imagick ) {
				return new WP_Error( 'image_editor_has_transparency_error_no_image', __( 'Transparency detection no image found.', 'webp-uploads' ) );
			}

			$file_path = $this->get_file();
			if ( isset( $file_path, self::$checked_images[ $file_path ] ) ) {
				return self::$checked_images[ $file_path ];
			}
			$transparency = false;
			$use_fallback = false;

			try {
				/*
				 * Check if the image has an alpha channel if false, then it can't have transparency so return early.
				 *
				 * Note that Imagick::getImageAlphaChannel() is only available if Imagick
				 * has been compiled against ImageMagick version 6.4.0 or newer.
				 */
				if ( Imagick::ALPHACHANNEL_UNDEFINED === $this->image->getImageAlphaChannel() ) {
					self::$checked_images[ $file_path ] = false;
					return false;
				}

				// Use mean and range to determine if there is any transparency more efficiently.
				$rgb_mean    = $this->image->getImageChannelMean( Imagick::CHANNEL_ALL );
				$alpha_range = $this->image->getImageChannelRange( Imagick::CHANNEL_ALPHA );

				if ( isset( $rgb_mean['mean'], $alpha_range['maxima'] ) ) {
					$maxima = (int) $alpha_range['maxima'];
					$mean   = (int) $rgb_mean['mean'];

					if ( 0 > $maxima || 0 > $mean ) {
						// For invalid values use fallback.
						$use_fallback = true;
					} elseif ( 0 === $maxima && 0 === $mean ) {
						// Alpha channel is all zeros AND no RGB content indicates fully transparent image.
						$transparency = true;
					} elseif ( 0 === $maxima && $mean > 0 ) {
						// Alpha maxima of 0 with RGB content present indicates no real alpha channel exists (hence fully opaque).
						$transparency = false;
					} elseif ( 0 < $maxima && 0 < $mean ) {
						// Non-zero alpha values with RGB content present indicates some transparency.
						$transparency = true;
					} else {
						// For any other case use fallback.
						$use_fallback = true;
					}
				} else {
					$use_fallback = true;
				}

				if ( $use_fallback ) {
					// Fallback to walk through the pixels and look for transparent pixels.
					$w = $this->image->getImageWidth();
					$h = $this->image->getImageHeight();
					for ( $x = 0; $x < $w; $x++ ) {
						for ( $y = 0; $y < $h; $y++ ) {
							$pixel = $this->image->getImagePixelColor( $x, $y );
							$color = $pixel->getColor( 2 );
							if ( $color['a'] < 255 ) {
								$transparency = true;
								break 2;
							}
						}
					}
				}

				self::$checked_images[ $file_path ] = $transparency;
				return $transparency;
			} catch ( Throwable $e ) {
				/* translators: %s is the error message */
				return new WP_Error( 'image_editor_has_transparency_error', sprintf( __( 'Transparency detection failed: %s', 'webp-uploads' ), $e->getMessage() ) );
			}
		}
	}
}
