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
