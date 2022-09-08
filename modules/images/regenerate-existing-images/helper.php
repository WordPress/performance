<?php
/**
 * Functions used by the background process API.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Create a new background job.
 *
 * @since n.e.x.t
 *
 * @param string $job_name The name of job.
 * @param array  $job_data Data for the job.
 *
 * @return Perflab_Background_Job|WP_Error The Background Job object on success, else WP_Error.
 */
function perflab_create_background_job( $job_name, array $job_data = array() ) {
	return Perflab_Background_Job::create( $job_name, $job_data );
}
