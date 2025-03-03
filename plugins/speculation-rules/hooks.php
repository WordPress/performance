<?php
/**
 * Hook callbacks used for Speculative Loading.
 *
 * @package speculation-rules
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

// Conditionally use either the WordPress Core API, or load the plugin's API implementation otherwise.
if ( function_exists( 'wp_get_speculation_rules_configuration' ) ) {
	require_once __DIR__ . '/wp-core-api.php';

	add_filter( 'wp_speculation_rules_configuration', 'plsr_filter_speculation_rules_configuration' );
	add_filter( 'wp_speculation_rules_href_exclude_paths', 'plsr_filter_speculation_rules_exclude_paths', 10, 2 );
} else {
	require_once __DIR__ . '/class-plsr-url-pattern-prefixer.php';
	require_once __DIR__ . '/plugin-api.php';

	add_action( 'wp_footer', 'plsr_print_speculation_rules' );
}

/**
 * Displays the HTML generator meta tag for the Speculative Loading plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 1.1.0
 */
function plsr_render_generator_meta_tag(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="speculation-rules ' . esc_attr( SPECULATION_RULES_VERSION ) . '">' . "\n";
}
add_action( 'wp_head', 'plsr_render_generator_meta_tag' );
