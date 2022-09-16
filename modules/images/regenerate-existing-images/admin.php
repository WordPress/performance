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
	 */
	if ( apply_filters( 'perflab_enable_background_process_queue_screen', false ) ) {
		add_action( 'admin_menu', 'perflab_management_page' );
		add_action( 'parent_file', 'perflab_backgroun_job_parent_file' );
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
function perflab_backgroun_job_parent_file( $parent_file ) {
	$screen = get_current_screen();
	if ( $screen instanceof WP_Screen && isset( $screen->taxonomy ) && 'background_job' === $screen->taxonomy ) {
		return 'tools.php';
	}

	return $parent_file;
}
