<?php
/**
 * Hook callbacks used for the Chrome DevTools third-party tools integration.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

declare( strict_types = 1 );

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_enqueue_scripts', 'perflab_devtools_enqueue_script_module' );
add_action( 'wp_footer', 'perflab_devtools_print_data', PHP_INT_MAX );
// @codeCoverageIgnoreEnd
