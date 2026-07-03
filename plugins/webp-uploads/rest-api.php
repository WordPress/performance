<?php
/**
 * REST API integration for the plugin.
 *
 * @package webp-uploads
 *
 * @since 1.0.0
 */

declare( strict_types = 1 );

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Updates the response for an attachment to include sources for additional mime types available the image.
 *
 * @since 1.0.0
 *
 * @param WP_REST_Response $response The original response object.
 * @param WP_Post          $post     The post object.
 * @return WP_REST_Response A new response object for the attachment with additional sources.
 */
function webp_uploads_update_rest_attachment( WP_REST_Response $response, WP_Post $post ): WP_REST_Response {
	$data = $response->get_data();
	if (
		! is_array( $data ) ||
		! isset( $data['media_details'] ) ||
		! is_array( $data['media_details'] ) ||
		! isset( $data['media_details']['sizes'] ) ||
		! is_array( $data['media_details']['sizes'] ) ) {
		return $response;
	}

	foreach ( $data['media_details']['sizes'] as $size => &$details ) {

		if (
			! is_array( $details ) ||
			! isset( $details['sources'], $details['source_url'] ) ||
			! is_array( $details['sources'] ) ||
			! is_string( $details['source_url'] )
		) {
			continue;
		}

		$image_url_basename = wp_basename( $details['source_url'] );
		foreach ( $details['sources'] as $mime => &$mime_details ) {
			if ( is_array( $mime_details ) && isset( $mime_details['file'] ) && is_string( $mime_details['file'] ) ) {
				$mime_details['source_url'] = str_replace( $image_url_basename, $mime_details['file'], $details['source_url'] );
			}
		}
		unset( $mime_details );
	}
	unset( $details );

	$full_src = wp_get_attachment_image_src( $post->ID, 'full' );
	if (
		isset( $full_src[0] ) &&
		isset( $data['media_details']['sources'] ) &&
		is_array( $data['media_details']['sources'] ) &&
		isset( $data['media_details']['sizes']['full'] ) &&
		is_array( $data['media_details']['sizes']['full'] )
	) {
		$full_url_basename = wp_basename( $full_src[0] );
		foreach ( $data['media_details']['sources'] as $mime => &$mime_details ) {
			if ( is_array( $mime_details ) && isset( $mime_details['file'] ) && is_string( $mime_details['file'] ) ) {
				$mime_details['source_url'] = str_replace( $full_url_basename, $mime_details['file'], $full_src[0] );
			}
		}
		unset( $mime_details );

		$data['media_details']['sizes']['full']['sources'] = $data['media_details']['sources'];
		unset( $data['media_details']['sources'] );
	}

	return new WP_REST_Response( $data );
}
add_filter( 'rest_prepare_attachment', 'webp_uploads_update_rest_attachment', 10, 2 );
