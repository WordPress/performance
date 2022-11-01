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
	if ( ! isset( $data['media_details'] ) || ! is_array( $data['media_details'] ) || ! isset( $data['media_details']['sizes'] ) || ! is_array( $data['media_details']['sizes'] ) ) {
		return $response;
	}

	foreach ( $data['media_details']['sizes'] as $size => &$details ) {

		if ( empty( $details['sources'] ) || ! is_array( $details['sources'] ) ) {
			continue;
		}

		$image_url_basename = wp_basename( $details['source_url'] );
		foreach ( $details['sources'] as $mime => &$mime_details ) {
			$mime_details['source_url'] = str_replace( $image_url_basename, $mime_details['file'], $details['source_url'] );
		}
	}

	$full_src = wp_get_attachment_image_src( $post->ID, 'full' );
	if ( ! empty( $full_src ) && ! empty( $data['media_details']['sources'] ) && ! empty( $data['media_details']['sizes']['full'] ) ) {
		$full_url_basename = wp_basename( $full_src[0] );
		foreach ( $data['media_details']['sources'] as $mime => &$mime_details ) {
			$mime_details['source_url'] = str_replace( $full_url_basename, $mime_details['file'], $full_src[0] );
		}

		$data['media_details']['sizes']['full']['sources'] = $data['media_details']['sources'];
		unset( $data['media_details']['sources'] );
	}

	return rest_ensure_response( $data );
}
add_filter( 'rest_prepare_attachment', 'webp_uploads_update_rest_attachment', 10, 3 );
