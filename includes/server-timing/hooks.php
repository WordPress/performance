<?php
/**
 * Hook callbacks used for Server Timing.
 *
 * @package performance-lab
 *
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Adds server timing to REST API response.
 *
 * @since n.e.x.t
 *
 * @param WP_REST_Response|WP_Error $response Result to send to the client. Usually a `WP_REST_Response`.
 * @return WP_REST_Response|WP_Error Filtered response.
 */
function rest_post_dispatch_add_server_timing( $response ) {
	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST || ! function_exists( 'perflab_server_timing' ) || ! $response instanceof WP_REST_Response ) {
		return $response;
	}

	$server_timing = perflab_server_timing();

	/** This filter is documented in includes/server-timing/class-perflab-server-timing.php */
	do_action( 'perflab_server_timing_send_header' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

	$response->header( 'Server-Timing', $server_timing->get_header() );

	return $response;
}
add_filter( 'rest_post_dispatch', 'rest_post_dispatch_add_server_timing', PHP_INT_MAX );
