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
			$this->assertGreaterThan(
				0,
				wp_next_scheduled(
					'webp_uploads_create_image',
					array(
						$attachment_id,
						$size_name,
						$targeted_mime,
					)
				)
			);
		}
	}

	public function provider_image_with_default_behaviors_during_upload() {
		yield 'JPEG image' => array(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg',
			'image/jpeg',
			'image/webp',
		);

		yield 'WebP image' => array(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/ballons.webp',
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

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata );
		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertArrayNotHasKey( 'sources', $properties );
			$this->assertFalse(
				wp_next_scheduled(
					'webp_uploads_create_image',
					array(
						$attachment_id,
						$size_name,
						'image/webp',
					)
				)
			);
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

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata );
		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertArrayHasKey( 'sources', $properties );
			$this->assertIsArray( $properties['sources'] );
			$this->assertArrayHasKey( 'image/jpeg', $properties['sources'] );
			$this->assertArrayHasKey( 'filesize', $properties['sources']['image/jpeg'] );
			$this->assertArrayHasKey( 'file', $properties['sources']['image/jpeg'] );
			$this->assertFalse(
				wp_next_scheduled(
					'webp_uploads_create_image',
					array(
						$attachment_id,
						$size_name,
						'image/webp',
					)
				)
			);
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

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/ballons.webp' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata );
		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertArrayNotHasKey( 'sources', $properties );
			$this->assertFalse(
				wp_next_scheduled(
					'webp_uploads_create_image',
					array(
						$attachment_id,
						$size_name,
						'image/webp',
					)
				)
			);
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
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/ballons.webp' );
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
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		$file          = get_attached_file( $attachment_id );

		$this->assertTrue( file_exists( $file ) );
		wp_delete_file( $file );
		$this->assertFalse( file_exists( $file ) );

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
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );

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
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
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
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );

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
	public function it_should_create_a_web_p_version_with_all_the_required_properties() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertArrayHasKey( 'sources', $metadata['sizes']['thumbnail'] );
		$this->assertArrayHasKey( 'image/jpeg', $metadata['sizes']['thumbnail']['sources'] );
		$this->assertArrayHasKey( 'filesize', $metadata['sizes']['thumbnail']['sources']['image/jpeg'] );
		$this->assertArrayHasKey( 'file', $metadata['sizes']['thumbnail']['sources']['image/jpeg'] );
		$this->assertArrayNotHasKey( 'image/webp', $metadata['sizes']['medium']['sources'] );

		$this->assertTrue( webp_uploads_generate_image_size( $attachment_id, 'thumbnail', 'image/webp' ) );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertArrayHasKey( 'image/webp', $metadata['sizes']['thumbnail']['sources'] );
		$this->assertArrayHasKey( 'filesize', $metadata['sizes']['thumbnail']['sources']['image/webp'] );
		$this->assertArrayHasKey( 'file', $metadata['sizes']['thumbnail']['sources']['image/webp'] );
		$file = $metadata['sizes']['thumbnail']['sources']['image/jpeg']['file'];
		$this->assertStringEndsNotWith( '.jpeg', $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] );
		$this->assertStringEndsWith( '.webp', $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] );
	}

	/**
	 * Create the sources property when the property does not exists
	 *
	 * @test
	 */
	public function it_should_create_the_sources_property_when_the_property_does_not_exists() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		unset( $metadata['sizes']['medium']['sources'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		$this->assertArrayNotHasKey( 'sources', $metadata['sizes']['medium'] );
		$this->assertTrue( webp_uploads_generate_image_size( $attachment_id, 'medium', 'image/webp' ) );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertArrayHasKey( 'sources', $metadata['sizes']['medium'] );
		$this->assertArrayNotHasKey( 'image/jpeg', $metadata['sizes']['medium']['sources'] );
		$this->assertArrayHasKey( 'image/webp', $metadata['sizes']['medium']['sources'] );
		$this->assertArrayHasKey( 'filesize', $metadata['sizes']['medium']['sources']['image/webp'] );
		$this->assertArrayHasKey( 'file', $metadata['sizes']['medium']['sources']['image/webp'] );
	}

	/**
	 * Create an image with the dimensions from the metadata instead of the dimensions of the sizes
	 *
	 * @test
	 */
	public function it_should_create_an_image_with_the_dimensions_from_the_metadata_instead_of_the_dimensions_of_the_sizes() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );

		$metadata                                 = wp_get_attachment_metadata( $attachment_id );
		$metadata['sizes']['thumbnail']['width']  = 200;
		$metadata['sizes']['thumbnail']['height'] = 200;
		wp_update_attachment_metadata( $attachment_id, $metadata );

		$this->assertTrue( webp_uploads_generate_image_size( $attachment_id, 'thumbnail', 'image/webp' ) );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertArrayHasKey( 'image/webp', $metadata['sizes']['thumbnail']['sources'] );
		$this->assertStringEndsWith( '200x200.webp', $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] );
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

		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		$this->assertStringEndsWith( '-scaled.jpg', get_attached_file( $attachment_id ) );

		$this->assertTrue( webp_uploads_generate_image_size( $attachment_id, 'medium', 'image/webp' ) );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertArrayHasKey( 'image/webp', $metadata['sizes']['medium']['sources'] );
		$this->assertStringEndsNotWith( '-scaled.webp', $metadata['sizes']['medium']['sources']['image/webp']['file'] );
		$this->assertStringEndsWith( '-300x200.webp', $metadata['sizes']['medium']['sources']['image/webp']['file'] );
	}
}
