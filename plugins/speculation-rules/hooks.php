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
	$rules = plsr_get_speculation_rules();
	if ( empty( $rules ) ) {
		return;
	}

	/**
	 * Filters whether speculation rules are printed to the page.
	 *
	 * Speculative loading can add excessive server load on sites where many users are logged-in since page caching
	 * is typically bypassed. So only enable if the user is not logged-in, except if the user is an administrator so
	 * the can actually see the plugin working. Similarly, when PHP sessions are used, concurrent requests are not
	 * possible. So if a page takes 2 seconds to generate, and two pages are requested at about the same time, the
	 * first page's generation will block the generation of the second page, causing the second page to take 4 seconds
	 * to generate. Therefore, speculation rules are disabled by default when a session is active.
	 *
	 * @since n.e.x.t
	 *
	 * @param bool $can_print Whether to print speculation rules.
	 */
	$can_print = (bool) apply_filters(
		'plsr_can_print_speculation_rules',
		(
			( ! is_user_logged_in() || current_user_can( 'activate_plugins' ) )
			&&
			session_status() !== PHP_SESSION_ACTIVE
		)
	);
	if ( ! $can_print ) {
		return;
	}

	// This workaround is needed for WP 6.4. See <https://core.trac.wordpress.org/ticket/60320>.
	$needs_html5_workaround = (
		! current_theme_supports( 'html5', 'script' ) &&
		version_compare( (string) strtok( (string) get_bloginfo( 'version' ), '-' ), '6.4', '>=' ) &&
		version_compare( (string) strtok( (string) get_bloginfo( 'version' ), '-' ), '6.5', '<' )
	);
	if ( $needs_html5_workaround ) {
		$backup_wp_theme_features = $GLOBALS['_wp_theme_features'];
		add_theme_support( 'html5', array( 'script' ) );
	}

	wp_print_inline_script_tag(
		(string) wp_json_encode( $rules ),
		array( 'type' => 'speculationrules' )
	);

	if ( $needs_html5_workaround ) {
		$GLOBALS['_wp_theme_features'] = $backup_wp_theme_features; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}
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
