<?php
/**
 * Hook callbacks used for Speculation Rules.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Prints the speculation rules in a cross-browser compatible way.
 *
 * For browsers that do not support speculation rules yet, the rules will not be loaded.
 *
 * @since n.e.x.t
 */
function plsr_print_speculation_rules() {
	$rules = plsr_get_speculation_rules();
	if ( empty( $rules ) ) {
		return;
	}

	$script = <<<JS
( function() {
	if ( ! HTMLScriptElement.supports || ! HTMLScriptElement.supports( 'speculationrules' ) ) {
		console.log( 'speculation rules not supported' );
		return;
	}

	var specScript = document.createElement( 'script' );
	specScript.type = 'speculationrules';
	specScript.textContent = '%s';
	console.log( 'speculation rules added' );
	document.body.append( specScript );
} )();
JS;

	wp_print_inline_script_tag(
		sprintf(
			$script,
			wp_json_encode( $rules )
		)
	);
}
add_action( 'wp_footer', 'plsr_print_speculation_rules' );
