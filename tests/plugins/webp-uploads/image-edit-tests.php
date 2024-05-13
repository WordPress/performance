<?php
/**
 * Tests for webp-uploads plugin image-edit.php.
 *
 * @package webp-uploads
 * @subpackage image-edit
 */

use PerformanceLab\Tests\TestCase\ImagesTestCase;

class WebP_Uploads_Image_Edit_Tests extends ImagesTestCase {

	public function set_up(): void {
		parent::set_up();

		add_filter( 'webp_uploads_discard_larger_generated_images', '__return_false' );
	}

	/**
	 * Backup the sources structure alongside the full size
	 *
	 * @test
	 */
	public function it_should_backup_the_sources_structure_alongside_the_full_size(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertEmpty( get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true ) );
		$this->assertEmpty( get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true ) );

		$editor = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();

		// Having a thumbnail ensures the process finished correctly.
		$this->assertTrue( $editor->success() );

		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );

		$this->assertIsArray( $backup_sizes );
		$this->assertArrayHasKey( 'full-orig', $backup_sizes );
		$this->assertArrayHasKey( 'thumbnail-orig', $backup_sizes );
		$this->assertArrayHasKey( 'medium-orig', $backup_sizes );
		$this->assertArrayHasKey( 'medium_large-orig', $backup_sizes );
		$this->assertArrayHasKey( 'large-orig', $backup_sizes );

		$backup_sources = get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true );
		$this->assertIsArray( $backup_sources );
		$this->assertArrayHasKey( 'full-orig', $backup_sources );
		$this->assertSame( $metadata['sources'], $backup_sources['full-orig'] );

		foreach ( $backup_sizes as $size => $properties ) {
			$size_name = str_replace( '-orig', '', $size );

			if ( 'full-orig' === $size ) {
				continue;
			}

			$this->assertArrayHasKey( 'sources', $properties );
			$this->assertSame( $metadata['sizes'][ $size_name ]['sources'], $properties['sources'] );
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
	}

	/**
	 * Restore the sources array from the backup when an image is edited
	 *
	 * @test
	 */
	public function it_should_restore_the_sources_array_from_the_backup_when_an_image_is_edited(): void {
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$this->markTestSkipped( 'Mime type image/webp is not supported.' );
		}

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );
		$metadata      = wp_get_attachment_metadata( $attachment_id );

		$editor = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();
		$this->assertTrue( $editor->success() );

		$backup_sources = get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true );
		$this->assertArrayHasKey( 'full-orig', $backup_sources );
		$this->assertIsArray( $backup_sources['full-orig'] );
		$this->assertSame( $metadata['sources'], $backup_sources['full-orig'] );

		wp_restore_image( $attachment_id );

		$this->assertImageNotHasSource( $attachment_id, 'image/jpeg' );
		$this->assertImageHasSource( $attachment_id, 'image/webp' );

		$metadata               = wp_get_attachment_metadata( $attachment_id );
		$updated_backup_sources = get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true );

		$this->assertSame( $backup_sources['full-orig'], $metadata['sources'] );
		$this->assertNotSame( $backup_sources, $updated_backup_sources );
		$this->assertCount( 1, $backup_sources );
		$this->assertCount( 2, $updated_backup_sources );

		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		foreach ( $backup_sizes as $size_name => $properties ) {
			// We are only interested in the original filenames to be compared against the backup and restored values.
			if ( false === strpos( $size_name, '-orig' ) ) {
				$this->assertSizeNameIsHashed( '', $size_name, "{$size_name} is not a valid edited name" );
				continue;
			}

			$size_name = str_replace( '-orig', '', $size_name );
			// Full name is verified above.
			if ( 'full' === $size_name ) {
				continue;
			}

			$this->assertArrayHasKey( $size_name, $metadata['sizes'] );
			$this->assertArrayHasKey( 'sources', $metadata['sizes'][ $size_name ] );
			$this->assertSame( $properties['sources'], $metadata['sizes'][ $size_name ]['sources'] );
		}
	}

	/**
	 * Prevent to back up the sources when the sources attributes does not exists
	 *
	 * @test
	 */
	public function it_should_prevent_to_back_up_the_sources_when_the_sources_attributes_does_not_exists(): void {
		// Disable the generation of the sources attributes.
		add_filter( 'webp_uploads_upload_image_mime_transforms', '__return_empty_array' );

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );
		$metadata      = wp_get_attachment_metadata( $attachment_id );

		$this->assertArrayNotHasKey( 'sources', $metadata );

		$editor = new WP_Image_Edit( $attachment_id );
		$editor->flip_vertical()->save();
		$this->assertTrue( $editor->success() );

		$backup_sources = get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true );
		$this->assertEmpty( $backup_sources );

		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		$this->assertIsArray( $backup_sizes );

		foreach ( $backup_sizes as $size_name => $properties ) {
			$this->assertArrayNotHasKey( 'sources', $properties );
		}
	}

	/**
	 * Prevent to backup the full size image if only the thumbnail is edited
	 *
	 * @test
	 */
	public function it_should_prevent_to_backup_the_full_size_image_if_only_the_thumbnail_is_edited(): void {
		if ( ! webp_uploads_image_edit_thumbnails_separately() ) {
			$this->markTestSkipped( 'Editing image thumbnails separately is disabled' );
		}

		// Create JPEG and WebP.
		$this->opt_in_to_jpeg_and_webp();

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		$this->assertArrayHasKey( 'sources', $metadata );

		$editor = new WP_Image_Edit( $attachment_id );
		$editor->flip_vertical()->only_thumbnail()->save();
		$this->assertTrue( $editor->success() );

		$backup_sources = get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true );
		$this->assertEmpty( $backup_sources );

		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		$this->assertIsArray( $backup_sizes );
		$this->assertCount( 1, $backup_sizes );
		$this->assertArrayHasKey( 'thumbnail-orig', $backup_sizes );
		$this->assertArrayHasKey( 'sources', $backup_sizes['thumbnail-orig'] );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertImageHasSource( $attachment_id, 'image/jpeg' );
		$this->assertImageHasSource( $attachment_id, 'image/webp' );
		$this->assertImageHasSizeSource( $attachment_id, 'thumbnail', 'image/jpeg' );
		$this->assertImageHasSizeSource( $attachment_id, 'thumbnail', 'image/webp' );

		$this->assertFileNameIsNotEdited( $metadata['sources']['image/jpeg']['file'] );
		$this->assertFileNameIsNotEdited( $metadata['sources']['image/webp']['file'] );

		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/jpeg' );
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/webp' );

			if ( 'thumbnail' === $size_name ) {
				$this->assertSame( $properties['file'], $properties['sources']['image/jpeg']['file'] );
				$this->assertSame( str_replace( '.jpg', '.webp', $properties['file'] ), $properties['sources']['image/webp']['file'] );
				$this->assertFileNameIsEdited( $properties['sources']['image/jpeg']['file'] );
				$this->assertFileNameIsEdited( $properties['sources']['image/webp']['file'] );
			} else {
				$this->assertFileNameIsNotEdited( $properties['sources']['image/jpeg']['file'] );
				$this->assertFileNameIsNotEdited( $properties['sources']['image/webp']['file'] );
			}
		}
	}

	/**
	 * Backup the image when all images except the thumbnail are updated
	 *
	 * @test
	 */
	public function it_should_backup_the_image_when_all_images_except_the_thumbnail_are_updated(): void {
		if ( ! webp_uploads_image_edit_thumbnails_separately() ) {
			$this->markTestSkipped( 'Editing image thumbnails separately is disabled' );
		}

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );
		$metadata      = wp_get_attachment_metadata( $attachment_id );

		$editor = new WP_Image_Edit( $attachment_id );
		$editor->rotate_left()->all_except_thumbnail()->save();
		$this->assertTrue( $editor->success() );

		$backup_sources = get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true );
		$this->assertIsArray( $backup_sources );
		$this->assertArrayHasKey( 'full-orig', $backup_sources );
		$this->assertSame( $metadata['sources'], $backup_sources['full-orig'] );

		$updated_metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertArrayHasKey( 'sources', $updated_metadata );
		$this->assertNotSame( $metadata['sources'], $updated_metadata['sources'] );
		$this->assertImageNotHasSource( $attachment_id, 'image/jpeg' );
		$this->assertImageHasSource( $attachment_id, 'image/webp' );

		foreach ( $updated_metadata['sources'] as $properties ) {
			$this->assertFileNameIsEdited( $properties['file'] );
		}

		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		$this->assertIsArray( $backup_sizes );
		$this->assertArrayNotHasKey( 'thumbnail-orig', $backup_sizes, 'The thumbnail-orig was stored in the back up' );

		foreach ( $backup_sizes as $size_name => $properties ) {
			if ( 'full-orig' === $size_name ) {
				continue;
			}
			$this->assertArrayHasKey( 'sources', $properties, "The '{$size_name}' does not have the sources." );
		}
	}

	/**
	 * Use the attached image when updating subsequent images not the original version
	 *
	 * @test
	 */
	public function it_should_use_the_attached_image_when_updating_subsequent_images_not_the_original_version(): void {
		if ( ! webp_uploads_image_edit_thumbnails_separately() ) {
			$this->markTestSkipped( 'Editing image thumbnails separately is disabled' );
		}

		// The leaves image is 1080 pixels wide with this filter we ensure a -scaled version is created for this test.
		add_filter(
			'big_image_size_threshold',
			static function () {
				// Due to the largest image size is 1024 and the image is 1080x720, 1050 is a good spot to create a scaled size for all images sizes.
				return 1050;
			}
		);

		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg'
		);

		$this->assertNotSame( wp_get_original_image_path( $attachment_id ), get_attached_file( $attachment_id ) );

		$editor = new WP_Image_Edit( $attachment_id );
		$editor->flip_right()->all_except_thumbnail()->save();

		$this->assertTrue( $editor->success() );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertArrayHasKey( 'original_image', $metadata );
		$this->assertArrayHasKey( 'sources', $metadata );
		$this->assertImageNotHasSource( $attachment_id, 'image/jpeg' );
		$this->assertImageHasSource( $attachment_id, 'image/webp' );

		foreach ( $metadata['sources'] as $properties ) {
			$this->assertFileNameIsEdited( $properties['file'] );
			$this->assertStringContainsString( '-scaled-', $properties['file'] );
		}

		$this->assertArrayHasKey( 'sizes', $metadata );
		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertImageNotHasSizeSource( $attachment_id, $size_name, 'image/jpeg' );
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/webp' );
			foreach ( $properties['sources'] as $mime_type => $values ) {
				if ( 'thumbnail' === $size_name ) {
					$this->assertFileNameIsNotEdited( $values['file'], "'{$size_name}' is not valid." );
					$this->assertStringNotContainsString( '-scaled-', $values['file'] );
				} else {
					$this->assertFileNameIsEdited( $values['file'], "'{$size_name}' is not valid." );
					$this->assertStringContainsString( '-scaled-', $values['file'] );
				}
			}
		}
	}

	/**
	 * Update source attributes when webp is edited.
	 *
	 * @test
	 */
	public function it_should_validate_source_attribute_update_when_webp_edited(): void {
		if ( ! webp_uploads_image_edit_thumbnails_separately() ) {
			$this->markTestSkipped( 'Editing image thumbnails separately is disabled' );
		}

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );

		$editor = new WP_Image_Edit( $attachment_id );
		$editor->crop( 1000, 200, 0, 0 )->save();
		$this->assertTrue( $editor->success() );

		$this->assertImageHasSource( $attachment_id, 'image/webp' );
		$this->assertImageNotHasSource( $attachment_id, 'image/jpeg' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertFileNameIsEdited( $metadata['sources']['image/webp']['file'] );

		$this->assertArrayHasKey( 'sources', $metadata );
		$this->assertArrayHasKey( 'sizes', $metadata );

		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertImageNotHasSizeSource( $attachment_id, $size_name, 'image/jpeg' );
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/webp' );
			$this->assertFileNameIsEdited( $properties['sources']['image/webp']['file'] );
		}
	}

	/**
	 * Not return a target if no backup image exists
	 *
	 * @test
	 */
	public function it_should_not_return_a_target_if_no_backup_image_exists(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );
		$this->assertNull( webp_uploads_get_next_full_size_key_from_backup( $attachment_id ) );
	}

	/**
	 * Return the full-orig target key when only one edit image exists
	 *
	 * @test
	 */
	public function it_should_return_the_full_orig_target_key_when_only_one_edit_image_exists(): void {
		// Remove the filter to prevent the usage of the next target.
		remove_filter( 'wp_update_attachment_metadata', 'webp_uploads_update_attachment_metadata' );

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );
		$editor        = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();

		$this->assertTrue( $editor->success() );
		$this->assertSame( 'full-orig', webp_uploads_get_next_full_size_key_from_backup( $attachment_id ) );
	}

	/**
	 * Return null when looking for a target that is already used
	 *
	 * @test
	 */
	public function it_should_return_null_when_looking_for_a_target_that_is_already_used(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );
		$editor        = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();

		$this->assertTrue( $editor->success() );
		$this->assertNull( webp_uploads_get_next_full_size_key_from_backup( $attachment_id ) );
	}

	/**
	 * Use the next available hash for the full size image on multiple image edits
	 *
	 * @test
	 */
	public function it_should_use_the_next_available_hash_for_the_full_size_image_on_multiple_image_edits(): void {
		// Create JPEG and WebP.
		$this->opt_in_to_jpeg_and_webp();

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );
		$editor        = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();

		$this->assertTrue( $editor->success() );
		$this->assertNull( webp_uploads_get_next_full_size_key_from_backup( $attachment_id ) );
		// Remove the filter to prevent the usage of the next target.
		remove_filter( 'wp_update_attachment_metadata', 'webp_uploads_update_attachment_metadata' );

		$editor->rotate_right()->save();

		$full_size_key = webp_uploads_get_next_full_size_key_from_backup( $attachment_id );
		// @phpstan-ignore-next-line method.impossibleType (Work around PHPStan remembering the return value above being null but now being a string. This is instead of adding rememberPossiblyImpureFunctionValues:false to the config.)
		$this->assertIsString( $full_size_key );
		$this->assertSizeNameIsHashed( 'full', $full_size_key );
	}

	/**
	 * Save populate the backup sources with the next target
	 *
	 * @test
	 */
	public function it_should_save_populate_the_backup_sources_with_the_next_target(): void {
		// Remove the filter to prevent the usage of the next target.
		remove_filter( 'wp_update_attachment_metadata', 'webp_uploads_update_attachment_metadata' );

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );
		$editor        = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();
		$this->assertTrue( $editor->success() );

		$sources = array(
			'image/webp' => array(
				'file'     => 'leaves.webp',
				'filesize' => 1234,
			),
		);
		webp_uploads_backup_full_image_sources( $attachment_id, $sources );

		$this->assertSame( array( 'full-orig' => $sources ), get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true ) );
	}

	/**
	 * Store the metadata on the next available hash
	 *
	 * @test
	 */
	public function it_should_store_the_metadata_on_the_next_available_hash(): void {
		// Create JPEG and WebP.
		$this->opt_in_to_jpeg_and_webp();

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );

		$editor = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();
		$this->assertTrue( $editor->success() );

		// Remove the filter to prevent the usage of the next target.
		remove_filter( 'wp_update_attachment_metadata', 'webp_uploads_update_attachment_metadata' );
		$editor->rotate_right()->save();
		$this->assertTrue( $editor->success() );

		$sources = array(
			'image/webp' => array(
				'file'     => 'leaves.webp',
				'filesize' => 1234,
			),
		);
		webp_uploads_backup_full_image_sources( $attachment_id, $sources );

		$backup_sources = get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true );
		$this->assertIsArray( $backup_sources );

		$backup_sources_keys = array_keys( $backup_sources );
		$this->assertSame( 'full-orig', reset( $backup_sources_keys ) );
		$this->assertSizeNameIsHashed( 'full', end( $backup_sources_keys ) );
		$this->assertSame( $sources, end( $backup_sources ) );
	}

	/**
	 * Prevent to store an empty set of sources
	 *
	 * @test
	 */
	public function it_should_prevent_to_store_an_empty_set_of_sources(): void {
		// Remove the filter to prevent the usage of the next target.
		remove_filter( 'wp_update_attachment_metadata', 'webp_uploads_update_attachment_metadata' );

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );
		$editor        = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();

		webp_uploads_backup_full_image_sources( $attachment_id, array() );

		$this->assertTrue( $editor->success() );
		$this->assertEmpty( get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true ) );
	}

	/**
	 * Store the next image hash on the backup sources
	 *
	 * @test
	 */
	public function it_should_store_the_next_image_hash_on_the_backup_sources(): void {
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$this->markTestSkipped( 'Mime type image/webp is not supported.' );
		}

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );
		$editor        = new WP_Image_Edit( $attachment_id );
		// Edit the image.
		$editor->rotate_right()->save();
		// Restore the image.
		wp_restore_image( $attachment_id );

		$backup_sources = get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true );

		$this->assertIsArray( $backup_sources );
		$this->assertCount( 2, $backup_sources );
		foreach ( array_keys( $backup_sources ) as $name ) {
			if ( 'full-orig' !== $name ) {
				$this->assertSizeNameIsHashed( '', $name );
			}
		}
	}

	/**
	 * Create backup of full size images with the same hash keys as the edited images
	 *
	 * @test
	 */
	public function it_should_create_backup_of_full_size_images_with_the_same_hash_keys_as_the_edited_images(): void {
		// Create JPEG and WebP.
		$this->opt_in_to_jpeg_and_webp();

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );

		$editor = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();
		$this->assertTrue( $editor->success() );
		$editor->rotate_right()->save();
		$this->assertTrue( $editor->success() );
		$editor->flip_right()->save();
		$this->assertTrue( $editor->success() );

		$backup_sizes   = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		$backup_sources = get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true );

		$this->assertIsArray( $backup_sources );
		$this->assertIsArray( $backup_sizes );
		$this->assertCount( 3, $backup_sources );

		foreach ( $backup_sources as $size_name => $sources ) {
			if ( 'full-orig' === $size_name ) {
				// Skip this size name due this name does not have a hash name.
				continue;
			}

			// Ensure the size name is an actual hashed value.
			$this->assertSizeNameIsHashed( 'full', $size_name );
			// Ensure the full-{hash} name is the same created on the backup sizes or that both hash names are the same.
			$this->assertArrayHasKey( $size_name, $backup_sizes );
			$this->assertIsArray( $backup_sizes[ $size_name ] );
		}
	}
}
