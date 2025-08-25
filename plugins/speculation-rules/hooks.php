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

/**
 * Determines whether Speculative Loading is enabled.
 *
 * @since 1.6.0
 *
 * @return bool Whether enabled.
 */
function plsr_is_speculative_loading_enabled(): bool {
	$option = plsr_get_stored_setting_value();

	// Disabled if the user is logged in, unless the setting explicitly allows the current user's role.
	if (
		is_user_logged_in()
		&&
		'any' !== $option['authentication']
		&&
		( ! current_user_can( 'manage_options' ) || 'logged_out_and_admins' !== $option['authentication'] )
	) {
		return false;
	}

	// Disable if pretty permalinks are not enabled, unless explicitly overridden by the filter.
	if (
		! (bool) get_option( 'permalink_structure' )
		&&
		/**
		 * Filters whether speculative loading should be enabled even though the site does not use pretty permalinks.
		 *
		 * Since query parameters are commonly used by plugins for dynamic behavior that can change state, ideally any
		 * such URLs are excluded from speculative loading. If the site does not use pretty permalinks though, they are
		 * impossible to recognize. Therefore, speculative loading is disabled by default for those sites.
		 *
		 * For site owners of sites without pretty permalinks that are certain their site is not using such a pattern,
		 * this filter can be used to still enable speculative loading at their own risk.
		 *
		 * @since 1.4.0
		 *
		 * @param bool $enabled Whether speculative loading is enabled even without pretty permalinks.
		 */
		! apply_filters( 'plsr_enabled_without_pretty_permalinks', false )
	) {
		return false;
	}

	return true;
}

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
