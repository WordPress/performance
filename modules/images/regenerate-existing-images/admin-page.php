<?php
/**
 * Admin page for background jobs.
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
	$page_hook = add_management_page(
		__( 'Background Jobs', 'performance-lab' ),
		__( 'Background Jobs', 'performance-lab' ),
		'manage_categories',
		'background-jobs',
		'perflab_render_background_jobs_page'
	);

	add_action( "load-{$page_hook}", 'perflab_setup_background_jobs_screen' );
}
add_action( 'admin_menu', 'perflab_register_background_jobs_page' );

/**
 * Adds screen options to the background jobs screen.
 *
 * @since n.e.x.t
 */
function perflab_setup_background_jobs_screen() {
	global $perflab_background_jobs_list_table;

	$arguments = array(
		'label'   => __( 'Jobs Per Page', 'performance-lab' ),
		'default' => 20,
		'option'  => 'background_jobs_per_page',
	);

	add_screen_option( 'per_page', $arguments );

	// Initialize background jobs table to load columns properly.
	perflab_get_background_jobs_list_table();
}

/**
 * Gets an instance of the background jobs list table.
 *
 * @since n.e.x.t
 */
function perflab_get_background_jobs_list_table() {
	global $perflab_background_jobs_list_table;

	if ( ! $perflab_background_jobs_list_table instanceof WP_Background_Jobs_List_Table ) {
		$perflab_background_jobs_list_table = new WP_Background_Jobs_List_Table();
	}

	return $perflab_background_jobs_list_table;
}

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

	$wp_list_table = perflab_get_background_jobs_list_table();

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
		<form id="perflab-background-jobs-list-form" method="get">
			<?php $wp_list_table->display(); ?>
		</form>
	</div>
	<?php
}
