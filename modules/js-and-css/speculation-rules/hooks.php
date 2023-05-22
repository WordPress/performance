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

/**
 * Prints the tag to opt in to the Chrome origin trial if the token constant is defined.
 *
 * After opting in to the origin trial via https://github.com/WICG/nav-speculation/blob/main/chrome-2023q1-experiment-overview.md,
 * please set your token in a `PLSR_ORIGIN_TRIAL_TOKEN` constant, e.g. in `wp-config.php`.
 *
 * This function is here temporarily and will eventually be removed.
 *
 * @since n.e.x.t
 * @access private
 * @ignore
 */
function plsr_print_origin_trial_optin() {
	if ( ! defined( 'PLSR_ORIGIN_TRIAL_TOKEN' ) || ! PLSR_ORIGIN_TRIAL_TOKEN ) {
		return;
	}
	?>
	<meta http-equiv="origin-trial" content="<?php echo esc_attr( PLSR_ORIGIN_TRIAL_TOKEN ); ?>">
	<?php
}
add_action( 'wp_head', 'plsr_print_origin_trial_optin' );
