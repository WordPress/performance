<?php
/**
 * Tests for webp-uploads plugin rest-api.php.
 *
 * @package webp-uploads
 */

class Test_WebP_Uploads_REST_API extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		add_filter( 'webp_uploads_discard_larger_generated_images', '__return_false' );
	}

	/**
	 * Checks whether the sources information is added to image sizes details of the REST response object.
	 *
	 * @covers ::webp_uploads_update_rest_attachment
	 */
	public function test_it_should_add_sources_to_rest_response(): void {
		remove_all_filters( 'webp_uploads_upload_image_mime_transforms' );

		add_filter(
			'webp_uploads_upload_image_mime_transforms',
			static function ( $transforms ) {
				$transforms['image/jpeg'] = array( 'image/jpeg', 'image/webp' );
				return $transforms;
			}
		);

		$file_location = TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg';
		$attachment_id = self::factory()->attachment->create_upload_object( $file_location );
		$metadata      = wp_get_attachment_metadata( $attachment_id );

		$request = new WP_REST_Request();
		$request->set_param( 'id', $attachment_id );

		$controller = new WP_REST_Attachments_Controller( 'attachment' );
		$response   = $controller->get_item( $request );

		$this->assertNotWPError( $response );

		$data       = $response->get_data();
		$mime_types = array( 'image/jpeg' );

		if ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			array_push( $mime_types, 'image/webp' );
		}

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

		$this->assertArrayNotHasKey( 'sources', $data['media_details'] );
	}

	/**
	 * Checks whether the media details information is added to the REST response object.
	 *
	 * @covers ::webp_uploads_update_rest_attachment
	 */
	public function test_it_should_check_media_details_in_rest_response(): void {
		$file_location = TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg';
		$attachment_id = self::factory()->attachment->create_upload_object( $file_location );

		$request = new WP_REST_Request();
		$request->set_param( 'id', $attachment_id );

		$controller = new WP_REST_Attachments_Controller( 'attachment' );
		$response   = $controller->get_item( $request );

		$this->assertNotWPError( $response );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'media_details', $data );
		$this->assertIsArray( $data['media_details'] );

		// Delete attachment metadata to set media_details as object in response.
		delete_post_meta( $attachment_id, '_wp_attachment_metadata' );

		$response = $controller->get_item( $request );

		$this->assertNotWPError( $response );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'media_details', $data );
		$this->assertIsNotArray( $data['media_details'] );
		$this->assertIsObject( $data['media_details'] );
	}

	/**
	 * Checks that an image size is skipped when its `source_url` is missing.
	 *
	 * @covers ::webp_uploads_update_rest_attachment
	 */
	public function test_it_should_skip_size_when_source_url_is_missing(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
		$post          = get_post( $attachment_id );
		$this->assertInstanceOf( WP_Post::class, $post );

		$response = new WP_REST_Response(
			array(
				'media_details' => array(
					'sizes' => array(
						'large' => array(
							// Note: 'source_url' is intentionally omitted.
							'sources' => array(
								'image/webp' => array( 'file' => 'leaves-large.webp' ),
							),
						),
					),
				),
			)
		);

		$data = webp_uploads_update_rest_attachment( $response, $post )->get_data();

		// The size was skipped, so no `source_url` was written into the mime source.
		$this->assertArrayNotHasKey( 'source_url', $data['media_details']['sizes']['large']['sources']['image/webp'] );
	}

	/**
	 * Checks that an image size is skipped when its `source_url` is not a string.
	 *
	 * @covers ::webp_uploads_update_rest_attachment
	 */
	public function test_it_should_skip_size_when_source_url_is_not_a_string(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
		$post          = get_post( $attachment_id );
		$this->assertInstanceOf( WP_Post::class, $post );

		$response = new WP_REST_Response(
			array(
				'media_details' => array(
					'sizes' => array(
						'large' => array(
							'source_url' => 12345, // Not a string.
							'sources'    => array(
								'image/webp' => array( 'file' => 'leaves-large.webp' ),
							),
						),
					),
				),
			)
		);

		$data = webp_uploads_update_rest_attachment( $response, $post )->get_data();

		// The size was skipped, so no `source_url` was written into the mime source.
		$this->assertArrayNotHasKey( 'source_url', $data['media_details']['sizes']['large']['sources']['image/webp'] );
	}

	/**
	 * Checks that a mime source is skipped when its `file` is missing or invalid, without affecting valid sources.
	 *
	 * @covers ::webp_uploads_update_rest_attachment
	 */
	public function test_it_should_skip_size_source_when_file_is_missing_or_invalid(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
		$post          = get_post( $attachment_id );
		$this->assertInstanceOf( WP_Post::class, $post );

		$response = new WP_REST_Response(
			array(
				'media_details' => array(
					'sizes' => array(
						'large' => array(
							'source_url' => 'https://example.com/wp-content/uploads/leaves-large.jpg',
							'sources'    => array(
								'image/webp' => array( 'file' => 'leaves-large.webp' ), // Valid: rewritten.
								'image/avif' => array(),                                 // Missing 'file': skipped.
								'image/gif'  => 'not-an-array',                          // Not an array: skipped.
							),
						),
					),
				),
			)
		);

		$data    = webp_uploads_update_rest_attachment( $response, $post )->get_data();
		$sources = $data['media_details']['sizes']['large']['sources'];

		$this->assertSame( 'https://example.com/wp-content/uploads/leaves-large.webp', $sources['image/webp']['source_url'] );
		$this->assertArrayNotHasKey( 'source_url', $sources['image/avif'] );
		$this->assertSame( 'not-an-array', $sources['image/gif'] );
	}

	/**
	 * Checks that a full-size mime source is skipped when its `file` is missing or invalid, without affecting valid sources.
	 *
	 * @covers ::webp_uploads_update_rest_attachment
	 */
	public function test_it_should_skip_full_source_when_file_is_missing_or_invalid(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
		$post          = get_post( $attachment_id );
		$this->assertInstanceOf( WP_Post::class, $post );

		$response = new WP_REST_Response(
			array(
				'media_details' => array(
					'sizes'   => array(
						'full' => array(),
					),
					'sources' => array(
						'image/webp' => array( 'file' => 'leaves.webp' ), // Valid: rewritten.
						'image/avif' => array(),                          // Missing 'file': skipped.
						'image/gif'  => 'not-an-array',                   // Not an array: skipped.
					),
				),
			)
		);

		$data = webp_uploads_update_rest_attachment( $response, $post )->get_data();

		// The top-level `sources` are moved under the `full` size.
		$this->assertArrayNotHasKey( 'sources', $data['media_details'] );
		$full_sources = $data['media_details']['sizes']['full']['sources'];

		$this->assertStringEndsWith( 'leaves.webp', $full_sources['image/webp']['source_url'] );
		$this->assertArrayNotHasKey( 'source_url', $full_sources['image/avif'] );
		$this->assertSame( 'not-an-array', $full_sources['image/gif'] );
	}

	/**
	 * Checks that the response is returned unchanged when its data is not an array.
	 *
	 * @covers ::webp_uploads_update_rest_attachment
	 */
	public function test_it_should_return_response_when_data_is_not_an_array(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
		$post          = get_post( $attachment_id );
		$this->assertInstanceOf( WP_Post::class, $post );

		$response = new WP_REST_Response( 'not-an-array' );

		$filtered = webp_uploads_update_rest_attachment( $response, $post );

		$this->assertSame( $response, $filtered );
		$this->assertSame( 'not-an-array', $filtered->get_data() );
	}
}
