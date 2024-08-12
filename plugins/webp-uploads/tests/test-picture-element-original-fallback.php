<?php
/**
 * Tests for webp-uploads plugin picture-element.php Original image fallback support.
 *
 * @group picture-element
 *
 * @package webp-uploads
 */

use WebP_Uploads\Tests\TestCase;

class Test_WebP_Uploads_Picture_Element_Original_Image_Fallback extends TestCase {

	/**
	 * Mime type.
	 *
	 * @var string
	 */
	public static $mime_type = 'image/webp';

	/**
	 * Attachment ID.
	 *
	 * @var int
	 */
	public static $image_id;

	public function set_up(): void {
		parent::set_up();

		if ( ! wp_image_editor_supports( array( 'mime_type' => self::$mime_type ) ) ) {
			$this->markTestSkipped( 'Mime type image/webp is not supported.' );
		}

		// Default to webp output for tests.
		$this->set_image_output_type( 'webp' );

		// Run critical hooks to satisfy webp_uploads_in_frontend_body() conditions.
		$this->mock_frontend_body_hooks();
	}

	/**
	 * Setup shared fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		// Disable fallback to JPEG IMG.
		update_option( 'perflab_generate_webp_and_jpeg', '0' );

		self::$image_id = $factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
	}

	public static function wpTearDownAfterClass(): void {
		wp_delete_attachment( self::$image_id, true );
		delete_option( 'perflab_generate_webp_and_jpeg' );
	}

	/**
	 * Test that the picture element is not applied when the original fallback image is not available.
	 */
	public function test_no_picture_tag_wrap_and_original_image_fallback(): void {
		// Create some content with the image.
		$image = wp_get_attachment_image(
			self::$image_id,
			'large',
			false,
			array(
				'class' => 'wp-image-' . self::$image_id,
				'alt'   => 'Green Leaves',
			)
		);

		$img_markup    = apply_filters( 'the_content', $image );
		$img_processor = new WP_HTML_Tag_Processor( $img_markup );
		$this->assertTrue( $img_processor->next_tag( array( 'tag_name' => 'IMG' ) ), 'There should be an IMG tag.' );
		$this->assertStringEndsWith( '.webp', $img_processor->get_attribute( 'src' ), 'Make sure the IMG should return WEBP src.' );
		// Apply picture element support.
		$this->opt_in_to_picture_element();
		$picture_markup    = apply_filters( 'the_content', $image );
		$picture_processor = new WP_HTML_Tag_Processor( $picture_markup );
		$this->assertFalse( $picture_processor->next_tag( array( 'tag_name' => 'picture' ) ), 'There should not be a PICTURE tag.' );
		$picture_processor = new WP_HTML_Tag_Processor( $picture_markup );
		$picture_processor->next_tag( array( 'tag_name' => 'IMG' ) );
		$this->assertStringEndsWith( '.webp', $picture_processor->get_attribute( 'src' ), 'Make sure the IMG should return WEBP src.' );
		$this->assertSame( $img_markup, $picture_markup, 'Make sure the IMG and Picture markup are same.' );
	}
}
