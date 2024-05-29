<?php
/**
 * Tests for webp-uploads plugin picture-element.php.
 *
 *
 * @group picture-element
 *
 * @package webp-uploads
 */

use PerformanceLab\Tests\TestCase\ImagesTestCase;

class Test_WebP_Uploads_Picture_Element extends ImagesTestCase {
	/**
	 * Test that images are wrapped in picture element when enabled.
	 *
	 * @dataProvider data_provider_it_should_maybe_wrap_images_in_picture_element
	 *
	 * @test
	 *
	 * @param bool $jpeg_and_webp          Whether to enable JPEG and WebP output.
	 * @param bool $picture_element        Whether to enable picture element output.
	 * @param bool $expect_picture_element Whether to expect the image to be wrapped in a picture element.
	 */
	public function it_should_maybe_wrap_images_in_picture_element( bool $jpeg_and_webp, bool $picture_element, bool $expect_picture_element ): void {
		$mime_type = 'image/webp';
		if ( ! wp_image_editor_supports( array( 'mime_type' => $mime_type ) ) ) {
			$this->markTestSkipped( "Mime type $mime_type is not supported." );
		}

		if ( $jpeg_and_webp ) {
			$this->opt_in_to_jpeg_and_webp();
		}

		if ( $picture_element ) {
			$this->opt_in_to_picture_element();
		}

		// Create an image.
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );

		// Create some content with the image.
		$the_image = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => "wp-image-{$attachment_id}" ) );

		// Apply the wp_content_img_tag filter.
		$the_image = apply_filters( 'wp_content_img_tag', $the_image, 'the_content', $attachment_id );

		// Check that the image is wrapped in a picture element with the correct class.
		$picture_element = sprintf( '<picture class="wp-picture-%s">', $attachment_id );
		if ( $expect_picture_element ) {
			$this->assertStringContainsString( $picture_element, $the_image );
		} else {
			$this->assertStringNotContainsString( $picture_element, $the_image );
		}

		// When both features are enabled, the picture element will contain two srcset elements.
		$this->assertEquals( ( $jpeg_and_webp && $expect_picture_element ) ? 2 : 1, substr_count( $the_image, 'srcset=' ) );
	}

	/**
	 * Data provider for it_should_maybe_wrap_images_in_picture_element.
	 *
	 * @return array<string, array<string, bool>>
	 *
	 */
	public function data_provider_it_should_maybe_wrap_images_in_picture_element(): array {
		return array(
			'jpeg and picture enabled' => array(
				'jpeg_and_webp'          => true,
				'picture_element'        => true,
				'expect_picture_element' => true,
			),
			'only picture enabled' => array(
				'jpeg_and_webp'          => false,
				'picture_element'        => true,
				'expect_picture_element' => true,
			),
			'only jpeg enabled' => array(
				'jpeg_and_webp'          => true,
				'picture_element'        => false,
				'expect_picture_element' => false,
			),
			'neither enabled' => array(
				'jpeg_and_webp'          => false,
				'picture_element'        => false,
				'expect_picture_element' => false,
			),
		);
	}
}

