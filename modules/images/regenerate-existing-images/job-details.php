<?php
/**
 * Job details.
 *
 * Displays the job details on custom admin page.
 *
 * @package performance-lab
 * @since n.e.x.t
 * @param array $args {
 *      $taxonomy   WP_Taxonomy Taxonomy object for background_job taxonomy.
 *      $job_status string      Current job status.
 *      $job        WP_Term     Job term object.
 *      $job_errors array       List of errors while job was executed.
 *      $job_data   array       Job data.
 * } These arguments will be passed to the template.
 */

?>
<div class="wrap">
	<h1><?php echo $args['taxonomy']->labels->view_item; ?></h1>	
	<!-- Job name -->
	<h3><?php esc_html_e( 'Job Name', 'performance-lab' ); ?></h3>
	<span><?php echo esc_html( $args['job']->name ); ?></span>

	<!-- Job status -->
	<h3><?php esc_html_e( 'Job Status', 'performance-lab' ); ?></h3>
	<span><?php echo esc_html( $args['job_status'] ); ?></span>

	<!-- Job data -->
	<h3><?php _e( 'Job Data', 'performance-lab' ); ?></h3>
	<textarea rows="10" cols="50" readonly>
<?php
	$job_data = array(
		'test'             => 123456,
		'post_ids'         => array(
			10,
			20,
			30,
		),
		'something_random' => 'stuff',
	);
			echo wp_json_encode( $job_data, JSON_PRETTY_PRINT );
	?>
	</textarea>
</div>
