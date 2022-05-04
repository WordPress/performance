<?php
/**
 * On demand generation functions.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Generates images on the fly for the registered image sizes and available transformations.
 *
 * @since n.e.x.t
 */
function webp_uploads_generate_missing_images() {
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

		// Try to normalize dimensions if only one part matches for the current size.
		if ( $width > 0 && $img_width !== $width && $img_height === $height ) {
			$dimensions = wp_constrain_dimensions( $width, $height, 0, $img_height );
			$img_width  = $dimensions[0];
		} elseif ( $img_width === $width && $img_height !== $height && $height > 0 ) {
			$dimensions = wp_constrain_dimensions( $width, $height, $img_width, 0 );
			$img_height = $dimensions[1];
		}

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

	// Bail if we can't find attachment metadata.
	$metadata = wp_get_attachment_metadata( $attachment_id );
	if ( empty( $metadata ) ) {
		return;
	}

	require_once ABSPATH . '/wp-admin/includes/image.php';

	// Generate missing sub size.
	$metadata = _wp_make_subsizes(
		array( $size => $sizes[ $size ] ),
		"{$uploads_dir['basedir']}/{$matches[1]}.{$matches[4]}",
		$metadata,
		$attachment_id
	);

	// Generate sources.
	$metadata = webp_uploads_create_sources_property( $metadata, $attachment_id );

	// Save updated metadata.
	wp_update_attachment_metadata( $attachment_id, $metadata );

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
add_action( 'template_redirect', 'webp_uploads_generate_missing_images' );

/**
 * Generates srcset URLs for missing sizes.
 *
 * @since n.e.x.t
 *
 * @param array  $sources       One or more arrays of source data to include in the 'srcset'.
 * @param array  $size_array    An array of requested width and height values.
 * @param string $image_src     The 'src' of the image.
 * @param array  $image_meta    The image meta data as returned by 'wp_get_attachment_metadata()'.
 * @param int    $attachment_id Image attachment ID or 0.
 * @return array Srcset sources array.
 */
function webp_uploads_generate_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
	$image_src_parts = wp_parse_url( $image_src );
	$image_host      = $image_src_parts['host'];
	if ( ! empty( $image_src_parts['port'] ) ) {
		$image_host .= ':' . $image_src_parts['port'];
	}

	// Do nothing if images are loaded from a different origin.
	if ( $image_host !== $_SERVER['HTTP_HOST'] ) {
		return $sources;
	}

	// Do nothing if the image attachment ID is not supplied.
	if ( empty( $attachment_id ) ) {
		return $sources;
	}

	require_once ABSPATH . '/wp-admin/includes/image.php';

	// Return early if there are no missing sizes.
	$missing_sizes = wp_get_missing_image_subsizes( $attachment_id );
	if ( empty( $missing_sizes ) ) {
		return $sources;
	}

	// Retrieve the uploads sub-directory from the full size image.
	$dirname = _wp_get_attachment_relative_path( $image_meta['file'] );
	if ( $dirname ) {
		$dirname = trailingslashit( $dirname );
	}

	$upload_dir    = wp_get_upload_dir();
	$image_baseurl = trailingslashit( $upload_dir['baseurl'] ) . $dirname;

	/*
	 * If currently on HTTPS, prefer HTTPS URLs when we know they're supported by the domain
	 * (which is to say, when they share the domain name of the current request).
	 */
	if ( is_ssl() && 'https' !== substr( $image_baseurl, 0, 5 ) && parse_url( $image_baseurl, PHP_URL_HOST ) === $_SERVER['HTTP_HOST'] ) {
		$image_baseurl = set_url_scheme( $image_baseurl, 'https' );
	}

	$ext  = pathinfo( $image_meta['file'], PATHINFO_EXTENSION );
	$name = wp_basename( $image_meta['file'], ".$ext" );
	foreach ( $missing_sizes as $size_name => $size_details ) {
		$dimensions = wp_constrain_dimensions(
			$size_array[0],
			$size_array[1],
			$size_details['width'],
			$size_details['height']
		);

		$sources[ $dimensions[0] ] = array(
			'url'        => sprintf( '%s%s-%dx%d.%s', $image_baseurl, $name, $dimensions[0], $dimensions[1], $ext ),
			'descriptor' => 'w',
			'value'      => $dimensions[0],
		);
	}

	return $sources;
}
add_filter( 'wp_calculate_image_srcset', 'webp_uploads_generate_image_srcset', 10, 5 );

/**
 * Updates the srcset attribute for images in the content. Adds missing sizes to the image.
 *
 * @since n.e.x.t
 *
 * @param string $img           An <img> tag.
 * @param string $context       The context where this is function is being used.
 * @param int    $attachment_id The ID of the attachment being modified.
 * @return string The updated img tag.
 */
function webp_uploads_update_img_tag_srcset( $img, $context, $attachment_id ) {
	list( $src, $width, $height ) = wp_get_attachment_image_src( $attachment_id, 'large', false );

	$image_meta = wp_get_attachment_metadata( $attachment_id );
	$srcset     = wp_calculate_image_srcset( array( $width, $height ), $src, $image_meta, $attachment_id );
	if ( empty( $srcset ) ) {
		return $img;
	}

	return stripos( $img, 'srcset=' ) > 0
		? preg_replace( '#srcset=[\'"].*?[\'"]#', "srcset=\"{$srcset}\"", $img )
		: str_replace( '<img ', "<img srcset=\"{$srcset}\" ", $img );
}
add_filter( 'webp_uploads_update_img_tag', 'webp_uploads_update_img_tag_srcset', 5, 3 );
