<?php
/**
 * Tests for webp_uploads_filter_block_background_images().
 *
 * @group background-image
 *
 * @package webp-uploads
 */

use WebP_Uploads\Tests\TestCase;

class Test_WebP_Uploads_Block_Background_Images extends TestCase {

	/**
	 * Temporary attachment IDs created during tests.
	 *
	 * @var array<int>
	 */
	private $temp_attachment_ids = array();

	/**
	 * Set up test environment.
	 */
	public function set_up(): void {
		parent::set_up();

		// Ensure we can generate both JPEG and WebP variants.
		$this->opt_in_to_jpeg_and_webp();
		update_option( 'perflab_modern_image_format', 'webp' );
		update_option( 'perflab_generate_webp_and_jpeg', '1' );

		// Satisfy webp_uploads_in_frontend_body() conditions.
		$this->mock_frontend_body_hooks();
	}

	/**
	 * Tear down and clean created artifacts.
	 */
	public function tear_down(): void {
		foreach ( $this->temp_attachment_ids as $id ) {
			wp_delete_attachment( $id, true );
		}
		$this->temp_attachment_ids = array();

		parent::tear_down();
	}

	/**
	 * Helper: Create a JPEG attachment and return its ID.
	 */
	private function create_jpeg_attachment(): int {
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
		$this->assertNotWPError( $attachment_id );
		$this->temp_attachment_ids[] = $attachment_id;
		return $attachment_id;
	}

	/**
	 * Generates block content markup and block array for a given block type using an attachment background image.
	 *
	 * @param string $block_name    Block name (e.g. 'core/cover' or 'core/group').
	 * @param int    $attachment_id Attachment ID whose URL will be used as original background image.
	 * @param string $original_url  The original full size image URL.
	 * @param string $content       Inner content (optional).
	 * @return array{block_content:string,block:array<string,mixed>} Array containing 'block_content' HTML and 'block' parsed block structure.
	 */
	private function generate_block_with_background( string $block_name, int $attachment_id, string $original_url, string $content = 'Content' ): array {
		if ( 'core/cover' === $block_name ) {
			$block_content = '<div class="wp-block-cover"><div style="background-image:url(' . esc_url( $original_url ) . ')"><span class="wp-block-cover__inner-container">' . esc_html( $content ) . '</span></div></div>';
			$block         = array(
				'blockName' => 'core/cover',
				'attrs'     => array(
					'id'  => $attachment_id,
					'url' => $original_url,
				),
			);
		} elseif ( 'core/group' === $block_name ) {
			$block_content = '<div class="wp-block-group" style="background-image:url(' . esc_url( $original_url ) . ')"><div class="wp-block-group__inner-container">' . esc_html( $content ) . '</div></div>';
			$block         = array(
				'blockName' => 'core/group',
				'attrs'     => array(
					'style' => array(
						'background' => array(
							'backgroundImage' => array(
								'id'  => $attachment_id,
								'url' => $original_url,
							),
						),
					),
				),
			);
		} else {
			$block_content = '';
			$block         = array(
				'blockName' => $block_name,
				'attrs'     => array(),
			);
		}

		return array(
			'block_content' => $block_content,
			'block'         => $block,
		);
	}

	/**
	 * It should replace a Cover block background image URL with a WebP variant when available.
	 */
	public function test_cover_block_background_image_replaced_with_webp(): void {
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$this->markTestSkipped( 'Mime type image/webp is not supported.' );
		}

		$attachment_id = $this->create_jpeg_attachment();
		$original_url  = wp_get_attachment_image_url( $attachment_id, 'full' );
		$this->assertIsString( $original_url );

		$generated = $this->generate_block_with_background( 'core/cover', $attachment_id, $original_url );
		$filtered  = webp_uploads_filter_block_background_images( $generated['block_content'], $generated['block'] );

		$webp_url = webp_uploads_get_mime_type_image( $attachment_id, $original_url, 'image/webp' );
		$this->assertNotNull( $webp_url, 'Expected a generated WebP URL.' );

		if ( $webp_url !== $original_url ) {
			$this->assertStringContainsString( $webp_url, $filtered, 'Filtered markup should reference WebP background image.' );
		}
	}

	/**
	 * It should not change background when no WebP source exists.
	 */
	public function test_cover_block_background_image_not_changed_without_webp_source(): void {
		// Remove any transform filters added during set_up so we start clean.
		remove_all_filters( 'webp_uploads_upload_image_mime_transforms' );

		// Restrict transforms to only JPEG so no WebP is produced for this test.
		$filter = static function ( $transforms ) {
			$transforms['image/jpeg'] = array( 'image/jpeg' );
			return $transforms;
		};
		add_filter( 'webp_uploads_upload_image_mime_transforms', $filter, 10 );

		$attachment_id = $this->create_jpeg_attachment();
		$original_url  = wp_get_attachment_image_url( $attachment_id, 'full' );
		$this->assertIsString( $original_url );

		$generated = $this->generate_block_with_background( 'core/cover', $attachment_id, $original_url );
		$filtered  = webp_uploads_filter_block_background_images( $generated['block_content'], $generated['block'] );

		remove_filter( 'webp_uploads_upload_image_mime_transforms', $filter, 10 );

		$this->assertSame( $generated['block_content'], $filtered, 'Background image should remain unchanged when no WebP source exists.' );
		$this->assertStringNotContainsString( '-jpg.webp', $filtered );
	}

	/**
	 * It should replace a Group block background image URL with a WebP variant when available.
	 */
	public function test_group_block_background_image_replaced_with_webp(): void {
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$this->markTestSkipped( 'Mime type image/webp is not supported.' );
		}

		$attachment_id = $this->create_jpeg_attachment();
		$original_url  = wp_get_attachment_image_url( $attachment_id, 'full' );
		$this->assertIsString( $original_url );

		$generated = $this->generate_block_with_background( 'core/group', $attachment_id, $original_url );
		$filtered  = webp_uploads_filter_block_background_images( $generated['block_content'], $generated['block'] );

		$webp_url = webp_uploads_get_mime_type_image( $attachment_id, $original_url, 'image/webp' );
		$this->assertNotNull( $webp_url, 'Expected a generated WebP URL.' );

		if ( $webp_url !== $original_url ) {
			$this->assertStringContainsString( $webp_url, $filtered, 'Filtered group block should reference WebP background image.' );
		}
	}
}
