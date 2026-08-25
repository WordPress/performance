<?php
/**
 * Module Name: Theme Style Early Hints
 * Description: Adds a 103 Early Hints response header to preload the theme's style.css file.
 * Experimental: Yes
 *
 * @since n.e.x.t
 * @package performance-lab
 */

/**
 * Checks whether the theme's style.css file is actually being enqueued and records the URL.
 *
 * @since n.e.x.t
 */
function perflab_tseh_stylesheet_check() {
	// Bail if not a frontend request.
	if ( is_admin() || defined( 'XMLRPC_REQUEST' ) || defined( 'REST_REQUEST' ) || defined( 'MS_FILES_REQUEST' ) ) {
		return;
	}

	// Bail if already populated.
	if ( get_option( 'perflab_preload_stylesheet' ) !== false ) {
		return;
	}

	$stylesheet_uri = get_stylesheet_uri();

	$handles    = array_merge( wp_styles()->queue, wp_styles()->done );
	$registered = wp_styles()->registered;

	$preload = '0';
	foreach ( $handles as $handle ) {
		if ( ! isset( $registered[ $handle ] ) ) {
			continue;
		}
		if ( $registered[ $handle ]->src === $stylesheet_uri ) {
			$preload = $registered[ $handle ]->src;
			$ver     = $registered[ $handle ]->ver;
			if ( null !== $ver ) {
				if ( false === $ver ) {
					$ver = wp_styles()->default_version;
				}
				$preload = add_query_arg( 'ver', $ver, $preload );
			}
			break;
		}
	}

	update_option( 'perflab_preload_stylesheet', $preload );
}
add_action( 'wp_print_styles', 'perflab_tseh_stylesheet_check' );

/**
 * Resets the theme's style.css check (typically upon changing the current theme).
 *
 * @since n.e.x.t
 */
function perflab_tseh_reset_stylesheet_check() {
	delete_option( 'perflab_preload_stylesheet' );
}
add_action( 'switch_theme', 'perflab_tseh_reset_stylesheet_check' );
add_action( 'update_option_home', 'perflab_tseh_reset_stylesheet_check' );
add_action( 'update_option_siteurl', 'perflab_tseh_reset_stylesheet_check' );

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
function perflab_tseh_fixed_status_header( $code, $description ) {
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
function perflab_tseh_send_early_hints_header() {
	global $wp_header_to_desc;

	// Bail if not a frontend request.
	if ( is_admin() || defined( 'XMLRPC_REQUEST' ) || defined( 'REST_REQUEST' ) || defined( 'MS_FILES_REQUEST' ) ) {
		return;
	}

	$preload_stylesheet = get_option( 'perflab_preload_stylesheet' );

	// Bail if stylesheet not used.
	if ( ! $preload_stylesheet ) {
		return;
	}

	perflab_tseh_fixed_status_header( 103, get_status_header_desc( 103 ) );
	header( "Link: <{$preload_stylesheet}>; rel=preload; as=style", false );

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
			perflab_tseh_fixed_status_header( $code, isset( $orig_header_to_desc[ $code ] ) ? $orig_header_to_desc[ $code ] : '' );

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
add_action( 'plugins_loaded', 'perflab_tseh_send_early_hints_header' );
