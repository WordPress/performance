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

/**
 * Filters the HTML output of the search form to inject speculative loading interactivity.
 *
 * @since n.e.x.t
 *
 * @param string|mixed $form The search form HTML output.
 * @return string Filtered HTML.
 */
function plsr_filter_searchform( $form ): string {
	if ( ! is_string( $form ) ) {
		return '';
	}

	$namespace        = 'speculationRules';
	$directive_prefix = 'data-wp-on'; // TODO: Use data-wp-on-async when available.

	$p = new WP_HTML_Tag_Processor( $form );
	while ( $p->next_tag() ) {
		if ( 'FORM' === $p->get_tag() ) {
			if ( ! $p->get_attribute( 'data-wp-interactive' ) ) {
				$p->set_attribute( 'data-wp-interactive', $namespace );
			}
			// Create context if not already present.
			// TODO: Should there be namespaced context?
			if ( ! $p->get_attribute( 'data-wp-context' ) ) {
				$p->set_attribute( 'data-wp-context', '{}' );
			}
			// TODO: Shouldn't we just watch changes to the one context key?
			$p->set_attribute( "data-wp-watch--{$namespace}", "{$namespace}::callbacks.doSpeculativeLoad" );
			$p->set_attribute( "data-wp-on--submit--{$namespace}", "{$namespace}::actions.handleFormSubmit" );

			$p->set_attribute( "{$directive_prefix}--change--{$namespace}", "{$namespace}::actions.updateSpeculativeLoadUrl" );

			wp_enqueue_script_module( 'speculation-rules-search-form' );
			plsr_add_search_form_config();
		} elseif (
			( 'INPUT' === $p->get_tag() || 'BUTTON' === $p->get_tag() )
			&&
			'submit' === $p->get_attribute( 'type' )
		) {
			$p->set_attribute( "{$directive_prefix}--focus--{$namespace}", "{$namespace}::actions.updateSpeculativeLoadUrl" );
			$p->set_attribute( "{$directive_prefix}--pointerover--{$namespace}", "{$namespace}::actions.updateSpeculativeLoadUrl" );
		} elseif (
			'INPUT' === $p->get_tag()
			&&
			's' === $p->get_attribute( 'name' )
		) {
			$p->set_attribute( "{$directive_prefix}--keydown--{$namespace}", "{$namespace}::actions.handleInputKeydown" );
		}
	}

	return $p->get_updated_html();
}
add_filter( 'get_search_form', 'plsr_filter_searchform' );
add_filter( 'render_block_core/search', 'plsr_filter_searchform' );

/**
 * Registers script module for the speculatively loading search form.
 *
 * @since n.e.x.t
 */
function plsr_register_script_module(): void {
	wp_register_script_module(
		'speculation-rules-search-form',
		plugin_dir_url( __FILE__ ) . 'search-form.js',
		array(
			array( 'id' => '@wordpress/interactivity' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'plsr_register_script_module' );
