<?php
/**
 * Tests for webp-uploads plugin load.php.
 *
 * @package webp-uploads
 */

use PerformanceLab\Tests\TestCase\ImagesTestCase;

class WebP_Uploads_Load_Tests extends ImagesTestCase {

	/**
	 * To unlink files.
	 *
	 * @var array
	 */
	protected $to_unlink = array();

	public function set_up(): void {
		parent::set_up();

		add_filter( 'webp_uploads_discard_larger_generated_images', '__return_false' );

		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$this->markTestSkipped( 'Mime type image/webp is not supported.' );
		}
	}

	public function tear_down(): void {
		$this->to_unlink = array_filter(
			$this->to_unlink,
			static function ( $filename ) {
				return unlink( $filename );
			}
		);

		parent::tear_down();
	}

	/**
	 * Don't create the original mime type for JPEG images.
	 *
	 * @test
	 */
	public function it_should_not_create_the_original_mime_type_for_jpeg_images(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );

		// There should be a WebP source, but no JPEG source for the full image.
		$this->assertImageHasSource( $attachment_id, 'image/webp' );
		$this->assertImageNotHasSource( $attachment_id, 'image/jpeg' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		// The full image should be a WebP.
		$this->assertArrayHasKey( 'file', $metadata );
		$this->assertStringEndsWith( $metadata['sources']['image/webp']['file'], $metadata['file'] );
		$this->assertStringEndsWith( $metadata['sources']['image/webp']['file'], get_attached_file( $attachment_id ) );

		// The original JPEG should be backed up.
		$this->assertStringEndsWith( '.jpg', wp_get_original_image_path( $attachment_id ) );

		// For compatibility reasons, the post MIME type should remain JPEG.
		$this->assertSame( 'image/jpeg', get_post_mime_type( $attachment_id ) );

		// There should be a WebP source, but no JPEG source for all sizes.
		foreach ( array_keys( $metadata['sizes'] ) as $size_name ) {
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/webp' );
			$this->assertImageNotHasSizeSource( $attachment_id, $size_name, 'image/jpeg' );
		}
	}

	/**
	 * Create the original mime type for WebP images.
	 *
	 * @test
	 */
	public function it_should_create_the_original_mime_type_as_well_with_all_the_available_sources_for_the_specified_mime(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/balloons.webp' );

		// There should be a WebP source, but no JPEG source for the full image.
		$this->assertImageNotHasSource( $attachment_id, 'image/jpeg' );
		$this->assertImageHasSource( $attachment_id, 'image/webp' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		// The full image should be a WebP.
		$this->assertArrayHasKey( 'file', $metadata );
		$this->assertStringEndsWith( $metadata['sources']['image/webp']['file'], $metadata['file'] );
		$this->assertStringEndsWith( $metadata['sources']['image/webp']['file'], get_attached_file( $attachment_id ) );

		// The post MIME type should be WebP.
		$this->assertSame( 'image/webp', get_post_mime_type( $attachment_id ) );

		// There should be a WebP source, but no JPEG source for all sizes.
		foreach ( array_keys( $metadata['sizes'] ) as $size_name ) {
			$this->assertImageNotHasSizeSource( $attachment_id, $size_name, 'image/jpeg' );
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/webp' );
		}
	}

	/**
	 * Create JPEG and WebP for JPEG images, if opted in.
	 *
	 * @test
	 */
	public function it_should_create_jpeg_and_webp_for_jpeg_images_if_opted_in(): void {
		$this->opt_in_to_jpeg_and_webp();

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );

		// There should be JPEG and WebP sources for the full image.
		$this->assertImageHasSource( $attachment_id, 'image/jpeg' );
		$this->assertImageHasSource( $attachment_id, 'image/webp' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		// The full image should be a JPEG.
		$this->assertArrayHasKey( 'file', $metadata );
		$this->assertStringEndsWith( $metadata['sources']['image/jpeg']['file'], $metadata['file'] );
		$this->assertStringEndsWith( $metadata['sources']['image/jpeg']['file'], get_attached_file( $attachment_id ) );

		// The post MIME type should be JPEG.
		$this->assertSame( 'image/jpeg', get_post_mime_type( $attachment_id ) );

		// There should be JPEG and WebP sources for all sizes.
		foreach ( array_keys( $metadata['sizes'] ) as $size_name ) {
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/jpeg' );
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/webp' );
		}
	}

	/**
	 * Create JPEG and WebP for JPEG images, if perflab_generate_webp_and_jpeg option set.
	 *
	 * @test
	 */
	public function it_should_create_jpeg_and_webp_for_jpeg_images_if_generate_webp_and_jpeg_set(): void {
		update_option( 'perflab_generate_webp_and_jpeg', true );

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );

		// There should be JPEG and WebP sources for the full image.
		$this->assertImageHasSource( $attachment_id, 'image/jpeg' );
		$this->assertImageHasSource( $attachment_id, 'image/webp' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		// The full image should be a JPEG.
		$this->assertArrayHasKey( 'file', $metadata );
		$this->assertStringEndsWith( $metadata['sources']['image/jpeg']['file'], $metadata['file'] );
		$this->assertStringEndsWith( $metadata['sources']['image/jpeg']['file'], get_attached_file( $attachment_id ) );

		// The post MIME type should be JPEG.
		$this->assertSame( 'image/jpeg', get_post_mime_type( $attachment_id ) );

		// There should be JPEG and WebP sources for all sizes.
		foreach ( array_keys( $metadata['sizes'] ) as $size_name ) {
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/jpeg' );
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/webp' );
		}
	}

	/**
	 * Don't create the sources property if no transform is provided.
	 *
	 * @test
	 */
	public function it_should_not_create_the_sources_property_if_no_transform_is_provided(): void {
		add_filter( 'webp_uploads_upload_image_mime_transforms', '__return_empty_array' );

		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg'
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
	public function it_should_create_the_sources_property_when_no_transform_is_available(): void {
		add_filter(
			'webp_uploads_upload_image_mime_transforms',
			static function () {
				return array( 'image/jpeg' => array() );
			}
		);

		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg'
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
	public function it_should_not_create_the_sources_property_if_the_mime_is_not_specified_on_the_transforms_images(): void {
		add_filter(
			'webp_uploads_upload_image_mime_transforms',
			static function () {
				return array( 'image/jpeg' => array() );
			}
		);

		$attachment_id = self::factory()->attachment->create_upload_object(
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
	public function it_should_create_a_webp_version_with_all_the_required_properties(): void {
		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg'
		);

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertArrayHasKey( 'sources', $metadata );
		$this->assertIsArray( $metadata['sources'] );

		$file    = get_attached_file( $attachment_id, true );
		$dirname = pathinfo( $file, PATHINFO_DIRNAME );

		$this->assertImageNotHasSource( $attachment_id, 'image/jpeg' );

		$this->assertImageHasSource( $attachment_id, 'image/webp' );
		$this->assertFileExists( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) );
		$this->assertSame( $metadata['sources']['image/webp']['filesize'], wp_filesize( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) ) );

		$this->assertImageNotHasSizeSource( $attachment_id, 'thumbnail', 'image/jpeg' );
		$this->assertImageHasSizeSource( $attachment_id, 'thumbnail', 'image/webp' );
	}

	/**
	 * Create the full size images when no size is available
	 *
	 * @test
	 */
	public function it_should_create_the_full_size_images_when_no_size_is_available(): void {
		add_filter( 'intermediate_image_sizes', '__return_empty_array' );
		add_filter( 'fallback_intermediate_image_sizes', '__return_empty_array' );

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertEmpty( $metadata['sizes'] );

		$this->assertImageNotHasSource( $attachment_id, 'image/jpeg' );
		$this->assertImageHasSource( $attachment_id, 'image/webp' );
	}

	/**
	 * Remove `scaled` suffix from the generated filename
	 *
	 * @test
	 */
	public function it_should_remove_scaled_suffix_from_the_generated_filename(): void {
		// Create JPEG and WebP to check for scaled suffix.
		$this->opt_in_to_jpeg_and_webp();

		// The leaves image is 1080 pixels wide with this filter we ensure a -scaled version is created.
		add_filter(
			'big_image_size_threshold',
			static function () {
				return 850;
			}
		);

		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg'
		);
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		$this->assertStringEndsWith( '-scaled.jpg', get_attached_file( $attachment_id ) );
		$this->assertImageHasSizeSource( $attachment_id, 'medium', 'image/webp' );
		$this->assertStringEndsNotWith( '-scaled.webp', $metadata['sizes']['medium']['sources']['image/webp']['file'] );
		$this->assertStringEndsWith( '-300x200-jpg.webp', $metadata['sizes']['medium']['sources']['image/webp']['file'] );
	}

	/**
	 * Remove the generated webp images when the attachment is deleted
	 *
	 * @test
	 */
	public function it_should_remove_the_generated_webp_images_when_the_attachment_is_deleted(): void {
		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg'
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
	public function it_should_remove_the_attached_webp_version_if_the_attachment_is_force_deleted(): void {
		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg'
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
	public function it_should_remove_full_size_images_when_no_size_image_exists(): void {
		add_filter( 'intermediate_image_sizes', '__return_empty_array' );
		add_filter( 'fallback_intermediate_image_sizes', '__return_empty_array' );

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );

		$file    = get_attached_file( $attachment_id, true );
		$dirname = pathinfo( $file, PATHINFO_DIRNAME );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertEmpty( $metadata['sizes'] );
		$this->assertFileExists( $file );
		$this->assertFileExists( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) );

		wp_delete_attachment( $attachment_id );

		$this->assertFileDoesNotExist( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) );
	}

	/**
	 * Remove the attached WebP version if the attachment is force deleted after edit.
	 *
	 * @test
	 */
	public function it_should_remove_the_backup_sizes_and_sources_if_the_attachment_is_deleted_after_edit(): void {
		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg'
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
	public function it_should_avoid_the_change_of_urls_of_images_that_are_not_part_of_the_media_library(): void {
		// Run critical hooks to satisfy webp_uploads_in_frontend_body() conditions.
		$this->mock_frontend_body_hooks();

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
	public function it_should_avoid_replacing_not_existing_attachment_i_ds(): void {
		// Run critical hooks to satisfy webp_uploads_in_frontend_body() conditions.
		$this->mock_frontend_body_hooks();

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
	public function it_should_prevent_replacing_a_webp_image(): void {
		// Create JPEG and WebP to check that WebP does not get replaced with JPEG.
		$this->opt_in_to_jpeg_and_webp();

		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/balloons.webp'
		);

		$tag          = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => "wp-image-{$attachment_id}" ) );
		$expected_tag = $tag;
		$metadata     = wp_get_attachment_metadata( $attachment_id );
		foreach ( $metadata['sizes'] as $size => $properties ) {
			$expected_tag = str_replace( $properties['sources']['image/webp']['file'], $properties['sources']['image/jpeg']['file'], $expected_tag );
		}

		$expected_tag = str_replace( $metadata['sources']['image/webp']['file'], $metadata['sources']['image/jpeg']['file'], $expected_tag );

		$this->assertNotEmpty( $expected_tag );
		$this->assertNotSame( $tag, $expected_tag );
		$this->assertSame( $expected_tag, webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
	}

	/**
	 * Prevent replacing a jpg image if the image does not have the target class name
	 *
	 * @test
	 */
	public function it_should_prevent_replacing_a_jpg_image_if_the_image_does_not_have_the_target_class_name(): void {
		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg'
		);

		// Run critical hooks to satisfy webp_uploads_in_frontend_body() conditions.
		$this->mock_frontend_body_hooks();

		$tag = wp_get_attachment_image( $attachment_id, 'medium' );

		$this->assertSame( $tag, webp_uploads_update_image_references( $tag ) );
	}

	/**
	 * Replace references to a JPG image to a WebP version
	 *
	 * @dataProvider provider_replace_images_with_different_extensions
	 * @group webp_uploads_update_image_references
	 *
	 * @test
	 */
	public function it_should_replace_references_to_a_jpg_image_to_a_webp_version( $image_path ): void {
		// Create JPEG and WebP to check replacement of JPEG => WebP.
		$this->opt_in_to_jpeg_and_webp();

		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );

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
	public function it_should_not_replace_the_references_to_a_jpg_image_when_disabled_via_filter( $image_path ): void {
		remove_all_filters( 'webp_uploads_content_image_mimes' );

		add_filter(
			'webp_uploads_content_image_mimes',
			static function ( $mime_types ) {
				unset( $mime_types[ array_search( 'image/webp', $mime_types, true ) ] );
				return $mime_types;
			}
		);

		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );
		$tag           = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => "wp-image-{$attachment_id}" ) );

		$this->assertSame( $tag, webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
	}

	public function provider_replace_images_with_different_extensions() {
		yield 'An image with a .jpg extension' => array( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );
		yield 'An image with a .jpeg extension' => array( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );
	}

	/**
	 * Replace all the images including the full size image
	 *
	 * @test
	 */
	public function it_should_replace_all_the_images_including_the_full_size_image(): void {
		// Create JPEG and WebP to check replacement of JPEG => WebP.
		$this->opt_in_to_jpeg_and_webp();

		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg'
		);

		$tag = wp_get_attachment_image( $attachment_id, 'full', false, array( 'class' => "wp-image-{$attachment_id}" ) );

		$expected = array(
			'ext'  => 'jpg',
			'type' => 'image/jpeg',
		);
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertSame( $expected, wp_check_filetype( get_attached_file( $attachment_id ) ) );
		$this->assertStringNotContainsString( wp_basename( get_attached_file( $attachment_id ) ), webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
		$this->assertStringContainsString( $metadata['sources']['image/webp']['file'], webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
	}

	/**
	 * Prevent replacing an image with no available sources
	 *
	 * @group webp_uploads_update_image_references
	 *
	 * @test
	 */
	public function it_should_prevent_replacing_an_image_with_no_available_sources(): void {
		add_filter( 'webp_uploads_upload_image_mime_transforms', '__return_empty_array' );

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );

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
	public function it_should_prevent_update_not_supported_images_with_no_available_sources( $image_path ): void {
		$attachment_id = self::factory()->attachment->create_upload_object( $image_path );

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
	public function it_should_use_the_original_image_to_generate_all_the_image_sizes(): void {
		// Use a 1500 threshold.
		add_filter(
			'big_image_size_threshold',
			static function () {
				return 1500;
			}
		);

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/paint.jpeg' );
		$metadata      = wp_get_attachment_metadata( $attachment_id );

		foreach ( $metadata['sizes'] as $size ) {
			if ( ! isset( $size['sources'] ) ) {
				continue;
			}

			$this->assertIsString( $size['sources']['image/webp']['file'] );
			$this->assertStringContainsString( (string) $size['width'], $size['sources']['image/webp']['file'] );
			$this->assertStringContainsString( (string) $size['height'], $size['sources']['image/webp']['file'] );
			$this->assertStringContainsString(
				// Remove the extension from the file.
				substr( $size['sources']['image/webp']['file'], 0, -13 ),
				$metadata['file']
			);
		}
	}

	/**
	 * Tests that we can force generating jpeg subsizes using the webp_uploads_upload_image_mime_transforms filter.
	 *
	 * @test
	 */
	public function it_should_preserve_jpeg_subsizes_using_transform_filter(): void {
		// Create JPEG and WebP.
		$this->opt_in_to_jpeg_and_webp();

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );

		$this->assertImageHasSource( $attachment_id, 'image/webp' );
		$this->assertImageHasSource( $attachment_id, 'image/jpeg' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		foreach ( array_keys( $metadata['sizes'] ) as $size_name ) {
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/webp' );
			$this->assertImageHasSizeSource( $attachment_id, $size_name, 'image/jpeg' );
		}
	}

	/**
	 * Allow the upload of a WebP image if at least one editor supports the format
	 *
	 * @test
	 */
	public function it_should_allow_the_upload_of_a_webp_image_if_at_least_one_editor_supports_the_format(): void {
		add_filter(
			'wp_image_editors',
			static function () {
				// WP core does not choose the WP_Image_Editor instance based on MIME type support,
				// therefore the one that does support WebP needs to be first in this list.
				return array( 'WP_Image_Editor_GD', 'WP_Image_Doesnt_Support_WebP' );
			}
		);

		$this->assertTrue( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) );

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );
		$metadata      = wp_get_attachment_metadata( $attachment_id );

		$this->assertArrayHasKey( 'sources', $metadata );
		$this->assertIsArray( $metadata['sources'] );

		$this->assertImageNotHasSource( $attachment_id, 'image/jpeg' );
		$this->assertImageHasSource( $attachment_id, 'image/webp' );

		$this->assertImageNotHasSizeSource( $attachment_id, 'thumbnail', 'image/jpeg' );
		$this->assertImageHasSizeSource( $attachment_id, 'thumbnail', 'image/webp' );
	}

	/**
	 * Replace the featured image to WebP when requesting the featured image
	 *
	 * @test
	 */
	public function it_should_replace_the_featured_image_to_webp_when_requesting_the_featured_image(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/paint.jpeg' );
		$post_id       = self::factory()->post->create();
		set_post_thumbnail( $post_id, $attachment_id );

		$featured_image = get_the_post_thumbnail( $post_id );
		$this->assertMatchesRegularExpression( '/<img .*?src=".*?\.webp".*>/', $featured_image );
	}

	/**
	 * Prevent replacing an image if image was uploaded via external source or plugin.
	 *
	 * @group webp_uploads_update_image_references
	 *
	 * @test
	 */
	public function it_should_prevent_replacing_an_image_uploaded_via_external_source(): void {
		remove_all_filters( 'webp_uploads_pre_replace_additional_image_source' );

		add_filter(
			'webp_uploads_pre_replace_additional_image_source',
			static function () {
				return '<img src="https://ia600200.us.archive.org/16/items/SPD-SLRSY-1867/hubblesite_2001_06.jpg">';
			}
		);

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );

		$tag = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => "wp-image-{$attachment_id}" ) );
		$this->assertNotSame( $tag, webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
	}

	/**
	 * The image with the smaller filesize should be used when webp_uploads_discard_larger_generated_images is set to true.
	 *
	 * @test
	 */
	public function it_should_create_webp_when_webp_is_smaller_than_jpegs(): void {
		// Create JPEG and WebP.
		$this->opt_in_to_jpeg_and_webp();

		add_filter( 'webp_uploads_discard_larger_generated_images', '__return_true' );

		// Look for an image that contains all of the additional mime type images.
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );
		$tag           = wp_get_attachment_image( $attachment_id, 'full', false, array( 'class' => "wp-image-{$attachment_id}" ) );
		$expected_tag  = $tag;
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		$file          = get_attached_file( $attachment_id, true );
		$dirname       = pathinfo( $file, PATHINFO_DIRNAME );
		$result        = webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id );
		$this->assertImageHasSource( $attachment_id, 'image/webp' );
		$this->assertImageHasSizeSource( $attachment_id, 'thumbnail', 'image/webp' );

		$this->assertNotSame( $tag, $result );

		$this->assertImageHasSource( $attachment_id, 'image/webp' );

		foreach ( $metadata['sizes'] as $size => $properties ) {
			$this->assertFileExists( path_join( $dirname, $properties['sources']['image/webp']['file'] ) );
			$expected_tag = str_replace( $properties['sources']['image/jpeg']['file'], $properties['sources']['image/webp']['file'], $expected_tag );
		}

		$this->assertFileExists( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) );
		$expected_tag = str_replace( $metadata['sources']['image/jpeg']['file'], $metadata['sources']['image/webp']['file'], $expected_tag );

		$this->assertNotEmpty( $expected_tag );
		$this->assertNotSame( $tag, $expected_tag );
		$this->assertSame( $expected_tag, $result );
	}

	/**
	 * The image with the smaller filesize should be used when webp_uploads_discard_larger_generated_images is set to true.
	 *
	 * @test
	 */
	public function it_should_create_webp_for_full_size_which_is_smaller_in_webp_format(): void {
		// Create JPEG and WebP.
		$this->opt_in_to_jpeg_and_webp();

		add_filter( 'webp_uploads_discard_larger_generated_images', '__return_true' );
		add_filter( 'wp_editor_set_quality', array( $this, 'force_webp_image_quality_86' ), PHP_INT_MAX, 2 );

		// Look for an image that contains only full size mime type images.
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );
		$tag           = wp_get_attachment_image( $attachment_id, 'full', false, array( 'class' => "wp-image-{$attachment_id}" ) );
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		$file          = get_attached_file( $attachment_id, true );
		$dirname       = pathinfo( $file, PATHINFO_DIRNAME );
		$this->assertImageHasSource( $attachment_id, 'image/webp' );
		$this->assertFileExists( path_join( $dirname, $metadata['sources']['image/webp']['file'] ) );

		foreach ( $metadata['sizes'] as $size => $properties ) {
			$this->assertImageNotHasSizeSource( $attachment_id, $size, 'image/webp' );
		}
		$this->assertNotSame( $tag, webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
	}

	/**
	 * The image with the smaller filesize should be used when webp_uploads_discard_larger_generated_images is set to true.
	 *
	 * @test
	 */
	public function it_should_create_webp_for_some_sizes_which_are_smaller_in_webp_format(): void {
		// Create JPEG and WebP.
		$this->opt_in_to_jpeg_and_webp();

		add_filter( 'webp_uploads_discard_larger_generated_images', '__return_true' );
		add_filter( 'wp_editor_set_quality', array( $this, 'force_webp_image_quality_86' ), PHP_INT_MAX, 2 );

		// Look for an image that contains all of the additional mime type images.
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/balloons.webp' );
		$tag           = wp_get_attachment_image( $attachment_id, 'full', false, array( 'class' => "wp-image-{$attachment_id}" ) );
		$expected_tag  = $tag;
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		$file          = get_attached_file( $attachment_id, true );
		$dirname       = pathinfo( $file, PATHINFO_DIRNAME );
		$updated_tag   = webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id );

		$this->assertFileExists( path_join( $dirname, $metadata['sizes']['medium']['sources']['image/jpeg']['file'] ) );
		$this->assertFileExists( path_join( $dirname, $metadata['sizes']['thumbnail']['sources']['image/jpeg']['file'] ) );
		$this->assertImageNotHasSource( $attachment_id, 'image/jpeg' );
		$this->assertImageHasSizeSource( $attachment_id, 'thumbnail', 'image/jpeg' );

		$expected_tag = str_replace( $metadata['sizes']['medium']['sources']['image/webp']['file'], $metadata['sizes']['medium']['sources']['image/jpeg']['file'], $expected_tag );
		$expected_tag = str_replace( $metadata['sizes']['thumbnail']['sources']['image/webp']['file'], $metadata['sizes']['medium']['sources']['image/jpeg']['file'], $expected_tag );

		$this->assertNotSame( $tag, $updated_tag );
		$this->assertSame( $expected_tag, $updated_tag );
	}

	/**
	 * Tests that the fallback script is added when a post with updated images is rendered.
	 *
	 * @test
	 */
	public function it_should_add_fallback_script_if_content_has_updated_images(): void {
		// Create JPEG and WebP to allow for fallback script.
		$this->opt_in_to_jpeg_and_webp();

		remove_all_actions( 'wp_footer' );

		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg'
		);

		// Run critical hooks to satisfy webp_uploads_in_frontend_body() conditions.
		$this->mock_frontend_body_hooks();

		apply_filters(
			'the_content',
			sprintf(
				'<p>before image</p>%s<p>after image</p>',
				wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => "wp-image-{$attachment_id}" ) )
			)
		);

		$this->assertTrue( has_action( 'wp_footer', 'webp_uploads_wepb_fallback' ) === 10 );

		$footer = get_echo( 'wp_footer' );
		$this->assertStringContainsString( 'data:image/webp;base64,UklGR', $footer );
	}

	/**
	 * Tests that the fallback script is not added when a post with no updated images is rendered.
	 *
	 * @test
	 */
	public function it_should_not_add_fallback_script_if_content_has_no_updated_images(): void {
		remove_all_actions( 'wp_footer' );

		apply_filters( 'the_content', '<p>no image</p>' );

		$this->assertFalse( has_action( 'wp_footer', 'webp_uploads_wepb_fallback' ) );

		$footer = get_echo( 'wp_footer' );
		$this->assertStringNotContainsString( 'data:image/webp;base64,UklGR', $footer );
	}

	/**
	 * Tests whether additional mime types generated only for allowed image sizes or not when the filter is used.
	 *
	 * @test
	 */
	public function it_should_create_mime_types_for_allowed_sizes_only_via_filter(): void {
		add_filter(
			'webp_uploads_image_sizes_with_additional_mime_type_support',
			static function ( $sizes ) {
				$sizes['allowed_size_400x300'] = true;
				return $sizes;
			}
		);

		add_image_size( 'allowed_size_400x300', 400, 300, true );
		add_image_size( 'not_allowed_size_200x150', 200, 150, true );

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );

		remove_image_size( 'allowed_size_400x300' );
		remove_image_size( 'not_allowed_size_200x150' );

		$this->assertImageHasSizeSource( $attachment_id, 'allowed_size_400x300', 'image/webp' );
		$this->assertImageNotHasSizeSource( $attachment_id, 'not_allowed_size_200x150', 'image/webp' );
	}

	/**
	 * Tests whether additional mime types generated only for allowed image sizes or not when the global variable is updated.
	 *
	 * @test
	 */
	public function it_should_create_mime_types_for_allowed_sizes_only_via_global_variable(): void {
		add_image_size( 'allowed_size_400x300', 400, 300, true );
		add_image_size( 'not_allowed_size_200x150', 200, 150, true );

		// TODO: This property should later be set via a new parameter on add_image_size().
		$GLOBALS['_wp_additional_image_sizes']['allowed_size_400x300']['provide_additional_mime_types'] = true;

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );

		remove_image_size( 'allowed_size_400x300' );
		remove_image_size( 'not_allowed_size_200x150' );

		$this->assertImageHasSizeSource( $attachment_id, 'allowed_size_400x300', 'image/webp' );
		$this->assertImageNotHasSizeSource( $attachment_id, 'not_allowed_size_200x150', 'image/webp' );
	}

	/**
	 * Test image quality for image conversion.
	 *
	 * @test
	 */
	public function it_should_set_quality_with_image_conversion(): void {
		// Temporary file path.
		$file = $this->temp_filename();

		$editor = wp_get_image_editor( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dice.png', array( 'mime_type' => 'image/png' ) );

		// Quality setting for the source image. For PNG the fallback default of 82 is used.
		$this->assertSame( 82, $editor->get_quality(), 'Default quality setting for PNG is 82.' );

		// A PNG image will be converted to WebP whose quality should be 82 universally.
		$editor->save( $file, 'image/webp' );
		$this->assertSame( 82, $editor->get_quality(), 'Output image format is WebP. Quality setting for it should be 82 universally.' );

		$editor = wp_get_image_editor( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );

		// Quality setting for the source image. For JPG the fallback default of 82 is used.
		$this->assertSame( 82, $editor->get_quality(), 'Default quality setting for JPG is 82.' );

		// A JPG image will be converted to WebP whose quality should be 82 universally.
		$editor->save( $file, 'image/webp' );
		$this->assertSame( 82, $editor->get_quality(), 'Output image format is WebP. Quality setting for it should be 82 universally.' );
	}

	/**
	 * Test webp_uploads_modify_webp_quality function for image quality.
	 *
	 * @covers ::webp_uploads_modify_webp_quality
	 *
	 * @test
	 */
	public function it_should_return_correct_quality_for_mime_types(): void {
		global $wp_version;
		$this->assertSame( 82, webp_uploads_modify_webp_quality( 90, 'image/webp' ), 'WebP image quality should always be 82.' );
		$this->assertSame( 82, webp_uploads_modify_webp_quality( 82, 'image/webp' ), 'WebP image quality should always be 82.' );
		$this->assertSame( 80, webp_uploads_modify_webp_quality( 80, 'image/jpeg' ), 'JPEG image quality should return default quality provided from WP filter wp_editor_set_quality.' );
	}

	/**
	 * Test printing the meta generator tag.
	 *
	 * @covers ::webp_uploads_render_generator
	 */
	public function test_webp_uploads_render_generator(): void {
		$tag = get_echo( 'webp_uploads_render_generator' );
		$this->assertStringStartsWith( '<meta', $tag );
		$this->assertStringContainsString( 'generator', $tag );
		$this->assertStringContainsString( 'webp-uploads ' . WEBP_UPLOADS_VERSION, $tag );
	}

	/**
	 * Runs (empty) hooks to satisfy webp_uploads_in_frontend_body() conditions.
	 */
	private function mock_frontend_body_hooks(): void {
		remove_all_actions( 'template_redirect' );
		do_action( 'template_redirect' );
	}

	/**
	 * Force return WebP image quality 86 for testing.
	 */
	public function force_webp_image_quality_86( $quality, $mime_type ) {
		if ( 'image/webp' === $mime_type ) {
			return 86;
		}
		return $quality;
	}

	/**
	 * Get temporary file name.
	 *
	 * @return string Temp File name.
	 */
	public function temp_filename(): string {
		$filename = wp_tempnam();

		// Store filename to unlink it later in tear down.
		$this->to_unlink[] = $filename;

		return $filename;
	}
}
