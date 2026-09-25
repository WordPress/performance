<?php
/**
 * Performance Marks API hooks file
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add performance mark output to the footer.
 *
 * @since n.e.x.t
 */
add_action( 'wp_footer', array( perflab_performance_marks(), 'send_marks' ), 999 );
