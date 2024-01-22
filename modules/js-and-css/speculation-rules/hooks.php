<?php
/**
 * Hook callbacks used for Speculation Rules.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Prints the speculation rules.
 *
 * For browsers that do not support speculation rules yet, the `script[type="speculationrules"]` tag will be ignored.
 *
 * @since n.e.x.t
 */
function plsr_print_speculation_rules() {
	$rules = plsr_get_speculation_rules();
	if ( empty( $rules ) ) {
		return;
	}

	// This workaround is needed for WP 6.4. See <https://core.trac.wordpress.org/ticket/56313>.
	$needs_html5_workaround = ! current_theme_supports( 'html5', 'script' );
	if ( $needs_html5_workaround ) {
		$backup_wp_theme_features = $GLOBALS['_wp_theme_features'];
		add_theme_support( 'html5', array( 'script' ) );
	}

	wp_print_inline_script_tag(
		wp_json_encode( $rules ),
		array( 'type' => 'speculationrules' )
	);

	if ( $needs_html5_workaround ) {
		$GLOBALS['_wp_theme_features'] = $backup_wp_theme_features; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}
}
add_action( 'wp_footer', 'plsr_print_speculation_rules' );
