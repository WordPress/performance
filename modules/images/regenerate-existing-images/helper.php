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
 * @param array  $data   Optional. Arbitrary data for the job. Default empty array.
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
	$term_name = str_replace( '-', '_', sanitize_title( $action ) );
	$term_data = wp_insert_term( $term_name, PERFLAB_BACKGROUND_JOB_TAXONOMY_SLUG );

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
 * Retrives the background job instance based on job ID.
 *
 * @since n.e.x.t
 *
 * @param  int $job_id Background job ID. Technically the term_id for `background_job` taxonomy.
 * @return Perflab_Background_Job|false Job instance. False if job doesn't exist.
 */
function perflab_get_background_job( $job_id ) {
	$job_id      = absint( $job_id );
	$found       = null;
	$cache_key   = 'perflab_job_' . $job_id;
	$cache_group = 'perflab_job_pool';

	$cached_job = wp_cache_get( $cache_key, $cache_group, $found );

	if ( null !== $found ) {
		$cached_job;
	}

	// Create the instance.
	$job = new Perflab_Background_Job( $job_id );

	if ( ! $job->exists() ) {
		$job = false;
	}

	// Save the result in object cache.
	wp_cache_set( $cache_key, $job, $cache_group );

	return $job;
}

/**
 * Dispatches the request to trigger the background job.
 *
 * @since n.e.x.t
 *
 * @param  int   $job_id       Job ID. Technically, the term_id from `background_job` taxonomy.
 * @param  array $request_body Arguments to pass in `body` of the wp_remote_post request.
 * @return bool|WP_Error True if request was made successfully; WP_Error otherwise.
 */
function perflab_start_background_job( $job_id, $request_body = array() ) {
	$default_request_body = array(
		'action' => Perflab_Background_Process::BG_PROCESS_ACTION,
		'job_id' => $job_id,
		'nonce'  => wp_create_nonce( Perflab_Background_Process::BG_PROCESS_ACTION ),
	);

	$request_body = wp_parse_args( $request_body, $default_request_body );

	// Build the HTTP Post request args.
	$params = array(
		'blocking'  => false,
		'body'      => $request_body,
		'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		'timeout'   => 0.1,
	);

	// Admin ajax to send request to.
	$url = admin_url( 'admin-ajax.php' );

	/**
	 * As this is a non-blocking request, we won't receive response from
	 * server, but we will get to know whether request was successfully made.
	 */
	$success = wp_remote_post( $url, $params );

	if ( is_wp_error( $success ) ) {
		return $success;
	}

	return true;
}

/**
 * Calls the next batch of a job by triggering HTTP request.
 *
 * @since n.e.x.t
 *
 * @param  int $job_id Job ID. Technically, the term_id from `background_job` taxonomy.
 * @return bool|WP_Error True if request was made successfully; WP_Error otherwise.
 */
function perflab_background_process_next_batch( $job_id ) {
	// Create a secret key for validating next batch request.
	$key         = wp_generate_password( 20, true, true );
	$encoded_key = urlencode_deep( $key );

	update_option( 'background_process_key_' . absint( $job_id ), $key, false );

	$request_body = array(
		'action' => Perflab_Background_Process::BG_PROCESS_NEXT_BATCH_ACTION,
		'job_id' => $job_id,
		'key'    => $encoded_key,
		'nonce'  => wp_create_nonce( Perflab_Background_Process::BG_PROCESS_NEXT_BATCH_ACTION ),
	);

	return perflab_start_background_job( $job_id, $request_body );
}
