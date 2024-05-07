<?php
/**
 * Tests for Image Placeholders plugin.
 *
 * @package dominant-color-images
 */

use PerformanceLab\Tests\TestCase\DominantColorTestCase;

class Dominant_Color_Image_Editor_GD_Test extends DominantColorTestCase {

	/**
	 * Test if the function returns the correct color.
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @covers       Dominant_Color_Image_Editor_GD::get_dominant_color
	 *
	 * @phpstan-param string[] $expected_color
	 */
	public function test_get_dominant_color( string $image_path, array $expected_color, bool $expected_transparency ): void {
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
	public function test_get_dominant_color_invalid( string $image_path ): void {
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
	public function test_get_dominant_color_none_images( string $image_path ): void {
		if ( ! extension_loaded( 'gd' ) || ! function_exists( 'gd_info' ) ) {
			$this->markTestSkipped( 'The GD PHP extension is not loaded.' );
		}

		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );
		wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );

		$dominant_color_data = dominant_color_get_dominant_color_data( $attachment_id );

		$this->assertWPError( $dominant_color_data );
	}
}
