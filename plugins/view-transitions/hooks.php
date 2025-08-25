<?php
/**
 * Hook callbacks used for View Transitions.
 *
 * @package view-transitions
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Displays the HTML generator tag for the plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 1.0.0
 */
function plvt_render_generator(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="view-transitions ' . esc_attr( VIEW_TRANSITIONS_VERSION ) . '">' . "\n";
}
add_action( 'wp_head', 'plvt_render_generator' );

/**
 * Hooks related to the key View Transitions functionality.
 */
add_action( 'after_setup_theme', 'plvt_polyfill_theme_support', PHP_INT_MAX );
add_action( 'init', 'plvt_sanitize_view_transitions_theme_support', 1 );
add_action( 'wp_enqueue_scripts', 'plvt_load_view_transitions' );
add_action( 'admin_head', 'plvt_print_view_transitions_admin_style' );

/**
 * Hooks related to the View Transitions settings.
 */
add_action( 'init', 'plvt_register_setting' );
add_action( 'init', 'plvt_apply_settings_to_theme_support' );
add_action( 'load-options-reading.php', 'plvt_add_setting_ui' );
add_filter( 'plugin_action_links_' . plugin_basename( VIEW_TRANSITIONS_MAIN_FILE ), 'plvt_add_settings_action_link' );
