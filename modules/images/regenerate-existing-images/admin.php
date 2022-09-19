<?php
/**
 * Admin functions used by the background process API.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Adds necessary hooks to enable job queue admin screen.
 *
 * This will add a submenu page under Tools menu.
 *
 * @since n.e.x.t
 *
 * @return void
 */
function perflab_add_queue_screen_hooks() {
	/**
	 * By default admin screen will be disabled.
	 *
	 * @since n.e.x.t
	 * @param bool $enable Flag to enable admin queue screen. Default false.
	 * @todo Change default value to false before merge.
	 */
	if ( apply_filters( 'perflab_enable_background_process_queue_screen', true ) ) {
		add_action( 'admin_menu', 'perflab_management_page' );
		add_action( 'parent_file', 'perflab_background_job_parent_file' );
		add_action( 'manage_edit-background_job_columns', 'perflab_job_taxonomy_columns' );
	}
}
add_action( 'init', 'perflab_add_queue_screen_hooks', 100 );

/**
 * Add management page which will be added in Tools menu.
 *
 * @since n.e.x.t
 *
 * @return void
 */
function perflab_management_page() {
	add_management_page(
		__( 'Background Jobs', 'performance-lab' ),
		__( 'Background Jobs', 'performance-lab' ),
		'edit_jobs',
		'edit-tags.php?taxonomy=background_job',
		'',
		100
	);
}

/**
 * Changes the parent file for background_job screen.
 *
 * @since n.e.x.t
 *
 * @param  string $parent_file Parent file.
 * @return string
 */
function perflab_background_job_parent_file( $parent_file ) {
	$screen = get_current_screen();
	if ( $screen instanceof WP_Screen && isset( $screen->taxonomy ) && 'background_job' === $screen->taxonomy ) {
		return 'tools.php';
	}

	return $parent_file;
}

/**
 * Add custom columns to the job taxonomy list table.
 *
 * @since n.e.x.t
 *
 * @param array $columns Columns for job taxonomy list table.
 *
 * @return array Filtered list of columns.
 */
function perflab_job_taxonomy_columns( array $columns ) {
	$new_columns = array();

	if ( isset( $columns['cb'] ) ) {
		$new_columns['cb'] = $columns['cb'];
	}

	$new_columns['job_id']     = __( 'Job ID', 'performance-lab' );
	$new_columns['job_name']   = __( 'Job Name', 'performance-lab' );
	$new_columns['job_status'] = __( 'Job Status', 'performance-lab' );

	return $new_columns;
}
