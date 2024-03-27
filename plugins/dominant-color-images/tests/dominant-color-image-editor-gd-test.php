<?php
/**
 * Tests for Image Placeholders plugin.
 *
 * @package dominant-color-images
 */

use Dominant_Color_Image\Tests\TestCase as DominantColorTestCase;

class Dominant_Color_Image_Editor_GD_Test extends DominantColorTestCase {

	/**
	 * Setup before class.
	 */
	public static function wpSetUpBeforeClass() {
		// Setup site options if it's a multisite network.
		if ( is_multisite() ) {
			$site_exts = explode( ' ', get_site_option( 'upload_filetypes', 'jpg jpeg png gif' ) );

			// Add `tiff` and `bmp` to the list of allowed file types.
			// These are removed by default in multisite.
			$site_exts[] = 'tiff';
			$site_exts[] = 'bmp';

			update_site_option( 'upload_filetypes', implode( ' ', $site_exts ) );
		}
	}

	/**
	 * Test if the function returns the correct color.
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @covers       Dominant_Color_Image_Editor_GD::get_dominant_color
	 */
	public function test_get_dominant_color( $image_path, $expected_color, $expected_transparency ) {
		if ( ! extension_loaded( 'gd' ) || ! function_exists( 'gd_info' ) ) {
			$this->markTestSkipped( 'The GD PHP extension is not loaded.' );
		}

		$mime_type = wp_check_filetype( $image_path )['type'];
		if ( ! wp_image_editor_supports( array( 'mime_type' => $mime_type ) ) ) {
			$this->markTestSkipped( "Mime type $mime_type is not supported." );
		}

		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );
		wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );

		$dominant_color_data = dominant_color_get_dominant_color_data( $attachment_id );

		$this->assertNotWPError( $dominant_color_data );
		$this->assertContains( $dominant_color_data['dominant_color'], $expected_color );
		$this->assertSame( $dominant_color_data['has_transparency'], $expected_transparency );
	}

	/**
	 * Test if the function returns the correct color.
	 *
	 * @dataProvider provider_get_dominant_color_invalid_images
	 *
	 * @group ms-excluded
	 *
	 * @covers       Dominant_Color_Image_Editor_GD::get_dominant_color
	 */
	public function test_get_dominant_color_invalid( $image_path ) {
		if ( ! extension_loaded( 'gd' ) || ! function_exists( 'gd_info' ) ) {
			$this->markTestSkipped( 'The GD PHP extension is not loaded.' );
		}

		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );
		wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );

		$dominant_color_data = dominant_color_get_dominant_color_data( $attachment_id );

		$this->assertWPError( $dominant_color_data );
		$this->assertStringContainsString( 'image_no_editor', $dominant_color_data->get_error_code() );
	}

	/**
	 * Test if the function returns the correct color.
	 *
	 * @dataProvider provider_get_dominant_color_none_images
	 *
	 * @covers       Dominant_Color_Image_Editor_GD::get_dominant_color
	 */
	public function test_get_dominant_color_none_images( $image_path ) {
		if ( ! extension_loaded( 'gd' ) || ! function_exists( 'gd_info' ) ) {
			$this->markTestSkipped( 'The GD PHP extension is not loaded.' );
		}

		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );
		wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );

		$dominant_color_data = dominant_color_get_dominant_color_data( $attachment_id );

		$this->assertWPError( $dominant_color_data );
	}
}