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
 * Sanitizes theme support arguments for the 'view-transitions' feature.
 *
 * If the feature was part of WordPress Core, the logic of this function would become part of the `add_theme_support()`
 * function instead. There is no action or filter that could be used though, hence it is implemented here in a separate
 * function that runs after `after_setup_theme`, but before the 'view-transitions' feature arguments are possibly used.
 *
 * @since 1.0.0
 *
 * @global array<string, mixed> $_wp_theme_features Theme support features added and their arguments.
 */
function plvt_sanitize_view_transitions_theme_support(): void {
	global $_wp_theme_features;

	if ( ! isset( $_wp_theme_features['view-transitions'] ) ) {
		return;
	}

	$args = $_wp_theme_features['view-transitions'];

	$defaults = array(
		'post-selector'           => '.wp-block-post.post, article.post, body.single main',
		'global-transition-names' => array(
			'header' => 'header',
			'main'   => 'main',
		),
		'post-transition-names'   => array(
			'.wp-block-post-title, .entry-title'     => 'post-title',
			'.wp-post-image'                         => 'post-thumbnail',
			'.wp-block-post-content, .entry-content' => 'post-content',
		),
	);

	// If no specific `$args` were provided, simply use the defaults.
	if ( true === $args ) {
		$args = $defaults;
	} else {
		/*
		 * By default, `add_theme_support()` will take all function parameters as `$args`, but for the
		 * 'view-transitions' feature, only a single associative array of arguments is relevant, which is expected as
		 * the sole (optional) parameter.
		 */
		if ( count( $args ) === 1 && isset( $args[0] ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$args = wp_parse_args( $args, $defaults );

		// Enforce correct types.
		if ( ! is_array( $args['global-transition-names'] ) ) {
			$args['global-transition-names'] = array();
		}
		if ( ! is_array( $args['post-transition-names'] ) ) {
			$args['post-transition-names'] = array();
		}
	}

	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$_wp_theme_features['view-transitions'] = $args;
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

	$theme_support = get_theme_support( 'view-transitions' );

	/*
	 * No point in loading the script if no specific view transition names are configured.
	 */
	if (
		( ! is_array( $theme_support['global-transition-names'] ) || count( $theme_support['global-transition-names'] ) === 0 ) &&
		( ! is_array( $theme_support['post-transition-names'] ) || count( $theme_support['post-transition-names'] ) === 0 )
	) {
		return;
	}

	$config = array(
		'postSelector'          => $theme_support['post-selector'],
		'globalTransitionNames' => $theme_support['global-transition-names'],
		'postTransitionNames'   => $theme_support['post-transition-names'],
	);

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$src_script = file_get_contents( plvt_get_asset_path( 'js/view-transitions.js' ) );
	if ( false === $src_script || '' === $src_script ) {
		// This clause should never be entered, but is needed to please PHPStan. Can't hurt to be safe.
		return;
	}

	$init_script = sprintf(
		'plvtInitViewTransitions( %s )',
		wp_json_encode( $config, JSON_FORCE_OBJECT )
	);

	/*
	 * This must be in the <head>, not in the footer.
	 * This is because the pagereveal event listener must be added before the first rAF occurs since that is when the event fires. See <https://issues.chromium.org/issues/40949146#comment10>.
	 * An inline script is used to avoid an extra request.
	 */
	wp_register_script( 'wp-view-transitions', false, array(), null, array() ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_add_inline_script( 'wp-view-transitions', $src_script );
	wp_add_inline_script( 'wp-view-transitions', $init_script );
	wp_enqueue_script( 'wp-view-transitions' );
}
