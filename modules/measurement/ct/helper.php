<?php
/**
 * Helper functions used by module.
 *
 * @package performance-lab
 * @since 1.0.0
 */

function perflab_should_debug_performance() {
	static $debug_performance = false;

	if ( $debug_performance ) {
		return $debug_performance;
	}

	if ( function_exists( 'getenv' ) ) {
        return (bool) getenv( 'WP_DEBUG_PERFORMANCE' );
    }

	if ( defined( 'WP_DEBUG_PERFORMANCE' ) ) {
        return (bool) WP_DEBUG_PERFORMANCE;
    }

	return (bool) $debug_performance;
}

add_filter( 'perflab_debug_performance_ct', 'perflab_should_debug_performance', 0 );
