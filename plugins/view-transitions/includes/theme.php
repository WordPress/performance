<?php
/**
 * Theme related functions for View Transitions.
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
 * @access private
 *
 * @global bool|null            $plvt_has_theme_support_with_args Whether the current theme explicitly supports view transitions with custom config.
 * @global array<string, mixed> $_wp_theme_features               Theme support features added and their arguments.
 */
function plvt_polyfill_theme_support(): void {
	global $plvt_has_theme_support_with_args, $_wp_theme_features;

	if ( current_theme_supports( 'view-transitions' ) ) {
		// If the current theme actually supports view transitions with a custom config, set a flag to inform the user.
		if ( isset( $_wp_theme_features['view-transitions'] ) && true !== $_wp_theme_features['view-transitions'] ) {
			$plvt_has_theme_support_with_args = true;
		}
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
 * @access private
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
		'post-selector'              => '.wp-block-post.post, article.post, body.single main',
		'global-transition-names'    => array(
			'header' => 'header',
			'main'   => 'main',
		),
		'post-transition-names'      => array(
			'.wp-block-post-title, .entry-title'     => 'post-title',
			'.wp-post-image'                         => 'post-thumbnail',
			'.wp-block-post-content, .entry-content' => 'post-content',
		),
		'default-animation'          => 'fade',
		'default-animation-duration' => 400,
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
 * Registers the default view transition animations and fires an action to register additional ones.
 *
 * @since 1.0.0
 * @access private
 *
 * @param PLVT_View_Transition_Animation_Registry $animation_registry Registry instance to register animations on.
 */
function plvt_register_view_transition_animations( PLVT_View_Transition_Animation_Registry $animation_registry ): void {
	/*
	 * This callback is used for certain kinds of animations that move content around, to determine whether specific
	 * view transition names should be applied for an animation or not. If a specific target name (i.e. not '*') is
	 * provided, they should be applied. But if the entire page is the target, they would visually mess with the
	 * animation.
	 */
	$is_specific_target_name = static function ( string $alias, array $args ): bool {
		return '*' === $args['target-name'] ? false : true;
	};

	/*
	 * This callback is used to return horizontal and vertical offsets (-1, 0, or 1) based on whether the given alias
	 * ends in a certain directional term ('left', 'top', 'bottom', 'right'). If none is used, the callback returns
	 * `null` for both offsets.
	 */
	$get_hv_offsets_based_on_alias = static function ( string $alias ): array {
		if ( str_ends_with( $alias, 'left' ) ) {
			return array( -1, 0 );
		}
		if ( str_ends_with( $alias, 'top' ) ) {
			return array( 0, -1 );
		}
		if ( str_ends_with( $alias, 'bottom' ) ) {
			return array( 0, 1 );
		}
		if ( str_ends_with( $alias, 'right' ) ) {
			return array( 1, 0 );
		}
		return array( null, null );
	};

	// Register default animations.
	$animation_registry->register_animation(
		'fade', // This is how view transitions are animated without any extra CSS.
		array(
			'use_stylesheet'              => false,
			'use_global_transition_names' => true,
			'use_post_transition_names'   => true,
		)
	);
	$animation_registry->register_animation(
		'slide',
		array(
			'aliases'                     => array(
				'slide-from-right',
				'slide-from-bottom',
				'slide-from-left',
				'slide-from-top',
			),
			'use_stylesheet'              => true,
			'use_global_transition_names' => $is_specific_target_name,
			'use_post_transition_names'   => $is_specific_target_name,
			'get_stylesheet_callback'     => static function ( string $css, string $alias, array $args ) use ( $get_hv_offsets_based_on_alias ) {
				// Set offsets based on alias, if relevant.
				list( $horizontal_offset, $vertical_offset ) = $get_hv_offsets_based_on_alias( $alias );
				if ( null !== $horizontal_offset && null !== $vertical_offset ) {
					$args['horizontal-offset'] = $horizontal_offset;
					$args['vertical-offset']   = $vertical_offset;
				}

				// Inject offsets as CSS variable to take effect.
				$css .= sprintf(
					'::view-transition-old(*), ::view-transition-new(*) { --plvt-view-transition-animation-slide-horizontal-offset: %d; --plvt-view-transition-animation-slide-vertical-offset: %d; }',
					$args['horizontal-offset'],
					$args['vertical-offset']
				);

				// If a specific element view transition name is targeted, scope the animation to only that name.
				if ( '*' !== $args['target-name'] ) {
					$css = str_replace( '(*)', "({$args['target-name']})", $css );
				}

				return $css;
			},
		),
		array(
			'horizontal-offset' => 1,
			'vertical-offset'   => 0,
			'target-name'       => '*',
		)
	);
	$animation_registry->register_animation(
		'swipe',
		array(
			'aliases'                     => array(
				'swipe-from-right',
				'swipe-from-bottom',
				'swipe-from-left',
				'swipe-from-top',
			),
			'use_stylesheet'              => true,
			'use_global_transition_names' => $is_specific_target_name,
			'use_post_transition_names'   => $is_specific_target_name,
			'get_stylesheet_callback'     => static function ( string $css, string $alias, array $args ) use ( $get_hv_offsets_based_on_alias ) {
				// Set offsets based on alias, if relevant.
				list( $horizontal_offset, $vertical_offset ) = $get_hv_offsets_based_on_alias( $alias );
				if ( null !== $horizontal_offset && null !== $vertical_offset ) {
					$args['horizontal-offset'] = $horizontal_offset;
					$args['vertical-offset']   = $vertical_offset;
				}

				// Inject offsets as CSS variable to take effect.
				$css .= sprintf(
					'::view-transition-old(*), ::view-transition-new(*) { --plvt-view-transition-animation-swipe-horizontal-offset: %d; --plvt-view-transition-animation-swipe-vertical-offset: %d; }',
					$args['horizontal-offset'],
					$args['vertical-offset']
				);

				// If a specific element view transition name is targeted, scope the animation to only that name.
				if ( '*' !== $args['target-name'] ) {
					$css = str_replace( '(*)', "({$args['target-name']})", $css );
				}

				return $css;
			},
		),
		array(
			'horizontal-offset' => 1,
			'vertical-offset'   => 0,
			'target-name'       => '*',
		)
	);
	$animation_registry->register_animation(
		'wipe',
		array(
			'aliases'                     => array(
				'wipe-from-right',
				'wipe-from-bottom',
				'wipe-from-left',
				'wipe-from-top',
			),
			'use_stylesheet'              => true,
			'use_global_transition_names' => false,
			'use_post_transition_names'   => true,
			'get_stylesheet_callback'     => static function ( string $css, string $alias, array $args ) {
				// Set angle based on alias, if relevant.
				if ( str_ends_with( $alias, 'left' ) ) {
					$args['angle'] = 90;
				} elseif ( str_ends_with( $alias, 'top' ) ) {
					$args['angle'] = 180;
				} elseif ( str_ends_with( $alias, 'bottom' ) ) {
					$args['angle'] = 0;
				} elseif ( str_ends_with( $alias, 'right' ) ) {
					$args['angle'] = 270;
				}

				// Inject angle as CSS variable to take effect.
				$css .= sprintf(
					'::view-transition-new(root) { --plvt-view-transition-animation-wipe-angle: %ddeg; }',
					$args['angle']
				);

				return $css;
			},
		),
		array( 'angle' => 270 )
	);

	/**
	 * Fires when view transition animations are being registered.
	 *
	 * This is only triggered if the theme supports view transitions, as otherwise the functionality is not relevant.
	 *
	 * @since 1.0.0
	 *
	 * @param PLVT_View_Transition_Animation_Registry $animation_registry Registry instance on which to register view
	 *                                                                    transition animations which can be used by
	 *                                                                    the theme.
	 */
	do_action( 'plvt_register_view_transition_animations', $animation_registry );
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

	// Instantiate transition animation registry and register available animations on it.
	$animation_registry = new PLVT_View_Transition_Animation_Registry();
	plvt_register_view_transition_animations( $animation_registry );

	// Use an inline style to avoid an extra request.
	$stylesheet = '@view-transition { navigation: auto; }';
	wp_register_style( 'plvt-view-transitions', false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_add_inline_style( 'plvt-view-transitions', $stylesheet );
	wp_enqueue_style( 'plvt-view-transitions' );

	$theme_support = get_theme_support( 'view-transitions' );

	/*
	 * Add the animation stylesheet for the default animation, if any.
	 */
	$default_animation_args       = isset( $theme_support['default-animation-args'] ) ? (array) $theme_support['default-animation-args'] : array();
	$default_animation_stylesheet = $animation_registry->get_animation_stylesheet( $theme_support['default-animation'], $default_animation_args );
	$default_animation_stylesheet = plvt_inject_animation_duration( $default_animation_stylesheet, absint( $theme_support['default-animation-duration'] ) );
	wp_add_inline_style( 'plvt-view-transitions', '@media (prefers-reduced-motion: no-preference) {' . $default_animation_stylesheet . '}' );

	/*
	 * No point in loading the script if no specific view transition names are configured.
	 */
	if (
		( ! is_array( $theme_support['global-transition-names'] ) || count( $theme_support['global-transition-names'] ) === 0 ) &&
		( ! is_array( $theme_support['post-transition-names'] ) || count( $theme_support['post-transition-names'] ) === 0 )
	) {
		return;
	}

	$animations_js_config = array(
		'default' => array(
			'useGlobalTransitionNames' => $animation_registry->use_animation_global_transition_names( $theme_support['default-animation'], $default_animation_args ),
			'usePostTransitionNames'   => $animation_registry->use_animation_post_transition_names( $theme_support['default-animation'], $default_animation_args ),
		),
	);

	$config = array(
		'postSelector'          => $theme_support['post-selector'],
		'globalTransitionNames' => $theme_support['global-transition-names'],
		'postTransitionNames'   => $theme_support['post-transition-names'],
		'animations'            => $animations_js_config,
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
	wp_register_script( 'plvt-view-transitions', false, array(), null, array() ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_add_inline_script( 'plvt-view-transitions', $src_script );
	wp_add_inline_script( 'plvt-view-transitions', $init_script );
	wp_enqueue_script( 'plvt-view-transitions' );
}

/**
 * Injects the animation duration placeholder in the provided CSS with a value based on the transition duration.
 *
 * @since 1.1.0
 * @access private
 *
 * @param string $css                The raw CSS string containing the placeholder `plvt-view-transition-duration;`.
 * @param int    $animation_duration Transition duration in milliseconds. Will be converted to seconds. Defaults to 1000ms if invalid.
 * @return string Modified CSS with the actual animation duration in seconds.
 */
function plvt_inject_animation_duration( string $css, int $animation_duration ): string {
	$seconds = $animation_duration / 1000;

	// Inject animation duration as CSS variable to take effect.
	$css .= sprintf(
		/* translators: %1$s: CSS property name. %2$s: Animation duration in seconds. */
		'::view-transition-group(*) { %1$s: %2$ss; }',
		'' !== $css ? '--plvt-view-transition-animation-duration' : 'animation-duration',
		$seconds
	);

	return $css;
}
