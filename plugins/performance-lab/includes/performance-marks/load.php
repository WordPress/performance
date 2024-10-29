<?php
/**
 * Performance Marks API integration file
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/hooks.php';

/**
 * Get the instance of the Perflab_Performance_Marks class.
 *
 * @since n.e.x.t
 *
 * @return Perflab_Performance_Marks The instance of the class.
 */
function perflab_performance_marks(): Perflab_Performance_Marks {
	static $instance;

	if ( ! $instance ) {
		$instance = new Perflab_Performance_Marks();
	}

	return $instance;
}

/**
 * Initialize the performance marks API.
 *
 * @since n.e.x.t
 */
function perflab_performance_marks_init(): void {
	perflab_performance_marks();
}
add_action( 'wp_loaded', 'perflab_performance_marks_init' );
