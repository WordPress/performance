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

class WebP_Uploads_Picture_Element_Tests extends ImagesTestCase {
	/**
	 * Test that images are wrapped in picture element when enabled.
	 *
	 *
	 *
	 * @test
	 */
	public function it_should_wrap_images_in_picture_element_when_enabled(): void {
		$this->opt_in_to_jpeg_and_webp();
		$this->opt_in_to_picture_element();

		// Create an image.
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );

		// Create some content with the image.
		$the_image = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => "wp-image-{$attachment_id}" ) );

		// Apply the content filters
		$the_image = apply_filters( 'wp_content_img_tag', $the_image, 'the_content', $attachment_id );

		// Check that the image is wrapped in a picture element with the correct class.
		$picture_element = sprintf( '<picture class=wp-picture-%s>', $attachment_id );
		$this->assertStringContainsString( $picture_element, $the_image );
	}
}
