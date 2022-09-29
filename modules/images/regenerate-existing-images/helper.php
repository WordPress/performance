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
 * @param string $name Name of job action.
 * @param array  $data Optional. Arbitrary data for the job. Default empty array.
 * @return Perflab_Background_Job|WP_Error Job object if created successfully, else WP_Error.
 */
function perflab_create_background_job( $name, array $data = array() ) {
	// Create the unique term name dynamically.
	$term_name = 'job_' . time() . rand();
	$term_data = wp_insert_term( $term_name, PERFLAB_BACKGROUND_JOB_TAXONOMY_SLUG );

	if ( is_wp_error( $term_data ) ) {
		return $term_data;
	}

	// Save the job data if present.
	if ( ! empty( $data ) ) {
		update_term_meta( $term_data['term_id'], Perflab_Background_Job::META_KEY_JOB_DATA, $data );
	}

	// Save job action. sanitize_title will be used before saving action.
	update_term_meta( $term_data['term_id'], Perflab_Background_Job::META_KEY_JOB_ACTION, sanitize_title( $name ) );

	// Create a fresh instance to return.
	$job = new Perflab_Background_Job( $term_data['term_id'] );
	// Set the queued status for freshly created jobs.
	$job->queued();

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
