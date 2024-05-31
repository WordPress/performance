<?php

use Dominant_Color_Images\Tests\TestCase;
/**
 * Tests for Image Placeholders plugin.
 *
 * @package dominant-color-images
 */
class Test_Dominant_Color extends TestCase {

	/**
	 * Tests dominant_color_metadata().
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @covers ::dominant_color_metadata
	 *
	 * @param string   $image_path Image path.
	 * @param string[] $expected_color Expected color.
	 */
	public function test_dominant_color_metadata( string $image_path, array $expected_color ): void {
		$mime_type = wp_check_filetype( $image_path )['type'];
		if ( ! wp_image_editor_supports( array( 'mime_type' => $mime_type ) ) ) {
			$this->markTestSkipped( "Mime type $mime_type is not supported." );
		}

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
	 *
	 * @param string   $image_path Image path.
	 * @param string[] $expected_color Expected color.
	 */
	public function test_dominant_color_get_dominant_color( string $image_path, array $expected_color ): void {
		$mime_type = wp_check_filetype( $image_path )['type'];
		if ( ! wp_image_editor_supports( array( 'mime_type' => $mime_type ) ) ) {
			$this->markTestSkipped( "Mime type $mime_type is not supported." );
		}

		// Creating attachment.
		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );
		$this->assertContains( dominant_color_get_dominant_color( $attachment_id ), $expected_color );
	}

