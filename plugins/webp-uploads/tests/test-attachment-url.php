<?php
/**
 * Tests for webp-uploads plugin rest-api.php webp_uploads_update_rest_attachment_original_source_url().
 *
 * @package webp-uploads
 */

use WebP_Uploads\Tests\TestCase;

class Test_WebP_Uploads_Attachment_Url extends TestCase {

	public function set_up(): void {
		parent::set_up();

		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$this->markTestSkipped( 'Mime type image/webp is not supported.' );
		}

		// Default to webp output for tests.
		$this->set_image_output_type( 'webp' );
	}

	/**
	 * Fetches the REST attachment response data for a given attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return array<string, mixed> The REST response data.
	 */
	private function get_rest_attachment_data( int $attachment_id ): array {
		$request = new WP_REST_Request();
		$request->set_param( 'id', $attachment_id );

		$controller = new WP_REST_Attachments_Controller( 'attachment' );
		$response   = $controller->get_item( $request );

		$this->assertNotWPError( $response );

		return $response->get_data();
	}

	/**
	 * When the fallback setting is disabled, the attachment's main file is swapped for the
	 * modern format and the original is backed up. The REST `source_url` (which the
	 * Image/Gallery blocks use for "Link to image file") should still resolve to the original.
	 *
	 * @covers ::webp_uploads_update_rest_attachment_original_source_url
	 */
	public function test_source_url_points_to_original_when_fallback_disabled(): void {
		update_option( 'perflab_generate_webp_and_jpeg', false );

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
		$this->assertNotWPError( $attachment_id );

		// Sanity check: the attached file itself was swapped to WebP (the post's mime type
		// stays JPEG for compatibility, which is why this can't be detected from that alone).
		$this->assertStringEndsWith( '.webp', get_attached_file( $attachment_id ) );

		$original_path = wp_get_original_image_path( $attachment_id );
		$this->assertStringEndsWith( '.jpg', $original_path );
		$this->assertFileExists( $original_path );

		$data = $this->get_rest_attachment_data( $attachment_id );

		$this->assertStringEndsWith( '.jpg', $data['source_url'] );
		$this->assertSame( wp_basename( $original_path ), wp_basename( $data['source_url'] ) );
		$this->assertSame( wp_basename( $original_path ), wp_basename( $data['media_details']['sizes']['full']['source_url'] ) );

		// The <img> src pipeline (wp_get_attachment_url()) must be unaffected: it still needs
		// to resolve to the current (modern-format) file, only the REST `source_url` changes.
		$this->assertStringEndsWith( '.webp', wp_get_attachment_url( $attachment_id ) );
	}

	/**
	 * When the fallback setting is enabled, the main file is not swapped, so `source_url`
	 * should be unaffected (still the original, as before this filter was added).
	 *
	 * @covers ::webp_uploads_update_rest_attachment_original_source_url
	 */
	public function test_source_url_unchanged_when_fallback_enabled(): void {
		update_option( 'perflab_generate_webp_and_jpeg', true );

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
		$this->assertNotWPError( $attachment_id );

		$this->assertSame( 'image/jpeg', get_post_mime_type( $attachment_id ) );

		$data = $this->get_rest_attachment_data( $attachment_id );

		$this->assertStringEndsWith( '.jpg', $data['source_url'] );
		$this->assertSame( wp_basename( get_attached_file( $attachment_id ) ), wp_basename( $data['source_url'] ) );
	}

	/**
	 * Core's own "-scaled" resizing for oversized images keeps the same mime type and is
	 * unrelated to this plugin's format swap. It must not be affected by the filter.
	 *
	 * @covers ::webp_uploads_update_rest_attachment_original_source_url
	 */
	public function test_source_url_unaffected_by_core_scaled_images(): void {
		update_option( 'perflab_generate_webp_and_jpeg', true );

		add_filter(
			'big_image_size_threshold',
			static function () {
				return 850;
			}
		);

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
		$this->assertNotWPError( $attachment_id );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertArrayHasKey( 'original_image', $metadata, 'Core should have backed up the pre-scaled original.' );

		$data = $this->get_rest_attachment_data( $attachment_id );

		// The URL should still be the "-scaled" file core itself produced, not the pre-scaled original.
		$this->assertSame( wp_basename( get_attached_file( $attachment_id ) ), wp_basename( $data['source_url'] ) );
		$this->assertStringContainsString( '-scaled', $data['source_url'] );
	}

	/**
	 * The <img> tag optimization must keep showing the modern format regardless of the
	 * REST `source_url` filter above.
	 *
	 * @covers ::webp_uploads_img_tag_update_mime_type
	 */
	public function test_img_tag_still_uses_modern_format_when_fallback_disabled(): void {
		update_option( 'perflab_generate_webp_and_jpeg', false );
		$this->mock_frontend_body_hooks();

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
		$this->assertNotWPError( $attachment_id );

		$image   = wp_get_attachment_image( $attachment_id, 'large', false, array( 'class' => 'wp-image-' . $attachment_id ) );
		$content = apply_filters( 'the_content', $image );

		$processor = new WP_HTML_Tag_Processor( $content );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'IMG' ) ) );
		$this->assertStringEndsWith( '.webp', $processor->get_attribute( 'src' ) );
	}
}
