<?php
/**
 * Term details.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * If job ID is not present, redirect back to term listing page.
 */
if ( empty( $_REQUEST['job_id'] ) ) {
	wp_die( __( 'No job ID specified.', 'performance-lab' ) );
}

$job_id = absint( $_REQUEST['job_id'] );
$job    = get_term( $job_id, 'background_job' );

if ( ! $job instanceof WP_Term ) {
	wp_die( __( 'You attempted to edit an item that does not exist. Perhaps it was deleted?', 'performance-lab' ) );
}

if ( ! current_user_can( 'manage_jobs' ) ) {
	wp_die( __( 'You do not have permission to access this page. Please contact administrator for more information.', 'performance-lab' ) );
}

$tax = get_taxonomy( $job->taxonomy );

$job_data = get_term_meta( $job_id, 'perflab_job_data', true );
?>

<div class="wrap">
	<h1><?php echo $tax->labels->view_item; ?></h1>
	<h3><?php _e( 'Job Name', 'performance-lab' ); ?></h3>
	<h3><?php _e( 'Job Data', 'performance-lab' ); ?></h3>
	<textarea rows="10" cols="50" readonly>
<?php
			$data = array(
				'test'             => 123456,
				'post_ids'         => array(
					10,
					20,
					30,
				),
				'something_random' => 'stuff',
			);
			echo wp_json_encode( $data, JSON_PRETTY_PRINT );
			?>
	</textarea>
</div>
