<?php
/**
 * Tests for webp-uploads module rest-api.php.
 *
 * @package performance-lab
 * @group   webp-uploads
 */

class WebP_Uploads_REST_API_Tests extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		add_filter( 'webp_uploads_discard_larger_generated_images', '__return_false' );
	}

	/**
	 * Checks whether the sources information is added to image sizes details of the REST response object.
	 *
	 * @test
	 */
	public function it_should_add_sources_to_rest_response() {
		remove_all_filters( 'webp_uploads_upload_image_mime_transforms' );

		add_filter(
			'webp_uploads_upload_image_mime_transforms',
			function( $transforms ) {
				$transforms['image/jpeg'] = array( 'image/jpeg', 'image/webp' );
				return $transforms;
			}
		);

		$file_location = TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $file_location );
		$metadata      = wp_get_attachment_metadata( $attachment_id );

		$request       = new WP_REST_Request();
		$request['id'] = $attachment_id;

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

}
