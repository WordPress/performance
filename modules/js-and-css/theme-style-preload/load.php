<?php
/**
 * Module Name: Theme Style Preload
 * Description: Adds preload link tag for the theme's style.css file.
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
function perflab_tsp_stylesheet_check() {
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
add_action( 'wp_print_styles', 'perflab_tsp_stylesheet_check' );

/**
 * Resets the theme's style.css check (typically upon changing the current theme).
 *
 * @since n.e.x.t
 */
function perflab_tsp_reset_stylesheet_check() {
	delete_option( 'perflab_stylesheet_loaded' );
}
add_action( 'switch_theme', 'perflab_tsp_reset_stylesheet_check' );

/**
 * Add theme's style.css as a resource to preload, if available.
 *
 * @since n.e.x.t
 *
 * @param array $resources Resources to preload.
 * @return array Modified $resources.
 */
function perflab_tsp_preload_resources( $resources ) {
	// Bail if stylesheet not used.
	if ( ! get_option( 'perflab_stylesheet_loaded' ) ) {
		return $resources;
	}

	$resources[] = array(
		'href' => get_stylesheet_uri(),
		'as'   => 'style',
	);

	return $resources;
}
add_filter( 'wp_preload_resources', 'perflab_tsp_preload_resources' );
