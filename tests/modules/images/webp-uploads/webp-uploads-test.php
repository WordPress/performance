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
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/ballons.webp'
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
			TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/ballons.webp'
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
	 * Process a request where ajax is not defined
	 *
	 * @test
	 */
	public function it_should_process_a_request_where_ajax_is_not_defined() {
		add_filter( 'wp_doing_ajax', '__return_false' );
		$this->assertFalse( _webp_uploads_is_valid_ajax_for_image_sizes() );
	}

	/**
	 * Process an ajax request with no additional parameters
	 *
	 * @test
	 */
	public function it_should_process_an_ajax_request_with_no_additional_parameters() {
		// Simulate ajax request.
		add_filter( 'wp_doing_ajax', '__return_true' );
		$this->assertFalse( _webp_uploads_is_valid_ajax_for_image_sizes() );
	}

	/**
	 * Process an ajax request with invalid action
	 *
	 * @test
	 */
	public function it_should_process_an_ajax_request_with_invalid_action() {
		// Simulate ajax request.
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['action'] = 'invalid-action';
		$this->assertFalse( _webp_uploads_is_valid_ajax_for_image_sizes() );
	}

	/**
	 * Process an ajax request with only the attachment id is included in the request
	 *
	 * @test
	 */
	public function it_should_process_an_ajax_request_with_only_the_attachment_id_is_included_in_the_request() {
		// Simulate ajax request.
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['attachment_id'] = 1;
		$this->assertFalse( _webp_uploads_is_valid_ajax_for_image_sizes() );
	}

	/**
	 * Process an ajax request with invalid attachment id
	 *
	 * @test
	 */
	public function it_should_process_an_ajax_request_with_invalid_attachment_id() {
		// Simulate ajax request.
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['action'] = 'media-create-image-subsizes';
		$_REQUEST['attachment_id'] = 0;
		$this->assertFalse( _webp_uploads_is_valid_ajax_for_image_sizes() );
	}

	/**
	 * Process an ajax request with valid request parameters
	 *
	 * @test
	 */
	public function it_should_process_an_ajax_request_with_valid_request_parameters() {
		// Simulate ajax request.
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['action'] = 'media-create-image-subsizes';
		$_REQUEST['attachment_id'] = 1;
		$this->assertTrue( _webp_uploads_is_valid_ajax_for_image_sizes() );
	}

	/**
	 * Process a rest request when rest is not defined
	 *
	 * @test
	 */
	public function it_should_process_a_rest_request_when_rest_is_not_defined() {
		$this->assertFalse( _webp_uploads_is_valid_rest_for_post_process() );
	}

	/**
	 * Process a REST request when the global wp is not defined
	 *
	 * @test
	 */
	public function it_should_process_a_rest_request_when_the_global_wp_is_not_defined() {
		define('REST_REQUEST', true);
		rest_get_server()->dispatch(new WP_REST_Request());
		unset( $GLOBALS['wp'] );
		$this->assertFalse( _webp_uploads_is_valid_rest_for_post_process() );
	}

	/**
	 * should process a REST request when the rest route query variable is not set
	 *
	 * @test
	 */
	public function it_should_should_process_a_rest_request_when_the_rest_route_query_variable_is_not_set() {
		define('REST_REQUEST', true);
		$GLOBALS['wp'] = new WP();

		$this->assertFalse( _webp_uploads_is_valid_rest_for_post_process() );
	}

	/**
	 * process a REST request when the query vars is set to a different route
	 *
	 * @dataProvider provider_rest_route
	 *
	 * @test
	 */
	public function it_should_process_a_rest_request_when_the_query_vars_is_set_to_a_different_route( $route ) {
		define('REST_REQUEST', true);
		$GLOBALS['wp'] = new WP();
		$GLOBALS['wp']->query_vars['rest_route'] = $route;

		$this->assertFalse( _webp_uploads_is_valid_rest_for_post_process() );
	}

	public function provider_rest_route() {
		yield 'media route' => array( '/wp/v2/media' );
		yield 'single attachment route' => array( '/wp/v2/media/1' );
		yield 'edit attachment route' => array( '/wp/v2/media/1/edit' );
	}

	/**
	 * Process a REST request when dispatched to the right endpoint
	 *
	 * @test
	 */
	public function it_should_process_a_rest_request_when_dispatched_to_the_right_endpoint() {
		define('REST_REQUEST', true);
		$GLOBALS['wp'] = new WP();
		$GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/media/1/post-process';

		$this->assertFalse( _webp_uploads_is_valid_rest_for_post_process() );
	}

	/**
	 * Process a REST request when an action is provided
	 *
	 * @test
	 */
	public function it_should_process_a_rest_request_when_an_action_is_provided() {
		define('REST_REQUEST', true);
		$GLOBALS['wp'] = new WP();
		$GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/media/1/post-process';
		$body = wp_json_encode( array( 'action' => 'subsizes' ) );

		$this->assertFalse( _webp_uploads_is_valid_rest_for_post_process( $body ) );
	}

	/**
	 * Process a REST request with invalid JSON payload
	 *
	 * @test
	 */
	public function it_should_process_a_rest_request_with_invalid_json_payload() {
		define('REST_REQUEST', true);
		$GLOBALS['wp'] = new WP();
		$GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/media/1/post-process';
		$body = ']]]]]][[][][]';

		$this->assertFalse( _webp_uploads_is_valid_rest_for_post_process( $body ) );
	}

	/**
	 * Process a REST request when the right action is provided
	 *
	 * @test
	 */
	public function it_should_process_a_rest_request_when_the_right_action_is_provided() {
		define('REST_REQUEST', true);
		$GLOBALS['wp'] = new WP();
		$GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/media/1/post-process';
		$body = wp_json_encode( array( 'action' => 'create-image-subsizes' ) );

		$this->assertTrue( _webp_uploads_is_valid_rest_for_post_process( $body ) );
	}
}
