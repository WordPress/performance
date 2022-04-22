<?php
/**
 * Module Name: Images Generation
 * Description: Generates images on the fly for the registered image sizes and available transformations.
 * Experimental: No
 *
 * @since   n.e.x.t
 * @package performance-lab
 */

/**
 * Generates images on the fly for the registered image sizes and available transformations.
 *
 * @since n.e.x.t
 */
function generate_missing_images() {
	if ( ! is_404() ) {
		return;
	}

	$uploads_dir = wp_upload_dir();
	$baseurl     = wp_parse_url( $uploads_dir['baseurl'], PHP_URL_PATH );

	// Bail if the current request is not to an uploaded image.
	if ( substr( $_SERVER['REQUEST_URI'], 0, strlen( $baseurl ) ) !== $baseurl ) {
		return;
	}

	$matches = array();
	$relpath = str_replace( $baseurl, '', $_SERVER['REQUEST_URI'] );

	// Bail if the current request is not to a subsize.
	if ( ! preg_match( '#/(.+)-(\d+)x(\d+)\.([a-zA-Z]+)$#', $relpath, $matches ) ) {
		return;
	}

	$img_width  = intval( $matches[2] );
	$img_height = intval( $matches[3] );

	$size       = '';
	$size_names = get_intermediate_image_sizes();
	$sizes      = wp_get_additional_image_sizes();

	// Find the requested subsize name.
	foreach ( $size_names as $size_name ) {
		$width = intval(
			isset( $sizes[ $size_name ] )
				? $sizes[ $size_name ]['width']
				: get_option( "{$size_name}_size_w" )
		);

		$height = intval(
			isset( $sizes[ $size_name ] )
				? $sizes[ $size_name ]['height']
				: get_option( "{$size_name}_size_h" )
		);

		if (
			// If the current size exactly matches requested width and height.
			( $width === $img_width && $height === $img_height ) ||
			// If the current size matches the requested height and has a variable width.
			( 0 === $width && $height === $img_height ) ||
			// If the current size matches the requested width and has a variable height.
			( $width === $img_width && 0 === $height )
		) {
			$size = $size_name;

			// Stop finding the size name if the current size exactly matches requested width and height.
			// If the current size has a variable width or height, we need to continue finding in case there
			// is an exact match.
			if ( $width && $height ) {
				break;
			}
		}
	}

	// Bail if the subsize name is not found.
	if ( empty( $size ) ) {
		return;
	}

	// Bail if we can't find an attachment with requested file.
	$attachment_id = attachment_url_to_postid( "{$uploads_dir['baseurl']}/{$matches[1]}.{$matches[4]}" );
	if ( empty( $attachment_id ) ) {
		return;
	}

	require_once ABSPATH . '/wp-admin/includes/image.php';

	// Regenerate all subsizes.
	$original_file = "{$uploads_dir['basedir']}/{$matches[1]}.{$matches[4]}";
	wp_create_image_subsizes( $original_file, $attachment_id );

	$image_file = $uploads_dir['basedir'] . $relpath;

	// Send headers if headers haven't been sent yet.
	if ( ! headers_sent() ) {
		$filetype = wp_check_filetype( $image_file );
		if ( ! empty( $filetype['type'] ) ) {
			header( 'Content-type: ' . $filetype['type'] );
		}

		header( 'Content-Length: ' . filesize( $image_file ) );
		header( 'Cache-Control: public, max-age=' . 0 );
		header( 'Content-Disposition: filename=' . wp_basename( $image_file ) );
		header( 'Last-Modified: ' . gmdate( DATE_RFC2822 ) );
	}

	// Send newly generated file and exit.
	readfile( $image_file );
	exit;
}
add_action( 'template_redirect', 'generate_missing_images' );
