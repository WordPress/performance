<?php
/**
 * Module Name: Hero Image Early Hints
 * Description: Adds a 103 Early Hints response header to preload the hero image of any page.
 * Experimental: Yes
 *
 * @since n.e.x.t
 * @package performance-lab
 */

/**
 * Intercepts image rendered in content to detect what is most likely the hero image.
 *
 * @since n.e.x.t
 *
 * @param string $filtered_image The image tag.
 * @param string $context        The context of the image.
 * @return string The unmodified image tag.
 */
function perflab_hieh_img_tag_check( $filtered_image, $context ) {
	global $perflab_hieh_request_uri;

	if ( ! isset( $perflab_hieh_request_uri ) ) {
		return $filtered_image;
	}

	if ( 'the_content' !== $context && 'the_post_thumbnail' !== $context ) {
		return $filtered_image;
	}

	// Determining hero image relies on lazy loading logic.
	if ( ! wp_lazy_loading_enabled( 'img', $context ) ) {
		return $filtered_image;
	}

	if ( ! empty( $filtered_image ) && strpos( $filtered_image, 'loading="lazy"' ) === false ) {
		// Store the entire srcset and pick which image to use for Early Hints when loading the page.
		// In reality, this approach will not work well, because the image loaded may be one in `srcset` rather than
		// in `src`. However, Early Hints do not support `imagesrcset` and `imagesizes`, this is only supported in
		// a `preload` link tag (see https://html.spec.whatwg.org/multipage/semantics.html#early-hints:attr-link-imagesrcset).
		// This probably means at this point Early Hints can only be reasonably used for CSS or JS.
		if ( preg_match( '/ srcset="([^"]+)/', $filtered_image, $matches ) ) {
			update_option( 'perflab_hieh_' . md5( $perflab_hieh_request_uri ), $matches[1] );
		}
		remove_filter( 'wp_content_img_tag', 'perflab_hieh_img_tag_check' );
		remove_filter( 'post_thumbnail_html', 'perflab_hieh_post_thumbnail_html_check' );
	}

	return $filtered_image;
}

/**
 * Intercepts the post thumbnail HTML to detect what is most likely the hero image.
 *
 * @since n.e.x.t
 *
 * @param string $html The post thumbnail HTML.
 * @return string The unmodified thumbnail HTML.
 */
function perflab_hieh_post_thumbnail_html_check( $html ) {
	return perflab_hieh_img_tag_check( $html, 'the_post_thumbnail' );
}

/**
 * Adds hooks to detect hero image, based on current user permissions.
 *
 * To avoid this from running on any (unauthenticated) page load and thus avoid race conditions due to high traffic,
 * this logic should only be run when a user with capabilities to edit posts is logged-in.
 *
 * The current user is set before the 'init' action.
 *
 * @since n.e.x.t
 */
function perflab_hieh_add_hooks() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	add_filter( 'wp_content_img_tag', 'perflab_hieh_img_tag_check', 10, 2 );
	add_filter( 'post_thumbnail_html', 'perflab_hieh_post_thumbnail_html_check' );
}
add_action( 'init', 'perflab_hieh_add_hooks' );

/**
 * Replacement for the WP core status_header() function.
 *
 * This function in theory supports setting multiple HTTP status headers, which WP core's function does not support.
 *
 * However, in practice this still doesn't matter, as PHP itself allows only for a single HTTP response code, making
 * this entire feature not usable in the server environment.
 *
 * @since n.e.x.t
 *
 * @param int    $code        HTTP status code.
 * @param string $description Description for the HTTP status.
 */
function perflab_hieh_fixed_status_header( $code, $description ) {
	if ( ! $description ) {
		return;
	}

	$protocol      = wp_get_server_protocol();
	$status_header = "$protocol $code $description";

	// This filter is copied over from the WP core function status_header().
	$status_header = apply_filters( 'status_header', $status_header, $code, $description, $protocol );

	if ( ! headers_sent() ) {
		// The `false` here is the critical change needed, to not override previous status headers.
		header( $status_header, false, $code );
	}
}

