<?php
/**
 * REST API integration for the module.
 *
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Updates the response for an attachment to include sources for additional mime types available the image.
 *
 * @since 1.0.0
 *
 * @param WP_REST_Response $response The original response object.
 * @param WP_Post          $post     The post object.
 * @param WP_REST_Request  $request  The request object.
 * @return WP_REST_Response A new response object for the attachment with additional sources.
 */
function webp_uploads_update_rest_attachment( WP_REST_Response $response, WP_Post $post, WP_REST_Request $request ) {
	$data = $response->get_data();
	if ( ! isset( $data['media_details']['sizes'] ) || ! is_array( $data['media_details']['sizes'] ) ) {
		return $response;
	}

	$metadata = wp_get_attachment_metadata( $post->ID );
	foreach ( $data['media_details']['sizes'] as $size => $details ) {
		if ( empty( $metadata['sizes'][ $size ]['sources'] ) || ! is_array( $metadata['sizes'][ $size ]['sources'] ) ) {
			continue;
		}

		$sources   = array();
		$directory = dirname( $data['media_details']['sizes'][ $size ]['source_url'] );
		foreach ( $metadata['sizes'][ $size ]['sources'] as $mime => $mime_details ) {
			$source_url                 = "{$directory}/{$mime_details['file']}";
			$mime_details['source_url'] = $source_url;
			$sources[ $mime ]           = $mime_details;
		}

		$data['media_details']['sizes'][ $size ]['sources'] = $sources;
	}

	return rest_ensure_response( $data );
}
add_filter( 'rest_prepare_attachment', 'webp_uploads_update_rest_attachment', 10, 3 );
