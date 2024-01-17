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

	wp_print_inline_script_tag(
		wp_json_encode( $rules ),
		array( 'type' => 'speculationrules' )
	);
}
add_action( 'wp_footer', 'plsr_print_speculation_rules' );
