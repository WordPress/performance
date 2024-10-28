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
 * Provides access to the performance marks API.
 *
 * @since n.e.x.t
 */
function perflab_performance_marks(): Perflab_Performance_Marks {
	static $performance_marks;

	if ( null === $performance_marks ) {
		$performance_marks = new Perflab_Performance_Marks();
	}

	return $performance_marks;
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
