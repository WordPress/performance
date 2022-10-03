<?php
/**
 * Functions used by the background process API.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Creates a background job.
 *
 * Job refers to the term and queue refers to the `background_job` taxonomy.
 *
 * @since n.e.x.t
 *
 * @param string $action Name of job action.
 * @param array  $data Optional. Arbitrary data for the job. Default empty array.
 * @return Perflab_Background_Job|WP_Error Job object if created successfully, else WP_Error.
 */
function perflab_create_background_job( $action, array $data = array() ) {
	// Ensure that job action is string.
	if ( ! is_string( $action ) ) {
		return new WP_Error( 'job_action_invalid_type', __( 'Job name must be string.', 'performance-lab' ) );
	}

	/**
	 * Create the unique term name dynamically.
	 *
	 * Save job action. sanitize_title will be used before saving action.
	 * Note that it will allow alphanumeric characters with underscores.
	 * For instance, 'custom_job_action' or 'my_custom_123_job'.
	 */
	$term_name   = sanitize_title( $action );
	$term_name   = str_replace( '-', '_', $term_name );
	$term_object = (object) array(
		'taxonomy' => PERFLAB_BACKGROUND_JOB_TAXONOMY_SLUG,
		'parent'   => null,
	);
	$slug        = wp_unique_term_slug( $term_name, $term_object );
	$term_data   = wp_insert_term( $term_name, PERFLAB_BACKGROUND_JOB_TAXONOMY_SLUG, array( 'slug' => $slug ) );

	if ( is_wp_error( $term_data ) ) {
		return $term_data;
	}

	// Save the job data if present.
	if ( ! empty( $data ) ) {
		update_term_meta( $term_data['term_id'], Perflab_Background_Job::META_KEY_JOB_DATA, $data );
	}

	// Create a fresh instance to return.
	$job = new Perflab_Background_Job( $term_data['term_id'] );
	// Set the queued status for freshly created jobs.
	$job->queue();

	return $job;
}

/**
 * Deletes a background job.
 *
 * Technically it is equivalent of deleting a term in
 * `background_job` taxonomy and all its associated meta.
 *
 * @since n.e.x.t
 *
 * @param int $job_id Job ID. Technically the term id for `background_job` taxonomy.
 * @return bool|WP_Error True on success, false if term does not exist. WP_Error if the taxonomy does not exist.
 */
function perflab_delete_background_job( $job_id ) {
	$job_id = absint( $job_id );
	$job    = new Perflab_Background_Job( $job_id );

	if ( ! $job->exists() ) {
		return false;
	}

	return wp_delete_term( $job_id, PERFLAB_BACKGROUND_JOB_TAXONOMY_SLUG );
}

/**
 * Dispatch the request to trigger the background process job.
 *
 * @since n.e.x.t
 *
 * @param int $job_id Job ID.
 */
function perflab_dispatch_background_process_request( $job_id ) {
	/**
	 * Do not call the background process from within the script if the
	 * real cron has been setup to do so.
	 */
	if ( defined( 'ENABLE_BG_PROCESS_CRON' ) ) {
		return;
	}

	$nonce  = wp_create_nonce( Perflab_Background_Process::BG_PROCESS_ACTION );
	$url    = admin_url( 'admin-ajax.php' );
	$params = array(
		'blocking'  => false,
		'body'      => array(
			'action' => Perflab_Background_Process::BG_PROCESS_ACTION,
			'job_id' => $job_id,
			'nonce'  => $nonce,
		),
		'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		'timeout'   => 0.1,
	);

	// We won't collect the response as it is trigger and forget!
	wp_remote_post( $url, $params );
}
