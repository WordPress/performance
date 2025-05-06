<?php
/**
 * Theme related functions for View Transitions.
 *
 * @package view-transitions
 * @since 1.0.0
 */

/**
 * Polyfills theme support for 'view-transitions', regardless of the theme.
 *
 * In WordPress Core, the 'view-transitions' feature may end up as an optional feature, or it may be added by default.
 * In any case, in the scope of the plugin it does not make sense to have the feature as opt-in, since it is the entire
 * purpose of the plugin.
 *
 * Therefore, this function will unconditionally add support with the default configuration, unless the theme itself
 * actually added support for it already.
 *
 * This function must run at the latest possible priority for `after_setup_theme`.
 *
 * @since 1.0.0
 */
function plvt_polyfill_theme_support(): void {
	if ( current_theme_supports( 'view-transitions' ) ) {
		return;
	}
	add_theme_support( 'view-transitions' );
}

/**
 * Loads view transitions based on the current configuration.
 *
 * @since 1.0.0
 */
function plvt_load_view_transitions(): void {
	if ( ! current_theme_supports( 'view-transitions' ) ) {
		return;
	}

	// Use an inline style to avoid an extra request.
	$stylesheet = '@view-transition { navigation: auto; }';
	wp_register_style( 'wp-view-transitions', false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_add_inline_style( 'wp-view-transitions', $stylesheet );
	wp_enqueue_style( 'wp-view-transitions' );
}