/**
 * Checks the request URI and based on it attempts to send a 103 Early Hints header for the hero image.
 *
 * @since n.e.x.t
 */
function perflab_hieh_send_early_hints_header() {
	global $perflab_hieh_request_uri, $wp_header_to_desc;

	// Bail if not a frontend request.
	if ( is_admin() || defined( 'XMLRPC_REQUEST' ) || defined( 'REST_REQUEST' ) || defined( 'MS_FILES_REQUEST' ) ) {
		return;
	}

	$perflab_hieh_request_uri = $_SERVER['REQUEST_URI'];

	$home_path = parse_url( home_url(), PHP_URL_PATH );
	if ( is_string( $home_path ) && '' !== $home_path ) {
		$home_path       = trim( $home_path, '/' );
		$home_path_regex = sprintf( '|^%s|i', preg_quote( $home_path, '|' ) );

		$perflab_hieh_request_uri = preg_replace( $home_path_regex, '', $perflab_hieh_request_uri );
		$perflab_hieh_request_uri = trim( $perflab_hieh_request_uri, '/' );
	}

	if ( empty( $perflab_hieh_request_uri ) ) {
		$perflab_hieh_request_uri = '/';
	}

	$hero_img_srcset = get_option( 'perflab_hieh_' . md5( $perflab_hieh_request_uri ) );
	if ( ! $hero_img_srcset ) {
		return;
	}

	$hero_img_srcset = array_filter(
		array_map(
			function( $srcset_entry ) {
				if ( ! preg_match( '/ (\d+)w$/', $srcset_entry, $matches ) ) {
					return false;
				}
				return array(
					'src' => str_replace( $matches[0], '', $srcset_entry ),
					'w'   => (int) $matches[1],
				);
			},
			explode( ', ', $hero_img_srcset )
		)
	);
	$hero_img_srcset = wp_list_sort( $hero_img_srcset, 'w', 'ASC' );

	// This approach is obviously not reliable and clearly shows how Early Hints as of today
	// is not really feasible for images due to lack of srcset and sizes support.
	$min_width    = wp_is_mobile() ? 1000 : 1600;
	$hero_img_url = '';
	foreach ( $hero_img_srcset as $srcset_entry ) {
		if ( $srcset_entry['w'] > $min_width ) {
			$hero_img_url = $srcset_entry['src'];
			break;
		}
	}
	if ( ! $hero_img_url ) {
		$hero_img_url = array_pop( $hero_img_srcset )['src'];
	}

	perflab_hieh_fixed_status_header( 103, get_status_header_desc( 103 ) );
	header( "Link: <{$hero_img_url}>; rel=preload; as=image", false );

	// Empty header descriptions list to prevent core from overriding status header.
	// Instead the status header will be manually printed below.
	$orig_header_to_desc = $wp_header_to_desc;
	$wp_header_to_desc   = array();

	// Output status header manually here since, per the above hack, WP core will no longer do that itself.
	add_filter(
		'wp_headers',
		function( $headers ) use ( $orig_header_to_desc ) {
			global $wp_query;

			if ( $wp_query->is_404() ) {
				$code = 404;
			} else {
				$code = 200;
			}

			// Output status header using this "fixed" function, so that previous status headers are not overwritten.
			perflab_hieh_fixed_status_header( $code, isset( $orig_header_to_desc[ $code ] ) ? $orig_header_to_desc[ $code ] : '' );

			// Restore original $wp_header_to_desc.
			add_action(
				'send_headers',
				function() use ( $orig_header_to_desc ) {
					global $wp_header_to_desc;

					$wp_header_to_desc = $orig_header_to_desc;
				},
				// phpcs:ignore PHPCompatibility.Constants.NewConstants.php_int_minFound
				PHP_INT_MIN
			);

			return $headers;
		}
	);
}
add_action( 'plugins_loaded', 'perflab_hieh_send_early_hints_header' );
