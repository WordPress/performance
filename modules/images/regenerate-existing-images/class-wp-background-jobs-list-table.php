<?php
/**
 * List table class for background jobs.
 *
 * @since n.e.x.t
 * @package performance-lab
 */

/**
 * Require parent class for the background jobs list table.
 */
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * The list table class for background jobs.
 *
 * @since n.e.x.t
 *
 * @see WP_List_Table
 */
class WP_Background_Jobs_List_Table extends WP_List_Table {

	/**
	 * Pulls background jobs and prepares pagination arguments.
	 *
	 * @since n.e.x.t
	 */
	public function prepare_items() {
		$taxonomy = 'background_job';
		$per_page = $this->get_items_per_page( 'background_jobs_per_page' );
		$page_num = $this->get_pagenum();

		$args = array(
			'taxonomy'   => $taxonomy,
			'page'       => $page_num,
			'offset'     => ( $page_num - 1 ) * $per_page,
			'number'     => $per_page,
			'hide_empty' => 0,
		);

		$this->items = get_terms( $args );

		$this->set_pagination_args(
			array(
				'total_items' => wp_count_terms( array( 'taxonomy' => $taxonomy ) ),
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Display the not found label of the background_job taxobomy when there is no jobs.
	 *
	 * @since n.e.x.t
	 */
	public function no_items() {
		echo get_taxonomy( 'background_job' )->labels->not_found;
	}

	/**
	 * Returns columns for the background jobs list table.
	 *
	 * @since n.e.x.t
	 *
	 * @return array Array of columns.
	 */
	public function get_columns() {
		$columns = array(
			'cb'     => '<input type="checkbox" />',
			'name'   => _x( 'Name', 'background job name', 'performance-lab' ),
			'status' => _x( 'Status', 'background job status', 'performance-lab' ),
		);

		return $columns;
	}

	/**
	 * Returns a checkbox for the cb column.
	 *
	 * @since n.e.x.t
	 *
	 * @param WP_Term $item The current background job item.
	 * @return string The checkbox.
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<label class="screen-reader-text" for="cb-select-%1$s">%2$s</label>' .
			'<input type="checkbox" name="delete_tags[]" value="%1$s" id="cb-select-%1$s" />',
			$item->term_id,
			/* translators: %s: Background job name. */
			sprintf( __( 'Select %s', 'performance-lab' ), $item->name )
		);
	}

	/**
	 * Returns a value for the name column.
	 *
	 * @since n.e.x.t
	 *
	 * @param WP_Term $item The current background job item.
	 * @return string The job name.
	 */
	public function column_name( $item ) {
		$actions = array();

		return sprintf(
			'<strong>%s</strong><br>%s',
			$item->name,
			$this->row_actions( $actions )
		);
	}

	/**
	 * Returns a value for the status column.
	 *
	 * @since n.e.x.t
	 *
	 * @param WP_Term $item The current background job item.
	 * @return string The job status.
	 */
	public function column_status( $item ) {
		$status = get_term_meta( $item->term_id, 'job_status', true );
		if ( empty( $status ) ) {
			$status = '-';
		}

		return $status;
	}

	/**
	 * Gets the name of the default primary column.
	 *
	 * @since n.e.x.t
	 *
	 * @return string Name of the default primary column, in this case, 'name'.
	 */
	protected function get_default_primary_column_name() {
		return 'name';
	}

}
