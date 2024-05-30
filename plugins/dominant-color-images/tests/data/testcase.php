<?php

namespace PerformanceLab\Tests\TestCase;

use WP_UnitTestCase;

abstract class DominantColorTestCase extends WP_UnitTestCase {
	/**
	 * Data provider for test_get_dominant_color_GD.
	 *
	 * @return array<string, mixed>
	 */
	public function provider_get_dominant_color(): array {
		return array(
			'animated_gif'  => array(
				'image_path'            => __DIR__ . '/images/animated.gif',
				'expected_color'        => array( '874e4e', '864e4e', 'df7f7f' ),
				'expected_transparency' => true,
			),
			'red_jpg'       => array(
				'image_path'            => __DIR__ . '/images/red.jpg',
				'expected_color'        => array( 'ff0000', 'fe0000' ),
				'expected_transparency' => false,
			),
			'green_jpg'     => array(
				'image_path'            => __DIR__ . '/images/green.jpg',
				'expected_color'        => array( '00ff00', '00ff01', '02ff01' ),
				'expected_transparency' => false,
			),
			'white_jpg'     => array(
				'image_path'            => __DIR__ . '/images/white.jpg',
				'expected_color'        => array( 'ffffff' ),
				'expected_transparency' => false,
			),

			'red_gif'       => array(
				'image_path'            => __DIR__ . '/images/red.gif',
				'expected_color'        => array( 'ff0000' ),
				'expected_transparency' => false,
			),
			'green_gif'     => array(
				'image_path'            => __DIR__ . '/images/green.gif',
				'expected_color'        => array( '00ff00' ),
				'expected_transparency' => false,
			),
			'white_gif'     => array(
				'image_path'            => __DIR__ . '/images/white.gif',
				'expected_color'        => array( 'ffffff' ),
				'expected_transparency' => false,
			),
			'trans_gif'     => array(
				'image_path'            => __DIR__ . '/images/trans.gif',
				'expected_color'        => array( '5a5a5a', '020202' ),
				'expected_transparency' => true,
			),

			'red_png'       => array(
				'image_path'            => __DIR__ . '/images/red.png',
				'expected_color'        => array( 'ff0000' ),
				'expected_transparency' => false,
			),
			'green_png'     => array(
				'image_path'            => __DIR__ . '/images/green.png',
				'expected_color'        => array( '00ff00' ),
				'expected_transparency' => false,
			),
			'white_png'     => array(
				'image_path'            => __DIR__ . '/images/white.png',
				'expected_color'        => array( 'ffffff' ),
				'expected_transparency' => false,
			),
			'trans_png'     => array(
				'image_path'            => __DIR__ . '/images/trans.png',
				'expected_color'        => array( '000000' ),
				'expected_transparency' => true,
			),

			'red_webp'      => array(
				'image_path'            => __DIR__ . '/images/red.webp',
				'expected_color'        => array( 'ff0000' ),
				'expected_transparency' => false,
			),
			'green_webp'    => array(
				'image_path'            => __DIR__ . '/images/green.webp',
				'expected_color'        => array( '00ff00' ),
				'expected_transparency' => false,
			),
			'white_webp'    => array(
				'image_path'            => __DIR__ . '/images/white.webp',
				'expected_color'        => array( 'ffffff' ),
				'expected_transparency' => false,
			),
			'trans_webp'    => array(
				'image_path'            => __DIR__ . '/images/trans.webp',
				'expected_color'        => array( '000000' ),
				'expected_transparency' => true,
			),
			'balloons_webp' => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/balloons.webp',
				'expected_color'        => array( 'c1bbb9', 'c0bbb9', 'c0bab8', 'c3bdbd', 'bfbab8' ),
				'expected_transparency' => false,
			),
			'half_opaque'   => array(
				'image_path'            => __DIR__ . '/images/half-opaque.png',
				'expected_color'        => array( '7e7e7e' ),
				'expected_transparency' => true,
			),
		);
	}

	/**
	 * Data provider for test_get_dominant_color_GD.
	 *
	 * @return array<string, mixed>
	 */
	public function provider_get_dominant_color_invalid_images(): array {
		return array(
			'tiff' => array(
				'image_path' => __DIR__ . '/images/test-image.tiff',
			),
			'bmp'  => array(
				'image_path' => __DIR__ . '/images/test-image.bmp',
			),
		);
	}

	/**
	 * Data provider for test_get_dominant_color_GD.
	 *
	 * @return array<string, mixed>
	 */
	public function provider_get_dominant_color_none_images(): array {
		return array(
			'pdf' => array(
				'files_path' => __DIR__ . '/images/wordpress-gsoc-flyer.pdf',
			),
			'mp4' => array(
				'files_path' => __DIR__ . '/images/small-video.mp4',
			),
		);
	}

	/**
	 * Test if the function returns the correct color.
	 *
	 * @covers Dominant_Color_Image_Editor_GD::get_dominant_color
	 * @covers Dominant_Color_Image_Editor_Imagick::get_dominant_color
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @phpstan-param string[] $expected_color
	 */
	public function test_get_dominant_color_valid( string $image_path, array $expected_color, bool $expected_transparency ): void {
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
	 * @covers Dominant_Color_Image_Editor_GD::get_dominant_color
	 * @covers Dominant_Color_Image_Editor_Imagick::get_dominant_color
	 *
	 * @dataProvider provider_get_dominant_color_invalid_images
	 */
	public function test_get_dominant_color_invalid( string $image_path ): void {
		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );
		wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );

		$dominant_color_data = dominant_color_get_dominant_color_data( $attachment_id );

		$this->assertWPError( $dominant_color_data );
		$this->assertStringContainsString( 'image_no_editor', $dominant_color_data->get_error_code() );
	}

	/**
	 * Test if the function returns the correct color.
	 *
	 * @covers Dominant_Color_Image_Editor_GD::get_dominant_color
	 * @covers Dominant_Color_Image_Editor_Imagick::get_dominant_color
	 *
	 * @dataProvider provider_get_dominant_color_none_images
	 */
	public function test_get_dominant_color_none_images( string $image_path ): void {
		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );
		wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );

		$dominant_color_data = dominant_color_get_dominant_color_data( $attachment_id );

		$this->assertWPError( $dominant_color_data );
	}
}
