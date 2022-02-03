<?php
/**
 * Helper functions used by module.
 *
 * @package performance-lab
 * @since 1.0.0
 */

function perflab_should_debug_performance() {
	static $debug_performance = false;

	if( $debug_performance ) {
		return $debug_performance;
	}

	if ( function_exists( 'getenv' ) ) {
        $has_env = getenv( 'WP_DEBUG_PERFORMANCE' );
        if ( false !== $has_env ) {
            $debug_performance = $has_env;
        }
    }

	if ( defined( 'WP_DEBUG_PERFORMANCE' ) ) {
        $debug_performance = WP_DEBUG_PERFORMANCE;
    }

	return $debug_performance;
}
