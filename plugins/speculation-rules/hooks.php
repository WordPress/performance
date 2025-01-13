<?php
/**
 * Hook callbacks used for Speculative Loading.
 *
 * @package speculation-rules
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints the speculation rules.
 *
 * For browsers that do not support speculation rules yet, the `script[type="speculationrules"]` tag will be ignored.
 *
 * @since 1.0.0
 */
function plsr_print_speculation_rules(): void {
	// Skip speculative loading for logged-in users.
	if ( is_user_logged_in() ) {
		return;
	}

	// Skip speculative loading for sites without pretty permalinks, unless explicitly enabled.
	if ( ! (bool) get_option( 'permalink_structure' ) ) {
		/**
		 * Filters whether speculative loading should be enabled even though the site does not use pretty permalinks.
		 *
		 * Since query parameters are commonly used by plugins for dynamic behavior that can change state, ideally any
		 * such URLs are excluded from speculative loading. If the site does not use pretty permalinks though, they are
		 * impossible to recognize. Therefore speculative loading is disabled by default for those sites.
		 *
		 * For site owners of sites without pretty permalinks that are certain their site is not using such a pattern,
		 * this filter can be used to still enable speculative loading at their own risk.
		 *
		 * @since n.e.x.t
		 *
		 * @param bool $enabled Whether speculative loading is enabled even without pretty permalinks.
		 */
		$enabled = (bool) apply_filters( 'plsr_enabled_without_pretty_permalinks', false );

		if ( ! $enabled ) {
			return;
		}
	}

	wp_print_inline_script_tag(
		(string) wp_json_encode( plsr_get_speculation_rules() ),
		array( 'type' => 'speculationrules' )
	);
}
add_action( 'wp_footer', 'plsr_print_speculation_rules' );

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
