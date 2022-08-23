<?php
/**
 * Module Name: Regenarate Existing Images
 * Description: Regenerate existing images when the image sizes changes during plugin/theme
 * activation/deactivation or for any other reasons.
 * Experimental: No
 *
 * @since   n.e.x.t
 * @package performance-lab
 */

/**
 * Register the background job taxonomy.
 *
 * Intentionally on lower priority, so that other post types can be
 * registered successfully and taxonomy can be used for all the post types.
 *
 * This taxonomy will be used as a queue system where each term represents a job in the queue.
 *
 * @return void
 */
function perflab_background_job_taxonomy() {
	// Get all registered public post types.
	$object_types = get_post_types(
		array(
			'public' => true,
		),
		'names'
	);

	// Labels for the background job taxonomy.
	$labels = array(
		'name'              => _x( 'Background Jobs', 'taxonomy general name', 'performance-lab' ),
		'singular_name'     => _x( 'Background Job', 'taxonomy singular name', 'performance-lab' ),
		'search_items'      => __( 'Search Background Jobs', 'performance-lab' ),
		'all_items'         => __( 'All Background Jobs', 'performance-lab' ),
		'parent_item'       => __( 'Parent Background Job', 'performance-lab' ),
		'parent_item_colon' => __( 'Parent Background Job:', 'performance-lab' ),
		'edit_item'         => __( 'Edit Background Job', 'performance-lab' ),
		'update_item'       => __( 'Update Background Job', 'performance-lab' ),
		'add_new_item'      => __( 'Add New Background Job', 'performance-lab' ),
		'new_item_name'     => __( 'New Background Job Name', 'performance-lab' ),
		'menu_name'         => __( 'Background Job', 'performance-lab' ),
	);

	$args = array(
		'hierarchical'      => false, // We do not need child job.
		'labels'            => $labels,
		'public'            => false,
		'show_ui'           => false,
		'show_admin_column' => false,
		'show_in_rest'      => false,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'background_job' ),
	);

	// Register background_job taxonomy.
	register_taxonomy( 'background_job', $object_types, $args );
}
add_action( 'init', 'perflab_background_job_taxonomy', 100 );
