<?php

namespace Dominant_Color_Images\Tests;

use WP_UnitTestCase;

abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->reset_wp_dependencies();
	}

	/**
	 * After a test method runs, resets any state in WordPress the test method might have changed.
	 */
	public function tear_down(): void {
		parent::tear_down();
		$this->reset_wp_dependencies();
	}

	/**
	 * Reset WP_Scripts and WP_Styles.
	 */
	private function reset_wp_dependencies(): void {
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
	}

	/**
	 * Data provider for test_get_dominant_color_GD.
	 *
	 * @return array<string, mixed>
	 */
	public function provider_get_dominant_color(): array {
		return array(
			'animated_gif'        => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/animated.gif',
				'expected_color'        => array( 'e3a3a3', 'e3a5a5' ),
				'expected_transparency' => true,
				'expected_lqip'         => null,
			),
			'red_jpg'             => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/red.jpg',
				'expected_color'        => array( 'ff0000', 'fe0000' ),
				'expected_transparency' => false,
				'expected_lqip'         => 174772,
			),
			'green_jpg'           => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/green.jpg',
				'expected_color'        => array( '00ff00', '00ff01', '02ff01' ),
				'expected_transparency' => false,
				'expected_lqip'         => 174797,
			),
			'white_jpg'           => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/white.jpg',
				'expected_color'        => array( 'ffffff' ),
				'expected_transparency' => false,
				'expected_lqip'         => 174819,
			),

			'red_gif'             => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/red.gif',
				'expected_color'        => array( 'ff0000' ),
				'expected_transparency' => false,
				'expected_lqip'         => 174781,
			),
			'green_gif'           => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/green.gif',
				'expected_color'        => array( '00ff00' ),
				'expected_transparency' => false,
				'expected_lqip'         => 174797,
			),
			'white_gif'           => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/white.gif',
				'expected_color'        => array( 'ffffff' ),
				'expected_transparency' => false,
				'expected_lqip'         => 174819,
			),
			'trans_gif'           => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/trans.gif',
				'expected_color'        => array( '5a5a5a', '828282' ),
				'expected_transparency' => true,
				'expected_lqip'         => null,
			),

			'red_png'             => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/red.png',
				'expected_color'        => array( 'ff0000' ),
				'expected_transparency' => false,
				'expected_lqip'         => 174781,
			),
			'green_png'           => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/green.png',
				'expected_color'        => array( '00ff00' ),
				'expected_transparency' => false,
				'expected_lqip'         => 174797,
			),
			'white_png'           => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/white.png',
				'expected_color'        => array( 'ffffff' ),
				'expected_transparency' => false,
				'expected_lqip'         => 174819,
			),
			'trans_png'           => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/trans.png',
				'expected_color'        => array( '000000', 'f7f7f7' ),
				'expected_transparency' => true,
				'expected_lqip'         => null,
			),

			'red_webp'            => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/red.webp',
				'expected_color'        => array( 'ff0000' ),
				'expected_transparency' => false,
				'expected_lqip'         => 174781,
			),
			'green_webp'          => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/green.webp',
				'expected_color'        => array( '00ff00' ),
				'expected_transparency' => false,
				'expected_lqip'         => 174797,
			),
			'white_webp'          => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/white.webp',
				'expected_color'        => array( 'ffffff' ),
				'expected_transparency' => false,
				'expected_lqip'         => 174819,
			),
			'trans_webp'          => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/trans.webp',
				'expected_color'        => array( '000000' ),
				'expected_transparency' => true,
				'expected_lqip'         => null,
			),
			'balloons_webp'       => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/balloons.webp',
				'expected_color'        => array( 'c9c4c6', 'c9c4c7', 'cac5c8' ),
				'expected_transparency' => false,
				'expected_lqip'         => 169443,
			),
			'half_opaque'         => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/half-opaque.png',
				'expected_color'        => array( '7e7e7e', 'ffffff', '000000' ),
				'expected_transparency' => true,
				'expected_lqip'         => null,
			),
			'checkerboard_bw_2x2' => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/data/images/checkerboard-bw-2x2.png',
				'expected_color'        => array( 'bbbbbb', 'bcbcbc' ),
				'expected_transparency' => false,
				'expected_lqip'         => 67299,
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
				'image_path' => TESTS_PLUGIN_DIR . '/tests/data/images/test-image.tiff',
			),
			'bmp'  => array(
				'image_path' => TESTS_PLUGIN_DIR . '/tests/data/images/test-image.bmp',
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
				'image_path' => TESTS_PLUGIN_DIR . '/tests/data/images/wordpress-gsoc-flyer.pdf',
			),
			'mp4' => array(
				'image_path' => TESTS_PLUGIN_DIR . '/tests/data/images/small-video.mp4',
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
	public function test_get_dominant_color_valid( string $image_path, array $expected_color, bool $expected_transparency, ?int $expected_lqip ): void {
		$mime_type = wp_check_filetype( $image_path )['type'];
		if ( ! wp_image_editor_supports( array( 'mime_type' => $mime_type ) ) ) {
			$this->markTestSkipped( "Mime type $mime_type is not supported." );
		}

		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );

		$dominant_color_data = dominant_color_get_dominant_color_data( $attachment_id );

		$this->assertNotWPError( $dominant_color_data );
		$this->assertContains( $dominant_color_data['dominant_color'], $expected_color );
		$this->assertSame( $dominant_color_data['has_transparency'], $expected_transparency );

		if ( null === $expected_lqip ) {
			$this->assertArrayNotHasKey( 'lqip', $dominant_color_data );
		} else {
			$this->assertArrayHasKey( 'lqip', $dominant_color_data );
			$this->assertSame( $expected_lqip, $dominant_color_data['lqip'] );
		}
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
		$mime_type = wp_check_filetype( $image_path )['type'];
		// Old WP does not support ".tiff" and ".bmp" so return false.
		if ( false === $mime_type ) {
			$this->markTestSkipped( 'Mime type is not supported.' );
		}
		if ( ! wp_image_editor_supports( array( 'mime_type' => $mime_type ) ) ) {
			$this->markTestSkipped( "Mime type $mime_type is not supported." );
		}
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => $mime_type,
			)
		);

		$dominant_color_data = dominant_color_get_dominant_color_data( $attachment_id );

		$this->assertWPError( $dominant_color_data );
		$this->assertSame( 'unsupported_attachment_type', $dominant_color_data->get_error_code() );
	}

	/**
	 * Tests dominant_color_get_dominant_color_data() returns a WP_Error when the
	 * dominant_color_supported_mime_types filter returns an empty array.
	 *
	 * @covers dominant_color_get_dominant_color_data
	 */
	public function test_get_dominant_color_data_unsupported_mime_type(): void {
		add_filter( 'dominant_color_supported_mime_types', '__return_empty_array' );

		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);

		$dominant_color_data = dominant_color_get_dominant_color_data( $attachment_id );

		$this->assertWPError( $dominant_color_data );
		$this->assertSame( 'unsupported_attachment_type', $dominant_color_data->get_error_code() );
	}

	/**
	 * Tests dominant_color_get_dominant_color_data() returns a WP_Error for non-image file types.
	 *
	 * @covers Dominant_Color_Image_Editor_GD::get_dominant_color
	 * @covers Dominant_Color_Image_Editor_Imagick::get_dominant_color
	 *
	 * @dataProvider provider_get_dominant_color_none_images
	 */
	public function test_get_dominant_color_none_images( string $image_path ): void {
		$mime_type = wp_check_filetype( $image_path )['type'];
		if ( false === $mime_type ) {
			$this->markTestSkipped( 'Mime type is not supported.' );
		}

		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => $mime_type,
			)
		);

		$dominant_color_data = dominant_color_get_dominant_color_data( $attachment_id );

		$this->assertWPError( $dominant_color_data );
		$this->assertSame( 'unsupported_attachment_type', $dominant_color_data->get_error_code() );
	}
}
