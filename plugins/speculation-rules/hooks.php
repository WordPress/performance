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

/**
 * Load the predict.js script which will uses on-device AI to predict links users are most likely to visit for prerendering.
 *
 * @since n.e.x.t
 */
function plsr_load_predict_script(): void {
	wp_enqueue_script(
		'plsr-predict',
		plugin_dir_url( __FILE__ ) . 'predict.js', // @todo switch to build version.
		array( 'genai_bundle' ),
		SPECULATION_RULES_VERSION,
		array(
			'strategy' => 'defer',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'plsr_load_predict_script' );
