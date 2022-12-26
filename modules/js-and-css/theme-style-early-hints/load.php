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
 * Checks whether the theme's style.css file is actually being enqueued.
 *
 * @since n.e.x.t
 */
function perflab_tseh_stylesheet_check() {
	// Bail if not a frontend request.
	if ( is_admin() || defined( 'XMLRPC_REQUEST' ) || defined( 'REST_REQUEST' ) || defined( 'MS_FILES_REQUEST' ) ) {
		return;
	}

	// Bail if already populated.
	if ( get_option( 'perflab_stylesheet_loaded' ) !== false ) {
		return;
	}

	$stylesheet_uri = get_stylesheet_uri();

	$handles    = array_merge( wp_styles()->queue, wp_styles()->done );
	$registered = wp_styles()->registered;

	$loaded = false;
	foreach ( $handles as $handle ) {
		if ( ! isset( $registered[ $handle ] ) ) {
			continue;
		}
		if ( $registered[ $handle ]->src === $stylesheet_uri ) {
			$loaded = true;
			break;
		}
	}

	update_option( 'perflab_stylesheet_loaded', $loaded ? '1' : '0' );
}
add_action( 'wp_print_styles', 'perflab_tseh_stylesheet_check' );

/**
 * Resets the theme's style.css check (typically upon changing the current theme).
 *
 * @since n.e.x.t
 */
function perflab_tseh_reset_stylesheet_check() {
	delete_option( 'perflab_stylesheet_loaded' );
}
add_action( 'switch_theme', 'perflab_tseh_reset_stylesheet_check' );

/**
 * Checks the request URI and based on it attempts to send a 103 Early Hints header for the hero image.
 *
 * @since n.e.x.t
 */
function perflab_tseh_send_early_hints_header() {
	// Bail if not a frontend request.
	if ( is_admin() || defined( 'XMLRPC_REQUEST' ) || defined( 'REST_REQUEST' ) || defined( 'MS_FILES_REQUEST' ) ) {
		return;
	}

	// Bail if stylesheet not used.
	if ( ! get_option( 'perflab_stylesheet_loaded' ) ) {
		return $resources;
	}

	$stylesheet_url = get_stylesheet_uri();

	status_header( 103 );
	header( "Link: <{$stylesheet_url}>; rel=preload; as=style", false );

	// Fix WP core headers no longer being output because of its problematic `headers_sent()` checks.
	add_filter(
		'wp_headers',
		function( $headers ) {
			// Send headers on 'send_headers' early, since status header will still be sent by WP.
			add_action(
				'send_headers',
				function() use ( $headers ) {
					if ( isset( $headers['Last-Modified'] ) && false === $headers['Last-Modified'] ) {
						unset( $headers['Last-Modified'] );

						header_remove( 'Last-Modified' );
					}

					foreach ( (array) $headers as $name => $field_value ) {
						header( "{$name}: {$field_value}" );
					}
				},
				// phpcs:ignore PHPCompatibility.Constants.NewConstants.php_int_minFound
				PHP_INT_MIN
			);
			return $headers;
		}
	);
}
add_action( 'plugins_loaded', 'perflab_tseh_send_early_hints_header' );