	/**
	 * Tests has_transparency_metadata().
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @covers ::dominant_color_metadata
	 *
	 * @param string   $image_path Image path.
	 * @param string[] $expected_color Expected color.
	 * @param bool     $expected_transparency Expected transparency.
	 */
	public function test_has_transparency_metadata( string $image_path, array $expected_color, bool $expected_transparency ): void {
		$mime_type = wp_check_filetype( $image_path )['type'];
		if ( ! wp_image_editor_supports( array( 'mime_type' => $mime_type ) ) ) {
			$this->markTestSkipped( "Mime type $mime_type is not supported." );
		}

		// Non-existing attachment.
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
	 *
	 * @param string   $image_path Image path.
	 * @param string[] $expected_color Expected color.
	 * @param bool     $expected_transparency Expected transparency.
	 */
	public function test_dominant_color_has_transparency( string $image_path, array $expected_color, bool $expected_transparency ): void {
		$mime_type = wp_check_filetype( $image_path )['type'];
		if ( ! wp_image_editor_supports( array( 'mime_type' => $mime_type ) ) ) {
			$this->markTestSkipped( "Mime type $mime_type is not supported." );
		}

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
	 *
	 * @param string   $image_path Image path.
	 * @param string[] $expected_color Expected color.
	 * @param bool     $expected_transparency Expected transparency.
	 */
	public function test_tag_add_adjust_to_image_attributes( string $image_path, array $expected_color, bool $expected_transparency ): void {
		$mime_type = wp_check_filetype( $image_path )['type'];
		if ( ! wp_image_editor_supports( array( 'mime_type' => $mime_type ) ) ) {
			$this->markTestSkipped( "Mime type $mime_type is not supported." );
		}

		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );
		wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );

		list( $src, $width, $height ) = wp_get_attachment_image_src( $attachment_id );
		// Testing tag_add_adjust() with image being lazy load.
		$filtered_image_mock_lazy_load = sprintf( '<img loading="lazy" class="test" src="%s" width="%d" height="%d" />', $src, $width, $height );

		$filtered_image_tags_added = dominant_color_img_tag_add_dominant_color( $filtered_image_mock_lazy_load, 'the_content', $attachment_id );

		$this->assertStringContainsString( 'data-has-transparency="' . wp_json_encode( $expected_transparency ) . '"', $filtered_image_tags_added );

		foreach ( $expected_color as $color ) {
			if ( str_contains( $filtered_image_tags_added, $color ) ) {
				$this->assertStringContainsString( 'style="--dominant-color: #' . $color . ';"', $filtered_image_tags_added );
				$this->assertStringContainsString( 'data-dominant-color="' . $color . '"', $filtered_image_tags_added );
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
	 * @dataProvider data_dominant_color_img_tag_add_dominant_color_requires_proper_quotes
	 *
	 * @covers ::dominant_color_img_tag_add_dominant_color
	 *
	 * @param string $image    The image markup.
	 *                         Must include %s for the 'src' value.
	 * @param bool   $expected Whether the dominant color should be added.
	 */
	public function test_dominant_color_img_tag_add_dominant_color_requires_proper_quotes( string $image, bool $expected ): void {
		$attachment_id = self::factory()->attachment->create_upload_object( __DIR__ . '/data/images/red.jpg' );
		wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );

		$image_url = wp_get_attachment_image_url( $attachment_id );
		$image     = sprintf( $image, $image_url );
		$result    = dominant_color_img_tag_add_dominant_color( $image, 'the_content', $attachment_id );

		if ( $expected ) {
			$this->assertStringContainsString( ' data-dominant-color=', $result );
		} else {
			$this->assertStringNotContainsString( ' data-dominant-color=', $result );
		}
	}

	/**
	 * Data provider for test_dominant_color_img_tag_add_dominant_color_requires_proper_quotes();
	 *
	 * @return array<string, mixed>
	 */
	public function data_dominant_color_img_tag_add_dominant_color_requires_proper_quotes(): array {
		return array(
			'double quotes'         => array(
				'image'    => '<img src="%s">',
				'expected' => true,
			),
			'single quotes'         => array(
				'image'    => "<img src='%s'>",
				'expected' => false,
			),
			'escaped double quotes' => array(
				'image'    => '<img src=\"%s\">',
				'expected' => false,
			),
		);
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
	public function test_dominant_color_img_tag_add_dominant_color_should_add_dominant_color_inline_style( string $filtered_image, string $expected ): void {
		$attachment_id = self::factory()->attachment->create_upload_object( __DIR__ . '/data/images/red.jpg' );
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
	 * @return array<string, mixed>
	 */
	public function data_provider_dominant_color_check_inline_style(): array {
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
	 * Tests that the dominant color style always comes before other existing inline styles.
	 *
	 * @dataProvider data_provider_dominant_color_filter_check_inline_style
	 *
	 * @param string $style_attr The image style attribute.
	 * @param string $expected   The expected style attribute and value.
	 */
	public function test_dominant_color_update_attachment_image_attributes( string $style_attr, string $expected ): void {
		$attachment_id = self::factory()->attachment->create_upload_object( __DIR__ . '/data/images/red.jpg' );

		$attachment_image = wp_get_attachment_image( $attachment_id, 'full', false, array( 'style' => $style_attr ) );
		$this->assertStringContainsString( $expected, $attachment_image );
	}

	/**
	 * Data provider for test_dominant_color_update_attachment_image_attributes().
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_dominant_color_filter_check_inline_style(): array {
		return array(
			'no inline styles'                   => array(
				'style_attr' => '',
				'expected'   => 'style="--dominant-color: #fe0000;"',
			),
			'inline style with end semicolon'    => array(
				'style_attr' => 'color: #ffffff;',
				'expected'   => 'style="--dominant-color: #fe0000;color: #ffffff;"',
			),
			'inline style without end semicolon' => array(
				'style_attr' => 'color: #ffffff',
				'expected'   => 'style="--dominant-color: #fe0000;color: #ffffff"',
			),
		);
	}

	/**
	 * Tests dominant_color_set_image_editors().
	 *
	 * @dataProvider provider_dominant_color_set_image_editors
	 *
	 * @covers ::dominant_color_set_image_editors
	 *
	 * @param array<string, mixed> $existing Existing.
	 * @param array<string, mixed> $expected Expected.
	 */
	public function test_dominant_color_set_image_editors( array $existing, array $expected ): void {
		$this->assertEqualSets( dominant_color_set_image_editors( $existing ), $expected );
	}

	/** @return array<string, mixed> */
	public function provider_dominant_color_set_image_editors(): array {
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
	public function test_dominant_color_rgb_to_hex( int $red, int $green, int $blue, ?string $hex ): void {
		$this->assertSame( $hex, dominant_color_rgb_to_hex( $red, $green, $blue ) );
	}

	/** @return array<string, mixed> */
	public function provider_get_hex_color(): array {
		return array(
			'black'    => array(
				'red'   => 0,
				'green' => 0,
				'blue'  => 0,
				'hex'   => '000000',
			),
			'white'    => array(
				'red'   => 255,
				'green' => 255,
				'blue'  => 255,
				'hex'   => 'ffffff',
			),
			'blue'     => array(
				'red'   => 255,
				'green' => 0,
				'blue'  => 0,
				'hex'   => 'ff0000',
			),
			'teal'     => array(
				'red'   => 255,
				'green' => 255,
				'blue'  => 0,
				'hex'   => 'ffff00',
			),
			'pink'     => array(
				'red'   => 255,
				'green' => 0,
				'blue'  => 255,
				'hex'   => 'ff00ff',
			),
			'purple'   => array(
				'red'   => 88,
				'green' => 42,
				'blue'  => 158,
				'hex'   => '582a9e',
			),
			'invalid1' => array(
				'red'   => -1,
				'green' => -1,
				'blue'  => -1,
				'hex'   => null,
			),
			'invalid2' => array(
				'red'   => 256,
				'green' => 256,
				'blue'  => 256,
				'hex'   => null,
			),
		);
	}

	/**
	 * Test printing the meta generator tag.
	 *
	 * @covers ::dominant_color_render_generator
	 */
	public function test_dominant_color_render_generator(): void {
		$tag = get_echo( 'dominant_color_render_generator' );
		$this->assertStringStartsWith( '<meta', $tag );
		$this->assertStringContainsString( 'generator', $tag );
		$this->assertStringContainsString( 'dominant-color-images ' . DOMINANT_COLOR_IMAGES_VERSION, $tag );
	}
}
