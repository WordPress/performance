<?php
/**
 * Tests for webp-uploads module load.php.
 *
 * @package performance-lab
 * @group   webp-uploads
 */

use PerformanceLab\Tests\TestCase\ImagesTestCase;

class WebP_Uploads_Load_Tests extends ImagesTestCase {
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
		$this->assertSame( $metadata['sources']['image/jpeg']['filesize'], wp_filesize( path_join( $dirname, $metadata['sources']['image/jpeg']['file'] ) ) );

		$this->assertImageHasSource( $attachment_id, 'image/webp' );
		$this->assertFileExists( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) );
		$this->assertSame( $metadata['sources']['image/webp']['filesize'], wp_filesize( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) ) );

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
	 * Remove the attached WebP version if the attachment is force deleted
	 *
	 * @test
	 */
	public function it_should_remove_the_attached_webp_version_if_the_attachment_is_force_deleted() {
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
	 * Remove the attached WebP version if the attachment is force deleted after edit.
	 *
	 * @test
	 */
	public function it_should_remove_the_backup_sizes_and_sources_if_the_attachment_is_deleted_after_edit() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		$file    = get_attached_file( $attachment_id, true );
		$dirname = pathinfo( $file, PATHINFO_DIRNAME );

		$this->assertIsString( $file );
		$this->assertFileExists( $file );

		$editor = new WP_Image_Edit( $attachment_id );
		$editor->rotate_right()->save();

		$backup_sources = get_post_meta( $attachment_id, '_wp_attachment_backup_sources', true );
		$this->assertNotEmpty( $backup_sources );
		$this->assertIsArray( $backup_sources );

		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		$this->assertNotEmpty( $backup_sizes );
		$this->assertIsArray( $backup_sizes );

		wp_delete_attachment( $attachment_id, true );

		$this->assertFileDoesNotExist( path_join( $dirname, $backup_sources['full-orig']['image/webp']['file'] ) );
		$this->assertFileDoesNotExist( path_join( $dirname, $backup_sizes['thumbnail-orig']['sources']['image/webp']['file'] ) );
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

		add_filter(
			'webp_uploads_image_sizes_with_additional_mime_type_support',
			function( $sizes ) {
				$sizes['1536x1536'] = '1536x1536';
				return $sizes;
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
	 * The image with the smaller filesize should be used when webp_uploads_prefer_smaller_image_file is set to true.
	 *
	 * @test
	 */
	public function it_should_use_smaller_jpg_image_when_smaller_size_preferred() {
		// Set prefer smaller image size to true.
		add_filter( 'webp_uploads_prefer_smaller_image_file', '__return_true' );

		// File generates smallest WebP version of the 'full' image size, all other sub sizes have a smaller JPG version.
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		$tag           = wp_get_attachment_image( $attachment_id, 'full', false, array( 'class' => "wp-image-{$attachment_id}" ) );
		$updated_tag   = webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id );

		// Replace the 'full' image size with the WebP version, all other sub sizes will use the smaller JPG version.
		$expected_tag = str_replace( $metadata['sources']['image/jpeg']['file'], $metadata['sources']['image/webp']['file'], $tag );

		$this->assertSame( $expected_tag, $updated_tag );
	}

	/**
	 * Replace the featured image to WebP when requesting the featured image
	 *
	 * @test
	 */
	public function it_should_replace_the_featured_image_to_web_p_when_requesting_the_featured_image() {
		add_filter(
			'webp_uploads_image_sizes_with_additional_mime_type_support',
			function( $sizes ) {
				$sizes['1536x1536'] = '1536x1536';
				return $sizes;
			}
		);

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/paint.jpeg' );
		$post_id       = $this->factory()->post->create();
		set_post_thumbnail( $post_id, $attachment_id );

		$featured_image = get_the_post_thumbnail( $post_id );

		$this->assertTrue( has_post_thumbnail( $post_id ) );
		$this->assertStringContainsString( '.webp', $featured_image );
		$this->assertStringNotContainsString( '.jpeg', $featured_image );
	}

	/**
	 * Prevent replacing an image if image was uploaded via external source or plugin.
	 *
	 * @group webp_uploads_update_image_references
	 *
	 * @test
	 */
	public function it_should_prevent_replacing_an_image_uploaded_via_external_source() {
		remove_all_filters( 'webp_uploads_pre_replace_additional_image_source' );

		add_filter(
			'webp_uploads_pre_replace_additional_image_source',
			function() {
				return '<img src="https://ia600200.us.archive.org/16/items/SPD-SLRSY-1867/hubblesite_2001_06.jpg">';
			}
		);

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );

		$tag = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => "wp-image-{$attachment_id}" ) );
		$this->assertNotSame( $tag, webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
	}

	/**
	 * Tests whether additional mime types generated only for allowed image sizes or not.
	 *
	 * @test
	 */
	public function it_should_create_mime_types_for_allowed_sizes_only() {
		add_filter(
			'webp_uploads_image_sizes_with_additional_mime_type_support',
			function( $sizes ) {
				$sizes['allowed_size_400x300'] = 'allowed_size_400x300';
				return $sizes;
			}
		);

		add_image_size( 'allowed_size_400x300', 400, 300, true );
		add_image_size( 'not_allowed_size_200x150', 200, 150, true );

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );

		remove_image_size( 'allowed_size_400x300' );
		remove_image_size( 'not_allowed_size_200x150' );

		$this->assertImageHasSizeSource( $attachment_id, 'allowed_size_400x300', 'image/webp' );
		$this->assertImageNotHasSizeSource( $attachment_id, 'not_allowed_size_200x150', 'image/webp' );
	}
}
