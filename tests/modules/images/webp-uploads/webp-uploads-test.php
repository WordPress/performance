<?php
/**
 * Tests for webp-uploads module.
 *
 * @package performance-lab
 * @group   webp-uploads
 */

class WebP_Uploads_Tests extends WP_UnitTestCase {
	/**
	 * Create the original image mime type when the image is uploaded
	 *
	 * @dataProvider provider_image_with_default_behaviors_during_upload
	 *
	 * @test
	 */
	public function it_should_create_the_original_image_mime_type_when_the_image_is_uploaded( $file_location, $expected_mime, $targeted_mime ) {
		$attachment_id = $this->factory->attachment->create_upload_object( $file_location );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata );
		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertArrayHasKey( 'sources', $properties );
			$this->assertIsArray( $properties['sources'] );
			$this->assertArrayHasKey( $expected_mime, $properties['sources'] );
			$this->assertArrayHasKey( 'filesize', $properties['sources'][ $expected_mime ] );
			$this->assertArrayHasKey( 'file', $properties['sources'][ $expected_mime ] );
			$this->assertArrayHasKey( $targeted_mime, $properties['sources'] );
			$this->assertArrayHasKey( 'filesize', $properties['sources'][ $targeted_mime ] );
			$this->assertArrayHasKey( 'file', $properties['sources'][ $targeted_mime ] );
		}
	}

	public function provider_image_with_default_behaviors_during_upload() {
		yield 'JPEG image' => array(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg',
			'image/jpeg',
			'image/webp',
		);

		yield 'WebP image' => array(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/balloons.webp',
			'image/webp',
			'image/jpeg',
		);
	}

	/**
	 * Not create the sources property if no transform is provided
	 *
	 * @test
	 */
	public function it_should_not_create_the_sources_property_if_no_transform_is_provided() {
		add_filter( 'webp_uploads_supported_image_mime_transforms', '__return_empty_array' );

		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata );
		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertArrayNotHasKey( 'sources', $properties );
		}
	}

	/**
	 * Create the sources property when no transform is available
	 *
	 * @test
	 */
	public function it_should_create_the_sources_property_when_no_transform_is_available() {
		add_filter(
			'webp_uploads_supported_image_mime_transforms',
			function () {
				return array( 'image/jpeg' => array() );
			}
		);

		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata );
		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertArrayHasKey( 'sources', $properties );
			$this->assertIsArray( $properties['sources'] );
			$this->assertArrayHasKey( 'image/jpeg', $properties['sources'] );
			$this->assertArrayHasKey( 'filesize', $properties['sources']['image/jpeg'] );
			$this->assertArrayHasKey( 'file', $properties['sources']['image/jpeg'] );
			$this->assertArrayNotHasKey( 'image/webp', $properties['sources'] );
		}
	}

	/**
	 * Not create the sources property if the mime is not specified on the transforms images
	 *
	 * @test
	 */
	public function it_should_not_create_the_sources_property_if_the_mime_is_not_specified_on_the_transforms_images() {
		add_filter(
			'webp_uploads_supported_image_mime_transforms',
			function () {
				return array( 'image/jpeg' => array() );
			}
		);

		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/balloons.webp'
		);

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata );
		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertArrayNotHasKey( 'sources', $properties );
		}
	}

	/**
	 * Prevent processing an image with corrupted metadata
	 *
	 * @dataProvider provider_with_modified_metadata
	 *
	 * @test
	 */
	public function it_should_prevent_processing_an_image_with_corrupted_metadata( callable $callback, $size ) {
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/balloons.webp'
		);
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		wp_update_attachment_metadata( $attachment_id, $callback( $metadata ) );
		$result = webp_uploads_generate_image_size( $attachment_id, $size, 'image/webp' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'image_mime_type_invalid_metadata', $result->get_error_code() );
	}

	public function provider_with_modified_metadata() {
		yield 'using a size that does not exists' => array(
			function ( $metadata ) {
				return $metadata;
			},
			'not-existing-size',
		);

		yield 'removing an existing metadata simulating that the image size still does not exists' => array(
			function ( $metadata ) {
				unset( $metadata['sizes']['medium'] );

				return $metadata;
			},
			'medium',
		);

		yield 'when the specified size is not a valid array' => array(
			function ( $metadata ) {
				$metadata['sizes']['medium'] = null;

				return $metadata;
			},
			'medium',
		);
	}

	/**
	 * Prevent to create an image size when attached file does not exists
	 *
	 * @test
	 */
	public function it_should_prevent_to_create_an_image_size_when_attached_file_does_not_exists() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);
		$file          = get_attached_file( $attachment_id );

		$this->assertFileExists( $file );
		wp_delete_file( $file );
		$this->assertFileDoesNotExist( $file );

		$result = webp_uploads_generate_image_size( $attachment_id, 'medium', 'image/webp' );
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'image_file_size_not_found', $result->get_error_code() );
	}

	/**
	 * Prevent to create a subsize if the image editor does not exists
	 *
	 * @test
	 */
	public function it_should_prevent_to_create_a_subsize_if_the_image_editor_does_not_exists() {
		// Make sure no editor is available.
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		add_filter( 'wp_image_editors', '__return_empty_array' );
		$result = webp_uploads_generate_image_size( $attachment_id, 'medium', 'image/webp' );
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'image_no_editor', $result->get_error_code() );
	}

	/**
	 * Prevent to upload a mime that is not supported by WordPress
	 *
	 * @test
	 */
	public function it_should_prevent_to_upload_a_mime_that_is_not_supported_by_wordpress() {
		// Make sure no editor is available.
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);
		$result        = webp_uploads_generate_image_size( $attachment_id, 'medium', 'image/avif' );
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'image_mime_type_invalid', $result->get_error_code() );
	}

	/**
	 * Prevent to process an image when the editor does not support the format
	 *
	 * @test
	 */
	public function it_should_prevent_to_process_an_image_when_the_editor_does_not_support_the_format() {
		// Make sure no editor is available.
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		add_filter(
			'wp_image_editors',
			function () {
				return array( 'WP_Image_Doesnt_Support_WebP' );
			}
		);
		$result = webp_uploads_generate_image_size( $attachment_id, 'medium', 'image/webp' );
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'image_mime_type_not_supported', $result->get_error_code() );
	}

	/**
	 * Create a WebP version with all the required properties
	 *
	 * @test
	 */
	public function it_should_create_a_webp_version_with_all_the_required_properties() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertArrayHasKey( 'sources', $metadata['sizes']['thumbnail'] );
		$this->assertArrayHasKey( 'image/jpeg', $metadata['sizes']['thumbnail']['sources'] );
		$this->assertArrayHasKey( 'filesize', $metadata['sizes']['thumbnail']['sources']['image/jpeg'] );
		$this->assertArrayHasKey( 'file', $metadata['sizes']['thumbnail']['sources']['image/jpeg'] );
		$this->assertArrayHasKey( 'image/webp', $metadata['sizes']['thumbnail']['sources'] );
		$this->assertArrayHasKey( 'filesize', $metadata['sizes']['thumbnail']['sources']['image/webp'] );
		$this->assertArrayHasKey( 'file', $metadata['sizes']['thumbnail']['sources']['image/webp'] );
		$this->assertStringEndsNotWith( '.jpeg', $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] );
		$this->assertStringEndsWith( '.webp', $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] );
	}

	/**
	 * Remove `scaled` suffix from the generated filename
	 *
	 * @test
	 */
	public function it_should_remove_scaled_suffix_from_the_generated_filename() {
		// The leafs image is 1080 pixels wide with this filter we ensure a -scaled version is created.
		add_filter(
			'big_image_size_threshold',
			function () {
				return 850;
			}
		);

		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		$this->assertStringEndsWith( '-scaled.jpg', get_attached_file( $attachment_id ) );
		$this->assertArrayHasKey( 'image/webp', $metadata['sizes']['medium']['sources'] );
		$this->assertStringEndsNotWith( '-scaled.webp', $metadata['sizes']['medium']['sources']['image/webp']['file'] );
		$this->assertStringEndsWith( '-300x200.webp', $metadata['sizes']['medium']['sources']['image/webp']['file'] );
	}

	/**
	 * Remove the generated webp images when the attachment is deleted
	 *
	 * @test
	 */
	public function it_should_remove_the_generated_webp_images_when_the_attachment_is_deleted() {
		// Make sure no editor is available.
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		$file    = get_attached_file( $attachment_id, true );
		$dirname = pathinfo( $file, PATHINFO_DIRNAME );

		$this->assertIsString( $file );
		$this->assertFileExists( $file );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$sizes    = array( 'thumbnail', 'medium' );

		foreach ( $sizes as $size_name ) {
			$this->assertArrayHasKey( 'image/webp', $metadata['sizes'][ $size_name ]['sources'] );
			$this->assertArrayHasKey( 'file', $metadata['sizes'][ $size_name ]['sources']['image/webp'] );
			$this->assertFileExists(
				path_join( $dirname, $metadata['sizes'][ $size_name ]['sources']['image/webp']['file'] )
			);
		}

		wp_delete_attachment( $attachment_id );

		foreach ( $sizes as $size_name ) {
			$this->assertFileDoesNotExist(
				path_join( $dirname, $metadata['sizes'][ $size_name ]['sources']['image/webp']['file'] )
			);
		}
	}

	/**
	 * Remove the attached WebP version if the attachment is force deleted but empty trash day is not defined
	 *
	 * @test
	 */
	public function it_should_remove_the_attached_webp_version_if_the_attachment_is_force_deleted_but_empty_trash_day_is_not_defined() {
		// Make sure no editor is available.
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		$file    = get_attached_file( $attachment_id, true );
		$dirname = pathinfo( $file, PATHINFO_DIRNAME );

		$this->assertIsString( $file );
		$this->assertFileExists( $file );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertFileExists(
			path_join( $dirname, $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] )
		);

		wp_delete_attachment( $attachment_id, true );

		$this->assertFileDoesNotExist(
			path_join( $dirname, $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] )
		);
	}

	/**
	 * Remove the WebP version of the image if the image is force deleted and empty trash days is set to zero
	 *
	 * @test
	 */
	public function it_should_remove_the_webp_version_of_the_image_if_the_image_is_force_deleted_and_empty_trash_days_is_set_to_zero() {
		// Make sure no editor is available.
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		$file    = get_attached_file( $attachment_id, true );
		$dirname = pathinfo( $file, PATHINFO_DIRNAME );

		$this->assertIsString( $file );
		$this->assertFileExists( $file );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertFileExists(
			path_join( $dirname, $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] )
		);

		define( 'EMPTY_TRASH_DAYS', 0 );

		wp_delete_attachment( $attachment_id, true );

		$this->assertFileDoesNotExist(
			path_join( $dirname, $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] )
		);
	}

	/**
	 * Avoid the change of URLs of images that are not part of the media library
	 *
	 * @group webp_uploads_update_image_references
	 *
	 * @test
	 */
	public function it_should_avoid_the_change_of_urls_of_images_that_are_not_part_of_the_media_library() {
		$paragraph = '<p>Donec accumsan, sapien et <img src="https://ia600200.us.archive.org/16/items/SPD-SLRSY-1867/hubblesite_2001_06.jpg">, id commodo nisi sapien et est. Mauris nisl odio, iaculis vitae pellentesque nec.</p>';

		$this->assertSame( $paragraph, webp_uploads_update_image_references( $paragraph ) );
	}

	/**
	 * Avoid replacing not existing attachment IDs
	 *
	 * @group webp_uploads_update_image_references
	 *
	 * @test
	 */
	public function it_should_avoid_replacing_not_existing_attachment_i_ds() {
		$paragraph = '<p>Donec accumsan, sapien et <img class="wp-image-0" src="https://ia600200.us.archive.org/16/items/SPD-SLRSY-1867/hubblesite_2001_06.jpg">, id commodo nisi sapien et est. Mauris nisl odio, iaculis vitae pellentesque nec.</p>';

		$this->assertSame( $paragraph, webp_uploads_update_image_references( $paragraph ) );
	}

	/**
	 * Prevent replacing a WebP image
	 *
	 * @group webp_uploads_update_image_references
	 *
	 * @test
	 */
	public function it_should_prevent_replacing_a_webp_image() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/balloons.webp'
		);

		$tag = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => "wp-image-{$attachment_id}" ) );

		$this->assertSame( $tag, webp_uploads_update_image_references( $tag ) );
	}

	/**
	 * Prevent replacing a jpg image if the image does not have the target class name
	 *
	 * @test
	 */
	public function it_should_prevent_replacing_a_jpg_image_if_the_image_does_not_have_the_target_class_name() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		$tag = wp_get_attachment_image( $attachment_id, 'medium' );

		$this->assertSame( $tag, webp_uploads_update_image_references( $tag ) );
	}

	/**
	 * Replace the references to a JPG image to a WebP version
	 *
	 * @dataProvider provider_replace_images_with_different_extensions
	 * @group webp_uploads_update_image_references
	 *
	 * @test
	 */
	public function it_should_replace_the_references_to_a_jpg_image_to_a_webp_version( $image_path ) {
		$attachment_id = $this->factory->attachment->create_upload_object( $image_path );

		$tag          = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => "wp-image-{$attachment_id}" ) );
		$expected_tag = $tag;
		$metadata     = wp_get_attachment_metadata( $attachment_id );
		foreach ( $metadata['sizes'] as $size => $properties ) {
			$this->assertArrayHasKey( 'image/jpeg', $properties['sources'] );
			$this->assertArrayHasKey( 'file', $properties['sources']['image/jpeg'] );
			$this->assertArrayHasKey( 'image/webp', $properties['sources'] );
			$this->assertArrayHasKey( 'file', $properties['sources']['image/webp'] );
			$expected_tag = str_replace( $properties['sources']['image/jpeg']['file'], $properties['sources']['image/webp']['file'], $expected_tag );
		}

		$this->assertNotEmpty( $expected_tag );
		$this->assertNotSame( $tag, $expected_tag );
		$this->assertSame( $expected_tag, webp_uploads_update_image_references( $tag ) );
	}

	public function provider_replace_images_with_different_extensions() {
		yield 'An image with a .jpg extension' => array( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		yield 'An image with a .jpeg extension' => array( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );
	}

	/**
	 * Contain the full image size from the original mime
	 *
	 * @group webp_uploads_update_image_references
	 *
	 * @test
	 */
	public function it_should_contain_the_full_image_size_from_the_original_mime() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		$tag = wp_get_attachment_image( $attachment_id, 'full', false, array( 'class' => "wp-image-{$attachment_id}" ) );

		$expected = array(
			'ext'  => 'jpg',
			'type' => 'image/jpeg',
		);
		$this->assertSame( $expected, wp_check_filetype( get_attached_file( $attachment_id ) ) );
		$this->assertContains( wp_basename( get_attached_file( $attachment_id ) ), webp_uploads_update_image_references( $tag ) );
	}

	/**
	 * Prevent replacing an image with no available sources
	 *
	 * @group webp_uploads_update_image_references
	 *
	 * @test
	 */
	public function it_should_prevent_replacing_an_image_with_no_available_sources() {
		add_filter( 'webp_uploads_supported_image_mime_transforms', '__return_empty_array' );

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );

		$tag      = wp_get_attachment_image( $attachment_id, 'full', false, array( 'class' => "wp-image-{$attachment_id}" ) );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		foreach ( $metadata['sizes'] as $size => $properties ) {
			$this->assertArrayNotHasKey( 'sources', $properties );
		}

		$this->assertSame( $tag, webp_uploads_update_image_references( $tag ) );
	}

	/**
	 * Prevent update not supported images with no available sources
	 *
	 * @dataProvider data_provider_not_supported_webp_images
	 * @group webp_uploads_update_image_references
	 *
	 * @test
	 */
	public function it_should_prevent_update_not_supported_images_with_no_available_sources( $image_path ) {
		$attachment_id = $this->factory->attachment->create_upload_object( $image_path );

		$this->assertIsNumeric( $attachment_id );
		$tag      = wp_get_attachment_image( $attachment_id, 'full', false, array( 'class' => "wp-image-{$attachment_id}" ) );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		foreach ( $metadata['sizes'] as $size => $properties ) {
			$this->assertArrayNotHasKey( 'sources', $properties );
		}

		$this->assertSame( $tag, webp_uploads_update_image_references( $tag ) );
	}

	public function data_provider_not_supported_webp_images() {
		yield 'PNG image' => array( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dice.png' );
		yield 'GIFT image' => array( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/earth.gif' );
	}
}
