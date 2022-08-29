<?php
/**
 * Admin page for background jobs
 *
 * @since n.e.x.t
 * @package performance-lab
 */

/**
 * Registers a management page for background jobs.
 *
 * @since n.e.x.t
 */
function perflab_register_background_jobs_page() {
	add_management_page(
		__( 'Background Jobs', 'performance-lab' ),
		__( 'Background Jobs', 'performance-lab' ),
		'manage_categories',
		'background-jobs',
		'perflab_render_background_jobs_page'
	);
}
add_action( 'admin_menu', 'perflab_register_background_jobs_page' );

/**
 * Renders the background jobs page.
 *
 * @since n.e.x.t
 */
function perflab_render_background_jobs_page() {
	$tax = get_taxonomy( 'background_job' );

	/*
	TODO: Uncomment this block once background_job capabilities are integrated with standard roles.
	if ( ! current_user_can( $tax->cap->manage_terms ) ) {
		wp_die(
			'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
			'<p>' . __( 'Sorry, you are not allowed to manage background jobs.' ) . '</p>',
			403
		);
	}
	*/

	$screen            = get_current_screen();
	$screen->post_type = 'post';
	$screen->id        = 'background_job';
	$screen->taxonomy  = 'background_job';

	$wp_list_table = _get_list_table( 'WP_Terms_List_Table', array( 'screen' => $screen ) );
	$pagenum       = $wp_list_table->get_pagenum();

	switch ( $wp_list_table->current_action() ) {
		// TODO: implement background job actions.
		default:
			break;
	}

	$wp_list_table->prepare_items();

	?>
	<div class="wrap">
		<h1 class="wp-heading-inline">
			<?php echo get_admin_page_title(); ?>
		</h1>
		<?php $wp_list_table->display(); ?>
	</div>
	<?php
}
