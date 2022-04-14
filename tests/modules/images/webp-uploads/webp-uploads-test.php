<?php
/**
 * Tests for webp-uploads module.
 *
 * @package performance-lab
 * @group   webp-uploads
 */

use PerformanceLab\Tests\TestCase\ImagesTestCase;

class WebP_Uploads_Tests extends ImagesTestCase {
	/**
	 * Create the original mime type as well with all the available sources for the specified mime
	 *
	 * @dataProvider provider_image_with_default_behaviors_during_upload
	 *
	 * @test
	 */
	public function it_should_create_the_original_mime_type_as_well_with_all_the_available_sources_for_the_specified_mime( $file_location, $expected_mime, $targeted_mime ) {
		$attachment_id = $this->factory->attachment->create_upload_object( $file_location );

		$this->assertImageHasSource( $attachment_id, $targeted_mime );
		$this->assertImageHasSource( $attachment_id, $expected_mime );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertArrayHasKey( 'file', $metadata );
		$this->assertStringEndsWith( $metadata['sources'][ $expected_mime ]['file'], $metadata['file'] );

		foreach ( array_keys( $metadata['sizes'] ) as $size_name ) {
			$this->assertImageHasSizeSource( $attachment_id, $size_name, $targeted_mime );
			$this->assertImageHasSizeSource( $attachment_id, $size_name, $expected_mime );
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
		add_filter( 'webp_uploads_upload_image_mime_transforms', '__return_empty_array' );

		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata );
		$this->assertArrayNotHasKey( 'sources', $metadata );
		foreach ( $metadata['sizes'] as $properties ) {
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
			'webp_uploads_upload_image_mime_transforms',
			function () {
				return array( 'image/jpeg' => array() );
			}
		);

		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		$this->assertImageHasSource( $attachment_id, 'image/jpeg' );
		$this->assertImageNotHasSource( $attachment_id, 'image/webp' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		foreach ( array_keys( $metadata['sizes'] ) as $size_name ) {
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/jpeg' );
			$this->assertImageNotHasSizeSource( $attachment_id, $size_name, 'image/webp' );
		}
	}

	/**
	 * Not create the sources property if the mime is not specified on the transforms images
	 *
	 * @test
	 */
	public function it_should_not_create_the_sources_property_if_the_mime_is_not_specified_on_the_transforms_images() {
		add_filter(
			'webp_uploads_upload_image_mime_transforms',
			function () {
				return array( 'image/jpeg' => array() );
			}
		);

		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/balloons.webp'
		);

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata );
		$this->assertArrayNotHasKey( 'sources', $metadata );
		foreach ( $metadata['sizes'] as $properties ) {
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
		$this->assertSame( 'original_image_file_not_found', $result->get_error_code() );
	}

