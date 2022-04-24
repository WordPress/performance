<?php
/**
 * Tests for webp-uploads module when the IMAGE_EDIT_OVERWRITE is
 * set to true.
 *
 * @package performance-lab
 * @group   webp-uploads
 */

class WebP_Uploads_Image_Overwrite_Defined_Tests extends WP_UnitTestCase {
	public function setUp() {
		parent::setUp();

		define( 'IMAGE_EDIT_OVERWRITE', true );
	}

	/**
	 * Prevent to store an additional image source when the IMAGE_EDIT_OVERWRITE is defined to true
	 *
	 * @test
	 */
	public function it_should_prevent_to_store_an_additional_image_source_when_the_image_edit_overwrite_is_defined_to_true() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		$editor        = new WP_Image_Edit( $attachment_id );
		// Edit the image.
		$editor->rotate_right()->save();
		// Restore the image.
		wp_restore_image( $attachment_id );

		$backup_sources = get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true );

		$this->assertIsArray( $backup_sources );
		$this->assertCount( 1, $backup_sources );
		$this->assertSame( 'full-orig', array_keys( $backup_sources )[0] );
	}
}
