<?php
/**
 * Tests for webp-uploads module helper.php.
 *
 * @package performance-lab
 * @group   webp-uploads
 */

use PerformanceLab\Tests\TestCase\ImagesTestCase;

class WebP_Uploads_Helper_Tests extends ImagesTestCase {

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
			'medium',
			array(),
			'image/webp',
		);

		add_filter( 'wp_image_editors', '__return_empty_array' );
		yield 'when no editor is present' => array(
			self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' ),
			'medium',
			array(),
			'image/avif',
		);

		remove_filter( 'wp_image_editors', '__return_empty_array' );
		yield 'when using a mime that is not supported' => array(
			self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' ),
			'medium',
			array(),
			'image/avif',
		);

		yield 'when no dimension is provided' => array(
			self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' ),
			'medium',
			array(),
			'image/webp',
		);

		yield 'when both dimensions are negative numbers' => array(
			self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' ),
			'medium',
			array(
				'width'  => -10,
				'height' => -20,
			),
			'image/webp',
		);

		yield 'when both dimensions are zero' => array(
			self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' ),
			'medium',
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
		// Create JPEG and WebP so that both versions are generated.
		$this->opt_in_to_jpeg_and_webp();

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );
		$size_data     = array(
			'width'  => 300,
			'height' => 300,
			'crop'   => true,
		);

		$result    = webp_uploads_generate_additional_image_source( $attachment_id, 'medium', $size_data, 'image/webp' );
		$file      = get_attached_file( $attachment_id );
		$directory = trailingslashit( pathinfo( $file, PATHINFO_DIRNAME ) );
		$name      = pathinfo( $file, PATHINFO_FILENAME );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'filesize', $result );
		$this->assertArrayHasKey( 'file', $result );
		$this->assertStringEndsWith( '300x300-jpeg.webp', $result['file'] );
		$this->assertFileExists( "{$directory}{$name}-300x300-jpeg.webp" );
	}

	/**
	 * Create a file in the specified location with the specified name
	 *
	 * @test
	 */
	public function it_should_create_a_file_in_the_specified_location_with_the_specified_name() {
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );
		$size_data     = array(
			'width'  => 300,
			'height' => 300,
			'crop'   => true,
		);

		$result = webp_uploads_generate_additional_image_source( $attachment_id, 'medium', $size_data, 'image/webp', '/tmp/image.jpg' );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'filesize', $result );
		$this->assertArrayHasKey( 'file', $result );
		$this->assertStringEndsWith( 'image.webp', $result['file'] );
		$this->assertFileExists( '/tmp/image.webp' );
	}

	/**
	 * Prevent processing an image with corrupted metadata
	 *
	 * @dataProvider provider_with_modified_metadata
	 *
	 * @test
	 */
	public function it_should_prevent_processing_an_image_with_corrupted_metadata( callable $callback, $size ) {
		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/balloons.webp'
		);
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		wp_update_attachment_metadata( $attachment_id, $callback( $metadata ) );
		$result = webp_uploads_generate_image_size( $attachment_id, $size, 'image/webp' );

		$this->assertWPError( $result );
		$this->assertSame( 'image_mime_type_invalid_metadata', $result->get_error_code() );
	}

	public function provider_with_modified_metadata() {
		yield 'using a size that does not exists' => array(
			static function ( $metadata ) {
				return $metadata;
			},
			'not-existing-size',
		);

		yield 'removing an existing metadata simulating that the image size still does not exists' => array(
			static function ( $metadata ) {
				unset( $metadata['sizes']['medium'] );

				return $metadata;
			},
			'medium',
		);

		yield 'when the specified size is not a valid array' => array(
			static function ( $metadata ) {
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
		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);
		$file          = get_attached_file( $attachment_id );
		$original_file = wp_get_original_image_path( $attachment_id );

		$this->assertFileExists( $file );
		$this->assertFileExists( $original_file );
		wp_delete_file( $file );
		wp_delete_file( $original_file );
		$this->assertFileDoesNotExist( $file );
		$this->assertFileDoesNotExist( $original_file );

		$result = webp_uploads_generate_image_size( $attachment_id, 'medium', 'image/webp' );
		$this->assertWPError( $result );
		$this->assertSame( 'original_image_file_not_found', $result->get_error_code() );
	}

	/**
	 * Prevent to create a subsize if the image editor does not exists
	 *
	 * @test
	 */
	public function it_should_prevent_to_create_a_subsize_if_the_image_editor_does_not_exists() {
		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		// Make sure no editor is available.
		add_filter( 'wp_image_editors', '__return_empty_array' );
		$result = webp_uploads_generate_image_size( $attachment_id, 'medium', 'image/webp' );
		$this->assertWPError( $result );
		$this->assertSame( 'image_mime_type_not_supported', $result->get_error_code() );
	}

	/**
	 * Prevent to upload a mime that is not supported by WordPress
	 *
	 * @test
	 */
	public function it_should_prevent_to_upload_a_mime_that_is_not_supported_by_wordpress() {
		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);
		$result        = webp_uploads_generate_image_size( $attachment_id, 'medium', 'image/avif' );
		$this->assertWPError( $result );
		$this->assertSame( 'image_mime_type_invalid', $result->get_error_code() );
	}

	/**
	 * Prevent to process an image when the editor does not support the format
	 *
	 * @test
	 */
	public function it_should_prevent_to_process_an_image_when_the_editor_does_not_support_the_format() {
		// Make sure no editor is available.
		$attachment_id = self::factory()->attachment->create_upload_object(
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg'
		);

		add_filter(
			'wp_image_editors',
			static function () {
				return array( 'WP_Image_Doesnt_Support_WebP' );
			}
		);

		$result = webp_uploads_generate_image_size( $attachment_id, 'medium', 'image/webp' );
		$this->assertWPError( $result );
		$this->assertSame( 'image_mime_type_not_supported', $result->get_error_code() );
	}

	/**
	 * Create an image with the filter webp_uploads_pre_generate_additional_image_source added.
	 *
	 * @test
	 */
	public function it_should_create_an_image_with_filter_webp_uploads_pre_generate_additional_image_source() {
		remove_all_filters( 'webp_uploads_pre_generate_additional_image_source' );

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );

		add_filter(
			'webp_uploads_pre_generate_additional_image_source',
			static function () {
				return array(
					'file' => 'image.webp',
					'path' => '/tmp/image.webp',
				);
			}
		);

		$size_data = array(
			'width'  => 300,
			'height' => 300,
			'crop'   => true,
		);

		$result = webp_uploads_generate_additional_image_source( $attachment_id, 'medium', $size_data, 'image/webp', '/tmp/image.jpg' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'filesize', $result );
		$this->assertArrayHasKey( 'file', $result );
		$this->assertStringEndsWith( 'image.webp', $result['file'] );
	}

	/**
	 * Tests the webp_uploads_pre_generate_additional_image_source filter returning filesize property.
	 *
	 * @test
	 */
	public function it_should_use_filesize_when_filter_webp_uploads_pre_generate_additional_image_source_returns_filesize() {
		remove_all_filters( 'webp_uploads_pre_generate_additional_image_source' );

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );

		add_filter(
			'webp_uploads_pre_generate_additional_image_source',
			static function () {
				return array(
					'file'     => 'image.webp',
					'filesize' => 777,
				);
			}
		);

		$size_data = array(
			'width'  => 300,
			'height' => 300,
			'crop'   => true,
		);

		$result = webp_uploads_generate_additional_image_source( $attachment_id, 'medium', $size_data, 'image/webp', '/tmp/image.jpg' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'filesize', $result );
		$this->assertEquals( 777, $result['filesize'] );
		$this->assertArrayHasKey( 'file', $result );
		$this->assertStringEndsWith( 'image.webp', $result['file'] );
	}

	/**
	 * Return an error when filter webp_uploads_pre_generate_additional_image_source returns WP_Error.
	 *
	 * @test
	 */
	public function it_should_return_an_error_when_filter_webp_uploads_pre_generate_additional_image_source_returns_wp_error() {
		remove_all_filters( 'webp_uploads_pre_generate_additional_image_source' );

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/car.jpeg' );

		add_filter(
			'webp_uploads_pre_generate_additional_image_source',
			static function () {
				return new WP_Error( 'image_additional_generated_error', __( 'Additional image was not generated.', 'performance-lab' ) );
			}
		);

		$size_data = array(
			'width'  => 300,
			'height' => 300,
			'crop'   => true,
		);

		$result = webp_uploads_generate_additional_image_source( $attachment_id, 'medium', $size_data, 'image/webp', '/tmp/image.jpg' );
		$this->assertWPError( $result );
		$this->assertSame( 'image_additional_generated_error', $result->get_error_code() );
	}

	/**
	 * Returns an empty array when the overwritten with empty array by webp_uploads_upload_image_mime_transforms filter.
	 *
	 * @test
	 */
	public function it_should_return_empty_array_when_filter_returns_empty_array() {
		add_filter( 'webp_uploads_upload_image_mime_transforms', '__return_empty_array' );

		$transforms = webp_uploads_get_upload_image_mime_transforms();

		$this->assertIsArray( $transforms );
		$this->assertSame( array(), $transforms );
	}

	/**
	 * Returns default transforms when the overwritten with non array type by webp_uploads_upload_image_mime_transforms filter.
	 *
	 * @test
	 */
	public function it_should_return_default_transforms_when_filter_returns_non_array_type() {
		/** @phpstan-ignore-next-line */
		add_filter( 'webp_uploads_upload_image_mime_transforms', '__return_null' );

		$default_transforms = array(
			'image/jpeg' => array( 'image/webp' ),
			'image/webp' => array( 'image/webp' ),
		);

		$transforms = webp_uploads_get_upload_image_mime_transforms();

		$this->assertIsArray( $transforms );
		$this->assertSame( $default_transforms, $transforms );
	}

	/**
	 * Returns transforms array with fallback to original mime with invalid transforms array.
	 *
	 * @test
	 */
	public function it_should_return_fallback_transforms_when_overwritten_invalid_transforms() {
		add_filter(
			'webp_uploads_upload_image_mime_transforms',
			static function () {
				return array( 'image/jpeg' => array() );
			}
		);

		$transforms = webp_uploads_get_upload_image_mime_transforms();

		$this->assertIsArray( $transforms );
		$this->assertSame( array( 'image/jpeg' => array( 'image/jpeg' ) ), $transforms );
	}

	/**
	 * Returns custom transforms array when overwritten by webp_uploads_upload_image_mime_transforms filter.
	 *
	 * @test
	 */
	public function it_should_return_custom_transforms_when_overwritten_by_filter() {
		add_filter(
			'webp_uploads_upload_image_mime_transforms',
			static function () {
				return array( 'image/jpeg' => array( 'image/jpeg', 'image/webp' ) );
			}
		);

		$transforms = webp_uploads_get_upload_image_mime_transforms();

		$this->assertIsArray( $transforms );
		$this->assertSame( array( 'image/jpeg' => array( 'image/jpeg', 'image/webp' ) ), $transforms );
	}

	/**
	 * Returns JPG and WebP transforms array when perflab_generate_webp_and_jpeg option is true.
	 *
	 * @test
	 */
	public function it_should_return_jpeg_and_webp_transforms_when_option_generate_webp_and_jpeg_set() {
		remove_all_filters( 'webp_uploads_get_upload_image_mime_transforms' );

		update_option( 'perflab_generate_webp_and_jpeg', true );

		$transforms = webp_uploads_get_upload_image_mime_transforms();

		$this->assertIsArray( $transforms );
		$this->assertSame(
			array(
				'image/jpeg' => array( 'image/jpeg', 'image/webp' ),
				'image/webp' => array( 'image/webp', 'image/jpeg' ),
			),
			$transforms
		);
	}

	/**
	 * @dataProvider data_provider_image_filesize
	 *
	 * @test
	 */
	public function it_should_discard_additional_image_if_larger_than_the_original_image( $original_filesize, $additional_filesize, $expected_status ) {
		add_filter( 'webp_uploads_discard_larger_generated_images', '__return_true' );

		$output = webp_uploads_should_discard_additional_image_file( $original_filesize, $additional_filesize );
		$this->assertSame( $output, $expected_status );
	}

	public function data_provider_image_filesize() {
		return array(
			array(
				array( 'filesize' => 120101 ),
				array( 'filesize' => 100101 ),
				false,
			),
			array(
				array( 'filesize' => 100101 ),
				array( 'filesize' => 120101 ),
				true,
			),
			array(
				array( 'filesize' => 10101 ),
				array( 'filesize' => 10101 ),
				true,
			),
		);
	}

	/**
	 * @dataProvider data_provider_image_filesize
	 *
	 * @test
	 */
	public function it_should_never_discard_additional_image_if_filter_is_false( $original_filesize, $additional_filesize ) {
		add_filter( 'webp_uploads_discard_larger_generated_images', '__return_false' );

		$output = webp_uploads_should_discard_additional_image_file( $original_filesize, $additional_filesize );
		$this->assertFalse( $output );
	}

	public function test_webp_uploads_in_frontend_body_without_wp_query() {
		unset( $GLOBALS['wp_query'] );

		$this->assertFalse( webp_uploads_in_frontend_body() );
	}

	public function test_webp_uploads_in_frontend_body_with_feed() {
		$this->mock_empty_action( 'template_redirect' );
		$GLOBALS['wp_query']->is_feed = true;

		$this->assertFalse( webp_uploads_in_frontend_body() );
	}

	public function test_webp_uploads_in_frontend_body_without_template_redirect() {
		$this->assertFalse( webp_uploads_in_frontend_body() );
	}

	public function test_webp_uploads_in_frontend_body_before_template_redirect() {
		$result = webp_uploads_in_frontend_body();
		$this->mock_empty_action( 'template_redirect' );

		$this->assertFalse( $result );
	}

	public function test_webp_uploads_in_frontend_body_after_template_redirect() {
		$this->mock_empty_action( 'template_redirect' );
		$result = webp_uploads_in_frontend_body();

		$this->assertTrue( $result );
	}

	public function test_webp_uploads_in_frontend_body_within_wp_head() {
		$this->mock_empty_action( 'template_redirect' );

		// Call function within a 'wp_head' callback.
		remove_all_actions( 'wp_head' );
		$result = null;
		add_action(
			'wp_head',
			static function () use ( &$result ) {
				$result = webp_uploads_in_frontend_body();
			}
		);
		do_action( 'wp_head' );

		$this->assertFalse( $result );
	}

	private function mock_empty_action( $action ) {
		remove_all_actions( $action );
		do_action( $action );
	}

	/**
	 * Add the original image's extension to the WebP file name to ensure it is unique
	 *
	 * @dataProvider data_provider_same_image_name
	 *
	 * @test
	 */
	public function it_should_add_original_image_extension_to_the_webp_file_name_to_ensure_it_is_unique( $jpeg_image, $jpg_image ) {
		$jpeg_image_attachment_id = self::factory()->attachment->create_upload_object( $jpeg_image );
		$jpg_image_attachment_id  = self::factory()->attachment->create_upload_object( $jpg_image );

		$size_data = array(
			'width'  => 300,
			'height' => 300,
			'crop'   => true,
		);

		$jpeg_image_result = webp_uploads_generate_additional_image_source( $jpeg_image_attachment_id, 'medium', $size_data, 'image/webp' );
		$jpg_image_result  = webp_uploads_generate_additional_image_source( $jpg_image_attachment_id, 'medium', $size_data, 'image/webp' );

		$this->assertIsArray( $jpeg_image_result );
		$this->assertIsArray( $jpg_image_result );
		$this->assertStringEndsWith( '300x300-jpeg.webp', $jpeg_image_result['file'] );
		$this->assertStringEndsWith( '300x300-jpg.webp', $jpg_image_result['file'] );
		$this->assertNotSame( $jpeg_image_result['file'], $jpg_image_result['file'] );
	}

	public function data_provider_same_image_name() {
		return array(
			array(
				TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/image.jpeg',
				TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/image.jpg',
			),
		);
	}
}