	/**
	 * Prevent to create a subsize if the image editor does not exists
	 *
	 * @test
	 */
	public function it_should_prevent_to_create_a_subsize_if_the_image_editor_does_not_exists() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		// Make sure no editor is available.
		add_filter( 'wp_image_editors', '__return_empty_array' );
		$result = webp_uploads_generate_image_size( $attachment_id, 'medium', 'image/webp' );
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'image_mime_type_not_supported', $result->get_error_code() );
	}

	/**
	 * Prevent to upload a mime that is not supported by WordPress
	 *
	 * @test
	 */
	public function it_should_prevent_to_upload_a_mime_that_is_not_supported_by_wordpress() {
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

		$this->assertArrayHasKey( 'sources', $metadata );
		$this->assertIsArray( $metadata['sources'] );

		$file    = get_attached_file( $attachment_id, true );
		$dirname = pathinfo( $file, PATHINFO_DIRNAME );

		$this->assertImageHasSource( $attachment_id, 'image/jpeg' );
		$this->assertStringEndsWith( $metadata['sources']['image/jpeg']['file'], $file );
		$this->assertFileExists( path_join( $dirname, $metadata['sources']['image/jpeg']['file'] ) );
		$this->assertSame( $metadata['sources']['image/jpeg']['filesize'], filesize( path_join( $dirname, $metadata['sources']['image/jpeg']['file'] ) ) );

		$this->assertImageHasSource( $attachment_id, 'image/webp' );
		$this->assertFileExists( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) );
		$this->assertSame( $metadata['sources']['image/webp']['filesize'], filesize( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) ) );

		$this->assertImageHasSizeSource( $attachment_id, 'thumbnail', 'image/jpeg' );
		$this->assertImageHasSizeSource( $attachment_id, 'thumbnail', 'image/webp' );
	}

	/**
	 * Create the full size images when no size is available
	 *
	 * @test
	 */
	public function it_should_create_the_full_size_images_when_no_size_is_available() {
		add_filter( 'intermediate_image_sizes', '__return_empty_array' );
		add_filter( 'fallback_intermediate_image_sizes', '__return_empty_array' );

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertEmpty( $metadata['sizes'] );

		$this->assertImageHasSource( $attachment_id, 'image/jpeg' );
		$this->assertImageHasSource( $attachment_id, 'image/webp' );
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
		$this->assertImageHasSizeSource( $attachment_id, 'medium', 'image/webp' );
		$this->assertStringEndsNotWith( '-scaled.webp', $metadata['sizes']['medium']['sources']['image/webp']['file'] );
		$this->assertStringEndsWith( '-300x200.webp', $metadata['sizes']['medium']['sources']['image/webp']['file'] );
	}

	/**
	 * Remove the generated webp images when the attachment is deleted
	 *
	 * @test
	 */
	public function it_should_remove_the_generated_webp_images_when_the_attachment_is_deleted() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		$file    = get_attached_file( $attachment_id, true );
		$dirname = pathinfo( $file, PATHINFO_DIRNAME );

		$this->assertIsString( $file );
		$this->assertFileExists( $file );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$sizes    = array( 'thumbnail', 'medium' );

		$this->assertFileExists( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) );

		foreach ( $sizes as $size_name ) {
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/webp' );
			$this->assertFileExists( path_join( $dirname, $metadata['sizes'][ $size_name ]['sources']['image/webp']['file'] ) );
		}

		wp_delete_attachment( $attachment_id );

		foreach ( $sizes as $size_name ) {
			$this->assertFileDoesNotExist( path_join( $dirname, $metadata['sizes'][ $size_name ]['sources']['image/webp']['file'] ) );
		}

		$this->assertFileDoesNotExist( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) );
	}

	/**
	 * Remove the attached WebP version if the attachment is force deleted but empty trash day is not defined
	 *
	 * @test
	 */
	public function it_should_remove_the_attached_webp_version_if_the_attachment_is_force_deleted_but_empty_trash_day_is_not_defined() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		$file    = get_attached_file( $attachment_id, true );
		$dirname = pathinfo( $file, PATHINFO_DIRNAME );

		$this->assertIsString( $file );
		$this->assertFileExists( $file );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertFileExists( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) );
		$this->assertFileExists( path_join( $dirname, $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] ) );

		wp_delete_attachment( $attachment_id, true );

		$this->assertFileDoesNotExist( path_join( $dirname, $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] ) );
		$this->assertFileDoesNotExist( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) );
	}

	/**
	 * Remove the WebP version of the image if the image is force deleted and empty trash days is set to zero
	 *
	 * @test
	 */
	public function it_should_remove_the_webp_version_of_the_image_if_the_image_is_force_deleted_and_empty_trash_days_is_set_to_zero() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		$file    = get_attached_file( $attachment_id, true );
		$dirname = pathinfo( $file, PATHINFO_DIRNAME );

		$this->assertIsString( $file );
		$this->assertFileExists( $file );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertFileExists( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) );
		$this->assertFileExists( path_join( $dirname, $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] ) );

		define( 'EMPTY_TRASH_DAYS', 0 );

		wp_delete_attachment( $attachment_id, true );

		$this->assertFileDoesNotExist( path_join( $dirname, $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] ) );
		$this->assertFileDoesNotExist( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) );
	}

	/**
	 * Remove full size images when no size image exists
	 *
	 * @test
	 */
	public function it_should_remove_full_size_images_when_no_size_image_exists() {
		add_filter( 'intermediate_image_sizes', '__return_empty_array' );
		add_filter( 'fallback_intermediate_image_sizes', '__return_empty_array' );

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );

		$file    = get_attached_file( $attachment_id, true );
		$dirname = pathinfo( $file, PATHINFO_DIRNAME );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertEmpty( $metadata['sizes'] );
		$this->assertFileExists( $file );
		$this->assertFileExists( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) );
		$this->assertFileExists( path_join( $dirname, $metadata['sources']['image/jpeg']['file'] ) );

		wp_delete_attachment( $attachment_id );

		$this->assertFileDoesNotExist( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) );
		$this->assertFileDoesNotExist( path_join( $dirname, $metadata['sources']['image/jpeg']['file'] ) );
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

		$this->assertSame( $tag, webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
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
			$expected_tag = str_replace( $properties['sources']['image/jpeg']['file'], $properties['sources']['image/webp']['file'], $expected_tag );
		}

		$expected_tag = str_replace( $metadata['sources']['image/jpeg']['file'], $metadata['sources']['image/webp']['file'], $expected_tag );

		$this->assertNotEmpty( $expected_tag );
		$this->assertNotSame( $tag, $expected_tag );
		$this->assertSame( $expected_tag, webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
	}

	/**
	 * Should not replace jpeg images in the content if other mime types are disabled via filter.
	 *
	 * @dataProvider provider_replace_images_with_different_extensions
	 * @group webp_uploads_update_image_references
	 *
	 * @test
	 */
	public function it_should_not_replace_the_references_to_a_jpg_image_when_disabled_via_filter( $image_path ) {
		remove_all_filters( 'webp_uploads_content_image_mimes' );

		add_filter(
			'webp_uploads_content_image_mimes',
			function( $mime_types ) {
				unset( $mime_types[ array_search( 'image/webp', $mime_types, true ) ] );
				return $mime_types;
			}
		);

		$attachment_id = $this->factory->attachment->create_upload_object( $image_path );
		$tag           = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => "wp-image-{$attachment_id}" ) );

		$this->assertSame( $tag, webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
	}

	public function provider_replace_images_with_different_extensions() {
		yield 'An image with a .jpg extension' => array( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		yield 'An image with a .jpeg extension' => array( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );
	}

	/**
	 * Replace all the images including the full size image
	 *
	 * @test
	 */
	public function it_should_replace_all_the_images_including_the_full_size_image() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		$tag = wp_get_attachment_image( $attachment_id, 'full', false, array( 'class' => "wp-image-{$attachment_id}" ) );

		$expected = array(
			'ext'  => 'jpg',
			'type' => 'image/jpeg',
		);
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertSame( $expected, wp_check_filetype( get_attached_file( $attachment_id ) ) );
		$this->assertNotContains( wp_basename( get_attached_file( $attachment_id ) ), webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
		$this->assertContains( $metadata['sources']['image/webp']['file'], webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
	}

	/**
	 * Prevent replacing an image with no available sources
	 *
	 * @group webp_uploads_update_image_references
	 *
	 * @test
	 */
	public function it_should_prevent_replacing_an_image_with_no_available_sources() {
		add_filter( 'webp_uploads_upload_image_mime_transforms', '__return_empty_array' );

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );

		$tag = wp_get_attachment_image( $attachment_id, 'full', false, array( 'class' => "wp-image-{$attachment_id}" ) );
		$this->assertSame( $tag, webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
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
		$tag = wp_get_attachment_image( $attachment_id, 'full', false, array( 'class' => "wp-image-{$attachment_id}" ) );

		$this->assertSame( $tag, webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
	}

	public function data_provider_not_supported_webp_images() {
		yield 'PNG image' => array( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dice.png' );
		yield 'GIFT image' => array( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/earth.gif' );
	}

	/**
	 * Checks whether the sources information is added to image sizes details of the REST response object.
	 *
	 * @test
	 */
	public function it_should_add_sources_to_rest_response() {
		$file_location = TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $file_location );
		$metadata      = wp_get_attachment_metadata( $attachment_id );

		$request       = new WP_REST_Request();
		$request['id'] = $attachment_id;

		$controller = new WP_REST_Attachments_Controller( 'attachment' );
		$response   = $controller->get_item( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );

		$data       = $response->get_data();
		$mime_types = array(
			'image/jpeg',
			'image/webp',
		);

		foreach ( $data['media_details']['sizes'] as $size_name => $properties ) {
			if ( ! isset( $metadata['sizes'][ $size_name ]['sources'] ) ) {
				continue;
			}

			$this->assertArrayHasKey( 'sources', $properties );
			$this->assertIsArray( $properties['sources'] );

			foreach ( $mime_types as $mime_type ) {
				$this->assertArrayHasKey( $mime_type, $properties['sources'] );

				$this->assertArrayHasKey( 'filesize', $properties['sources'][ $mime_type ] );
				$this->assertArrayHasKey( 'file', $properties['sources'][ $mime_type ] );
				$this->assertArrayHasKey( 'source_url', $properties['sources'][ $mime_type ] );

				$this->assertNotFalse( filter_var( $properties['sources'][ $mime_type ]['source_url'], FILTER_VALIDATE_URL ) );
			}
		}
	}

	/**
	 * Return an error when creating an additional image source with invalid parameters
	 *
	 * @dataProvider data_provider_invalid_arguments_for_webp_uploads_generate_additional_image_source
	 *
	 * @test
	 */
	public function it_should_return_an_error_when_creating_an_additional_image_source_with_invalid_parameters( $attachment_id, $size_data, $mime, $destination_file = null ) {
		$this->assertInstanceOf( WP_Error::class, webp_uploads_generate_additional_image_source( $attachment_id, $size_data, $mime, $destination_file ) );
	}

	public function data_provider_invalid_arguments_for_webp_uploads_generate_additional_image_source() {
		yield 'when trying to use an attachment ID that does not exists' => array(
			PHP_INT_MAX,
			array(),
			'image/webp',
		);

		add_filter( 'wp_image_editors', '__return_empty_array' );
		yield 'when no editor is present' => array(
			$this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' ),
			array(),
			'image/avif',
		);

		remove_filter( 'wp_image_editors', '__return_empty_array' );
		yield 'when using a mime that is not supported' => array(
			$this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' ),
			array(),
			'image/avif',
		);

		yield 'when no dimension is provided' => array(
			$this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' ),
			array(),
			'image/webp',
		);

		yield 'when both dimensions are negative numbers' => array(
			$this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' ),
			array(
				'width'  => -10,
				'height' => -20,
			),
			'image/webp',
		);

		yield 'when both dimensions are zero' => array(
			$this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' ),
			array(
				'width'  => 0,
				'height' => 0,
			),
			'image/webp',
		);
	}

	/**
	 * Create an image with the default suffix in the same location when no destination is specified
	 *
	 * @test
	 */
	public function it_should_create_an_image_with_the_default_suffix_in_the_same_location_when_no_destination_is_specified() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );
		$size_data     = array(
			'width'  => 300,
			'height' => 300,
			'crop'   => true,
		);

		$result    = webp_uploads_generate_additional_image_source( $attachment_id, $size_data, 'image/webp' );
		$file      = get_attached_file( $attachment_id );
		$directory = trailingslashit( pathinfo( $file, PATHINFO_DIRNAME ) );
		$name      = pathinfo( $file, PATHINFO_FILENAME );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'filesize', $result );
		$this->assertArrayHasKey( 'file', $result );
		$this->assertStringEndsWith( '300x300.webp', $result['file'] );
		$this->assertFileExists( "{$directory}{$name}-300x300.webp" );
	}

	/**
	 * Create a file in the specified location with the specified name
	 *
	 * @test
	 */
	public function it_should_create_a_file_in_the_specified_location_with_the_specified_name() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );
		$size_data     = array(
			'width'  => 300,
			'height' => 300,
			'crop'   => true,
		);

		$result = webp_uploads_generate_additional_image_source( $attachment_id, $size_data, 'image/webp', '/tmp/image.jpg' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'filesize', $result );
		$this->assertArrayHasKey( 'file', $result );
		$this->assertStringEndsWith( 'image.webp', $result['file'] );
		$this->assertFileExists( '/tmp/image.webp' );
	}

	/**
	 * Use the original image to generate all the image sizes
	 *
	 * @test
	 */
	public function it_should_use_the_original_image_to_generate_all_the_image_sizes() {
		// Use a 1500 threshold.
		add_filter(
			'big_image_size_threshold',
			function () {
				return 1500;
			}
		);

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/paint.jpeg' );
		$metadata      = wp_get_attachment_metadata( $attachment_id );

		$this->assertArrayHasKey( '1536x1536', $metadata['sizes'] );
		foreach ( $metadata['sizes'] as $size ) {
			$this->assertStringContainsString( $size['width'], $size['sources']['image/webp']['file'] );
			$this->assertStringContainsString( $size['height'], $size['sources']['image/webp']['file'] );
			$this->assertStringContainsString(
				// Remove the extension from the file.
				substr( $size['sources']['image/webp']['file'], 0, -4 ),
				$size['sources']['image/jpeg']['file']
			);
		}
	}

	/**
	 * Tests that we can force transformation from jpeg to webp by using the webp_uploads_upload_image_mime_transforms filter.
	 *
	 * @test
	 */
	public function it_should_transform_jpeg_to_webp_subsizes_using_transform_filter() {
		remove_all_filters( 'webp_uploads_upload_image_mime_transforms' );

		add_filter(
			'webp_uploads_upload_image_mime_transforms',
			function( $transforms ) {
				// Unset "image/jpeg" mime type for jpeg images.
				unset( $transforms['image/jpeg'][ array_search( 'image/jpeg', $transforms['image/jpeg'], true ) ] );

				return $transforms;
			}
		);

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );

		$this->assertImageHasSource( $attachment_id, 'image/webp' );
		$this->assertImageNotHasSource( $attachment_id, 'image/jpeg' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		foreach ( array_keys( $metadata['sizes'] ) as $size_name ) {
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/webp' );
			$this->assertImageNotHasSizeSource( $attachment_id, $size_name, 'image/jpeg' );
		}
	}

	/**
	 * Backup the sources structure alongside the full size
	 *
	 * @test
	 */
	public function it_should_backup_the_sources_structure_alongside_the_full_size() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertEmpty( get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true ) );
		$this->assertEmpty( get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true ) );

		$editor = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();

		// Having a thumbnail ensures the process finished correctly.
		$this->assertTrue( $editor->success() );

		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );

		$this->assertNotEmpty( $backup_sizes );
		$this->assertIsArray( $backup_sizes );

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
	public function it_should_restore_the_sources_array_from_the_backup_when_an_image_is_edited() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		$metadata      = wp_get_attachment_metadata( $attachment_id );

		$editor = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();
		$this->assertTrue( $editor->success() );

		$backup_sources = get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true );
		$this->assertArrayHasKey( 'full-orig', $backup_sources );
		$this->assertIsArray( $backup_sources['full-orig'] );
		$this->assertSame( $metadata['sources'], $backup_sources['full-orig'] );

		wp_restore_image( $attachment_id );

		$this->assertImageHasSource( $attachment_id, 'image/jpeg' );
		$this->assertImageHasSource( $attachment_id, 'image/webp' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertSame( $backup_sources['full-orig'], $metadata['sources'] );
		$this->assertSame( $backup_sources, get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true ) );

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
	public function it_should_prevent_to_back_up_the_sources_when_the_sources_attributes_does_not_exists() {
		// Disable the generation of the sources attributes.
		add_filter( 'webp_uploads_upload_image_mime_transforms', '__return_empty_array' );

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
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
	public function it_should_prevent_to_backup_the_full_size_image_if_only_the_thumbnail_is_edited() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
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

		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/jpeg' );
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/webp' );
		}
	}

	/**
	 * Backup the image when all images except the thumbnail are updated
	 *
	 * @test
	 */
	public function it_should_backup_the_image_when_all_images_except_the_thumbnail_are_updated() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
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
		$this->assertImageHasSource( $attachment_id, 'image/jpeg' );
		$this->assertImageHasSource( $attachment_id, 'image/webp' );

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
	 * Update source attributes when webp is edited.
	 *
	 * @test
	 */
	public function it_should_validate_source_attribute_update_when_webp_edited() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );

		$editor = new WP_Image_Edit( $attachment_id );
		$editor->crop( 1000, 200, 0, 0 )->save();
		$this->assertTrue( $editor->success() );

		$this->assertImageHasSource( $attachment_id, 'image/webp' );
		$this->assertImageHasSource( $attachment_id, 'image/jpeg' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertFileNameIsEdited( $metadata['sources']['image/webp']['file'] );
		$this->assertFileNameIsEdited( $metadata['sources']['image/jpeg']['file'] );

		$this->assertArrayHasKey( 'sources', $metadata );
		$this->assertArrayHasKey( 'sizes', $metadata );

		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertArrayHasKey( 'sources', $properties );
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/webp' );
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/jpeg' );

			$this->assertFileNameIsEdited( $properties['sources']['image/webp']['file'] );
			$this->assertFileNameIsEdited( $properties['sources']['image/jpeg']['file'] );
		}
	}

	/**
	 * Allow the upload of a WebP image if at least one editor supports the format
	 *
	 * @test
	 */
	public function it_should_allow_the_upload_of_a_web_p_image_if_at_least_one_editor_supports_the_format() {
		add_filter(
			'wp_image_editors',
			function () {
				return array( 'WP_Image_Doesnt_Support_WebP', 'WP_Image_Editor_GD' );
			}
		);

		$this->assertTrue( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) );

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		$metadata      = wp_get_attachment_metadata( $attachment_id );

		$this->assertArrayHasKey( 'sources', $metadata );
		$this->assertIsArray( $metadata['sources'] );

		$this->assertImageHasSource( $attachment_id, 'image/jpeg' );
		$this->assertImageHasSource( $attachment_id, 'image/webp' );

		$this->assertImageHasSizeSource( $attachment_id, 'thumbnail', 'image/jpeg' );
		$this->assertImageHasSizeSource( $attachment_id, 'thumbnail', 'image/webp' );
	}

	/**
	 * Not return a target if no backup image exists
	 *
	 * @test
	 */
	public function it_should_not_return_a_target_if_no_backup_image_exists() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		$this->assertNull( webp_uploads_get_next_full_size_key_from_backup( $attachment_id ) );
	}

	/**
	 * Return the full-orig target key when only one edit image exists
	 *
	 * @test
	 */
	public function it_should_return_the_full_orig_target_key_when_only_one_edit_image_exists() {
		// Remove the filter to prevent the usage of the next target.
		remove_filter( 'wp_update_attachment_metadata', 'webp_uploads_update_attachment_metadata' );

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
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
	public function it_should_return_null_when_looking_for_a_target_that_is_already_used() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		$editor        = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();

		$this->assertTrue( $editor->success() );
		$this->assertNull( webp_uploads_get_next_full_size_key_from_backup( $attachment_id ) );
	}

	/**
	 * USe the next available hash for the full size image on multiple image edits
	 *
	 * @test
	 */
	public function it_should_u_se_the_next_available_hash_for_the_full_size_image_on_multiple_image_edits() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		$editor        = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();

		$this->assertTrue( $editor->success() );
		$this->assertNull( webp_uploads_get_next_full_size_key_from_backup( $attachment_id ) );
		// Remove the filter to prevent the usage of the next target.
		remove_filter( 'wp_update_attachment_metadata', 'webp_uploads_update_attachment_metadata' );

		$editor->rotate_right()->save();
		$this->assertSizeNameIsHashed( 'full', webp_uploads_get_next_full_size_key_from_backup( $attachment_id ) );
	}

	/**
	 * Save populate the backup sources with the next target
	 *
	 * @test
	 */
	public function it_should_save_populate_the_backup_sources_with_the_next_target() {
		// Remove the filter to prevent the usage of the next target.
		remove_filter( 'wp_update_attachment_metadata', 'webp_uploads_update_attachment_metadata' );

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		$editor        = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();
		$this->assertTrue( $editor->success() );

		$sources = array( 'image/webp' => 'leafs.webp' );
		webp_uploads_backup_full_image_sources( $attachment_id, $sources );

		$this->assertSame( array( 'full-orig' => $sources ), get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true ) );
	}

	/**
	 * Store the metadata on the next available hash
	 *
	 * @test
	 */
	public function it_should_store_the_metadata_on_the_next_available_hash() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );

		$editor = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();
		$this->assertTrue( $editor->success() );

		// Remove the filter to prevent the usage of the next target.
		remove_filter( 'wp_update_attachment_metadata', 'webp_uploads_update_attachment_metadata' );
		$editor->rotate_right()->save();
		$this->assertTrue( $editor->success() );

		$sources = array( 'image/webp' => 'leafs.webp' );
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
	public function it_should_prevent_to_store_an_empty_set_of_sources() {
		// Remove the filter to prevent the usage of the next target.
		remove_filter( 'wp_update_attachment_metadata', 'webp_uploads_update_attachment_metadata' );

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		$editor        = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();

		webp_uploads_backup_full_image_sources( $attachment_id, array() );

		$this->assertTrue( $editor->success() );
		$this->assertEmpty( get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true ) );
	}

	/**
	 * Test webp_uploads_get_mime_types_by_filesize returns smallest filesize, in this case webp.
	 *
	 * @test
	 */
	public function it_should_return_smaller_webp_mime_type() {
		// File should generate smallest webp image size.
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );

		$mime_types = webp_uploads_get_mime_types_by_filesize( array( 'image/jpeg', 'image/webp' ), $attachment_id, 'the_content' );

		$this->assertIsArray( $mime_types );
		$this->assertSame( array( 'image/webp', 'image/jpeg' ), $mime_types );
	}

	/**
	 * Test webp_uploads_get_mime_types_by_filesize returns smallest filesize, in this case jpeg.
	 *
	 * @test
	 */
	public function it_should_return_smaller_jpeg_mime_type() {
		// File should generate smallest jpeg image size.
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/paint.jpeg' );

		// Mock attachment meta data to test when jpeg image is smaller.
		add_filter(
			'wp_get_attachment_metadata',
			function( $data, $attachment_id ) {
				$data['sources'] = array(
					'image/jpeg' => array(
						'file'     => 'paint.jpeg',
						'filesize' => 1000,
					),
					'image/webp' => array(
						'file'     => 'paint.webp',
						'filesize' => 2000,
					),
				);

				return $data;
			},
			10,
			2
		);

		$mime_types = webp_uploads_get_mime_types_by_filesize( array( 'image/jpeg', 'image/webp' ), $attachment_id, 'the_content' );

		$this->assertIsArray( $mime_types );
		$this->assertSame( array( 'image/jpeg', 'image/webp' ), $mime_types );
	}

	/**
	 * Test webp_uploads_get_mime_types_by_filesize removes invalid mime types with zero filesize.
	 *
	 * @test
	 */
	public function it_should_remove_mime_types_with_zero_filesize() {
		// File should generate smallest jpeg image size.
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/paint.jpeg' );

		// Mock attachment meta data to test mime type with zero filesize.
		add_filter(
			'wp_get_attachment_metadata',
			function( $data, $attachment_id ) {
				$data['sources'] = array(
					'image/jpeg'    => array(
						'file'     => 'paint.jpeg',
						'filesize' => 1000,
					),
					'image/webp'    => array(
						'file'     => 'paint.webp',
						'filesize' => 2000,
					),
					'image/invalid' => array(
						'file'     => 'paint.avif',
						'filesize' => 0,
					),
				);

				return $data;
			},
			10,
			2
		);

		$mime_types = webp_uploads_get_mime_types_by_filesize( array( 'image/jpeg', 'image/webp' ), $attachment_id, 'the_content' );

		$this->assertIsArray( $mime_types );
		$this->assertNotContains( 'image/invalid', $mime_types );
		$this->assertSame( array( 'image/jpeg', 'image/webp' ), $mime_types );
	}
}
