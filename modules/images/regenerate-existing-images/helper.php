<?php
/**
 * Functions used by the background process API.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Start a job in the background process.
 *
 * @since n.e.x.t
 *
 * @param int $job_id The job ID to run.
 *
 * @return boolean|WP_Error True on success, WP_Error om failure.
 */
function perflab_start_background_job( $job_id ) {
	$args = array(
		'blocking'  => false,
		'body'      => array(
			'action' => 'wp_ajax_background_process_handle_request',
			'job_id' => $job_id,
			'nonce'  => wp_create_nonce( 'wp_ajax_background_process_handle_request' ),
		),
		'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		'timeout'   => 0.1,
	);

	$response = wp_remote_post( admin_url( 'admin-ajax.php' ), $args );

	if ( ! is_wp_error( $response ) ) {
		return true;
	}

	return $response;
}
