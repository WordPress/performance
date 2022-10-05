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

$job_data = array(
	'test'             => 123456,
	'post_ids'         => array(
		10,
		20,
		30,
	),
	'something_random' => 'stuff',
);

$job_errors = array(
	array(
		'datetime' => gmdate( 'Y-m-d H:I:s' ),
		'message'  => 'An error occured.',
		'data'     => array( 'post' => 10 ),
	),
);

?>
<div class="wrap">

	<h1><?php echo $args['taxonomy']->labels->view_item; ?></h1>

	<table class="form-table" role="presentation">
		<tr>
			<th><?php esc_html_e( 'Job ID', 'performance-lab' ); ?></th>
			<td><?php echo esc_html( $args['job_id'] ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Job Name', 'performance-lab' ); ?></th>
			<td><?php echo esc_html( $args['job']->name ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Job Status', 'performance-lab' ); ?></th>
			<td><?php echo esc_html( $args['job_status'] ); ?></td>
		</tr>
		<tr>
			<th><?php _e( 'Job Data', 'performance-lab' ); ?></th>
			<td>
				<code style="display: block"><pre><?php echo wp_json_encode( $job_data, JSON_PRETTY_PRINT ); ?></pre></code>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Job Errors', 'performance-lab' ); ?></th>
			<td>
				<ol style="list-style: none; margin: 0;">
				<?php foreach ( $job_errors as $job_error ) : ?>
					<li>[<?php echo $job_error['datetime']; ?>] <?php echo $job_error['message']; ?> <?php echo wp_json_encode( $job_error['data'] ); ?></li>
				<?php endforeach; ?>
				</ol>
			</td>
		</tr>
	</table>
</div>
