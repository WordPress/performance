<?php

use PerformanceLab\Tests\TestCase\DominantColorTestCase;
/**
 * Tests for dominant-color module.
 *
 * @package performance-lab
 * @group dominant-color
 */
class Dominant_Color_Test extends DominantColorTestCase {

	/**
	 * Tests dominant_color_metadata().
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @covers ::dominant_color_metadata
	 */
	public function test_dominant_color_metadata( $image_path, $expected_color, $expected_transparency ) {
		// Non existing attachment.
		$dominant_color_metadata = dominant_color_metadata( array(), 1 );
		$this->assertEmpty( $dominant_color_metadata );

		// Creating attachment.
		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );
		wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );
		$dominant_color_metadata = dominant_color_metadata( array(), $attachment_id );
		$this->assertArrayHasKey( 'dominant_color', $dominant_color_metadata );
		$this->assertNotEmpty( $dominant_color_metadata['dominant_color'] );
		$this->assertContains( $dominant_color_metadata['dominant_color'], $expected_color );
	}

	/**
	 * Tests dominant_color_get_dominant_color().
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @covers ::dominant_color_get_dominant_color
	 */
	public function test_dominant_color_get_dominant_color( $image_path, $expected_color, $expected_transparency ) {
		// Creating attachment.
		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );
		$this->assertContains( dominant_color_get_dominant_color( $attachment_id ), $expected_color );
	}

	/**
	 * Tests has_transparency_metadata().
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @covers ::has_transparency_metadata
	 */
	public function test_has_transparency_metadata( $image_path, $expected_color, $expected_transparency ) {
		// Non existing attachment.
		$transparency_metadata = dominant_color_metadata( array(), 1 );
		$this->assertEmpty( $transparency_metadata );

		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );
		wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );
		$transparency_metadata = dominant_color_metadata( array(), $attachment_id );
		$this->assertArrayHasKey( 'has_transparency', $transparency_metadata );
		$this->assertSame( $expected_transparency, $transparency_metadata['has_transparency'] );
	}

	/**
	 * Tests dominant_color_get_dominant_color().
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @covers ::dominant_color_get_dominant_color
	 */
	public function test_dominant_color_has_transparency( $image_path, $expected_color, $expected_transparency ) {
		// Creating attachment.
		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );
		$this->assertSame( $expected_transparency, dominant_color_has_transparency( $attachment_id ) );
	}

	/**
	 * Tests tag_add_adjust().
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @covers ::dominant_color_img_tag_add_dominant_color
	 */
	public function test_tag_add_adjust_to_image_attributes( $image_path, $expected_color, $expected_transparency ) {
		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );
		wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );

		list( $src, $width, $height ) = wp_get_attachment_image_src( $attachment_id );
		// Testing tag_add_adjust() with image being lazy load.
		$filtered_image_mock_lazy_load = sprintf( '<img loading="lazy" class="test" src="%s" width="%d" height="%d" />', $src, $width, $height );

		$filtered_image_tags_added = dominant_color_img_tag_add_dominant_color( $filtered_image_mock_lazy_load, 'the_content', $attachment_id );

		$this->assertStringContainsString( 'data-has-transparency="' . json_encode( $expected_transparency ) . '"', $filtered_image_tags_added );

		foreach ( $expected_color as $color ) {
			if ( false !== strpos( $color, $filtered_image_tags_added ) ) {
				$this->assertStringContainsString( 'style="--dominant-color: #' . $expected_color . ';"', $filtered_image_tags_added );
				$this->assertStringContainsString( 'data-dominant-color="' . $expected_color . '"', $filtered_image_tags_added );
				break;
			}
		}

		// Deactivate filter.
		add_filter( 'dominant_color_img_tag_add_dominant_color', '__return_false' );
		$filtered_image_tags_not_added = dominant_color_img_tag_add_dominant_color( $filtered_image_mock_lazy_load, 'the_content', $attachment_id );
		$this->assertEquals( $filtered_image_mock_lazy_load, $filtered_image_tags_not_added );
	}

	/**
	 * Tests that only img tags using double quotes are updated.
	 *
	 * @covers ::dominant_color_img_tag_add_dominant_color
	 */
	public function test_dominant_color_img_tag_add_dominant_color_requires_proper_quotes() {
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/red.jpg' );
		wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );

		$image_url = wp_get_attachment_image_url( $attachment_id );

		$img_with_double_quotes = '<img src="' . $image_url . '">';
		$this->assertStringContainsString( ' data-dominant-color=', dominant_color_img_tag_add_dominant_color( $img_with_double_quotes, 'the_content', $attachment_id ) );

		$img_with_single_quotes = "<img src='" . $image_url . "'>";
		$this->assertStringNotContainsString( ' data-dominant-color=', dominant_color_img_tag_add_dominant_color( $img_with_single_quotes, 'the_content', $attachment_id ) );

		$img_with_escaped_quotes = '<img src=\"' . $image_url . '\">';
		$this->assertStringNotContainsString( ' data-dominant-color=', dominant_color_img_tag_add_dominant_color( $img_with_escaped_quotes, 'the_content', $attachment_id ) );
	}

	/**
	 * Tests that dominant_color_img_tag_add_dominant_color() does not replace existing inline styles.
	 *
	 * @dataProvider data_provider_dominant_color_check_inline_style
	 *
	 * @covers ::dominant_color_img_tag_add_dominant_color
	 *
	 * @param string $filtered_image The filtered image markup.
	 *                               Must include `src="%s" width="%d" height="%d"`.
	 * @param string $expected       The expected style attribute and value.
	 */
	public function test_dominant_color_img_tag_add_dominant_color_should_add_dominant_color_inline_style( $filtered_image, $expected ) {
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/red.jpg' );
		wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );

		list( $src, $width, $height ) = wp_get_attachment_image_src( $attachment_id );

		$filtered_image = sprintf( $filtered_image, $src, $width, $height );

		$this->assertStringContainsString(
			$expected,
			dominant_color_img_tag_add_dominant_color( $filtered_image, 'the_content', $attachment_id )
		);
	}

	/**
	 * Data provider for test_dominant_color_img_tag_add_dominant_color_should_add_dominant_color_inline_style().
	 *
	 * @return array[]
	 */
	public function data_provider_dominant_color_check_inline_style() {
		return array(
			'no existing inline styles' => array(
				'filtered_image' => '<img src="%s" width="%d" height="%d" />',
				'expected'       => 'style="--dominant-color: #fe0000;"',
			),
			'existing inline styles'    => array(
				'filtered_image' => '<img style="color: #ffffff;" src="%s" width="%d" height="%d" />',
				'expected'       => 'style="--dominant-color: #fe0000; color: #ffffff;"',
			),
		);
	}

	/**
	 * Tests dominant_color_set_image_editors().
	 *
	 * @dataProvider provider_dominant_color_set_image_editors
	 *
	 * @covers ::dominant_color_set_image_editors
	 */
	public function test_dominant_color_set_image_editors( $existing, $expected ) {
		$this->assertEqualSets( dominant_color_set_image_editors( $existing ), $expected );
	}

	public function provider_dominant_color_set_image_editors() {
		return array(
			'default'  => array(
				'existing' => array(
					'WP_Image_Editor_GD',
					'WP_Image_Editor_Imagick',
				),
				'expected' => array(
					'Dominant_Color_Image_Editor_GD',
					'Dominant_Color_Image_Editor_Imagick',
				),
			),
			'filtered' => array(
				'existing' => array(
					'WP_Image_Editor_Filered_GD',
					'WP_Image_Editor_Filered_Imagick',
				),
				'expected' => array(
					'WP_Image_Editor_Filered_GD',
					'WP_Image_Editor_Filered_Imagick',
				),
			),
			'added'    => array(
				'existing' => array(
					'WP_Image_Editor_Filered_GD',
					'WP_Image_Editor_Filered_Imagick',
					'WP_Image_Editor_GD',
					'WP_Image_Editor_Imagick',
				),
				'expected' => array(
					'WP_Image_Editor_Filered_GD',
					'WP_Image_Editor_Filered_Imagick',
					'Dominant_Color_Image_Editor_GD',
					'Dominant_Color_Image_Editor_Imagick',
				),
			),
			'empty'    => array(
				'existing' => array(),
				'expected' => array(),
			),
		);
	}

	/**
	 * Tests dominant_color_rgb_to_hex().
	 *
	 * @dataProvider provider_get_hex_color
	 *
	 * @covers ::dominant_color_rgb_to_hex
	 */
	public function test_dominant_color_rgb_to_hex( $red, $green, $blue, $hex ) {
		$this->assertSame( $hex, dominant_color_rgb_to_hex( $red, $green, $blue ) );
	}

	public function provider_get_hex_color() {
		return array(
			'black'   => array(
				'red'   => 0,
				'green' => 0,
				'blue'  => 0,
				'hex'   => '000000',
			),
			'white'   => array(
				'red'   => 255,
				'green' => 255,
				'blue'  => 255,
				'hex'   => 'ffffff',
			),
			'blue'    => array(
				'red'   => 255,
				'green' => 0,
				'blue'  => 0,
				'hex'   => 'ff0000',
			),
			'teal'    => array(
				'red'   => 255,
				'green' => 255,
				'blue'  => 0,
				'hex'   => 'ffff00',
			),
			'pink'    => array(
				'red'   => 255,
				'green' => 0,
				'blue'  => 255,
				'hex'   => 'ff00ff',
			),
			'purple'  => array(
				'red'   => 88,
				'green' => 42,
				'blue'  => 158,
				'hex'   => '582a9e',
			),
			'invalid' => array(
				'red'   => -1,
				'green' => -1,
				'blue'  => -1,
				'hex'   => null,
			),
		);
	}
}
