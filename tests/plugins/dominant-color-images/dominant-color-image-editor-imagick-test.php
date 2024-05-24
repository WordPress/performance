<?php
/**
 * Tests for Image Placeholders plugin.
 *
 * @since 1.2.0
 *
 * @package dominant-color-images
 */

use PerformanceLab\Tests\TestCase\DominantColorTestCase;

class Dominant_Color_Image_Editor_Imagick_Test extends DominantColorTestCase {

	/**
	 * Makes sure that only the Imagick editor is used.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick', false ) ) {
			$this->markTestSkipped( 'The Imagick PHP extension is not loaded.' );
		}

		add_filter(
			'wp_image_editors',
			static function ( array $editors ): array {
				return array_filter(
					$editors,
					static function ( $editor ): bool {
						return WP_Image_Editor_Imagick::class === $editor;
					}
				);
			}
		);
	}
}
