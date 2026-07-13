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

/**
 * Ensures the REST attachment response's `source_url` points to the original uploaded file when
 * this plugin has swapped the attachment's main file for a modern format.
 *
 * When the fallback setting is disabled, the plugin replaces the attachment's main file with the
 * generated modern-format version and backs up the true original in the `original_image` metadata.
 * `source_url` is what the Image/Gallery blocks read to build the "Link to image file" href, so
 * without this it would point to the modern-format file instead of the original upload. This is
 * intentionally scoped to the REST response only: `wp_get_attachment_url()` itself must keep
 * returning the swapped file everywhere else, since that's what the plugin's own `<img>` tag
 * rewriting relies on to show the modern format.
 *
 * @since n.e.x.t
 *
 * @param WP_REST_Response $response The response object so far.
 * @param WP_Post          $post     The attachment post object.
 * @return WP_REST_Response The response object, with `source_url` corrected where applicable.
 */
function webp_uploads_update_rest_attachment_original_source_url( WP_REST_Response $response, WP_Post $post ): WP_REST_Response {
	$data = $response->get_data();
	if ( ! is_array( $data ) || ! isset( $data['source_url'] ) || ! is_string( $data['source_url'] ) || '' === $data['source_url'] ) {
		return $response;
	}

	$metadata = wp_get_attachment_metadata( $post->ID );
	if ( ! is_array( $metadata ) || ! isset( $metadata['original_image'] ) || ! is_string( $metadata['original_image'] ) || '' === $metadata['original_image'] ) {
		return $response;
	}

	$attached_file = get_attached_file( $post->ID );
	if ( false === $attached_file ) {
		return $response;
	}

	/*
	 * Compare the mime type of the currently attached file against the backed-up original, not
	 * `get_post_mime_type()`: WordPress intentionally keeps the post's mime type as the original
	 * one for compatibility even after this plugin swaps the attached file. Core's own "-scaled"
	 * resizing keeps the same file mime type as the original; only override when this plugin
	 * actually swapped the attachment to a different mime type.
	 */
	$current_mime  = wp_check_filetype( $attached_file )['type'];
	$original_mime = wp_check_filetype( $metadata['original_image'] )['type'];
	if ( ! is_string( $current_mime ) || $original_mime === $current_mime ) {
		return $response;
	}

	/*
	 * The plugin swapped the attached file to a modern format. Oversized images that
	 * WordPress scaled to "-scaled" keep that suffix on the swapped file, and the
	 * "Link to image file" href should stay scaled in the output format. Only fall
	 * back to the backed-up original upload for non-scaled images, where the
	 * `original_image` metadata has no "-scaled" suffix.
	 */
	if ( preg_match( '/-scaled(?=\.[^.]+$)/', $attached_file ) === 1 ) {
		$target_url = wp_get_attachment_url( $post->ID );
	} else {
		$target_url = path_join( dirname( $data['source_url'] ), $metadata['original_image'] );
	}

	if ( ! is_string( $target_url ) || '' === $target_url ) {
		return $response;
	}

	$data['source_url'] = $target_url;
	if ( isset( $data['media_details']['sizes']['full']['source_url'] ) ) {
		$data['media_details']['sizes']['full']['source_url'] = $target_url;
	}

	return new WP_REST_Response( $data );
}
add_filter( 'rest_prepare_attachment', 'webp_uploads_update_rest_attachment_original_source_url', 10, 2 );
