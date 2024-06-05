<?php
/**
 * Tests for Image Placeholders plugin.
 *
 * @package dominant-color-images
 */

use Dominant_Color_Images\Tests\TestCase;

class Test_Dominant_Color_Image_Editor_GD extends TestCase {

	/**
	 * Makes sure that only the GD editor is used.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! extension_loaded( 'gd' ) || ! function_exists( 'gd_info' ) ) {
			$this->markTestSkipped( 'The GD PHP extension is not loaded.' );
		}

		add_filter(
			'wp_image_editors',
			static function ( array $editors ): array {
				return array_filter(
					$editors,
					static function ( $editor ): bool {
						return WP_Image_Editor_GD::class === $editor;
					}
				);
			}
		);
	}
}
