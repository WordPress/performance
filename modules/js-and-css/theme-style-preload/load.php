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
 * Checks whether the theme's style.css file is actually being enqueued and records the URL.
 *
 * @since n.e.x.t
 */
function perflab_tsp_stylesheet_check() {
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
add_action( 'wp_print_styles', 'perflab_tsp_stylesheet_check' );

/**
 * Resets the theme's style.css check (typically upon changing the current theme).
 *
 * @since n.e.x.t
 */
function perflab_tsp_reset_stylesheet_check() {
	delete_option( 'perflab_preload_stylesheet' );
}
add_action( 'switch_theme', 'perflab_tsp_reset_stylesheet_check' );
add_action( 'update_option_home', 'perflab_tsp_reset_stylesheet_check' );
add_action( 'update_option_siteurl', 'perflab_tsp_reset_stylesheet_check' );

/**
 * Add theme's style.css as a resource to preload, if available.
 *
 * @since n.e.x.t
 *
 * @param array $resources Resources to preload.
 * @return array Modified $resources.
 */
function perflab_tsp_preload_resources( $resources ) {
	$preload_stylesheet = get_option( 'perflab_preload_stylesheet' );

	// Bail if stylesheet not used.
	if ( ! $preload_stylesheet ) {
		return $resources;
	}

	$resources[] = array(
		'href' => $preload_stylesheet,
		'as'   => 'style',
	);

	return $resources;
}
add_filter( 'wp_preload_resources', 'perflab_tsp_preload_resources' );
