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
		add_filter( 'manage_background_job_custom_column', 'perflab_job_column_data', 10, 3 );
		add_filter( 'submenu_file', 'perflab_term_details_submenu_file' );
	}
}
add_action( 'init', 'perflab_add_queue_screen_hooks' );

/**
 * Add management page which will be added in Tools menu.
 *
 * @since n.e.x.t
 *
 * @return void
 */
function perflab_management_page() {
	// @todo Replace taxonomy name with constant.
	add_management_page(
		__( 'Background Jobs', 'performance-lab' ),
		__( 'Background Jobs', 'performance-lab' ),
		'manage_jobs',
		'edit-tags.php?taxonomy=background_job',
		'',
		100
	);

	// Term details view page.
	add_submenu_page(
		'tools.php',
		__( 'Background Job', 'performance-lab' ),
		__( 'Background Job', 'performance-lab' ),
		'manage_jobs',
		'background-job-details',
		'perflab_admin_job_details'
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

	if ( ! $screen instanceof WP_Screen ) {
		return $parent_file;
	}

	if ( isset( $screen->taxonomy ) && 'background_job' === $screen->taxonomy ) {
		return 'tools.php';
	}

	return $parent_file;
}

/**
 * Changes the submenu file for job details screen.
 *
 * @since n.e.x.t
 *
 * @param  string $submenu_file Sub menu file.
 * @return string Submenu file.
 */
function perflab_term_details_submenu_file( $submenu_file ) {
	global $plugin_page;

	if ( 'background-job-details' === $plugin_page ) {
		$new_submenu_file = add_query_arg( array( 'taxonomy' => 'background_job' ), admin_url( 'edit-tags.php' ) );
		return $new_submenu_file;
	}

	return $submenu_file;
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

	if ( isset( $columns['cb'] ) && current_user_can( 'edit_jobs' ) ) {
		$new_columns['cb'] = $columns['cb'];
	}

	$new_columns['job_id']     = __( 'Job ID', 'performance-lab' );
	$new_columns['job_name']   = __( 'Job Name', 'performance-lab' );
	$new_columns['job_status'] = __( 'Job Status', 'performance-lab' );

	return $new_columns;
}

/**
 * Add the job specific data in custom columns in taxonomy list table.
 *
 * @since n.e.x.t
 *
 * @param string $column Markup for the taxonomy term.
 * @param string $column_name Custom column name.
 * @param int    $term_id Job ID.
 * @return string
 */
function perflab_job_column_data( $column, $column_name, $term_id ) {
	static $count;
	if ( is_null( $count ) ) {
		$count = 0;
	}

	switch ( $column_name ) {
		case 'job_id':
			$column = esc_html( $term_id );
			break;
		case 'job_name':
			$term_details_page = add_query_arg(
				array(
					'page'   => 'background-job-details',
					'job_id' => $term_id,
				),
				admin_url( 'admin.php' )
			);
			$column            = wp_kses(
				sprintf( '<a href="%1s">%2s %d</a>', esc_url( $term_details_page ), 'Job name', ++$count ),
				array(
					'a' => array(
						'href'  => array(),
						'title' => array(),
					),
				)
			);
			break;
		case 'job_status':
			$column = esc_html( 'Queued' );
			break;
	}

	return $column;
}

/**
 * Load the job details in admin.
 *
 * This is substitute of term.php page as there is very limited
 * flexibility.
 *
 * @since n.e.x.t
 */
function perflab_admin_job_details() {
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

	$tax        = get_taxonomy( $job->taxonomy );
	$job_data   = get_term_meta( $job_id, 'perflab_job_data', true );
	$job_errors = get_term_meta( $job_id, 'perflab_job_errors', true );

	$template_args = array(
		'taxonomy'   => $tax,
		'job'        => $job,
		'job_data'   => $job_data,
		'job_status' => 'Queued',
		'job_errors' => $job_errors,
	);

	// Pass all the data as arguments to the template.
	load_template( __DIR__ . '/job-details.php', true, $template_args );
}
